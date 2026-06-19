<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\AdminNotificationRead;
use App\Models\ContactMessage;
use App\Models\FavoriteStore;
use App\Models\MainVisualSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@mennavi.local'],
            [
                'name' => '管理者',
                'password' => Hash::make('admin1234'),
                'role' => 'admin',
                'status' => 'active',
            ],
        );

        $testUser = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'status' => 'active',
                'phone' => '09012345678',
                'postal_code' => '1500041',
                'address' => '東京都渋谷区神南',
                'point_balance' => 0,
            ],
        );

        MainVisualSetting::updateOrCreate(
            ['id' => 1],
            [
                'title' => '今日の一杯を見つけよう',
                'description' => '厳選された究極のラーメン店ガイド。あなたの気分に合わせた最高の一杯をご提案します。',
                'image_path' => null,
                'is_active' => true,
            ],
        );

        SubscriptionPlan::updateOrCreate(
            ['code' => 'mennavi_plus'],
            [
                'name' => '麺ナビ Plus',
                'price' => 980,
                'currency' => 'jpy',
                'billing_cycle' => 'monthly',
                'discount_rate' => 15,
                'free_delivery' => true,
                'is_active' => true,
            ],
        );

        $defaultCategories = [
            ['name' => 'メイン', 'display_order' => 1],
            ['name' => 'トッピング', 'display_order' => 2],
            ['name' => 'サイド', 'display_order' => 3],
            ['name' => 'ドリンク & お酒', 'display_order' => 4],
        ];

        foreach ($defaultCategories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                [
                    'display_order' => $category['display_order'],
                    'is_active' => true,
                ],
            );
        }

        $categories = Category::pluck('id', 'name');
        $store = Store::updateOrCreate(
            ['name' => '麺処 極 -KIWAMI-'],
            [
                'description' => '24時間かけて丁寧に炊き上げた濃厚豚骨スープと、特製極細麺が織りなす至極の一杯。奥深い旨みと上品な香りをお楽しみください。',
                'address' => '東京都渋谷区神南 1-2-3',
                'phone' => '03-1234-5678',
                'invoice_number' => 'T1234567890123',
                'weekday_hours' => "11:00-15:00\n17:00-22:00",
                'weekend_hours' => '11:00-22:00',
                'holiday' => '火曜日',
                'rating' => 0,
                'review_count' => 0,
                'budget_label' => '予算: ¥1,000〜¥2,000',
                'display_order' => 1,
                'is_active' => true,
            ],
        );

        $products = [
            [
                'id' => 1,
                'category' => 'メイン',
                'name' => '特製濃厚醤油ラーメン',
                'description' => '当店自慢のスープは、鶏と魚介を12時間じっくり煮込んで仕上げたものです。',
                'price' => 1280,
                'display_order' => 1,
            ],
            [
                'id' => 2,
                'category' => 'メイン',
                'name' => '昔ながらの淡麗醤油ラーメン',
                'description' => 'あっさりとした伝統的な一杯。澄んだ鶏清湯スープの旨みを味わえます。',
                'price' => 980,
                'display_order' => 2,
            ],
            [
                'id' => 3,
                'category' => 'メイン',
                'name' => '生姜香る旨辛ラーメン',
                'description' => '生姜エキスと自家製ラー油で、体が温まる刺激的な一杯です。',
                'price' => 1100,
                'display_order' => 3,
            ],
            [
                'id' => 4,
                'category' => 'メイン',
                'name' => '特製濃厚つけ麺',
                'description' => '太くもちもちとした麺を熱々の濃厚つけ汁で楽しむ一杯です。',
                'price' => 1350,
                'display_order' => 4,
            ],
            [
                'id' => 5,
                'category' => 'サイド',
                'name' => '自家製焼き餃子（6個）',
                'description' => '香ばしく焼き上げた定番サイドメニューです。',
                'price' => 450,
                'display_order' => 5,
            ],
            [
                'id' => 6,
                'category' => 'ドリンク & お酒',
                'name' => '生ビール',
                'description' => 'ラーメンと相性の良い冷えた生ビールです。',
                'price' => 550,
                'display_order' => 6,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['id' => $product['id']],
                [
                    'category_id' => $categories[$product['category']],
                    'store_id' => $store->id,
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'status' => 'active',
                    'display_order' => $product['display_order'],
                ],
            );
        }

        FavoriteStore::firstOrCreate([
            'user_id' => $testUser->id,
            'store_id' => $store->id,
        ]);

        $completedOrder = Order::updateOrCreate(
            ['order_number' => 'MN-20260615-HJAYNX'],
            [
                'user_id' => $testUser->id,
                'customer_name' => $testUser->name,
                'customer_email' => $testUser->email,
                'customer_phone' => $testUser->phone,
                'store_name_snapshot' => $store->name,
                'store_invoice_number' => $store->invoice_number,
                'receipt_type' => 'delivery',
                'subtotal_amount' => 1730,
                'delivery_fee' => 350,
                'tax_rate' => 8,
                'tax_amount' => 166,
                'total_amount' => 2246,
                'earned_points' => 67,
                'order_status' => 'completed',
                'payment_method' => 'card',
                'payment_status' => 'paid',
                'delivery_staff_name' => '佐藤A',
                'ordered_at' => now()->setTime(12, 10),
                'delivered_at' => now()->setTime(12, 25),
                'received_at' => now()->setTime(12, 40),
            ],
        );

        $completedOrder->items()->delete();
        $completedOrder->items()->createMany([
            [
                'product_id' => 1,
                'product_name' => '特製濃厚醤油ラーメン',
                'unit_price' => 1280,
                'quantity' => 1,
                'subtotal' => 1280,
            ],
            [
                'product_id' => 5,
                'product_name' => '自家製焼き餃子（6個）',
                'unit_price' => 450,
                'quantity' => 1,
                'subtotal' => 450,
            ],
        ]);

        Payment::updateOrCreate(
            ['provider_charge_id' => 'ch_test_seed_completed'],
            [
                'order_id' => $completedOrder->id,
                'user_id' => $testUser->id,
                'provider' => 'payjp',
                'provider_customer_id' => 'cus_test_seed',
                'provider_card_id' => 'car_test_seed',
                'payment_method' => 'card',
                'payment_status' => 'paid',
                'amount' => $completedOrder->total_amount,
                'currency' => 'jpy',
                'card_brand' => 'Visa',
                'card_last4' => '4242',
                'card_exp_month' => 12,
                'card_exp_year' => 2030,
                'provider_response' => ['id' => 'ch_test_seed_completed', 'livemode' => false],
                'paid_at' => $completedOrder->ordered_at,
            ],
        );

        Review::updateOrCreate(
            ['user_id' => $testUser->id, 'order_id' => $completedOrder->id],
            [
                'store_id' => $store->id,
                'rating' => 5,
                'content' => '濃厚なスープと餃子の相性がとても良かったです。',
            ],
        );

        $receivedOrder = Order::updateOrCreate(
            ['order_number' => 'MN-20260615-NEW001'],
            [
                'user_id' => $testUser->id,
                'customer_name' => $testUser->name,
                'customer_email' => $testUser->email,
                'customer_phone' => $testUser->phone,
                'receipt_type' => 'pickup',
                'subtotal_amount' => 980,
                'delivery_fee' => 0,
                'tax_rate' => 8,
                'tax_amount' => 78,
                'total_amount' => 1058,
                'earned_points' => 31,
                'order_status' => 'received',
                'payment_method' => 'paypay',
                'payment_status' => 'paid',
                'ordered_at' => now()->subMinutes(8),
            ],
        );

        $receivedOrder->items()->delete();
        $receivedOrder->items()->create([
            'product_id' => 2,
            'product_name' => '昔ながらの淡麗醤油ラーメン',
            'unit_price' => 980,
            'quantity' => 1,
            'subtotal' => 980,
        ]);

        Payment::updateOrCreate(
            ['provider_charge_id' => 'paypay_test_seed_received'],
            [
                'order_id' => $receivedOrder->id,
                'user_id' => $testUser->id,
                'provider' => 'paypay',
                'payment_method' => 'paypay',
                'payment_status' => 'paid',
                'amount' => $receivedOrder->total_amount,
                'currency' => 'jpy',
                'provider_response' => ['id' => 'paypay_test_seed_received', 'test_mode' => true],
                'paid_at' => $receivedOrder->ordered_at,
            ],
        );

        ContactMessage::updateOrCreate(
            ['email' => $testUser->email, 'order_number' => $completedOrder->order_number],
            [
                'user_id' => $testUser->id,
                'name' => $testUser->name,
                'category' => '注文について',
                'message' => '注文内容について確認したいです。',
                'status' => 'new',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'DatabaseSeeder',
            ],
        );

        AdminNotificationRead::updateOrCreate(
            ['user_id' => $admin->id, 'notification_id' => 'completed-'.$completedOrder->id],
            ['read_at' => now()],
        );

        $testUser->forceFill([
            'point_balance' => Order::where('user_id', $testUser->id)
                ->where('order_status', '<>', 'canceled')
                ->sum('earned_points'),
        ])->save();

        $store->forceFill([
            'rating' => round((float) $store->reviews()->avg('rating'), 1),
            'review_count' => $store->reviews()->count(),
        ])->save();

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('main_visual_settings', 'id'), (SELECT MAX(id) FROM main_visual_settings))");
            DB::statement("SELECT setval(pg_get_serial_sequence('products', 'id'), (SELECT MAX(id) FROM products))");
            DB::statement("SELECT setval(pg_get_serial_sequence('orders', 'id'), (SELECT MAX(id) FROM orders))");
        }
    }
}
