<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use App\Models\UserApiToken;
use App\Models\UserPaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    private const DELIVERY_FEE = 350;
    private const TAX_RATE = 8;
    private const POINT_RATE = 0.03;

    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        $orders = Order::with(['items.product.store', 'reviews'])
            ->where('user_id', $user->id)
            ->latest('ordered_at')
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount,
                'subtotal_amount' => $order->subtotal_amount,
                'delivery_fee' => $order->delivery_fee,
                'membership_discount_rate' => (float) $order->membership_discount_rate,
                'membership_discount_amount' => $order->membership_discount_amount,
                'delivery_discount_amount' => $order->delivery_discount_amount,
                'applied_subscription_code' => $order->applied_subscription_code,
                'tax_rate' => (float) $order->tax_rate,
                'tax_amount' => $order->tax_amount,
                'earned_points' => $order->earned_points,
                'receipt_type' => $order->receipt_type,
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'delivery_staff_name' => $order->delivery_staff_name,
                'ordered_at' => $order->ordered_at?->toISOString(),
                'delivered_at' => $order->delivered_at?->toISOString(),
                'received_at' => $order->received_at?->toISOString(),
                'store_id' => $order->items->first()?->product?->store_id,
                'store_name' => $order->store_name_snapshot ?? $order->items->first()?->product?->store?->name,
                'store_invoice_number' => $order->store_invoice_number,
                'review' => $order->reviews->first()
                    ? [
                        'id' => $order->reviews->first()->id,
                        'rating' => $order->reviews->first()->rating,
                        'content' => $order->reviews->first()->content,
                    ]
                    : null,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'imagePath' => $item->product?->image_path,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal,
                ])->values(),
            ]);

        return response()->json([
            'orders' => $orders,
            'points' => [
                'balance' => $user->point_balance,
                'earned_total' => (int) $orders->sum('earned_points'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'payjp_token' => ['nullable', 'string'],
            'payment_method_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string', 'in:card,paypay,cash'],
            'receipt_type' => ['nullable', 'string', 'in:delivery,pickup'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $cart = $user->carts()->with(['items.product.category', 'items.product.store'])->first();
        if (! $cart || ! $cart->items->count()) {
            return response()->json(['message' => 'カートが空です。'], 422);
        }

        if ($cart->expires_at && $cart->expires_at->isPast()) {
            $cart->delete();
            return response()->json(['message' => 'カートの有効期限が切れています。'], 422);
        }

        if (! $this->cartHasMainMenu($cart)) {
            return response()->json(['message' => '注文にはメインメニューを1点以上追加してください。'], 422);
        }

        $subscription = $this->activeSubscriptionForUser($user->id);
        $receiptType = $validated['receipt_type'] ?? 'delivery';
        $pricing = $this->calculatePricing(
            $cart->items->sum(fn ($item) => $this->cartItemUnitPrice($item) * $item->quantity),
            $receiptType,
            $subscription,
        );

        if ($pricing['total_amount'] <= 0) {
            return response()->json(['message' => '注文金額が不正です。'], 422);
        }

        $selectedPaymentMethod = $validated['payment_method'] ?? 'card';
        $paymentMethod = null;

        if ($selectedPaymentMethod === 'card' && empty($validated['payment_method_id']) && empty($validated['payjp_token'])) {
            return response()->json(['message' => 'カード情報を設定してください。'], 422);
        }

        if ($selectedPaymentMethod === 'card' && ! empty($validated['payment_method_id'])) {
            $paymentMethod = $user->paymentMethods()
                ->where('provider', 'payjp')
                ->find($validated['payment_method_id']);

            if (! $paymentMethod) {
                return response()->json(['message' => '登録済みカードが見つかりません。'], 422);
            }
        }

        $charge = match ($selectedPaymentMethod) {
            'card' => $paymentMethod
                ? $this->createPayjpChargeFromPaymentMethod($paymentMethod, $pricing['total_amount'])
                : $this->createPayjpCharge($validated['payjp_token'], $pricing['total_amount']),
            'paypay' => $this->createPaypayTestPayment($pricing['total_amount']),
            'cash' => $this->createCashPaymentResponse($pricing['total_amount']),
        };

        $cardSnapshot = $selectedPaymentMethod === 'card'
            ? $this->cardSnapshot($charge, $paymentMethod)
            : $this->emptyCardSnapshot();
        $paymentStatus = $selectedPaymentMethod === 'cash' ? 'pending' : 'paid';
        $paymentProvider = match ($selectedPaymentMethod) {
            'card' => 'payjp',
            'paypay' => 'paypay',
            'cash' => 'cash',
        };
        $earnedPoints = $this->calculateEarnedPoints($pricing['total_amount']);

        $order = DB::transaction(function () use ($user, $cart, $pricing, $validated, $charge, $cardSnapshot, $paymentMethod, $paymentProvider, $paymentStatus, $selectedPaymentMethod, $earnedPoints): Order {
            $store = $cart->items->first()?->product?->store;

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $user->id,
                'user_subscription_id' => $pricing['user_subscription_id'],
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $user->phone,
                'store_name_snapshot' => $store?->name ?? $cart->store_name,
                'store_invoice_number' => $store?->invoice_number,
                'receipt_type' => $pricing['receipt_type'],
                'subtotal_amount' => $pricing['subtotal_amount'],
                'membership_discount_rate' => $pricing['membership_discount_rate'],
                'membership_discount_amount' => $pricing['membership_discount_amount'],
                'delivery_fee' => $pricing['delivery_fee'],
                'delivery_discount_amount' => $pricing['delivery_discount_amount'],
                'applied_subscription_code' => $pricing['applied_subscription_code'],
                'tax_rate' => self::TAX_RATE,
                'tax_amount' => $pricing['tax_amount'],
                'total_amount' => $pricing['total_amount'],
                'earned_points' => $earnedPoints,
                'order_status' => 'received',
                'payment_method' => $selectedPaymentMethod,
                'payment_status' => $paymentStatus,
                'note' => $validated['note'] ?? null,
                'ordered_at' => now(),
            ]);

            foreach ($cart->items as $cartItem) {
                /** @var Product $product */
                $product = $cartItem->product;
                $selectedOptions = $this->normalizeSelectedOptions($cartItem->selected_options ?? []);
                $unitPrice = $product->price + collect($selectedOptions)->sum(fn (array $option) => $option['price']);

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'selected_options' => $selectedOptions,
                    'unit_price' => $unitPrice,
                    'quantity' => $cartItem->quantity,
                    'subtotal' => $unitPrice * $cartItem->quantity,
                ]);
            }

            $order->payments()->create([
                'user_id' => $user->id,
                'user_payment_method_id' => $paymentMethod?->id,
                'provider' => $paymentProvider,
                'provider_customer_id' => $paymentMethod?->provider_customer_id,
                'provider_card_id' => $paymentMethod?->provider_card_id ?? ($charge['card']['id'] ?? null),
                'provider_charge_id' => $charge['id'] ?? null,
                'payment_method' => $selectedPaymentMethod,
                'payment_status' => $paymentStatus,
                'amount' => $pricing['total_amount'],
                'currency' => $charge['currency'] ?? 'jpy',
                ...$cardSnapshot,
                'provider_response' => $charge,
                'paid_at' => $paymentStatus === 'paid' ? now() : null,
            ]);

            $cart->delete();
            $user->increment('point_balance', $earnedPoints);

            return $order->fresh(['items', 'payments']);
        });

        return response()->json(['order' => $this->orderResponse($order)], 201);
    }

    public function webhook(Request $request): JsonResponse
    {
        $webhookToken = config('services.payjp.webhook_token');

        if ($webhookToken && $request->header('X-Payjp-Webhook-Token') !== $webhookToken) {
            return response()->json(['message' => 'Invalid webhook token.'], 401);
        }

        $event = $request->input('type');
        $charge = $request->input('data.object');
        $chargeId = is_array($charge) ? ($charge['id'] ?? null) : null;

        if ($chargeId && $event === 'charge.refunded') {
            $payment = Payment::where('provider_charge_id', $chargeId)->first();
            $payment?->order()->update(['payment_status' => 'refunded']);
            $payment?->update([
                'payment_status' => 'refunded',
                'refunded_at' => now(),
            ]);
        }

        return response()->json(['received' => true]);
    }

    public function receive(Request $request, Order $order): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user || $order->user_id !== $user->id) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        if ($order->order_status !== 'delivering') {
            return response()->json(['message' => '配送中の注文のみ受け取りできます。'], 422);
        }

        $order->forceFill([
            'order_status' => 'completed',
            'received_at' => now(),
        ])->save();

        return response()->json(['order' => $this->orderResponse($order->fresh(['items.product.store', 'reviews']) ?? $order)]);
    }

    public function review(Request $request, Order $order): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user || $order->user_id !== $user->id) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        if ($order->order_status !== 'completed') {
            return response()->json(['message' => '完了した注文のみレビューできます。'], 422);
        }

        if ($order->reviews()->exists()) {
            return response()->json(['message' => 'この注文はレビュー済みです。'], 422);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['nullable', 'string', 'max:1000'],
        ]);

        $order->loadMissing('items.product');
        $store = $order->items
            ->map(fn ($item) => $item->product?->store)
            ->filter()
            ->first();

        if (! $store instanceof Store) {
            return response()->json(['message' => 'レビュー対象の店舗が見つかりません。'], 422);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'order_id' => $order->id,
            'rating' => $validated['rating'],
            'content' => $validated['content'] ?? null,
        ]);

        $this->refreshStoreRating($store);

        return response()->json(['review' => [
            'id' => $review->id,
            'rating' => $review->rating,
            'content' => $review->content,
        ]], 201);
    }

    private function authenticatedUser(Request $request): ?User
    {
        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        $apiToken = UserApiToken::with('user')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $apiToken || $apiToken->user->status !== 'active') {
            return null;
        }

        $apiToken->forceFill(['last_used_at' => now()])->save();

        return $apiToken->user;
    }

    /**
     * @return array{receipt_type: string, subtotal_amount: int, delivery_fee: int, membership_discount_rate: float, membership_discount_amount: int, delivery_discount_amount: int, user_subscription_id: int|null, applied_subscription_code: string|null, tax_amount: int, total_amount: int}
     */
    private function calculatePricing(int $subtotalAmount, string $receiptType, ?object $subscription): array
    {
        $deliveryFee = $receiptType === 'delivery' ? self::DELIVERY_FEE : 0;
        $membershipDiscountRate = $subscription ? (float) $subscription->discount_rate : 0;
        $membershipDiscountAmount = $subscription ? (int) floor($subtotalAmount * $membershipDiscountRate / 100) : 0;
        $deliveryDiscountAmount = $subscription && $subscription->free_delivery && $receiptType === 'delivery'
            ? $deliveryFee
            : 0;
        $discountedSubtotal = max($subtotalAmount - $membershipDiscountAmount, 0);
        $discountedDeliveryFee = max($deliveryFee - $deliveryDiscountAmount, 0);
        $taxAmount = (int) floor(($discountedSubtotal + $discountedDeliveryFee) * self::TAX_RATE / 100);

        return [
            'receipt_type' => $receiptType,
            'subtotal_amount' => $subtotalAmount,
            'delivery_fee' => $deliveryFee,
            'membership_discount_rate' => $membershipDiscountRate,
            'membership_discount_amount' => $membershipDiscountAmount,
            'delivery_discount_amount' => $deliveryDiscountAmount,
            'user_subscription_id' => $subscription?->id,
            'applied_subscription_code' => $subscription?->code,
            'tax_amount' => $taxAmount,
            'total_amount' => $discountedSubtotal + $discountedDeliveryFee + $taxAmount,
        ];
    }

    private function activeSubscriptionForUser(int $userId): ?object
    {
        return DB::table('user_subscriptions')
            ->join('subscription_plans', 'user_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->where('user_subscriptions.user_id', $userId)
            ->where('user_subscriptions.status', 'active')
            ->where('user_subscriptions.current_period_start', '<=', now())
            ->where('user_subscriptions.current_period_end', '>', now())
            ->whereNull('user_subscriptions.deleted_at')
            ->where('subscription_plans.is_active', true)
            ->orderByDesc('user_subscriptions.current_period_end')
            ->select([
                'user_subscriptions.id',
                'subscription_plans.code',
                'subscription_plans.discount_rate',
                'subscription_plans.free_delivery',
            ])
            ->first();
    }

    private function cartHasMainMenu($cart): bool
    {
        return $cart->items->contains(fn ($item) => $item->product?->category?->name === 'メイン');
    }

    private function calculateEarnedPoints(int $totalAmount): int
    {
        return (int) floor($totalAmount * self::POINT_RATE);
    }

    private function refreshStoreRating(Store $store): void
    {
        $rating = (float) $store->reviews()->avg('rating');
        $reviewCount = $store->reviews()->count();

        $store->forceFill([
            'rating' => $reviewCount > 0 ? round($rating, 1) : 0,
            'review_count' => $reviewCount,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayjpCharge(string $token, int $amount): array
    {
        $secretKey = config('services.payjp.secret_key');

        if (! $secretKey) {
            abort(500, 'PAY.JP secret key is not configured.');
        }

        $response = Http::asForm()
            ->withBasicAuth($secretKey, '')
            ->post('https://api.pay.jp/v1/charges', [
                'amount' => $amount,
                'currency' => 'jpy',
                'card' => $token,
                'capture' => 'true',
            ]);

        if ($response->failed()) {
            abort(422, $response->json('error.message') ?? '決済に失敗しました。');
        }

        return $response->json();
    }

    /**
     * @return array{card_brand: mixed, card_last4: mixed, card_exp_month: mixed, card_exp_year: mixed}
     */
    private function cardSnapshot(array $charge, ?UserPaymentMethod $paymentMethod): array
    {
        $card = is_array($charge['card'] ?? null) ? $charge['card'] : [];

        return [
            'card_brand' => $card['brand'] ?? $paymentMethod?->brand,
            'card_last4' => $card['last4'] ?? $paymentMethod?->last4,
            'card_exp_month' => $card['exp_month'] ?? $paymentMethod?->exp_month,
            'card_exp_year' => $card['exp_year'] ?? $paymentMethod?->exp_year,
        ];
    }

    /**
     * @return array{card_brand: null, card_last4: null, card_exp_month: null, card_exp_year: null}
     */
    private function emptyCardSnapshot(): array
    {
        return [
            'card_brand' => null,
            'card_last4' => null,
            'card_exp_month' => null,
            'card_exp_year' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createPaypayTestPayment(int $amount): array
    {
        if (! config('services.paypay.test_mode')) {
            abort(422, 'PayPay決済は現在テストモードのみ対応しています。');
        }

        return [
            'id' => 'paypay_test_' . Str::lower(Str::random(24)),
            'amount' => $amount,
            'currency' => 'jpy',
            'provider' => 'paypay',
            'payment_method' => 'paypay',
            'status' => 'paid',
            'test_mode' => true,
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createCashPaymentResponse(int $amount): array
    {
        return [
            'id' => null,
            'amount' => $amount,
            'currency' => 'jpy',
            'provider' => 'cash',
            'payment_method' => 'cash',
            'status' => 'pending',
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayjpChargeFromPaymentMethod(UserPaymentMethod $paymentMethod, int $amount): array
    {
        $secretKey = config('services.payjp.secret_key');

        if (! $secretKey) {
            abort(500, 'PAY.JP secret key is not configured.');
        }

        $payload = [
            'amount' => $amount,
            'currency' => 'jpy',
            'customer' => $paymentMethod->provider_customer_id,
            'capture' => 'true',
        ];

        if ($paymentMethod->provider_card_id) {
            $payload['card'] = $paymentMethod->provider_card_id;
        }

        $response = Http::asForm()
            ->withBasicAuth($secretKey, '')
            ->post('https://api.pay.jp/v1/charges', $payload);

        if ($response->failed()) {
            abort(422, $response->json('error.message') ?? '決済に失敗しました。');
        }

        return $response->json();
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'MN-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    private function cartItemUnitPrice($cartItem): int
    {
        $selectedOptions = $this->normalizeSelectedOptions($cartItem->selected_options ?? []);
        $optionTotal = collect($selectedOptions)->sum(fn (array $option) => $option['price']);

        return (int) $cartItem->product->price + $optionTotal;
    }

    /**
     * @param array<int, array<string, mixed>> $options
     * @return array<int, array{product_id: int|null, name: string, price: int}>
     */
    private function normalizeSelectedOptions(array $options): array
    {
        return collect($options)
            ->map(fn (array $option) => [
                'product_id' => isset($option['product_id']) ? (int) $option['product_id'] : null,
                'name' => (string) ($option['name'] ?? ''),
                'price' => max((int) ($option['price'] ?? 0), 0),
            ])
            ->filter(fn (array $option) => $option['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function orderResponse(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'total_amount' => $order->total_amount,
            'subtotal_amount' => $order->subtotal_amount,
            'delivery_fee' => $order->delivery_fee,
            'membership_discount_rate' => (float) $order->membership_discount_rate,
            'membership_discount_amount' => $order->membership_discount_amount,
            'delivery_discount_amount' => $order->delivery_discount_amount,
            'applied_subscription_code' => $order->applied_subscription_code,
            'tax_rate' => (float) $order->tax_rate,
            'tax_amount' => $order->tax_amount,
            'earned_points' => $order->earned_points,
            'receipt_type' => $order->receipt_type,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'delivery_staff_name' => $order->delivery_staff_name,
            'ordered_at' => $order->ordered_at?->toISOString(),
            'delivered_at' => $order->delivered_at?->toISOString(),
            'received_at' => $order->received_at?->toISOString(),
            'store_name' => $order->store_name_snapshot ?? $order->items->first()?->product?->store?->name,
            'store_invoice_number' => $order->store_invoice_number,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'imagePath' => $item->product?->image_path,
                'selected_options' => $item->selected_options ?? [],
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
            ])->values(),
        ];
    }
}
