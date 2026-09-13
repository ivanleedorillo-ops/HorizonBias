<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiasOutcome extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'target_at' => 'immutable_datetime',
            'observed_at' => 'immutable_datetime',
            'evaluated_at' => 'immutable_datetime',
            'aligned' => 'boolean',
            'baseline_close' => 'float',
            'future_close' => 'float',
            'atr14' => 'float',
            'forward_return_percent' => 'float',
            'normalized_move' => 'float',
        ];
    }

    public function historyPoint(): BelongsTo
    {
        return $this->belongsTo(BiasHistoryPoint::class, 'bias_history_point_id');
    }
}
