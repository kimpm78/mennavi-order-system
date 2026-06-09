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
            if (! Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status', 20)->default('pending')->index()->after('payment_method')->comment('決済状態 / pending, paid, failed, refunded');
            }

            if (! Schema::hasColumn('orders', 'payjp_charge_id')) {
                $table->string('payjp_charge_id', 100)->nullable()->unique()->after('payment_status')->comment('PAY.JP Charge ID');
            }
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON COLUMN orders.payment_status IS '決済状態 / pending, paid, failed, refunded'");
            DB::statement("COMMENT ON COLUMN orders.payjp_charge_id IS 'PAY.JP Charge ID'");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_check CHECK (payment_status IN ('pending', 'paid', 'failed', 'refunded'))");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_status_check');
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payjp_charge_id')) {
                $table->dropColumn('payjp_charge_id');
            }

            if (Schema::hasColumn('orders', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
        });
    }
};
