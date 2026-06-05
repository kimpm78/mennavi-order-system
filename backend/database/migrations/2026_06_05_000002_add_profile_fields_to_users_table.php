<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'postal_code')) {
                $table->string('postal_code', 10)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('users', 'address')) {
                $table->string('address')->nullable()->after('postal_code');
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 100)->default('user')->after('address');
            }

            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['phone', 'postal_code', 'address', 'role', 'deleted_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
