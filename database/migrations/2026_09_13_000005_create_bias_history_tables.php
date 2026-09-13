<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bias_history_points', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20);
            $table->string('scope', 10);
            $table->smallInteger('score');
            $table->string('label', 24);
            $table->json('component_scores')->nullable();
            $table->json('metrics')->nullable();
            $table->json('source_snapshot_ids');
            $table->string('source_hash', 64)->unique();
            $table->timestamp('data_as_of');
            $table->timestamp('generated_at');
            $table->string('status', 20)->default('ready');
            $table->timestamps();

            $table->index(['symbol', 'scope', 'generated_at'], 'bias_history_scope_generated');
            $table->index(['symbol', 'scope', 'data_as_of'], 'bias_history_scope_data');
        });

        Schema::create('bias_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bias_history_point_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('horizon_minutes');
            $table->timestamp('target_at');
            $table->timestamp('observed_at')->nullable();
            $table->decimal('baseline_close', 18, 8);
            $table->decimal('future_close', 18, 8)->nullable();
            $table->decimal('atr14', 18, 8)->nullable();
            $table->decimal('forward_return_percent', 12, 6)->nullable();
            $table->decimal('normalized_move', 12, 6)->nullable();
            $table->string('actual_direction', 12)->nullable();
            $table->boolean('aligned')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['bias_history_point_id', 'horizon_minutes'], 'bias_outcome_identity');
            $table->index(['status', 'target_at'], 'bias_outcome_due');
        });

        Schema::table('bias_snapshots', function (Blueprint $table) {
            $table->index(['symbol', 'timeframe', 'generated_at'], 'bias_snapshot_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('bias_snapshots', function (Blueprint $table) {
            $table->dropIndex('bias_snapshot_lookup');
        });
        Schema::dropIfExists('bias_outcomes');
        Schema::dropIfExists('bias_history_points');
    }
};
