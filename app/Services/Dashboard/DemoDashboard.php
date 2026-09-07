<?php

namespace App\Services\Dashboard;

final class DemoDashboard
{
    public function data(bool $licensingGateApplied = false): array
    {
        $scores = ['5m' => 20, '15m' => 35, '1h' => 45, '4h' => 65, '1d' => 55, '1w' => 25, '1mo' => 10];
        $frames = [];
        foreach (config('horizon.timeframes') as $key => $settings) {
            $score = $scores[$key];
            $frames[] = [
                'key' => $key,
                'label' => $settings['label'],
                'score' => $score,
                'bias' => $score >= 60 ? 'Strong Bullish' : ($score >= 20 ? 'Bullish' : 'Neutral'),
                'component_scores' => ['trend' => min(35, $score), 'momentum' => $score > 20 ? 15 : 0, 'structure' => $key === '4h' ? 25 : 0, 'breakout' => 0],
                'metrics' => ['close' => 2486.40, 'ema20' => 2479.12, 'ema50' => 2468.75, 'ema200' => 2412.20, 'rsi14' => 57.42, 'macd' => 4.12, 'macd_signal' => 2.86, 'roc10' => 0.74, 'atr14' => 13.65, 'adx14' => 24.18],
                'explanations' => ['Moving-average alignment supports the upside.', 'Momentum measures lean positive.', 'This is illustrative demo analysis, not current market data.'],
                'data_as_of' => '2026-09-01T12:00:00+00:00',
                'stale' => false,
                'status' => 'demo',
            ];
        }

        return [
            'mode' => 'demo',
            'notice' => $licensingGateApplied ? 'Live bias is disabled in production until external-display licensing is confirmed.' : 'Illustrative demo data — not live market analysis.',
            'symbol' => 'XAU/USD',
            'quote' => ['price' => 2486.40, 'currency' => 'USD', 'change' => 12.30, 'change_percent' => 0.50, 'as_of' => '2026-09-01T12:00:00+00:00'],
            'overall' => ['score' => 45, 'label' => 'Bullish', 'summary' => 'Illustrative multi-timeframe conditions lean bullish, led by the 4-hour and daily views.', 'generated_at' => '2026-09-01T12:00:00+00:00', 'stale' => false],
            'timeframes' => $frames,
            'macro' => [
                'stance' => 'mixed', 'risk_level' => 'medium',
                'summary' => 'Illustrative context: softer yields may support gold while a firm US dollar can limit momentum. Configure Gemini for verified current events.',
                'events' => [], 'generated_at' => '2026-09-01T12:00:00+00:00', 'stale' => false, 'status' => 'demo',
            ],
            'system' => ['market_provider' => 'Illustrative fixtures', 'ai_provider' => 'Illustrative fixtures', 'licensing_gate_applied' => $licensingGateApplied],
        ];
    }
}
