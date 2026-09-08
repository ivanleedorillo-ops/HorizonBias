<?php

namespace App\Services\Macro;

use App\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Cache;

final class AiUsageLimiter
{
    public function claim(string $provider): void
    {
        $limit = max(1, (int) config('horizon.ai.daily_request_cap', 24));
        $key = $this->key($provider);
        $lock = Cache::lock($key.':lock', 10);

        try {
            $lock->block(5);
            $attempts = (int) Cache::get($key, 0);
            if ($attempts >= $limit) {
                throw new AiProviderException($provider, 'quota_limited', "{$provider} daily free-tier request cap reached.");
            }

            Cache::put($key, $attempts + 1, now('UTC')->endOfDay());
        } finally {
            optional($lock)->release();
        }
    }

    public function attempts(string $provider): int
    {
        return (int) Cache::get($this->key($provider), 0);
    }

    private function key(string $provider): string
    {
        return 'horizon-bias:ai-usage:'.strtolower(preg_replace('/[^a-z0-9]+/i', '-', $provider) ?? $provider).':'.now('UTC')->toDateString();
    }
}
