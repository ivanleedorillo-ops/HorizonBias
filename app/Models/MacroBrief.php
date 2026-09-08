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
            'analyses' => 'array',
            'consensus' => 'array',
            'provider_status' => 'array',
            'confidence' => 'integer',
            'generated_at' => 'immutable_datetime',
        ];
    }
}
