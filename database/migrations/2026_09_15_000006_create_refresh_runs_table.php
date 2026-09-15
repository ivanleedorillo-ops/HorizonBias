<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_runs', function (Blueprint $table) {
            $table->id();
            $table->string('subsystem', 20);
            $table->string('target', 20)->nullable();
            $table->string('provider', 80);
            $table->string('status', 20)->default('running');
            $table->string('reason_code', 40)->nullable();
            $table->string('safe_message', 200)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['subsystem', 'target', 'finished_at'], 'refresh_run_target_finished');
            $table->index(['status', 'finished_at'], 'refresh_run_status_finished');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_runs');
    }
};
