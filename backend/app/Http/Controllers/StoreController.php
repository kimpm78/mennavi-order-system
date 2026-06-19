<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function index(): JsonResponse
    {
        $stores = Store::with(['products.category', 'reviews.user'])
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn (Store $store) => $this->storeResponse($store))
            ->values();

        return response()->json(['stores' => $stores]);
    }

    public function show(Store $store): JsonResponse
    {
        if (! $store->is_active) {
            return response()->json(['message' => '店舗が見つかりません。'], 404);
        }

        return response()->json(['store' => $this->storeResponse($store->load(['products.category', 'reviews.user']))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storeResponse(Store $store): array
    {
        $categories = $store->products->pluck('category.name')->filter()->unique()->values();
        $popularToppings = $store->products
            ->filter(fn ($product) => $product->category?->name === 'トッピング' && $product->status === 'active')
            ->sortBy('display_order')
            ->map(fn ($product) => [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
            ])
            ->values();

        return [
            'id' => $store->id,
            'name' => $store->name,
            'description' => $store->description,
            'address' => $store->address,
            'phone' => $store->phone,
            'weekdayHours' => $store->weekday_hours,
            'weekendHours' => $store->weekend_hours,
            'holiday' => $store->holiday,
            'imagePath' => $store->image_path,
            'rating' => number_format((float) ($store->reviews->avg('rating') ?? 0), 1),
            'reviews' => number_format($store->reviews->count()),
            'orderCount' => $this->storeOrderCount($store),
            'reviewItems' => $store->reviews
                ->sortByDesc('created_at')
                ->take(5)
                ->map(fn ($review) => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'content' => $review->content,
                    'userName' => $this->maskedUserName($review->user?->name),
                    'createdAt' => $review->created_at?->toISOString(),
                ])
                ->values(),
            'budget' => $store->budget_label,
            'categories' => $categories,
            'tags' => $categories,
            'imageClass' => 'ramen-photo ramen-photo-shop',
            'products' => $store->products
                ->where('status', '!=', 'hidden')
                ->sortBy('display_order')
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category?->name ?? 'メイン',
                    'status' => $product->status,
                    'price' => $product->price,
                    'description' => $product->description,
                    'imagePath' => $product->image_path,
                    'imageClass' => 'ramen-photo ramen-photo-bowl',
                    'toppings' => $product->category?->name === 'メイン' ? $popularToppings : [],
                ])
                ->values(),
        ];
    }

    private function maskedUserName(?string $name): string
    {
        if (! $name) {
            return 'ゲ****';
        }

        return mb_substr($name, 0, 2) . '****';
    }

    private function storeOrderCount(Store $store): int
    {
        return DB::table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('products.store_id', $store->id)
            ->whereNull('orders.deleted_at')
            ->where('orders.order_status', '!=', 'canceled')
            ->distinct('orders.id')
            ->count('orders.id');
    }
}
