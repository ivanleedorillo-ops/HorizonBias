<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiasSnapshot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'component_scores' => 'array',
            'metrics' => 'array',
            'explanations' => 'array',
            'data_as_of' => 'immutable_datetime',
            'generated_at' => 'immutable_datetime',
        ];
    }
}
