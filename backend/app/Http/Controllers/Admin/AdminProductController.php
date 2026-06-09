<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        return response()->json([
            'categories' => Category::orderBy('display_order')->get(['id', 'name', 'is_active']),
            'products' => Product::with('category')
                ->orderBy('display_order')
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category?->name ?? '-',
                    'price' => $product->price,
                    'status' => $product->status,
                ])
                ->values(),
        ]);
    }
}
