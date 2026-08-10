<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 16);
            $table->string('actor_name', 120)->nullable();
            $table->string('actor_role', 32)->nullable();
            $table->string('action', 64)->index();
            $table->string('entity_type', 64)->nullable()->index();
            $table->string('entity_id', 64)->nullable()->index();
            $table->string('summary', 500)->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
