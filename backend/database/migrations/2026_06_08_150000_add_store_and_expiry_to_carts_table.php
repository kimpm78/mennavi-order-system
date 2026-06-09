<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (! Schema::hasColumn('carts', 'store_name')) {
                $table->string('store_name', 150)->nullable()->after('session_id')->comment('店舗名 / カート内の商品を同一店舗に制限');
            }

            if (! Schema::hasColumn('carts', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->index()->after('store_name')->comment('カート有効期限 / 最終操作から30分');
            }
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON COLUMN carts.store_name IS '店舗名 / カート内の商品を同一店舗に制限'");
            DB::statement("COMMENT ON COLUMN carts.expires_at IS 'カート有効期限 / 最終操作から30分'");
        }
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasColumn('carts', 'expires_at')) {
                $table->dropColumn('expires_at');
            }

            if (Schema::hasColumn('carts', 'store_name')) {
                $table->dropColumn('store_name');
            }
        });
    }
};
