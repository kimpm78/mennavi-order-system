<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_and_fetch_cart_items(): void
    {
        [$token, $product] = $this->createUserTokenAndProduct();

        $this->withToken($token)
            ->postJson('/api/cart/items', [
                'store_name' => '麺処 極 -KIWAMI-',
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('items.0.name', '特製濃厚醤油ラーメン')
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('total', 2560);

        $this->assertDatabaseHas('carts', ['store_name' => '麺処 極 -KIWAMI-']);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);

        $this->withToken($token)
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('items.0.menuItemId', $product->id);
    }

    public function test_adding_same_product_increments_quantity(): void
    {
        [$token, $product] = $this->createUserTokenAndProduct();

        $this->withToken($token)->postJson('/api/cart/items', [
            'store_name' => '麺処 極 -KIWAMI-',
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->withToken($token)
            ->postJson('/api/cart/items', [
                'store_name' => '麺処 極 -KIWAMI-',
                'product_id' => $product->id,
                'quantity' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('items.0.quantity', 4);
    }

    public function test_adding_different_store_replaces_cart(): void
    {
        [$token, $product] = $this->createUserTokenAndProduct();
        $secondProduct = Product::create([
            'category_id' => $product->category_id,
            'name' => '昔ながらの淡麗醤油ラーメン',
            'price' => 980,
            'status' => 'active',
            'display_order' => 2,
        ]);

        $this->withToken($token)->postJson('/api/cart/items', [
            'store_name' => '麺処 極 -KIWAMI-',
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->withToken($token)
            ->postJson('/api/cart/items', [
                'store_name' => '醤油の匠 蔵',
                'product_id' => $secondProduct->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('store_name', '醤油の匠 蔵')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.menuItemId', $secondProduct->id);

        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    /**
     * @return array{string, Product}
     */
    private function createUserTokenAndProduct(): array
    {
        $user = User::factory()->create();
        $plainToken = 'plain-test-token';

        UserApiToken::create([
            'user_id' => $user->id,
            'name' => 'web',
            'token_hash' => hash('sha256', $plainToken),
        ]);

        $category = Category::create([
            'name' => 'メイン',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => '特製濃厚醤油ラーメン',
            'price' => 1280,
            'status' => 'active',
            'display_order' => 1,
        ]);

        return [$plainToken, $product];
    }
}
