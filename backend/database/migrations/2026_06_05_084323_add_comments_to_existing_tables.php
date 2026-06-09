<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $this->commentTable('users', 'ユーザー情報');
        $this->commentColumn('users', 'id', 'ユーザーID');
        $this->commentColumn('users', 'name', '氏名/注文者名');
        $this->commentColumn('users', 'email', 'メールアドレス');
        $this->commentColumn('users', 'email_verified_at', 'メール認証日時');
        $this->commentColumn('users', 'password', 'パスワード');
        $this->commentColumn('users', 'phone', '電話番号');
        $this->commentColumn('users', 'postal_code', '郵便番号');
        $this->commentColumn('users', 'address', '住所');
        $this->commentColumn('users', 'role', '権限');
        $this->commentColumn('users', 'remember_token', 'ログイン保持トークン');
        $this->commentColumn('users', 'created_at', '作成日時');
        $this->commentColumn('users', 'updated_at', '更新日時');
        $this->commentColumn('users', 'deleted_at', '削除日時');

        $this->commentTable('user_api_tokens', 'API認証トークン情報');
        $this->commentColumn('user_api_tokens', 'id', 'APIトークンID');
        $this->commentColumn('user_api_tokens', 'user_id', 'ユーザーID');
        $this->commentColumn('user_api_tokens', 'name', 'トークン名');
        $this->commentColumn('user_api_tokens', 'token_hash', 'APIトークンハッシュ');
        $this->commentColumn('user_api_tokens', 'last_used_at', '最終利用日時');
        $this->commentColumn('user_api_tokens', 'expires_at', '有効期限');
        $this->commentColumn('user_api_tokens', 'created_at', '作成日時');
        $this->commentColumn('user_api_tokens', 'updated_at', '更新日時');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([
            'users' => [
                'id',
                'name',
                'email',
                'email_verified_at',
                'password',
                'phone',
                'postal_code',
                'address',
                'role',
                'remember_token',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
            'user_api_tokens' => [
                'id',
                'user_id',
                'name',
                'token_hash',
                'last_used_at',
                'expires_at',
                'created_at',
                'updated_at',
            ],
        ] as $table => $columns) {
            $this->commentTable($table, null);

            foreach ($columns as $column) {
                $this->commentColumn($table, $column, null);
            }
        }
    }

    private function commentTable(string $table, ?string $comment): void
    {
        DB::statement(sprintf(
            'COMMENT ON TABLE %s IS %s',
            $this->identifier($table),
            $this->commentValue($comment),
        ));
    }

    private function commentColumn(string $table, string $column, ?string $comment): void
    {
        DB::statement(sprintf(
            'COMMENT ON COLUMN %s.%s IS %s',
            $this->identifier($table),
            $this->identifier($column),
            $this->commentValue($comment),
        ));
    }

    private function identifier(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    private function commentValue(?string $comment): string
    {
        if ($comment === null) {
            return 'NULL';
        }

        return DB::getPdo()->quote($comment);
    }
};
