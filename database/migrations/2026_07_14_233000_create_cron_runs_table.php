<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_runs', function (Blueprint $table) {
            $table->id();
            $table->string('job_key', 64)->index();
            $table->string('command', 120);
            $table->string('trigger', 16)->default('schedule')->index();
            $table->string('status', 16)->default('running')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('counts')->nullable();
            $table->string('message', 500)->nullable();
            $table->text('error')->nullable();
            $table->mediumText('log')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['job_key', 'started_at']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_runs');
    }
};
