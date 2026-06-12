<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_api_tokens', function (Blueprint $table) {
            $table->id()->comment('APIトークンID');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('ユーザーID');
            $table->string('name')->nullable()->comment('トークン名');
            $table->string('token_hash', 64)->unique()->comment('APIトークンハッシュ');
            $table->timestamp('last_used_at')->nullable()->comment('最終利用日時');
            $table->timestamp('expires_at')->nullable()->comment('有効期限');
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON TABLE user_api_tokens IS 'API認証トークン情報'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_api_tokens');
    }
};
