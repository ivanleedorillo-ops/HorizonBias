<?php

namespace Tests\Unit;

use App\Services\Market\TwelveDataMarketDataProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class TwelveDataMarketDataProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['horizon.twelve_data.api_key' => 'test-key']);
    }

    #[Test] public function it_normalizes_sorts_and_discards_the_newest_candle(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ok', 'values' => $this->values()], 200)]);
        $candles = (new TwelveDataMarketDataProvider)->fetch('1h');
        $this->assertCount(250, $candles);
        $this->assertTrue($candles[0]->openedAt->lessThan($candles[249]->openedAt));
        $this->assertSame(2.0, $candles[249]->close);
        Http::assertSent(fn ($request) => $request['symbol'] === 'XAU/USD' && $request['interval'] === '1h' && ! str_contains($request->body(), 'test-key'));
    }

    #[Test] public function it_skips_sparse_invalid_ohlc_rows_without_fabricating_prices(): void
    {
        $values = $this->values();
        $values[100]['high'] = '0';
        Http::fake(['*' => Http::response(['status' => 'ok', 'values' => $values])]);

        $candles = (new TwelveDataMarketDataProvider)->fetch('1h');

        $this->assertCount(250, $candles);
        $this->assertNotContains($values[100]['datetime'], array_map(fn ($candle) => $candle->openedAt->format('Y-m-d H:i:s'), $candles));
    }

    #[Test] public function it_rejects_a_series_without_enough_valid_ohlc_data(): void
    {
        $values = $this->values();
        foreach (array_slice(array_keys($values), 0, 60) as $index) {
            $values[$index]['high'] = '0';
        }
        Http::fake(['*' => Http::response(['status' => 'ok', 'values' => $values])]);
        $this->expectException(RuntimeException::class);
        (new TwelveDataMarketDataProvider)->fetch('1h');
    }

    #[Test] public function it_reports_credentials_rate_limits_and_service_failures_safely(): void
    {
        foreach ([401, 403, 429, 500] as $status) {
            Http::fake(['*' => Http::response([], $status)]);
            try {
                (new TwelveDataMarketDataProvider)->fetch('1h');
                $this->fail("Status {$status} should throw.");
            } catch (RuntimeException $exception) {
                $this->assertStringNotContainsString('test-key', $exception->getMessage());
            }
        }
    }

    #[Test] public function it_rejects_provider_error_payloads(): void
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'bad request'])]);
        $this->expectException(RuntimeException::class);
        (new TwelveDataMarketDataProvider)->fetch('1h');
    }

    private function values(): array
    {
        $values = [];
        for ($i = 0; $i < 261; $i++) {
            $close = 1 + $i;
            $values[] = ['datetime' => now('UTC')->subHours($i)->format('Y-m-d H:i:s'), 'open' => (string) ($close - .2), 'high' => (string) ($close + .5), 'low' => (string) ($close - .5), 'close' => (string) $close, 'volume' => '100'];
        }
        return $values;
    }
}
