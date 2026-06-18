<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_store_list_returns_store_products_from_database(): void
    {
        $category = Category::create([
            'name' => 'メイン',
            'display_order' => 1,
            'is_active' => true,
        ]);
        $store = Store::create([
            'name' => '麺ナビ 本店',
            'description' => 'DB管理店舗',
            'rating' => 4.8,
            'review_count' => 120,
            'display_order' => 1,
            'is_active' => true,
        ]);
        Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => '醤油ラーメン',
            'price' => 1000,
            'status' => 'active',
            'display_order' => 1,
        ]);
        Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => '売り切れラーメン',
            'price' => 1200,
            'status' => 'sold_out',
            'display_order' => 2,
        ]);
        Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => '非表示ラーメン',
            'price' => 900,
            'status' => 'hidden',
            'display_order' => 3,
        ]);

        $this->getJson('/api/stores')
            ->assertOk()
            ->assertJsonPath('stores.0.name', '麺ナビ 本店')
            ->assertJsonPath('stores.0.products.0.name', '醤油ラーメン')
            ->assertJsonPath('stores.0.products.1.name', '売り切れラーメン')
            ->assertJsonPath('stores.0.products.1.status', 'sold_out')
            ->assertJsonMissing(['name' => '非表示ラーメン']);
    }

    public function test_admin_can_create_store_and_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plainToken = 'admin-store-token';
        UserApiToken::create([
            'user_id' => $admin->id,
            'name' => 'web',
            'token_hash' => hash('sha256', $plainToken),
        ]);
        $category = Category::create([
            'name' => 'メイン',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->withToken($plainToken)
            ->postJson('/api/admin/stores', [
                'name' => '管理店舗',
                'description' => '管理画面から登録',
            ])
            ->assertCreated()
            ->assertJsonPath('store.name', '管理店舗');

        $store = Store::where('name', '管理店舗')->firstOrFail();

        Storage::fake('public');
        $imagePath = storage_path('framework/testing/store-upload.jpg');
        File::ensureDirectoryExists(dirname($imagePath));
        File::put($imagePath, base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/Aaf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/Aaf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IR//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z'));
        $image = new UploadedFile($imagePath, 'shop.jpg', 'image/jpeg', null, true);

        $this->withToken($plainToken)
            ->post("/api/admin/stores/{$store->id}/image", [
                'image' => $image,
            ])
            ->assertOk()
            ->assertJsonPath('store.image_path', fn ($path) => is_string($path)
                && preg_match('#^/storage/store/'.$store->id.'_guan-li-dian-pu/\d{8}_\d{6}_[A-Za-z0-9]{8}\.jpg$#', $path) === 1);

        $store->refresh();
        $this->assertNotNull($store->image_path);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $store->image_path));

        $this->withToken($plainToken)
            ->postJson('/api/admin/products', [
                'store_id' => $store->id,
                'category_id' => $category->id,
                'name' => '管理ラーメン',
                'price' => 1200,
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('product.name', '管理ラーメン');

        $product = Product::where('name', '管理ラーメン')->firstOrFail();

        $this->withToken($plainToken)
            ->patchJson("/api/admin/products/{$product->id}", [
                'store_id' => $store->id,
                'category_id' => $category->id,
                'name' => '更新ラーメン',
                'price' => 1300,
                'status' => 'sold_out',
            ])
            ->assertOk()
            ->assertJsonPath('product.name', '更新ラーメン')
            ->assertJsonPath('product.status', 'sold_out');

        $this->withToken($plainToken)
            ->deleteJson("/api/admin/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('message', 'メニューを削除しました。');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
