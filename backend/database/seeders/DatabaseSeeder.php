<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
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
            ],
        );

        $mainCategory = Category::updateOrCreate(
            ['name' => 'メイン'],
            [
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
