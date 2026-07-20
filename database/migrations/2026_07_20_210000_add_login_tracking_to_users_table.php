<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('is_blocked');
            }
            if (!Schema::hasColumn('users', 'last_ip')) {
                $table->string('last_ip', 45)->nullable()->after('last_login_at');
            }
            if (!Schema::hasColumn('users', 'registration_ip')) {
                $table->string('registration_ip', 45)->nullable()->after('last_ip');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->index('last_login_at');
            }
            if (Schema::hasColumn('users', 'last_ip')) {
                $table->index('last_ip');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropIndex(['last_login_at']);
            }
            if (Schema::hasColumn('users', 'last_ip')) {
                $table->dropIndex(['last_ip']);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            foreach (['last_login_at', 'last_ip', 'registration_ip'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
