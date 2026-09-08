<?php

return [
    'symbol' => 'XAU/USD',
    'market_mode' => env('MARKET_MODE', 'demo'),
    'market_provider' => env('MARKET_DATA_PROVIDER', 'twelve_data'),
    'external_display_licensed' => env('MARKET_DATA_EXTERNAL_DISPLAY_LICENSED', false),
    'usd_proxy' => [
        'symbol' => env('USD_PROXY_SYMBOL', 'UUP'),
    ],
    'twelve_data' => [
        'api_key' => env('TWELVE_DATA_API_KEY'),
        'base_url' => env('TWELVE_DATA_BASE_URL', 'https://api.twelvedata.com'),
    ],
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'search_grounding' => env('GEMINI_SEARCH_GROUNDING', false),
        'refresh_minutes' => (int) env('MACRO_REFRESH_MINUTES', 180),
        'free_tier_models' => [
            'gemini-3.5-flash-lite',
            'gemini-2.5-flash-lite',
            'gemini-2.5-flash',
        ],
    ],
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'free_tier_models' => [
            'openai/gpt-oss-120b',
            'openai/gpt-oss-20b',
        ],
    ],
    'ai' => [
        'dual_enabled' => env('DUAL_AI_ENABLED', true),
        'free_tier_only' => env('AI_FREE_TIER_ONLY', true),
        'daily_request_cap' => (int) env('AI_DAILY_REQUEST_CAP', 24),
    ],
    'macro_feeds' => [
        'max_age_days' => (int) env('MACRO_FEED_MAX_AGE_DAYS', 14),
        'max_items' => 10,
        'max_items_per_source' => 4,
        'sources' => [
            [
                'name' => 'Federal Reserve — Monetary Policy',
                'url' => 'https://www.federalreserve.gov/feeds/press_monetary.xml',
                'allowed_hosts' => ['www.federalreserve.gov'],
                'relevance_filter' => false,
            ],
            [
                'name' => 'Federal Reserve — Speeches & Testimony',
                'url' => 'https://www.federalreserve.gov/feeds/speeches_and_testimony.xml',
                'allowed_hosts' => ['www.federalreserve.gov'],
                'relevance_filter' => true,
            ],
            [
                'name' => 'U.S. Bureau of Economic Analysis',
                'url' => 'https://apps.bea.gov/rss/rss.xml',
                'allowed_hosts' => ['www.bea.gov'],
                'relevance_filter' => true,
            ],
        ],
    ],
    'timeframes' => [
        '5m' => ['label' => '5 Minutes', 'interval' => '5min', 'weight' => 0.05, 'stale_after' => 15],
        '15m' => ['label' => '15 Minutes', 'interval' => '15min', 'weight' => 0.10, 'stale_after' => 45],
        '1h' => ['label' => '1 Hour', 'interval' => '1h', 'weight' => 0.15, 'stale_after' => 180],
        '4h' => ['label' => '4 Hours', 'interval' => '4h', 'weight' => 0.20, 'stale_after' => 720],
        '1d' => ['label' => '1 Day', 'interval' => '1day', 'weight' => 0.25, 'stale_after' => 4320],
        '1w' => ['label' => '1 Week', 'interval' => '1week', 'weight' => 0.15, 'stale_after' => 20160],
        '1mo' => ['label' => '1 Month', 'interval' => '1month', 'weight' => 0.10, 'stale_after' => 64800],
    ],
];
