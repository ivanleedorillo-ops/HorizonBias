<?php

namespace Tests\Unit;

use App\Services\Macro\OfficialMacroFeedProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfficialMacroFeedProviderTest extends TestCase
{
    #[Test]
    public function default_configuration_includes_official_bls_and_structured_treasury_sources(): void
    {
        $sources = collect(config('horizon.macro_feeds.sources'));

        $this->assertTrue($sources->contains(fn (array $source) => ($source['format'] ?? null) === 'bls_api_json'
            && $source['url'] === 'https://api.bls.gov/publicAPI/v2/timeseries/data/'
            && array_keys($source['series'] ?? []) === ['CUSR0000SA0', 'CES0000000001', 'LNS14000000', 'WPSFD4']));
        $this->assertTrue($sources->contains(fn (array $source) => ($source['format'] ?? null) === 'treasury_json'
            && $source['url'] === 'https://home.treasury.gov/news-data/press-releases/search/{year}.json'));
    }

    #[Test]
    public function it_collects_recent_relevant_items_and_rejects_untrusted_urls(): void
    {
        CarbonImmutable::setTestNow('2026-09-08T12:00:00Z');
        config([
            'horizon.macro_feeds.max_age_days' => 14,
            'horizon.macro_feeds.max_items' => 10,
            'horizon.macro_feeds.max_items_per_source' => 4,
            'horizon.macro_feeds.sources' => [[
                'name' => 'Official Source',
                'url' => 'https://feeds.example.test/feed.xml',
                'allowed_hosts' => ['official.example.test'],
                'relevance_filter' => true,
            ]],
        ]);
        Http::fake(['feeds.example.test/*' => Http::response($this->rss([
            ['Inflation report released', 'https://official.example.test/inflation', 'Mon, 07 Sep 2026 12:00:00 GMT'],
            ['Sports update', 'https://official.example.test/sport', 'Mon, 07 Sep 2026 11:00:00 GMT', 'A team won its match.'],
            ['Federal Reserve phishing link', 'https://evil.example.test/item', 'Mon, 07 Sep 2026 10:00:00 GMT'],
            ['Old GDP release', 'https://official.example.test/old', 'Mon, 01 Jun 2026 10:00:00 GMT'],
        ]), 200, ['Content-Type' => 'application/rss+xml'])]);

        $items = (new OfficialMacroFeedProvider)->collect();

        $this->assertCount(1, $items);
        $this->assertSame('S1', $items['S1']['citation_id']);
        $this->assertSame('https://official.example.test/inflation', $items['S1']['source_url']);
    }

    #[Test]
    public function one_failed_feed_does_not_discard_another_official_source(): void
    {
        CarbonImmutable::setTestNow('2026-09-08T12:00:00Z');
        config([
            'horizon.macro_feeds.max_age_days' => 14,
            'horizon.macro_feeds.sources' => [
                ['name' => 'Offline', 'url' => 'https://offline.example.test/feed.xml', 'allowed_hosts' => ['offline.example.test'], 'relevance_filter' => false],
                ['name' => 'Working', 'url' => 'https://working.example.test/feed.xml', 'allowed_hosts' => ['working.example.test'], 'relevance_filter' => false],
            ],
        ]);
        Http::fake([
            'offline.example.test/*' => Http::response('', 500),
            'working.example.test/*' => Http::response($this->rss([
                ['Policy statement', 'https://working.example.test/policy', 'Mon, 07 Sep 2026 12:00:00 GMT'],
            ])),
        ]);

        $items = (new OfficialMacroFeedProvider)->collect();

        $this->assertCount(1, $items);
        $this->assertSame('Working', $items['S1']['source_name']);
    }

    #[Test]
    public function it_normalizes_latest_official_bls_observations_without_inventing_release_times(): void
    {
        CarbonImmutable::setTestNow('2026-09-08T12:00:00Z');
        config([
            'horizon.macro_feeds.max_items' => 10,
            'horizon.macro_feeds.max_items_per_source' => 4,
            'horizon.macro_feeds.sources' => [[
                'name' => 'U.S. Bureau of Labor Statistics',
                'url' => 'https://api.bls.test/publicAPI/v2/timeseries/data/',
                'format' => 'bls_api_json',
                'allowed_hosts' => ['data.bls.test'],
                'relevance_filter' => false,
                'series' => [
                    'CPI' => ['label' => 'Consumer Price Index', 'measure' => 'index', 'source_url' => 'https://data.bls.test/timeseries/CPI'],
                    'JOBS' => ['label' => 'Total nonfarm payroll employment', 'measure' => 'thousands', 'source_url' => 'https://data.bls.test/timeseries/JOBS'],
                    'RATE' => ['label' => 'U.S. unemployment rate', 'measure' => 'percent', 'source_url' => 'https://data.bls.test/timeseries/RATE'],
                ],
            ]],
        ]);
        Http::fake(['api.bls.test/*' => Http::response([
            'status' => 'REQUEST_SUCCEEDED',
            'Results' => ['series' => [
                ['seriesID' => 'CPI', 'data' => $this->monthlySeries(334.0, 330.0, 320.0)],
                ['seriesID' => 'JOBS', 'data' => $this->monthlySeries(160100, 159950, 158000)],
                ['seriesID' => 'RATE', 'data' => $this->monthlySeries(4.2, 4.1, 4.0)],
                ['seriesID' => 'UNKNOWN', 'data' => $this->monthlySeries(1, 1, 1)],
            ]],
        ], 200, ['Content-Type' => 'application/json'])]);

        $items = (new OfficialMacroFeedProvider)->collect();

        $this->assertCount(3, $items);
        $this->assertStringContainsString('monthly', $items['S1']['headline']);
        $this->assertStringContainsString('+150 thousand', $items['S2']['headline']);
        $this->assertStringContainsString('4.2%', $items['S3']['headline']);
        $this->assertSame('retrieved_at', $items['S1']['timestamp_basis']);
        $this->assertSame('August 2026', $items['S1']['observation_period']);
        $this->assertSame('2026-09-08T12:00:00+00:00', $items['S1']['published_at']);
        $this->assertStringContainsString('retrieval time is not the original release timestamp', $items['S1']['source_summary']);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->data()['seriesid'] === ['CPI', 'JOBS', 'RATE']
            && $request->data()['startyear'] === '2025'
            && $request->data()['endyear'] === '2026');
    }

    #[Test]
    public function it_collects_relevant_treasury_items_from_the_official_yearly_json_catalogue(): void
    {
        CarbonImmutable::setTestNow('2026-09-08T12:00:00Z');
        config([
            'horizon.macro_feeds.max_age_days' => 14,
            'horizon.macro_feeds.max_items' => 10,
            'horizon.macro_feeds.max_items_per_source' => 4,
            'horizon.macro_feeds.sources' => [[
                'name' => 'U.S. Department of the Treasury',
                'url' => 'https://home.treasury.test/news-data/press-releases/search/{year}.json',
                'base_url' => 'https://home.treasury.test',
                'format' => 'treasury_json',
                'allowed_hosts' => ['home.treasury.test'],
                'relevance_filter' => true,
            ]],
        ]);
        Http::fake(['home.treasury.test/*' => Http::response([
            'category' => 'press-releases',
            'items' => [
                [
                    'title' => 'Quarterly Refunding Statement and Treasury Borrowing Estimates',
                    'searchText' => 'quarterly refunding treasury borrowing estimates',
                    'datetime' => '2026-09-08T10:00:00Z',
                    'url' => '/news/press-releases/example-1/',
                ],
                [
                    'title' => 'Treasury Announces an Internal Technology Award',
                    'searchText' => 'internal technology award',
                    'datetime' => '2026-09-08T09:00:00Z',
                    'url' => '/news/press-releases/example-2/',
                ],
                [
                    'title' => 'Treasury Sanctions a Global Oil Network',
                    'searchText' => 'sanctions global oil geopolitical risk',
                    'datetime' => '2026-09-07T10:00:00Z',
                    'url' => 'https://evil.example.test/news/forged/',
                ],
            ],
        ], 200, ['Content-Type' => 'application/json'])]);

        $items = (new OfficialMacroFeedProvider)->collect();

        $this->assertCount(1, $items);
        $this->assertSame('Quarterly Refunding Statement and Treasury Borrowing Estimates', $items['S1']['headline']);
        $this->assertSame('https://home.treasury.test/news/press-releases/example-1/', $items['S1']['source_url']);
        $this->assertSame('2026-09-08T10:00:00+00:00', $items['S1']['published_at']);
        Http::assertSent(fn ($request) => $request->url() === 'https://home.treasury.test/news-data/press-releases/search/2026.json'
            && $request->hasHeader('Accept', 'application/json'));
    }

    #[Test]
    public function a_malformed_treasury_catalogue_does_not_discard_a_working_rss_source(): void
    {
        CarbonImmutable::setTestNow('2026-09-08T12:00:00Z');
        config([
            'horizon.macro_feeds.max_age_days' => 14,
            'horizon.macro_feeds.sources' => [
                [
                    'name' => 'Malformed Treasury',
                    'url' => 'https://treasury.example.test/{year}.json',
                    'base_url' => 'https://treasury.example.test',
                    'format' => 'treasury_json',
                    'allowed_hosts' => ['treasury.example.test'],
                    'relevance_filter' => true,
                ],
                [
                    'name' => 'Working BLS',
                    'url' => 'https://bls.example.test/cpi.rss',
                    'allowed_hosts' => ['bls.example.test'],
                    'relevance_filter' => false,
                ],
            ],
        ]);
        Http::fake([
            'treasury.example.test/*' => Http::response('{invalid-json', 200),
            'bls.example.test/*' => Http::response($this->rss([
                ['Consumer Price Index release', 'https://bls.example.test/cpi', 'Mon, 07 Sep 2026 12:00:00 GMT'],
            ])),
        ]);

        $items = (new OfficialMacroFeedProvider)->collect();

        $this->assertCount(1, $items);
        $this->assertSame('Working BLS', $items['S1']['source_name']);
    }

    private function rss(array $items): string
    {
        $xmlItems = collect($items)->map(fn (array $item) => '<item><title>'.htmlspecialchars($item[0]).'</title><link>'.htmlspecialchars($item[1]).'</link><pubDate>'.$item[2].'</pubDate><description>'.htmlspecialchars($item[3] ?? 'Official economic policy information.').'</description></item>')->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Feed</title>'.$xmlItems.'</channel></rss>';
    }

    private function monthlySeries(float $latest, float $previous, float $yearAgo): array
    {
        $items = [
            ['year' => '2026', 'period' => 'M08', 'periodName' => 'August', 'value' => (string) $latest],
            ['year' => '2026', 'period' => 'M07', 'periodName' => 'July', 'value' => (string) $previous],
        ];
        for ($month = 6; $month >= 1; $month--) {
            $items[] = ['year' => '2026', 'period' => 'M'.str_pad((string) $month, 2, '0', STR_PAD_LEFT), 'periodName' => 'Prior month', 'value' => (string) $previous];
        }
        for ($month = 12; $month >= 9; $month--) {
            $items[] = ['year' => '2025', 'period' => 'M'.$month, 'periodName' => 'Prior month', 'value' => (string) $previous];
        }
        $items[] = ['year' => '2025', 'period' => 'M08', 'periodName' => 'August', 'value' => (string) $yearAgo];

        return $items;
    }
}
