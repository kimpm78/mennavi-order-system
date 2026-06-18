<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::updateOrCreate(
            [
                'code' => 'mennavi_plus',
            ],
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
    }
}