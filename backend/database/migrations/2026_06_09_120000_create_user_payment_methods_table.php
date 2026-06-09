<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_payment_methods', function (Blueprint $table) {
            $table->id()->comment('ユーザー決済方法ID');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('ユーザーID');
            $table->string('provider', 20)->default('payjp')->index()->comment('決済プロバイダ');
            $table->string('provider_customer_id', 100)->comment('決済プロバイダ側のCustomer ID');
            $table->string('provider_card_id', 100)->nullable()->comment('決済プロバイダ側のCard ID');
            $table->string('brand', 50)->nullable()->comment('カードブランド');
            $table->string('last4', 4)->nullable()->comment('カード番号下4桁');
            $table->unsignedTinyInteger('exp_month')->nullable()->comment('有効期限月');
            $table->unsignedSmallInteger('exp_year')->nullable()->comment('有効期限年');
            $table->boolean('is_default')->default(false)->index()->comment('デフォルトカード');
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
            $table->unique(['provider', 'provider_card_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON TABLE user_payment_methods IS 'ユーザー決済方法'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_payment_methods');
    }
};
