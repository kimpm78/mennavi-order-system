<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CartController extends Controller
{
    private const CART_TTL_MINUTES = 30;

    public function show(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        $cart = $this->activeCart($user);

        return response()->json($this->cartResponse($cart));
    }

    public function addItem(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'store_name' => ['required', 'string', 'max:150'],
            'selected_options' => ['nullable', 'array'],
            'selected_options.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'selected_options.*.name' => ['required_with:selected_options', 'string', 'max:150'],
            'selected_options.*.price' => ['required_with:selected_options', 'integer', 'min:0'],
        ]);

        $product = Product::where('status', 'active')->findOrFail($validated['product_id']);
        $cart = $this->activeCart($user) ?? Cart::create(['user_id' => $user->id]);

        if ($cart->store_name && $cart->store_name !== $validated['store_name']) {
            $cart->items()->delete();
        }

        $cart->forceFill([
            'store_name' => $validated['store_name'],
            'expires_at' => $this->freshExpiresAt(),
        ])->save();

        $cartItem = CartItem::firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);
        $cartItem->quantity = ($cartItem->exists ? $cartItem->quantity : 0) + $validated['quantity'];
        $cartItem->selected_options = $this->normalizeSelectedOptions($validated['selected_options'] ?? []);
        $cartItem->save();

        return response()->json($this->cartResponse($cart->fresh(['items.product.category'])), 201);
    }

    public function updateItem(Request $request, Product $product): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->activeCart($user);
        if (! $cart) {
            return response()->json(['message' => 'カートが空です。'], 404);
        }

        $cartItem = $cart->items()->where('product_id', $product->id)->first();
        if (! $cartItem) {
            return response()->json(['message' => '商品がカートにありません。'], 404);
        }

        $cartItem->forceFill(['quantity' => $validated['quantity']])->save();
        $cart->forceFill(['expires_at' => $this->freshExpiresAt()])->save();

        return response()->json($this->cartResponse($cart->fresh(['items.product.category'])));
    }

    public function removeItem(Request $request, Product $product): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        $cart = $this->activeCart($user);
        if (! $cart) {
            return response()->json(['message' => 'カートが空です。'], 404);
        }

        $cart->items()->where('product_id', $product->id)->delete();
        $cart->forceFill(['expires_at' => $this->freshExpiresAt()])->save();

        if (! $cart->items()->exists()) {
            $cart->forceFill(['store_name' => null])->save();
        }

        return response()->json($this->cartResponse($cart->fresh(['items.product.category'])));
    }

    public function clear(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        Cart::where('user_id', $user->id)->delete();

        return response()->json($this->cartResponse(null));
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

    private function activeCart(User $user): ?Cart
    {
        $cart = Cart::with('items.product.category')->where('user_id', $user->id)->first();

        if (! $cart) {
            return null;
        }

        if ($cart->expires_at && $cart->expires_at->isPast()) {
            $cart->delete();
            return null;
        }

        return $cart;
    }

    private function freshExpiresAt(): Carbon
    {
        return now()->addMinutes(self::CART_TTL_MINUTES);
    }

    /**
     * @return array{store_name: string|null, expires_at: string|null, items: array<int, array<string, mixed>>, total: int}
     */
    private function cartResponse(?Cart $cart): array
    {
        if (! $cart) {
            return [
                'store_name' => null,
                'expires_at' => null,
                'items' => [],
                'total' => 0,
            ];
        }

        $items = $cart->items
            ->filter(fn (CartItem $item) => $item->product !== null)
            ->map(function (CartItem $item) use ($cart) {
                $selectedOptions = $this->normalizeSelectedOptions($item->selected_options ?? []);
                $optionTotal = collect($selectedOptions)->sum(fn (array $option) => $option['price']);

                return [
                    'storeName' => $cart->store_name,
                    'menuItemId' => $item->product_id,
                    'name' => $item->product->name,
                    'category' => $item->product->category?->name,
                    'price' => $item->product->price + $optionTotal,
                    'basePrice' => $item->product->price,
                    'selectedOptions' => $selectedOptions,
                    'quantity' => $item->quantity,
                ];
            })
            ->values()
            ->all();

        return [
            'store_name' => $cart->store_name,
            'expires_at' => $cart->expires_at?->toISOString(),
            'items' => $items,
            'total' => collect($items)->sum(fn (array $item) => $item['price'] * $item['quantity']),
        ];
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
}
