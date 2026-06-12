<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    public function index(): JsonResponse
    {
        $stores = Store::with(['products.category'])
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

        return response()->json(['store' => $this->storeResponse($store->load('products.category'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storeResponse(Store $store): array
    {
        $categories = $store->products->pluck('category.name')->filter()->unique()->values();

        return [
            'id' => $store->id,
            'name' => $store->name,
            'description' => $store->description,
            'address' => $store->address,
            'phone' => $store->phone,
            'imagePath' => $store->image_path,
            'rating' => (string) $store->rating,
            'reviews' => number_format($store->review_count),
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
                    'toppings' => [],
                ])
                ->values(),
        ];
    }
}
