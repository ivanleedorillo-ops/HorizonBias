<?php

namespace App\Services\Market;

final class MarketMode
{
    public function effective(): string
    {
        $requested = config('horizon.market_mode');
        if ($requested !== 'live') {
            return 'demo';
        }

        if (app()->environment('production') && ! config('horizon.external_display_licensed')) {
            return 'demo';
        }

        return 'live';
    }

    public function licensingGateApplied(): bool
    {
        return config('horizon.market_mode') === 'live'
            && app()->environment('production')
            && ! config('horizon.external_display_licensed');
    }
}
