<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiasHistoryPoint extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'component_scores' => 'array',
            'metrics' => 'array',
            'source_snapshot_ids' => 'array',
            'data_as_of' => 'immutable_datetime',
            'generated_at' => 'immutable_datetime',
        ];
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(BiasOutcome::class);
    }
}
