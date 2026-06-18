<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminProductController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        return response()->json([
            'stores' => Store::orderBy('display_order')
                ->get([
                    'id',
                    'name',
                    'description',
                    'address',
                    'phone',
                    'weekday_hours',
                    'weekend_hours',
                    'holiday',
                    'image_path',
                    'budget_label',
                    'is_active',
                ]),
            'categories' => Category::orderBy('display_order')->get(['id', 'name', 'is_active']),
            'products' => Product::with(['store', 'category'])
                ->orderBy('display_order')
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'store' => $product->store?->name ?? '-',
                    'store_id' => $product->store_id,
                    'category_id' => $product->category_id,
                    'category' => $product->category?->name ?? '-',
                    'description' => $product->description,
                    'imagePath' => $product->image_path,
                    'price' => $product->price,
                    'status' => $product->status,
                ])
                ->values(),
        ]);
    }

    public function storeStore(Request $request): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'weekday_hours' => ['nullable', 'string', 'max:100'],
            'weekend_hours' => ['nullable', 'string', 'max:100'],
            'holiday' => ['nullable', 'string', 'max:100'],
            'budget_label' => ['nullable', 'string', 'max:100'],
        ]);

        $store = Store::create([
            ...$validated,
            'rating' => 0,
            'review_count' => 0,
            'display_order' => (Store::max('display_order') ?? 0) + 1,
            'is_active' => true,
        ]);

        return response()->json(['store' => $store], 201);
    }

    public function updateStore(Request $request, Store $store): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'weekday_hours' => ['nullable', 'string', 'max:100'],
            'weekend_hours' => ['nullable', 'string', 'max:100'],
            'holiday' => ['nullable', 'string', 'max:100'],
            'budget_label' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $store->update($validated);

        return response()->json(['store' => $store->fresh()]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:active,sold_out,hidden'],
        ]);

        $product = Product::create([
            ...$validated,
            'display_order' => (Product::where('store_id', $validated['store_id'])->max('display_order') ?? 0) + 1,
        ]);

        return response()->json(['product' => $product->load(['store', 'category'])], 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:active,sold_out,hidden'],
        ]);

        $product->update($validated);

        return response()->json(['product' => $product->load(['store', 'category'])]);
    }

    public function destroyProduct(Request $request, Product $product): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $product->delete();

        return response()->json(['message' => 'メニューを削除しました。']);
    }

    public function uploadProductImage(Request $request, Product $product): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $file = $validated['image'];
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $productSlug = Str::slug($product->name) ?: 'product';
        $productCode = $product->id.'_'.$productSlug;
        $fileName = now()->format('Ymd_His').'_'.Str::random(8).'.'.$extension;
        $path = $file->storeAs("product/{$productCode}", $fileName, 'public');

        $product->update(['image_path' => Storage::url($path)]);

        return response()->json([
            'product' => $product->fresh(['store', 'category']),
            'image_path' => $product->image_path,
        ]);
    }

    public function uploadStoreImage(Request $request, Store $store): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $file = $validated['image'];
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $storeSlug = Str::slug($store->name) ?: 'store';
        $storeCode = $store->id.'_'.$storeSlug;
        $fileName = now()->format('Ymd_His').'_'.Str::random(8).'.'.$extension;
        $path = $file->storeAs("store/{$storeCode}", $fileName, 'public');

        $store->update(['image_path' => Storage::url($path)]);

        return response()->json([
            'store' => $store->fresh(),
            'image_path' => $store->image_path,
        ]);
    }
}
