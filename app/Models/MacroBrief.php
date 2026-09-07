<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MacroBrief extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'sources' => 'array',
            'generated_at' => 'immutable_datetime',
        ];
    }
}
