<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
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
        User::updateOrCreate(
            ['email' => 'admin@mennavi.local'],
            [
                'name' => '管理者',
                'password' => Hash::make('admin1234'),
                'role' => 'admin',
                'status' => 'active',
            ],
        );

        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'status' => 'active',
                'postal_code' => '1500041',
                'address' => '東京都渋谷区神南',
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

        $mainCategory = Category::where('name', 'メイン')->firstOrFail();
        $store = Store::updateOrCreate(
            ['name' => '麺処 極 -KIWAMI-'],
            [
                'description' => '24時間かけて丁寧に炊き上げた濃厚豚骨スープと、特製極細麺が織りなす至極の一杯。奥深い旨みと上品な香りをお楽しみください。',
                'address' => '東京都渋谷区神南 1-2-3',
                'rating' => 4.8,
                'review_count' => 2400,
                'budget_label' => '予算: ¥1,000〜¥2,000',
                'display_order' => 1,
                'is_active' => true,
            ],
        );

        $products = [
            [
                'id' => 1,
                'name' => '特製濃厚醤油ラーメン',
                'description' => '当店自慢のスープは、鶏と魚介を12時間じっくり煮込んで仕上げたものです。',
                'price' => 1280,
                'display_order' => 1,
            ],
            [
                'id' => 2,
                'name' => '昔ながらの淡麗醤油ラーメン',
                'description' => 'あっさりとした伝統的な一杯。澄んだ鶏清湯スープの旨みを味わえます。',
                'price' => 980,
                'display_order' => 2,
            ],
            [
                'id' => 3,
                'name' => '生姜香る旨辛ラーメン',
                'description' => '生姜エキスと自家製ラー油で、体が温まる刺激的な一杯です。',
                'price' => 1100,
                'display_order' => 3,
            ],
            [
                'id' => 4,
                'name' => '特製濃厚つけ麺',
                'description' => '太くもちもちとした麺を熱々の濃厚つけ汁で楽しむ一杯です。',
                'price' => 1350,
                'display_order' => 4,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['id' => $product['id']],
                [
                    'category_id' => $mainCategory->id,
                    'store_id' => $store->id,
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'status' => 'active',
                    'display_order' => $product['display_order'],
                ],
            );
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('products', 'id'), (SELECT MAX(id) FROM products))");
        }
    }
}
