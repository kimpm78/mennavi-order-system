<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('receipt_type', 20)->default('delivery')->after('customer_phone')->comment('受け取り方法 / delivery, pickup');
            $table->integer('subtotal_amount')->default(0)->after('receipt_type')->comment('商品小計 / 税抜');
            $table->integer('delivery_fee')->default(0)->after('subtotal_amount')->comment('配送料');
            $table->decimal('tax_rate', 5, 2)->default(8)->after('delivery_fee')->comment('税率');
            $table->integer('tax_amount')->default(0)->after('tax_rate')->comment('税額');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON COLUMN orders.receipt_type IS '受け取り方法 / delivery, pickup'");
            DB::statement("COMMENT ON COLUMN orders.subtotal_amount IS '商品小計 / 税抜'");
            DB::statement("COMMENT ON COLUMN orders.delivery_fee IS '配送料'");
            DB::statement("COMMENT ON COLUMN orders.tax_rate IS '税率'");
            DB::statement("COMMENT ON COLUMN orders.tax_amount IS '税額'");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_receipt_type_check CHECK (receipt_type IN ('delivery', 'pickup'))");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_subtotal_amount_non_negative CHECK (subtotal_amount >= 0)");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_fee_non_negative CHECK (delivery_fee >= 0)");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_tax_amount_non_negative CHECK (tax_amount >= 0)");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_tax_amount_non_negative');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_delivery_fee_non_negative');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_subtotal_amount_non_negative');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_receipt_type_check');
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_type',
                'subtotal_amount',
                'delivery_fee',
                'tax_rate',
                'tax_amount',
            ]);
        });
    }
};
