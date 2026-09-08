<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'analyses' => fn (Blueprint $table) => $table->json('analyses')->nullable(),
            'consensus' => fn (Blueprint $table) => $table->json('consensus')->nullable(),
            'agreement' => fn (Blueprint $table) => $table->string('agreement', 24)->nullable(),
            'confidence' => fn (Blueprint $table) => $table->unsignedTinyInteger('confidence')->nullable(),
            'provider_status' => fn (Blueprint $table) => $table->json('provider_status')->nullable(),
            'evidence_hash' => fn (Blueprint $table) => $table->string('evidence_hash', 64)->nullable(),
            'prompt_version' => fn (Blueprint $table) => $table->string('prompt_version', 40)->nullable(),
        ];

        // Previous experimental branches may have left untracked columns in a
        // local SQLite database. Preserve them and add only what is missing.
        foreach ($columns as $name => $addColumn) {
            if (! Schema::hasColumn('macro_briefs', $name)) {
                Schema::table('macro_briefs', $addColumn);
            }
        }
    }

    public function down(): void
    {
        foreach (['analyses', 'consensus', 'agreement', 'confidence', 'provider_status', 'evidence_hash', 'prompt_version'] as $column) {
            if (Schema::hasColumn('macro_briefs', $column)) {
                Schema::table('macro_briefs', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
