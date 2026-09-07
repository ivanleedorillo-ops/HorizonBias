<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_candles', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('symbol', 20);
            $table->string('timeframe', 10);
            $table->timestamp('opened_at');
            $table->decimal('open', 18, 8);
            $table->decimal('high', 18, 8);
            $table->decimal('low', 18, 8);
            $table->decimal('close', 18, 8);
            $table->decimal('volume', 24, 8)->nullable();
            $table->timestamps();
            $table->unique(['provider', 'symbol', 'timeframe', 'opened_at'], 'market_candle_identity');
        });
    }

    public function down(): void { Schema::dropIfExists('market_candles'); }
};
