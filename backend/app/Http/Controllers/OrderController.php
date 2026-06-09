<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
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

    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        $orders = Order::with('items')
            ->where('user_id', $user->id)
            ->latest('ordered_at')
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount,
                'subtotal_amount' => $order->subtotal_amount,
                'delivery_fee' => $order->delivery_fee,
                'tax_rate' => (float) $order->tax_rate,
                'tax_amount' => $order->tax_amount,
                'receipt_type' => $order->receipt_type,
                'order_status' => $order->order_status,
                'payment_method' => $order->payment_method,
                'ordered_at' => $order->ordered_at?->toISOString(),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal,
                ])->values(),
            ]);

        return response()->json(['orders' => $orders]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'payjp_token' => ['nullable', 'required_without:payment_method_id', 'string'],
            'payment_method_id' => ['nullable', 'required_without:payjp_token', 'integer'],
            'payment_method' => ['nullable', 'string', 'max:20'],
            'receipt_type' => ['nullable', 'string', 'in:delivery,pickup'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $cart = $user->carts()->with('items.product')->first();
        if (! $cart || ! $cart->items->count()) {
            return response()->json(['message' => 'カートが空です。'], 422);
        }

        if ($cart->expires_at && $cart->expires_at->isPast()) {
            $cart->delete();
            return response()->json(['message' => 'カートの有効期限が切れています。'], 422);
        }

        $receiptType = $validated['receipt_type'] ?? 'delivery';
        $pricing = $this->calculatePricing(
            $cart->items->sum(fn ($item) => $item->product->price * $item->quantity),
            $receiptType,
        );

        if ($pricing['total_amount'] <= 0) {
            return response()->json(['message' => '注文金額が不正です。'], 422);
        }

        $paymentMethod = null;
        if (! empty($validated['payment_method_id'])) {
            $paymentMethod = $user->paymentMethods()
                ->where('provider', 'payjp')
                ->find($validated['payment_method_id']);

            if (! $paymentMethod) {
                return response()->json(['message' => '登録済みカードが見つかりません。'], 422);
            }
        }

        $charge = $paymentMethod
            ? $this->createPayjpChargeFromPaymentMethod($paymentMethod, $pricing['total_amount'])
            : $this->createPayjpCharge($validated['payjp_token'], $pricing['total_amount']);

        $order = DB::transaction(function () use ($user, $cart, $pricing, $validated, $charge): Order {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $user->id,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $user->phone,
                'receipt_type' => $pricing['receipt_type'],
                'subtotal_amount' => $pricing['subtotal_amount'],
                'delivery_fee' => $pricing['delivery_fee'],
                'tax_rate' => self::TAX_RATE,
                'tax_amount' => $pricing['tax_amount'],
                'total_amount' => $pricing['total_amount'],
                'order_status' => 'received',
                'payment_method' => $validated['payment_method'] ?? 'card',
                'payment_status' => 'paid',
                'payjp_charge_id' => $charge['id'] ?? null,
                'note' => $validated['note'] ?? null,
                'ordered_at' => now(),
            ]);

            foreach ($cart->items as $cartItem) {
                /** @var Product $product */
                $product = $cartItem->product;
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $cartItem->quantity,
                    'subtotal' => $product->price * $cartItem->quantity,
                ]);
            }

            $order->payments()->create([
                'user_id' => $user->id,
                'provider' => 'payjp',
                'provider_charge_id' => $charge['id'] ?? null,
                'payment_method' => $validated['payment_method'] ?? 'card',
                'payment_status' => 'paid',
                'amount' => $pricing['total_amount'],
                'currency' => $charge['currency'] ?? 'jpy',
                'provider_response' => $charge,
                'paid_at' => now(),
            ]);

            $cart->delete();

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
            Order::where('payjp_charge_id', $chargeId)->update(['payment_status' => 'refunded']);
            Payment::where('provider_charge_id', $chargeId)->update([
                'payment_status' => 'refunded',
                'refunded_at' => now(),
            ]);
        }

        return response()->json(['received' => true]);
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
     * @return array{receipt_type: string, subtotal_amount: int, delivery_fee: int, tax_amount: int, total_amount: int}
     */
    private function calculatePricing(int $subtotalAmount, string $receiptType): array
    {
        $deliveryFee = $receiptType === 'delivery' ? self::DELIVERY_FEE : 0;
        $taxAmount = (int) floor(($subtotalAmount + $deliveryFee) * self::TAX_RATE / 100);

        return [
            'receipt_type' => $receiptType,
            'subtotal_amount' => $subtotalAmount,
            'delivery_fee' => $deliveryFee,
            'tax_amount' => $taxAmount,
            'total_amount' => $subtotalAmount + $deliveryFee + $taxAmount,
        ];
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
            'tax_rate' => (float) $order->tax_rate,
            'tax_amount' => $order->tax_amount,
            'receipt_type' => $order->receipt_type,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'ordered_at' => $order->ordered_at?->toISOString(),
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
            ])->values(),
        ];
    }
}
