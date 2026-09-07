<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bias_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20);
            $table->string('timeframe', 10);
            $table->smallInteger('score');
            $table->string('label', 24);
            $table->json('component_scores');
            $table->json('metrics');
            $table->json('explanations');
            $table->string('provider', 40);
            $table->timestamp('data_as_of');
            $table->timestamp('generated_at');
            $table->string('status', 20)->default('ready');
            $table->timestamps();
            $table->index(['timeframe', 'generated_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('bias_snapshots'); }
};
