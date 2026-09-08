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

    private function rss(array $items): string
    {
        $xmlItems = collect($items)->map(fn (array $item) => '<item><title>'.htmlspecialchars($item[0]).'</title><link>'.htmlspecialchars($item[1]).'</link><pubDate>'.$item[2].'</pubDate><description>'.htmlspecialchars($item[3] ?? 'Official economic policy information.').'</description></item>')->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Feed</title>'.$xmlItems.'</channel></rss>';
    }
}
