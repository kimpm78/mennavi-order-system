<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ユーザーテーブルの作成
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment('ユーザーID');
            $table->string('name', 100)->comment('氏名');
            $table->string('email')->unique()->comment('メールアドレス');
            $table->string('password')->comment('パスワード');
            $table->string('phone', 20)->nullable()->comment('電話番号');
            $table->string('postal_code', 10)->nullable()->comment('郵便番号');
            $table->string('address')->nullable()->comment('住所');
            $table->string('role', 20)->default('user')->index()->comment('権限');
            $table->string('status', 20)->default('active')->index()->comment('利用状態');
            $table->timestamp('email_verified_at')->nullable()->comment('メール認証日時');
            $table->timestamp('last_login_at')->nullable()->comment('最終ログイン日時');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes()->comment('削除日時');
        });

        // パスワードリセットトークンテーブルの作成
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // セッションテーブルの作成
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON TABLE users IS 'ユーザー情報'");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('user', 'admin'))");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'suspended', 'withdrawn'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
