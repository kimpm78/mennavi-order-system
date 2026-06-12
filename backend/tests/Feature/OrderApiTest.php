<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\UserApiToken;
use App\Models\UserPaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_order_history(): void
    {
        $user = User::factory()->create();
        $plainToken = 'order-history-token';

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
            'image_path' => '/images/products/special-ramen.png',
            'status' => 'active',
            'display_order' => 1,
        ]);
        $order = Order::create([
            'order_number' => 'MN-20260608-0001',
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => '09012345678',
            'total_amount' => 2560,
            'order_status' => 'received',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'ordered_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 1280,
            'quantity' => 2,
            'subtotal' => 2560,
        ]);

        $this->withToken($plainToken)
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.order_number', 'MN-20260608-0001')
            ->assertJsonPath('orders.0.payment_status', 'paid')
            ->assertJsonPath('orders.0.items.0.product_name', '特製濃厚醤油ラーメン')
            ->assertJsonPath('orders.0.items.0.imagePath', '/images/products/special-ramen.png');
    }

    public function test_user_can_create_order_with_payjp_token(): void
    {
        config(['services.payjp.secret_key' => 'sk_test_example']);
        Http::fake([
            'https://api.pay.jp/v1/charges' => Http::response([
                'id' => 'ch_test_123',
                'amount' => 2764,
                'currency' => 'jpy',
                'paid' => true,
                'card' => [
                    'brand' => 'Visa',
                    'last4' => '4242',
                    'exp_month' => 12,
                    'exp_year' => 2030,
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $plainToken = 'order-create-token';
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
        $cart = Cart::create([
            'user_id' => $user->id,
            'store_name' => '麺処 極 -KIWAMI-',
            'expires_at' => now()->addMinutes(30),
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->withToken($plainToken)
            ->postJson('/api/orders', [
                'payjp_token' => 'tok_test_123',
                'receipt_type' => 'pickup',
            ])
            ->assertCreated()
            ->assertJsonPath('order.subtotal_amount', 2560)
            ->assertJsonPath('order.delivery_fee', 0)
            ->assertJsonPath('order.tax_amount', 204)
            ->assertJsonPath('order.total_amount', 2764)
            ->assertJsonPath('order.payment_status', 'paid');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total_amount' => 2764,
        ]);
        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'provider' => 'payjp',
            'provider_charge_id' => 'ch_test_123',
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'amount' => 2764,
            'currency' => 'jpy',
            'card_brand' => 'Visa',
            'card_last4' => '4242',
            'card_exp_month' => 12,
            'card_exp_year' => 2030,
        ]);
        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
    }

    public function test_user_can_save_default_payjp_card_and_create_order_with_it(): void
    {
        config(['services.payjp.secret_key' => 'sk_test_example']);
        Http::fake([
            'https://api.pay.jp/v1/customers' => Http::response([
                'id' => 'cus_test_123',
                'default_card' => 'car_test_123',
                'cards' => [
                    'data' => [[
                        'id' => 'car_test_123',
                        'brand' => 'Visa',
                        'last4' => '4242',
                        'exp_month' => 12,
                        'exp_year' => 2030,
                    ]],
                ],
            ]),
            'https://api.pay.jp/v1/charges' => Http::response([
                'id' => 'ch_saved_123',
                'amount' => 1760,
                'currency' => 'jpy',
                'paid' => true,
            ]),
        ]);

        $user = User::factory()->create();
        $plainToken = 'saved-card-token';
        UserApiToken::create([
            'user_id' => $user->id,
            'name' => 'web',
            'token_hash' => hash('sha256', $plainToken),
        ]);

        $this->withToken($plainToken)
            ->postJson('/api/payment-methods', [
                'payjp_token' => 'tok_card_123',
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('payment_method.last4', '4242')
            ->assertJsonPath('payment_method.is_default', true);

        $paymentMethod = UserPaymentMethod::where('user_id', $user->id)->firstOrFail();

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
        $cart = Cart::create([
            'user_id' => $user->id,
            'store_name' => '麺処 極 -KIWAMI-',
            'expires_at' => now()->addMinutes(30),
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->withToken($plainToken)
            ->postJson('/api/orders', [
                'payment_method_id' => $paymentMethod->id,
            ])
            ->assertCreated()
            ->assertJsonPath('order.subtotal_amount', 1280)
            ->assertJsonPath('order.delivery_fee', 350)
            ->assertJsonPath('order.tax_amount', 130)
            ->assertJsonPath('order.total_amount', 1760)
            ->assertJsonPath('order.payment_status', 'paid');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'provider_charge_id' => 'ch_saved_123',
            'card_brand' => 'Visa',
            'card_last4' => '4242',
            'card_exp_month' => 12,
            'card_exp_year' => 2030,
        ]);
        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
    }
}
