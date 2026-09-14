<!DOCTYPE html>
<html lang="en" data-report-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $report['title'] }} &mdash; {{ $report['report_id'] }}</title>
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('horizon_report_theme') || localStorage.getItem('horizon_theme');
                var theme = saved === 'dark' || saved === 'light'
                    ? saved
                    : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.dataset.reportTheme = theme;
            } catch (error) {
                document.documentElement.dataset.reportTheme = 'light';
            }
        }());
    </script>
    <style>
        :root {
            color-scheme: light;
            --page: #e9edf3;
            --paper: #ffffff;
            --surface: #f6f8fb;
            --surface-strong: #eef2f7;
            --text: #182236;
            --text-soft: #46536a;
            --muted: #68758b;
            --border: #d9e0e9;
            --border-strong: #c6cfdb;
            --gold: #a56d09;
            --gold-bright: #e6aa22;
            --gold-soft: #fff5d9;
            --positive: #087f5b;
            --negative: #c2374b;
            --neutral: #8a650e;
            --shadow: 0 18px 50px rgba(15, 23, 42, .14);
            font-family: Inter, ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
        }
        html[data-report-theme="dark"] {
            color-scheme: dark;
            --page: #06090f;
            --paper: #0d131e;
            --surface: #111927;
            --surface-strong: #172131;
            --text: #f3f6fb;
            --text-soft: #c7d0de;
            --muted: #93a0b4;
            --border: #263247;
            --border-strong: #36445b;
            --gold: #f4bd35;
            --gold-bright: #ffd66d;
            --gold-soft: #2a230f;
            --positive: #42dda7;
            --negative: #ff7688;
            --neutral: #ffd45f;
            --shadow: 0 20px 60px rgba(0, 0, 0, .46);
        }
        * { box-sizing: border-box; }
        html { background: var(--page); }
        body { margin: 0; background: var(--page); color: var(--text); transition: background .18s ease, color .18s ease; }
        button, a { font: inherit; }
        .toolbar { position: sticky; top: 0; z-index: 10; border-bottom: 1px solid #263247; background: rgba(9, 14, 24, .96); color: #f7f9fc; backdrop-filter: blur(14px); }
        .toolbar-inner { width: min(1080px, calc(100% - 28px)); margin: auto; display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 10px 0; }
        .toolbar-title { display: flex; align-items: center; gap: 9px; min-width: 140px; font-size: 12px; font-weight: 800; letter-spacing: .02em; }
        .toolbar-title::before { content: ""; width: 8px; height: 8px; border-radius: 999px; background: #f4bd35; box-shadow: 0 0 12px rgba(244, 189, 53, .75); }
        .toolbar-actions, .theme-choice { display: flex; align-items: center; gap: 7px; }
        .theme-choice { padding: 3px; border: 1px solid #344158; border-radius: 10px; background: #111927; }
        .theme-choice-label { margin-right: 2px; padding-left: 6px; color: #aab5c5; font-size: 11px; font-weight: 700; }
        .theme-button, .action { min-height: 34px; border: 1px solid transparent; border-radius: 8px; padding: 7px 11px; color: #dce3ed; background: transparent; font-size: 12px; font-weight: 750; text-decoration: none; cursor: pointer; transition: background .15s ease, border-color .15s ease, color .15s ease; }
        .theme-button:hover, .theme-button[aria-pressed="true"], .action.secondary:hover { border-color: #52617a; background: #202b3d; color: #fff; }
        .action.primary { border-color: #c68b10; background: #b77b0c; color: #fff; }
        .action.primary:hover { background: #c98b12; }
        .action.secondary { border-color: #344158; }
        .theme-button:focus-visible, .action:focus-visible, a:focus-visible { outline: 3px solid rgba(244, 189, 53, .5); outline-offset: 2px; }
        .report { position: relative; width: min(1080px, calc(100% - 32px)); margin: 28px auto 48px; padding: 46px; overflow: hidden; border: 1px solid var(--border); border-radius: 16px; background: var(--paper); box-shadow: var(--shadow); }
        .watermark { position: absolute; top: 30px; right: -58px; transform: rotate(38deg); width: 255px; padding: 8px; text-align: center; background: var(--gold-bright); color: #332100; font-size: 11px; font-weight: 900; letter-spacing: .13em; }
        .report-header { display: flex; align-items: center; justify-content: space-between; gap: 28px; padding-bottom: 24px; border-bottom: 2px solid var(--gold); }
        .brand { display: flex; align-items: center; gap: 13px; }
        .brand img { width: 52px; height: 52px; object-fit: contain; }
        h1 { margin: 0; font-size: 26px; letter-spacing: -.025em; }
        .brand-accent { color: var(--gold); }
        .eyebrow { color: var(--gold); font-size: 10px; font-weight: 850; letter-spacing: .15em; text-transform: uppercase; }
        .meta { text-align: right; color: var(--text-soft); font-size: 11px; line-height: 1.7; }
        .status { display: inline-flex; align-items: center; gap: 5px; border: 1px solid var(--gold); border-radius: 999px; padding: 3px 9px; background: var(--gold-soft); color: var(--neutral); font-size: 10px; font-weight: 850; text-transform: uppercase; }
        .section { margin-top: 25px; }
        .section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 11px; padding-bottom: 8px; border-bottom: 1px solid var(--border); }
        h2 { margin: 0; color: var(--text); font-size: 16px; letter-spacing: -.01em; }
        h3 { margin: 15px 0 5px; font-size: 13px; }
        p { margin: 6px 0; color: var(--text-soft); font-size: 12px; line-height: 1.6; }
        .muted { color: var(--muted); font-size: 10px; }
        .notice { margin-top: 17px; border: 1px solid var(--gold); border-radius: 10px; padding: 11px 13px; background: var(--gold-soft); color: var(--neutral); font-size: 11px; line-height: 1.5; }
        .snapshot { display: grid; grid-template-columns: 1.4fr repeat(2, 1fr); overflow: hidden; border: 1px solid var(--border); border-radius: 13px; background: var(--surface); }
        .snapshot-cell { min-width: 0; padding: 18px; border-left: 1px solid var(--border); }
        .snapshot-cell:first-child { border-left: 0; }
        .snapshot-label { display: block; margin-bottom: 7px; color: var(--muted); font-size: 9px; font-weight: 850; letter-spacing: .12em; text-transform: uppercase; }
        .snapshot-value { display: block; color: var(--text); font-size: 20px; font-weight: 850; letter-spacing: -.025em; }
        .snapshot-value.hero { font-size: 27px; }
        .snapshot-detail { display: block; margin-top: 4px; color: var(--muted); font-size: 10px; line-height: 1.45; }
        .tone-positive { color: var(--positive) !important; }
        .tone-negative { color: var(--negative) !important; }
        .tone-neutral { color: var(--neutral) !important; }
        .meter { height: 5px; margin-top: 11px; overflow: hidden; border-radius: 99px; background: var(--surface-strong); }
        .meter-fill { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--negative), var(--gold), var(--positive)); }
        .summary-copy { margin-top: 12px; padding: 0 2px; }
        .takeaways { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-top: 12px; }
        .takeaway { position: relative; margin: 0; border: 1px solid var(--border); border-radius: 9px; padding: 10px 12px 10px 27px; background: var(--surface); color: var(--text-soft); font-size: 10.5px; line-height: 1.5; break-inside: avoid; }
        .takeaway::before { content: ""; position: absolute; top: 15px; left: 12px; width: 6px; height: 6px; border-radius: 99px; background: var(--gold-bright); }
        .timeframe-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .timeframe-card { border: 1px solid var(--border); border-radius: 10px; padding: 11px; background: var(--surface); break-inside: avoid; }
        .timeframe-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
        .timeframe-label { font-size: 12px; font-weight: 850; }
        .timeframe-score { font-size: 16px; font-weight: 900; font-variant-numeric: tabular-nums; }
        .timeframe-bias { margin-top: 2px; font-size: 10px; font-weight: 800; }
        .timeframe-status { color: var(--muted); font-size: 8px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .components { display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px 7px; margin-top: 9px; padding-top: 8px; border-top: 1px solid var(--border); color: var(--muted); font-size: 9px; }
        .components span { display: flex; justify-content: space-between; gap: 5px; }
        .components b { color: var(--text-soft); font-variant-numeric: tabular-nums; }
        .table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        th, td { padding: 7px 6px; border-bottom: 1px solid var(--border); text-align: right; vertical-align: top; font-variant-numeric: tabular-nums; }
        th:first-child, td:first-child { text-align: left; }
        tbody tr:last-child td { border-bottom: 0; }
        th { background: var(--surface-strong); color: var(--muted); font-size: 8px; font-weight: 850; letter-spacing: .06em; text-transform: uppercase; }
        td { color: var(--text-soft); }
        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; }
        .card { border: 1px solid var(--border); border-radius: 10px; padding: 12px; background: var(--surface); break-inside: avoid; }
        .card small { display: block; color: var(--muted); font-size: 9px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
        .card strong { display: block; margin-top: 5px; color: var(--text); font-size: 15px; }
        .context-layout { display: grid; grid-template-columns: 1.05fr .95fr; gap: 12px; }
        .context-panel { border: 1px solid var(--border); border-radius: 12px; padding: 15px; background: var(--surface); break-inside: avoid; }
        .context-panel .cards { grid-template-columns: repeat(3, 1fr); }
        .model-strip { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .model-view { border: 1px solid var(--border); border-radius: 999px; padding: 5px 8px; color: var(--text-soft); font-size: 9px; }
        .event-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 9px; }
        .event { border: 1px solid var(--border); border-radius: 10px; padding: 12px; background: var(--surface); break-inside: avoid; }
        .event h3 { margin-top: 0; }
        ul { margin: 7px 0 0; padding-left: 19px; }
        li { margin: 4px 0; color: var(--text-soft); font-size: 11px; line-height: 1.5; }
        a { color: var(--gold); overflow-wrap: anywhere; }
        .risk { margin-top: 18px; border: 1px solid var(--gold); border-left-width: 4px; border-radius: 9px; padding: 13px; background: var(--gold-soft); color: var(--text); font-size: 11px; font-weight: 650; line-height: 1.55; break-inside: avoid; }
        footer { display: flex; justify-content: space-between; gap: 12px; margin-top: 27px; padding-top: 12px; border-top: 1px solid var(--border); color: var(--muted); font-size: 9px; }
        @media (max-width: 800px) {
            .toolbar-inner { align-items: stretch; flex-direction: column; }
            .toolbar-title { display: none; }
            .toolbar-actions { flex-wrap: wrap; }
            .theme-choice { align-self: flex-start; }
            .report { width: calc(100% - 16px); margin: 8px auto 24px; padding: 28px 18px; border-radius: 11px; }
            .report-header { align-items: flex-start; flex-direction: column; }
            .meta { text-align: left; }
            .snapshot { grid-template-columns: 1fr; }
            .snapshot-cell { border-top: 1px solid var(--border); border-left: 0; }
            .snapshot-cell:first-child { border-top: 0; }
            .timeframe-grid { grid-template-columns: repeat(2, 1fr); }
            .cards, .event-grid, .takeaways, .context-layout { grid-template-columns: 1fr; }
            footer { flex-direction: column; }
        }
        @media (max-width: 460px) {
            .action { flex: 1 1 auto; text-align: center; }
            .timeframe-grid { grid-template-columns: 1fr; }
        }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; } }
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            html, body { background: var(--paper) !important; }
            .toolbar { display: none !important; }
            .report { width: 100%; margin: 0; padding: 0; overflow: visible; border: 0; border-radius: 0; box-shadow: none; }
            .section { margin-top: 18px; }
            .section-heading { break-after: avoid; }
            .timeframe-grid { grid-template-columns: repeat(4, 1fr); }
            .snapshot, .timeframe-card, .card, .event, .risk, table { break-inside: avoid; }
            a { color: var(--text); text-decoration: none; }
        }
    </style>
</head>
<body>
@php
    $time = static fn ($value) => $value ? \Carbon\CarbonImmutable::parse($value)->utc()->format('M j, Y H:i').' UTC' : 'Unavailable';
    $number = static fn ($value, $decimals = 2) => is_numeric($value) ? number_format((float) $value, $decimals, '.', ',') : '—';
    $score = static fn ($value) => is_numeric($value) ? (((int) $value > 0 ? '+' : '').(int) $value) : '—';
    $tone = static function ($label): string {
        $label = strtolower((string) $label);
        return str_contains($label, 'bullish') ? 'tone-positive' : (str_contains($label, 'bearish') ? 'tone-negative' : 'tone-neutral');
    };
    $modeLabel = strtoupper($report['mode']);
    $overallStale = (bool) ($report['overall']['stale'] ?? false);
    $overallScore = is_numeric($report['overall']['score'] ?? null) ? (int) $report['overall']['score'] : 0;
    $meterWidth = max(0, min(100, ($overallScore + 100) / 2));
@endphp

<nav class="toolbar" aria-label="Report actions">
    <div class="toolbar-inner">
        <div class="toolbar-title">HorizonBias report preview</div>
        <div class="theme-choice" role="group" aria-label="Report color theme">
            <span class="theme-choice-label">Theme</span>
            <button type="button" class="theme-button" data-theme-option="light" aria-pressed="false">Light</button>
            <button type="button" class="theme-button" data-theme-option="dark" aria-pressed="false">Dark</button>
        </div>
        <div class="toolbar-actions">
            <button class="action primary" type="button" onclick="window.print()">Print / Save as PDF</button>
            <a class="action secondary" href="{{ route('reports.current', ['format' => 'pdf']) }}">Download PDF</a>
            <a class="action secondary" href="{{ route('reports.current', ['format' => 'docx']) }}">Download Word</a>
            <a class="action secondary" href="{{ route('dashboard') }}">Dashboard</a>
        </div>
    </div>
</nav>

<main class="report">
    @if($report['mode'] !== 'live' || $overallStale)
        <div class="watermark">{{ $report['mode'] === 'demo' ? 'ILLUSTRATIVE DEMO' : ($overallStale ? 'STALE DATA' : 'UNAVAILABLE') }}</div>
    @endif

    <header class="report-header">
        <div class="brand">
            <img src="{{ asset('images/horizonbias-logo.png') }}" alt="HorizonBias logo">
            <div>
                <div class="eyebrow">Decision-support evidence</div>
                <h1>Horizon<span class="brand-accent">Bias</span></h1>
                <div class="muted">XAU/USD STORED BIAS REPORT</div>
            </div>
        </div>
        <div class="meta">
            <span class="status">{{ $modeLabel }}{{ $overallStale ? ' · STALE' : '' }}</span><br>
            <strong>{{ $report['report_id'] }}</strong><br>
            Generated {{ $time($report['generated_at']) }}
        </div>
    </header>

    @if($report['notice'])<div class="notice"><strong>Data notice:</strong> {{ $report['notice'] }}</div>@endif

    <section class="section" aria-labelledby="overview-heading">
        <div class="section-heading">
            <div><div class="eyebrow">Executive snapshot</div><h2 id="overview-heading">Market overview</h2></div>
            <span class="muted">Stored state &middot; no provider refresh</span>
        </div>
        <div class="snapshot">
            <div class="snapshot-cell">
                <span class="snapshot-label">Spot Gold &middot; {{ $report['symbol'] }}</span>
                <strong class="snapshot-value hero">${{ $number($report['quote']['price'] ?? null) }}</strong>
                <span class="snapshot-detail">{{ $report['system']['market_provider_label'] ?? 'Stored market data' }}</span>
            </div>
            <div class="snapshot-cell">
                <span class="snapshot-label">Overall technical bias</span>
                <strong class="snapshot-value {{ $tone($report['overall']['label'] ?? '') }}">{{ $report['overall']['label'] ?? 'Unavailable' }} {{ $score($report['overall']['score'] ?? null) }}</strong>
                <div class="meter" aria-hidden="true"><span class="meter-fill" style="width: {{ $meterWidth }}%"></span></div>
                <span class="snapshot-detail">Deterministic score from -100 to +100</span>
            </div>
            <div class="snapshot-cell">
                <span class="snapshot-label">Latest completed market period</span>
                <strong class="snapshot-value" style="font-size:14px">{{ $time($report['quote']['completed_at'] ?? $report['quote']['as_of'] ?? null) }}</strong>
                <span class="snapshot-detail">Only completed candles are analyzed</span>
            </div>
        </div>
        <div class="takeaways" aria-label="Key report takeaways">
            @foreach($report['executive']['takeaways'] ?? [] as $takeaway)
                <p class="takeaway">{{ $takeaway }}</p>
            @endforeach
        </div>
    </section>

    <section class="section" aria-labelledby="timeframes-heading">
        <div class="section-heading">
            <div><div class="eyebrow">Closed-candle matrix</div><h2 id="timeframes-heading">Seven-timeframe technical evidence</h2></div>
        </div>
        <div class="timeframe-grid">
            @forelse($report['timeframes'] as $frame)
                <article class="timeframe-card">
                    <div class="timeframe-top">
                        <div><div class="timeframe-label">{{ $frame['label'] }}</div><div class="timeframe-bias {{ $tone($frame['bias'] ?? '') }}">{{ $frame['bias'] }}</div></div>
                        <div style="text-align:right"><div class="timeframe-score {{ $tone($frame['bias'] ?? '') }}">{{ $score($frame['score']) }}</div><div class="timeframe-status">{{ $frame['status'] }}</div></div>
                    </div>
                    <div class="components">
                        <span>Trend <b>{{ $score($frame['component_scores']['trend'] ?? null) }}</b></span>
                        <span>Momentum <b>{{ $score($frame['component_scores']['momentum'] ?? null) }}</b></span>
                        <span>Structure <b>{{ $score($frame['component_scores']['structure'] ?? null) }}</b></span>
                        <span>Breakout <b>{{ $score($frame['component_scores']['breakout'] ?? null) }}</b></span>
                    </div>
                </article>
            @empty
                <p>No valid timeframe snapshots are available.</p>
            @endforelse
        </div>
    </section>

    <section class="section" aria-labelledby="macro-heading">
        <div class="section-heading">
            <div><div class="eyebrow">Independent interpretation</div><h2 id="macro-heading">Dual-AI context and verified events</h2></div>
            <span class="muted">Separate from technical scoring</span>
        </div>
        <div class="context-layout">
            <div class="context-panel">
                <div class="cards">
                    <div class="card"><small>Gold</small><strong class="{{ $tone($report['macro']['gold_bias'] ?? '') }}">{{ strtoupper($report['macro']['gold_bias'] ?? 'Unavailable') }}</strong></div>
                    <div class="card"><small>USD</small><strong>{{ strtoupper($report['macro']['usd_strength'] ?? 'Unavailable') }}</strong></div>
                    <div class="card"><small>Confidence</small><strong>{{ $report['macro']['confidence'] ?? 0 }}%</strong><span class="muted">{{ strtoupper(str_replace('_', ' ', $report['macro']['agreement'] ?? 'Unavailable')) }}</span></div>
                </div>
                <p>{{ $report['executive']['macro_summary'] ?? 'AI context is unavailable.' }}</p>
                <div class="model-strip" aria-label="Individual AI positions">
                    @foreach($report['macro']['analyses'] ?? [] as $analysis)
                        <span class="model-view"><strong>{{ $analysis['provider'] ?? 'AI' }}</strong> &middot; {{ strtoupper($analysis['gold_bias'] ?? 'unavailable') }} gold &middot; {{ $analysis['confidence'] ?? 0 }}%</span>
                    @endforeach
                </div>
                <p class="muted">Generated {{ $time($report['macro']['generated_at'] ?? null) }} &middot; {{ strtoupper($report['macro']['risk_level'] ?? 'Unavailable') }} risk &middot; {{ strtoupper($report['macro']['status'] ?? 'unavailable') }}</p>
            </div>
            <div class="context-panel">
                <div class="eyebrow">Top verified developments</div>
                @forelse($report['executive']['events'] ?? [] as $event)
                    <article class="event">
                        <h3>{{ $event['headline'] }} <span class="{{ $tone($event['direction'] ?? '') }}">&middot; {{ strtoupper($event['direction'] ?? 'mixed') }}</span></h3>
                        <p>{{ $event['why_it_matters'] }}</p>
                        <p class="muted">{{ $event['source_name'] }} &middot; <a href="{{ $event['source_url'] }}" rel="noopener noreferrer">Verified source</a></p>
                    </article>
                @empty
                    <p>No verified event citations are available in this stored assessment.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="history-heading">
        <div class="section-heading"><div><div class="eyebrow">Reliability context</div><h2 id="history-heading">Bias history and historical alignment</h2></div></div>
        <p>{{ $report['history']['availability']['message'] }}</p>
        <div class="cards">
            <div class="card"><small>Mature samples</small><strong>{{ $report['history']['summary']['mature_samples'] ?? 0 }}</strong></div>
            <div class="card"><small>Alignment</small><strong>{{ isset($report['history']['summary']['alignment_percent']) ? $report['history']['summary']['alignment_percent'].'%' : 'Collecting' }}</strong></div>
            <div class="card"><small>Sample quality</small><strong>{{ strtoupper($report['history']['summary']['sample_quality'] ?? 'Insufficient') }}</strong></div>
        </div>
        <p class="muted">{{ $report['history']['methodology']['statement'] ?? 'Historical alignment is not a profitability backtest.' }}</p>
    </section>

    <section class="section" aria-labelledby="boundaries-heading">
        <div class="section-heading"><div><div class="eyebrow">Use boundaries</div><h2 id="boundaries-heading">Methodology and risk notice</h2></div></div>
        <ul>@foreach($report['boundaries'] as $boundary)<li>{{ $boundary }}</li>@endforeach</ul>
        <div class="risk"><strong>Educational context only.</strong><br>{{ $report['disclaimer'] }}</div>
    </section>

    <footer><span>{{ $report['report_id'] }} &middot; Closed-candle UTC analysis &middot; No execution</span><span>Generated by HorizonBias</span></footer>
</main>

<script>
    (function () {
        var buttons = Array.from(document.querySelectorAll('[data-theme-option]'));
        var applyTheme = function (theme) {
            var active = theme === 'dark' ? 'dark' : 'light';
            document.documentElement.dataset.reportTheme = active;
            buttons.forEach(function (button) {
                button.setAttribute('aria-pressed', button.dataset.themeOption === active ? 'true' : 'false');
            });
            try { localStorage.setItem('horizon_report_theme', active); } catch (error) {}
        };
        buttons.forEach(function (button) {
            button.addEventListener('click', function () { applyTheme(button.dataset.themeOption); });
        });
        applyTheme(document.documentElement.dataset.reportTheme);
    }());
</script>
</body>
</html>
