<?php

return [
    'symbol' => 'XAU/USD',
    'market_mode' => env('MARKET_MODE', 'demo'),
    'market_provider' => env('MARKET_DATA_PROVIDER', 'twelve_data'),
    'external_display_licensed' => env('MARKET_DATA_EXTERNAL_DISPLAY_LICENSED', false),
    'twelve_data' => [
        'api_key' => env('TWELVE_DATA_API_KEY'),
        'base_url' => env('TWELVE_DATA_BASE_URL', 'https://api.twelvedata.com'),
    ],
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.7-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'search_grounding' => env('GEMINI_SEARCH_GROUNDING', true),
        'refresh_minutes' => (int) env('MACRO_REFRESH_MINUTES', 180),
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
