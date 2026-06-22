<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public bool $withinTransaction = false;
    
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ユーザーテーブルの作成
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email');
            $table->string('password');
            $table->string('phone', 20)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('address')->nullable();
            $table->string('role', 20)->default('user');
            $table->string('status', 20)->default('active');
            $table->integer('point_balance')->default(0);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // PostgreSQL環境で自動生成される制約名の衝突を避けるため、メールアドレスの一意制約名を明示する
            $table->unique('email', 'users_email_order_system_unique');
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
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_point_balance_non_negative CHECK (point_balance >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
