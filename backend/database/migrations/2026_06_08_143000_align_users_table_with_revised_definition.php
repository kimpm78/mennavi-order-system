<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 20)->default('active')->after('role');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            }
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("UPDATE users SET status = 'active' WHERE status IS NULL");
            DB::statement('ALTER TABLE users ALTER COLUMN email TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(20)');
            DB::statement('ALTER TABLE users ALTER COLUMN status TYPE VARCHAR(20)');
            DB::statement("ALTER TABLE users ALTER COLUMN status SET DEFAULT 'active'");
            DB::statement('ALTER TABLE users ALTER COLUMN status SET NOT NULL');

            if (Schema::hasColumn('users', 'login_id')) {
                DB::statement('ALTER TABLE users DROP COLUMN login_id');
            }

            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('user', 'admin'))");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'suspended', 'withdrawn'))");

            DB::statement("COMMENT ON TABLE users IS 'ユーザー情報'");
            DB::statement("COMMENT ON COLUMN users.name IS '氏名 / 注文者名'");
            DB::statement("COMMENT ON COLUMN users.email IS 'メールアドレス / ログインID'");
            DB::statement("COMMENT ON COLUMN users.password IS 'パスワード / ハッシュ化して保存'");
            DB::statement("COMMENT ON COLUMN users.role IS '権限 / user または admin'");
            DB::statement("COMMENT ON COLUMN users.status IS '利用状態 / active, suspended, withdrawn'");
            DB::statement("COMMENT ON COLUMN users.email_verified_at IS 'メール認証日時 / メール認証を使う場合'");
            DB::statement("COMMENT ON COLUMN users.last_login_at IS '最終ログイン日時 / 管理画面表示用'");
            DB::statement("COMMENT ON COLUMN users.deleted_at IS '削除日時 / 論理削除'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'login_id')) {
                $table->string('login_id', 100)->nullable()->unique()->after('name');
            }

            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }

            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
