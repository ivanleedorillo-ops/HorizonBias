<?php

namespace Tests\Unit;

use App\Exceptions\AiProviderException;
use App\Services\Macro\AiUsageLimiter;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiUsageLimiterTest extends TestCase
{
    #[Test]
    public function it_stops_requests_at_the_application_free_tier_cap(): void
    {
        Cache::clear();
        config(['horizon.ai.daily_request_cap' => 2]);
        $limiter = new AiUsageLimiter;

        $limiter->claim('Groq GPT-OSS');
        $limiter->claim('Groq GPT-OSS');

        $this->assertSame(2, $limiter->attempts('Groq GPT-OSS'));
        try {
            $limiter->claim('Groq GPT-OSS');
            $this->fail('Expected the local daily cap to stop the request.');
        } catch (AiProviderException $exception) {
            $this->assertSame('quota_limited', $exception->category);
            $this->assertSame(2, $limiter->attempts('Groq GPT-OSS'));
        }
    }
}
