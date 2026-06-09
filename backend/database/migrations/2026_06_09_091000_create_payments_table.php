<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id()->comment('決済ID');
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete()->comment('注文ID');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('ユーザーID');
            $table->string('provider', 20)->default('payjp')->index()->comment('決済プロバイダ');
            $table->string('provider_charge_id', 100)->nullable()->unique()->comment('決済プロバイダ側のCharge ID');
            $table->string('payment_method', 20)->default('card')->comment('支払方法');
            $table->string('payment_status', 20)->default('pending')->index()->comment('決済状態');
            $table->integer('amount')->comment('決済金額');
            $table->string('currency', 3)->default('jpy')->comment('通貨');
            $table->json('provider_response')->nullable()->comment('決済プロバイダのレスポンスJSON');
            $table->timestamp('paid_at')->nullable()->index()->comment('決済完了日時');
            $table->timestamp('failed_at')->nullable()->comment('決済失敗日時');
            $table->timestamp('refunded_at')->nullable()->comment('返金日時');
            $table->timestamps();

            $table->index(['order_id', 'payment_status']);
            $table->index(['user_id', 'created_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON TABLE payments IS '決済情報'");
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_amount_non_negative CHECK (amount >= 0)");
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_provider_check CHECK (provider IN ('payjp'))");
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_payment_status_check CHECK (payment_status IN ('pending', 'paid', 'failed', 'refunded'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
