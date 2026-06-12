<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_operational_dashboard_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plainToken = 'admin-dashboard-token';
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
        $product = Product::create([
            'category_id' => $category->id,
            'name' => '醤油ラーメン',
            'price' => 1000,
            'status' => 'active',
            'display_order' => 1,
        ]);
        $order = Order::create([
            'order_number' => 'MN-ADMIN-0001',
            'user_id' => $admin->id,
            'customer_name' => '管理 太郎',
            'customer_email' => 'admin@example.com',
            'receipt_type' => 'delivery',
            'subtotal_amount' => 1000,
            'delivery_fee' => 350,
            'tax_rate' => 8,
            'tax_amount' => 108,
            'total_amount' => 1458,
            'order_status' => 'received',
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'ordered_at' => now(),
        ]);
        Order::create([
            'order_number' => 'MN-ADMIN-0000',
            'user_id' => $admin->id,
            'customer_name' => '管理 太郎',
            'customer_email' => 'admin@example.com',
            'receipt_type' => 'pickup',
            'subtotal_amount' => 729,
            'delivery_fee' => 0,
            'tax_rate' => 8,
            'tax_amount' => 58,
            'total_amount' => 787,
            'order_status' => 'completed',
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'ordered_at' => now()->subDay(),
        ]);
        Order::create([
            'order_number' => 'MN-ADMIN-0002',
            'user_id' => $admin->id,
            'customer_name' => '管理 花子',
            'customer_email' => 'admin2@example.com',
            'receipt_type' => 'delivery',
            'subtotal_amount' => 2000,
            'delivery_fee' => 350,
            'tax_rate' => 8,
            'tax_amount' => 188,
            'total_amount' => 2538,
            'order_status' => 'completed',
            'payment_method' => 'card',
            'payment_status' => 'partial_refunded',
            'ordered_at' => now()->subMinutes(5),
        ]);
        Order::create([
            'order_number' => 'MN-ADMIN-0003',
            'user_id' => $admin->id,
            'customer_name' => '管理 次郎',
            'customer_email' => 'admin3@example.com',
            'receipt_type' => 'delivery',
            'subtotal_amount' => 1000,
            'delivery_fee' => 350,
            'tax_rate' => 8,
            'tax_amount' => 108,
            'total_amount' => 1458,
            'order_status' => 'cooking',
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'ordered_at' => now()->subMinutes(15),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 1000,
            'quantity' => 1,
            'subtotal' => 1000,
        ]);

        $this->withToken($plainToken)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('summary.today_orders', 3)
            ->assertJsonPath('summary.today_sales', 1458)
            ->assertJsonPath('summary.today_orders_change_rate', 200)
            ->assertJsonPath('summary.today_sales_change_rate', 85)
            ->assertJsonPath('summary.average_cooking_minutes', 15)
            ->assertJsonPath('summary.kitchen_load', 10)
            ->assertJsonPath('orders.0.order_number', 'MN-ADMIN-0001')
            ->assertJsonPath('orders.0.payment_status', 'paid')
            ->assertJsonMissing(['delivery_networks' => []]);

        $this->withToken($plainToken)
            ->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonFragment([
                'order_number' => 'MN-ADMIN-0002',
                'payment_status' => 'partial_refunded',
            ]);

        $this->withToken($plainToken)
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'order_status' => 'cooking',
            ])
            ->assertOk()
            ->assertJsonPath('order.order_number', 'MN-ADMIN-0001')
            ->assertJsonPath('order.status', 'cooking');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'cooking',
        ]);

        $this->withToken($plainToken)
            ->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonPath('products.0.name', '醤油ラーメン');
    }

    public function test_customer_cannot_fetch_admin_data(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $plainToken = 'customer-token';
        UserApiToken::create([
            'user_id' => $user->id,
            'name' => 'web',
            'token_hash' => hash('sha256', $plainToken),
        ]);

        $this->withToken($plainToken)
            ->getJson('/api/admin/dashboard')
            ->assertUnauthorized();
    }
}
