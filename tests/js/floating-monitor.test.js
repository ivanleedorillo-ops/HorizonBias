import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
    deriveMonitorStatus,
    normalizeMonitorTimeframe,
    normalizeMonitorAi,
    buildMonitorViewModel,
    compactBiasLabel,
} from '../../resources/js/floating-monitor.js';

describe('Floating Monitor State Normalization', () => {

    describe('deriveMonitorStatus', () => {
        test('live mode with fresh overall and macro returns live and fresh', () => {
            const data = {
                mode: 'live',
                overall: { score: 45, label: 'Bullish', stale: false },
                macro: { status: 'ready', stale: false },
            };
            const result = deriveMonitorStatus(data, false);
            assert.equal(result.mode, 'live');
            assert.equal(result.modeLabel, 'Live');
            assert.equal(result.freshness, 'fresh');
            assert.equal(result.freshnessLabel, 'Fresh');
        });

        test('live mode with stale overall returns Stale', () => {
            const data = {
                mode: 'live',
                overall: { score: 45, label: 'Bullish', stale: true },
                macro: { status: 'ready', stale: false },
            };
            const result = deriveMonitorStatus(data, false);
            assert.equal(result.freshness, 'stale');
            assert.equal(result.freshnessLabel, 'Stale');
        });

        test('live mode with stale macro keeps technical freshness independent', () => {
            const data = {
                mode: 'live',
                overall: { score: 45, label: 'Bullish', stale: false },
                macro: { status: 'stale', stale: true },
            };
            const result = deriveMonitorStatus(data, false);
            assert.equal(result.freshness, 'fresh');
            assert.equal(result.freshnessLabel, 'Fresh');
        });

        test('connection failure returns Connection issue regardless of mode', () => {
            const data = {
                mode: 'live',
                overall: { score: 45, label: 'Bullish', stale: false },
                macro: { status: 'ready', stale: false },
            };
            const result = deriveMonitorStatus(data, true);
            assert.equal(result.freshness, 'connection_issue');
            assert.equal(result.freshnessLabel, 'Connection issue');
        });

        test('unavailable mode returns Unavailable for mode and freshness', () => {
            const data = {
                mode: 'unavailable',
                overall: null,
                macro: { status: 'unavailable', stale: true },
            };
            const result = deriveMonitorStatus(data, false);
            assert.equal(result.mode, 'unavailable');
            assert.equal(result.modeLabel, 'Unavailable');
            assert.equal(result.freshness, 'unavailable');
            assert.equal(result.freshnessLabel, 'Unavailable');
        });

        test('missing overall returns Unavailable for freshness even if mode was live', () => {
            const data = {
                mode: 'live',
                overall: null,
                macro: { status: 'ready', stale: false },
            };
            const result = deriveMonitorStatus(data, false);
            assert.equal(result.freshness, 'unavailable');
            assert.equal(result.freshnessLabel, 'Unavailable');
        });

        test('demo mode visibly says Demo and does NOT claim to be Fresh live analysis', () => {
            const data = {
                mode: 'demo',
                overall: { score: 45, label: 'Bullish', stale: false },
                macro: { status: 'demo', stale: false },
            };
            const result = deriveMonitorStatus(data, false);
            assert.equal(result.mode, 'demo');
            assert.equal(result.modeLabel, 'Demo');
            assert.notEqual(result.freshnessLabel, 'Fresh', 'Demo fixtures must not be labeled Fresh');
            assert.equal(result.freshnessLabel, 'Demo');
        });

        test('live mode with unavailable macro keeps a fresh technical snapshot Fresh', () => {
            const data = {
                mode: 'live',
                overall: { score: 45, label: 'Bullish', stale: false },
                macro: { status: 'unavailable', analyses: [] },
            };
            const result = deriveMonitorStatus(data, false);
            assert.equal(result.mode, 'live');
            assert.equal(result.freshness, 'fresh');
            assert.equal(result.freshnessLabel, 'Fresh');
        });

        test('live mode with all AI analyses failed keeps technical freshness independent', () => {
            const data = {
                mode: 'live',
                overall: { score: 45, label: 'Bullish', stale: false },
                macro: {
                    status: 'ready',
                    analyses: [
                        { provider: 'Gemini', status: 'failed' },
                        { provider: 'Groq', status: 'failed' },
                    ],
                },
            };
            const result = deriveMonitorStatus(data, false);
            assert.equal(result.freshness, 'fresh');
            assert.equal(result.freshnessLabel, 'Fresh');
        });

        test('live mode with partial macro (one AI ready) returns Fresh if not stale', () => {
            const data = {
                mode: 'live',
                overall: { score: 45, label: 'Bullish', stale: false },
                macro: {
                    status: 'partial',
                    analyses: [
                        { provider: 'Gemini', status: 'ready' },
                        { provider: 'Groq', status: 'failed' },
                    ],
                },
            };
            const result = deriveMonitorStatus(data, false);
            assert.equal(result.freshness, 'fresh');
            assert.equal(result.freshnessLabel, 'Fresh');
        });
    });

    describe('normalizeMonitorTimeframe', () => {
        test('ready timeframe displays score, compact bias, and accessible labels', () => {
            const frame = {
                key: '5m',
                label: '5 Minutes',
                score: 20,
                bias: 'Bullish',
                status: 'ready',
                stale: false,
            };
            const result = normalizeMonitorTimeframe(frame, '5m');
            assert.equal(result.status, 'ready');
            assert.equal(result.scoreDisplay, '+20');
            assert.equal(result.compactBias, 'B');
            assert.equal(result.staleIndicator, false);
            assert.equal(result.title, '5 Minutes: Bullish (+20)');
            assert.equal(result.ariaLabel, '5 Minutes: Bullish, score +20');
        });

        test('stale timeframe displays score, visible stale indicator, and stale in aria-label', () => {
            const frame = {
                key: '1h',
                label: '1 Hour',
                score: -15,
                bias: 'Bearish',
                status: 'stale',
                stale: true,
            };
            const result = normalizeMonitorTimeframe(frame, '1h');
            assert.equal(result.status, 'stale');
            assert.equal(result.scoreDisplay, '-15');
            assert.equal(result.compactBias, 'BR');
            assert.equal(result.staleIndicator, true);
            assert.match(result.title, /stale/i);
            assert.match(result.ariaLabel, /stale/i);
        });

        test('missing timeframe object returns Unavailable with em dash and neutral styling', () => {
            const result = normalizeMonitorTimeframe(null, '4h');
            assert.equal(result.status, 'unavailable');
            assert.equal(result.scoreDisplay, '—');
            assert.equal(result.compactBias, '—');
            assert.equal(result.bias, 'Unavailable');
            assert.match(result.ariaLabel, /unavailable/i);
        });

        test('timeframe with status unavailable returns Unavailable without fabricating zero or neutral', () => {
            const frame = {
                key: '1d',
                label: '1 Day',
                score: null,
                bias: null,
                status: 'unavailable',
            };
            const result = normalizeMonitorTimeframe(frame, '1d');
            assert.equal(result.status, 'unavailable');
            assert.equal(result.scoreDisplay, '—');
            assert.notEqual(result.scoreDisplay, '0');
            assert.equal(result.bias, 'Unavailable');
            assert.notEqual(result.bias, 'Neutral');
        });

        test('timeframe with non-numeric score is treated as unavailable', () => {
            const frame = {
                key: '1w',
                label: '1 Week',
                score: 'bad_score',
                bias: 'Bullish',
                status: 'ready',
            };
            const result = normalizeMonitorTimeframe(frame, '1w');
            assert.equal(result.status, 'unavailable');
            assert.equal(result.scoreDisplay, '—');
        });

        test('timeframe with missing bias label is treated as unavailable', () => {
            const frame = {
                key: '1mo',
                label: '1 Month',
                score: 10,
                bias: '',
                status: 'ready',
            };
            const result = normalizeMonitorTimeframe(frame, '1mo');
            assert.equal(result.status, 'unavailable');
            assert.equal(result.scoreDisplay, '—');
        });
    });

    describe('normalizeMonitorAi', () => {
        test('null or missing macro returns Unavailable and does not pretend to be neutral', () => {
            const result = normalizeMonitorAi(null);
            assert.equal(result.status, 'unavailable');
            assert.equal(result.biasLabel, 'Unavailable');
            assert.notEqual(result.biasLabel, 'Neutral');
            assert.equal(result.agreementLabel, '· No consensus');
            assert.equal(result.confidenceDisplay, '· —');
            assert.equal(result.riskDisplay, '· —');
            assert.match(result.summary, /unavailable/i);
        });

        test('macro with status unavailable returns Unavailable', () => {
            const macro = {
                status: 'unavailable',
                gold_bias: 'neutral',
                agreement: 'unavailable',
                confidence: 0,
                risk_level: 'medium',
                analyses: [],
                summary: 'Dual-AI context is not available yet.',
            };
            const result = normalizeMonitorAi(macro);
            assert.equal(result.status, 'unavailable');
            assert.equal(result.biasLabel, 'Unavailable');
            assert.equal(result.agreementLabel, '· No consensus');
            assert.equal(result.confidenceDisplay, '· —');
            assert.equal(result.riskDisplay, '· —');
        });

        test('partial macro with one provider ready identifies provider and shows Partial', () => {
            const macro = {
                status: 'partial',
                gold_bias: 'bullish',
                agreement: 'partial',
                confidence: 45,
                risk_level: 'low',
                analyses: [
                    { provider: 'Gemini', status: 'ready', confidence: 45 },
                    { provider: 'Groq', status: 'failed', confidence: 0 },
                ],
                summary: 'Single provider assessment.',
            };
            const result = normalizeMonitorAi(macro);
            assert.equal(result.status, 'partial');
            assert.equal(result.biasLabel, 'Partial');
            assert.equal(result.isPartial, true);
            assert.match(result.partialNotice, /Gemini/);
            assert.match(result.partialNotice, /valid assessment/);
            assert.equal(result.confidenceDisplay, '· 45%');
        });

        test('partial macro via provider_status array identifies ready provider safely', () => {
            const macro = {
                status: 'partial',
                gold_bias: 'bearish',
                agreement: 'partial',
                confidence: 40,
                risk_level: 'high',
                analyses: [
                    { provider: 'Groq', status: 'ready', confidence: 40 },
                ],
                provider_status: [
                    { provider: 'Gemini', status: 'rate_limited' },
                    { provider: 'Groq', status: 'ready' },
                ],
                summary: 'Single Groq assessment.',
            };
            const result = normalizeMonitorAi(macro);
            assert.equal(result.status, 'partial');
            assert.equal(result.biasLabel, 'Partial');
            assert.match(result.partialNotice, /Groq/);
        });

        test('macro with all failed analyses returns Unavailable without fabricating Neutral conclusion', () => {
            const macro = {
                status: 'ready',
                analyses: [
                    { provider: 'Gemini', status: 'failed' },
                    { provider: 'Groq', status: 'failed' },
                ],
                provider_status: [
                    { provider: 'Gemini', status: 'error' },
                    { provider: 'Groq', status: 'error' },
                ],
            };
            const result = normalizeMonitorAi(macro);
            assert.equal(result.status, 'unavailable');
            assert.equal(result.biasLabel, 'Unavailable');
            assert.notEqual(result.biasLabel, 'Neutral');
            assert.equal(result.confidenceDisplay, '· —');
            assert.equal(result.riskDisplay, '· —');
        });

        test('stale macro retains last valid assessment and flags stale notice', () => {
            const macro = {
                status: 'ready',
                stale: true,
                gold_bias: 'bearish',
                agreement: 'agree',
                confidence: 65,
                risk_level: 'medium',
                analyses: [
                    { provider: 'Gemini', status: 'ready' },
                    { provider: 'Groq', status: 'ready' },
                ],
                generated_at: '2026-09-14T10:00:00+00:00',
                summary: 'Bearish macro conditions.',
            };
            const result = normalizeMonitorAi(macro);
            assert.equal(result.status, 'stale');
            assert.equal(result.isStale, true);
            assert.match(result.staleNotice, /Stale AI context/);
            assert.equal(result.biasLabel, 'Bearish');
            assert.equal(result.confidenceDisplay, '· 65%');
            assert.match(result.timeDisplay, /Generated/);
        });

        test('ready dual-AI consensus displays agreement, confidence, and risk', () => {
            const macro = {
                status: 'ready',
                stale: false,
                gold_bias: 'bullish',
                agreement: 'agree',
                confidence: 72,
                risk_level: 'low',
                analyses: [
                    { provider: 'Gemini', status: 'ready' },
                    { provider: 'Groq', status: 'ready' },
                ],
                generated_at: '2026-09-14T12:00:00+00:00',
                summary: 'Both models agree on bullish bias.',
            };
            const result = normalizeMonitorAi(macro);
            assert.equal(result.status, 'ready');
            assert.equal(result.biasLabel, 'Bullish');
            assert.equal(result.agreementLabel, '· Agree');
            assert.equal(result.confidenceDisplay, '· 72%');
            assert.equal(result.riskDisplay, '· Low risk');
            assert.equal(result.isPartial, false);
            assert.equal(result.isStale, false);
        });
    });

    describe('buildMonitorViewModel', () => {
        test('produces complete, normalized contract for demo data', () => {
            const data = {
                mode: 'demo',
                quote: { price: 2486.4, currency: 'USD', change: 12.3, change_percent: 0.5, as_of: '2026-09-01T12:00:00Z', completed_at: '2026-09-01T12:05:00Z' },
                overall: { score: 45, label: 'Bullish', stale: false, generated_at: '2026-09-01T12:00:00Z' },
                timeframes: [
                    { key: '5m', label: '5 Minutes', score: 20, bias: 'Bullish', status: 'demo' },
                    { key: '15m', label: '15 Minutes', score: 35, bias: 'Bullish', status: 'demo' },
                    { key: '1h', label: '1 Hour', score: 45, bias: 'Bullish', status: 'demo' },
                    { key: '4h', label: '4 Hours', score: 65, bias: 'Strong Bullish', status: 'demo' },
                    { key: '1d', label: '1 Day', score: 55, bias: 'Bullish', status: 'demo' },
                    { key: '1w', label: '1 Week', score: 25, bias: 'Bullish', status: 'demo' },
                    { key: '1mo', label: '1 Month', score: 10, bias: 'Neutral', status: 'demo' },
                ],
                macro: {
                    status: 'demo',
                    gold_bias: 'neutral',
                    agreement: 'agree',
                    confidence: 52,
                    risk_level: 'medium',
                    analyses: [
                        { provider: 'Gemini (demo)', status: 'demo' },
                        { provider: 'Groq GPT-OSS (demo)', status: 'demo' },
                    ],
                    summary: 'Illustrative dual-AI consensus.',
                },
            };

            const vm = buildMonitorViewModel(data, false);
            assert.equal(vm.status.modeLabel, 'Demo');
            assert.equal(vm.status.freshnessLabel, 'Demo');
            assert.equal(vm.quote.priceDisplay, '$2,486.40');
            assert.match(vm.quote.asOfDisplay, /^Through /);
            assert.match(vm.quote.asOfDisplay, /12:05/);
            assert.equal(vm.overall.label, 'Bullish');
            assert.equal(vm.overall.scoreDisplay, '+45');
            assert.equal(vm.timeframes.length, 7);
            assert.equal(vm.timeframes[0].key, '5m');
            assert.equal(vm.timeframes[0].scoreDisplay, '+20');
            assert.equal(vm.timeframes[3].compactBias, 'SB');
            assert.equal(vm.ai.confidenceDisplay, '· 52%');
        });

        test('produces safe unavailable fallback when data is empty', () => {
            const vm = buildMonitorViewModel({}, false);
            assert.equal(vm.status.modeLabel, 'Unavailable');
            assert.equal(vm.status.freshnessLabel, 'Unavailable');
            assert.equal(vm.quote.priceDisplay, '$—');
            assert.equal(vm.overall.label, 'Unavailable');
            assert.equal(vm.overall.scoreDisplay, '—');
            assert.equal(vm.timeframes.length, 7);
            assert.ok(vm.timeframes.every(tf => tf.isUnavailable));
            assert.equal(vm.ai.biasLabel, 'Unavailable');
        });
    });

    describe('compactBiasLabel', () => {
        test('maps all standard bias strings to distinct compact abbreviations', () => {
            assert.equal(compactBiasLabel('Strong Bullish'), 'SB');
            assert.equal(compactBiasLabel('Bullish'), 'B');
            assert.equal(compactBiasLabel('Neutral'), 'N');
            assert.equal(compactBiasLabel('Bearish'), 'BR');
            assert.equal(compactBiasLabel('Strong Bearish'), 'SBR');
            assert.equal(compactBiasLabel(''), 'N');
        });
    });
});
