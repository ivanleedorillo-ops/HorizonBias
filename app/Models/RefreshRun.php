<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefreshRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
