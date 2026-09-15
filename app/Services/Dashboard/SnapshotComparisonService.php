<?php

namespace App\Services\Dashboard;

use App\Models\BiasHistoryPoint;
use App\Models\BiasSnapshot;
use Illuminate\Support\Collection;

final class SnapshotComparisonService
{
    public function build(Collection $latest, ?array $overall): array
    {
        $changes = $latest->map(function (BiasSnapshot $current, string $key) {
            $previous = BiasSnapshot::query()
                ->where('symbol', $current->symbol)
                ->where('timeframe', $current->timeframe)
                ->where('data_as_of', '<', $current->data_as_of)
                ->latest('data_as_of')
                ->latest('id')
                ->first();

            if (! $previous) {
                return null;
            }

            $componentChanges = collect($current->component_scores ?? [])->map(function ($score, string $component) use ($previous) {
                $before = (int) ($previous->component_scores[$component] ?? 0);
                $after = (int) $score;

                return [
                    'component' => $component,
                    'previous' => $before,
                    'current' => $after,
                    'delta' => $after - $before,
                ];
            })->filter(fn (array $item) => $item['delta'] !== 0)
                ->sortByDesc(fn (array $item) => abs($item['delta']))
                ->values()
                ->all();

            return [
                'key' => $key,
                'label' => config("horizon.timeframes.{$key}.label", $key),
                'previous_score' => $previous->score,
                'current_score' => $current->score,
                'delta' => $current->score - $previous->score,
                'previous_bias' => $previous->label,
                'current_bias' => $current->label,
                'label_changed' => $previous->label !== $current->label,
                'component_changes' => $componentChanges,
                'previous_data_as_of' => $previous->data_as_of->toIso8601String(),
                'current_data_as_of' => $current->data_as_of->toIso8601String(),
            ];
        })->filter()->values();

        $meaningful = $changes->filter(fn (array $change) => $change['delta'] !== 0 || $change['label_changed']);
        $highlights = $meaningful
            ->sortByDesc(fn (array $change) => abs($change['delta']) + ($change['label_changed'] ? 1000 : 0))
            ->take(3)
            ->map(fn (array $change) => $this->summary($change))
            ->values()
            ->all();

        $overallChange = $this->overallChange($overall);

        return [
            'status' => $changes->isEmpty() ? 'collecting' : ($meaningful->isEmpty() ? 'unchanged' : 'ready'),
            'summary' => $changes->isEmpty()
                ? 'A second completed-candle snapshot is required before changes can be compared.'
                : ($meaningful->isEmpty()
                    ? 'No technical score changed from the previous completed observations.'
                    : 'Latest completed-candle changes are shown below. AI context does not alter these values.'),
            'overall' => $overallChange,
            'highlights' => $highlights,
            'timeframes' => $changes->all(),
        ];
    }

    private function overallChange(?array $overall): ?array
    {
        if (! $overall) {
            return null;
        }

        $points = BiasHistoryPoint::query()
            ->where('symbol', config('horizon.symbol'))
            ->where('scope', 'overall')
            ->latest('generated_at')
            ->latest('id')
            ->limit(2)
            ->get();

        $previous = $points->count() >= 2 ? $points->get(1) : null;
        if (! $previous) {
            return null;
        }

        return [
            'previous_score' => $previous->score,
            'current_score' => $overall['score'],
            'delta' => $overall['score'] - $previous->score,
            'previous_bias' => $previous->label,
            'current_bias' => $overall['label'],
            'label_changed' => $previous->label !== $overall['label'],
            'compared_at' => $previous->generated_at->toIso8601String(),
        ];
    }

    private function summary(array $change): string
    {
        $delta = $change['delta'];
        $movement = $delta === 0 ? 'held steady' : sprintf('moved %s%d points', $delta > 0 ? '+' : '', $delta);
        $classification = $change['label_changed']
            ? " ({$change['previous_bias']} to {$change['current_bias']})"
            : " and remains {$change['current_bias']}";
        $drivers = collect($change['component_changes'])->take(2)->map(function (array $component) {
            $delta = $component['delta'];

            return ucfirst($component['component']).' '.($delta > 0 ? '+' : '').$delta;
        })->implode(', ');

        return "{$change['key']} {$movement}{$classification}".($drivers !== '' ? "; {$drivers}." : '.');
    }
}
