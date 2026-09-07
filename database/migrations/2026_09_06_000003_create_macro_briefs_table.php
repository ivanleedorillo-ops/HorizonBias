<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('macro_briefs', function (Blueprint $table) {
            $table->id();
            $table->string('stance', 12);
            $table->string('risk_level', 12);
            $table->text('summary');
            $table->json('events');
            $table->json('sources');
            $table->timestamp('generated_at');
            $table->string('status', 20)->default('ready');
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('macro_briefs'); }
};
