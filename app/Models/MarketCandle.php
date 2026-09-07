<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketCandle extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['opened_at' => 'immutable_datetime'];
    }
}
