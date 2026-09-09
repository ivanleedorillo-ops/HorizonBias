<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Auditable multi-timeframe technical bias and macro context dedicated exclusively to XAU/USD.">
    <link rel="icon" type="image/png" href="{{ asset('images/horizonbias-logo.png') }}">
    <title>HorizonBias — XAU/USD Decision Support Dashboard</title>
    <script>
        (function() {
            try {
                var stored = localStorage.getItem('horizon_theme');
                var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                var theme = stored ? stored : (prefersDark ? 'dark' : 'light');
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.style.colorScheme = 'dark';
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.style.colorScheme = 'light';
                }
            } catch (e) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <script type="application/json" id="dashboard-data">{!! json_encode($dashboard, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="horizonDashboard" x-init="init()" class="min-h-screen w-full max-w-full overflow-x-hidden antialiased bg-[var(--color-bg-page)] text-[var(--color-text-primary)]">
    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-50 border-b border-[var(--color-border)] bg-[var(--color-bg-surface-translucent)] backdrop-blur-xl transition-colors">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-2.5 sm:px-6 sm:py-3 lg:px-8">
            <!-- Brand & Back to Overview Navigation -->
            <div class="flex items-center gap-3 sm:gap-4 shrink-0">
                <a href="{{ url('/') }}" class="group flex items-center gap-2.5" aria-label="Return to HorizonBias landing page">
                    <span class="relative grid h-9 w-9 sm:h-10 sm:w-10 place-items-center overflow-hidden rounded-xl border border-[var(--color-gold-border)] bg-[var(--color-gold-bg)] p-1 transition-all duration-300 group-hover:scale-105 shrink-0">
                        <img src="{{ asset('images/horizonbias-logo.png') }}" alt="" class="h-full w-full object-contain" width="40" height="40" decoding="async">
                    </span>
                    <div>
                        <strong class="block text-sm sm:text-base font-bold tracking-tight text-[var(--color-text-primary)] leading-none">Horizon<span class="text-[var(--color-gold-accent)]">Bias</span></strong>
                        <small class="hidden sm:block text-xs font-semibold uppercase tracking-widest text-[var(--color-text-muted)] mt-1">XAU/USD Intelligence</small>
                    </div>
                </a>

                <div class="hidden sm:block h-5 w-px bg-[var(--color-border)] mx-1"></div>

                <!-- Return to Landing Link -->
                <a href="{{ url('/') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-surface)] px-3 py-1.5 text-xs font-medium text-[var(--color-text-secondary)] transition hover:border-[var(--color-border-strong)] hover:text-[var(--color-text-primary)]" id="back-to-home">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Overview</span>
                </a>
            </div>

            <!-- Status Badges & Controls -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <!-- Mobile Return Link -->
                <a href="{{ url('/') }}" class="sm:hidden inline-flex items-center gap-1 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-surface)] px-2.5 py-1 text-xs font-medium text-[var(--color-text-secondary)]">
                    <span>← Overview</span>
                </a>

                <span class="hidden md:inline-flex items-center gap-1.5 rounded-full border border-[var(--color-border)] bg-[var(--color-bg-surface)] px-3 py-1 text-xs font-medium text-[var(--color-text-muted)]">
                    <span class="status-dot text-[var(--color-gold-accent)]"></span> Spot Gold Only
                </span>

                <!-- Mode Indicator Badge -->
                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold uppercase tracking-wider"
                      :class="data.mode === 'live' ? 'bias-bullish' : 'bias-neutral'">
                    <span class="status-dot"></span>
                    <span x-text="data.mode === 'live' ? 'Live' : 'Demo'"></span>
                </span>

                <!-- Theme Toggle Button -->
                <button type="button"
                        @click="toggleTheme()"
                        class="theme-toggle-btn"
                        :aria-label="theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'"
                        :title="theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'">
                    <!-- Sun icon shown in dark theme -->
                    <svg x-show="theme === 'dark'" x-cloak class="h-4 w-4 text-[var(--color-gold-accent)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <!-- Moon icon shown in light theme -->
                    <svg x-show="theme === 'light'" x-cloak class="h-4 w-4 text-[var(--color-text-secondary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>

                <!-- Manual Refresh Trigger -->
                <button type="button" @click="refresh()" class="btn-ghost !px-2.5 !py-1.5 text-xs" :disabled="refreshing" aria-label="Refresh dashboard data">
                    <svg class="h-3.5 w-3.5" :class="refreshing ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span class="hidden sm:inline" x-text="refreshing ? 'Refreshing…' : 'Sync'"></span>
                </button>
            </div>
        </div>
    </header>

    <!-- Notice / Offline / Demo Warning Banner -->
    <div x-show="data.notice || connectionIssue" x-cloak class="border-b border-[var(--color-neutral-border)] bg-[var(--color-neutral-bg)] transition-colors">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-2 text-xs text-[var(--color-neutral-text)] sm:px-6 lg:px-8">
            <p class="flex items-center gap-2">
                <span class="status-dot shrink-0"></span>
                <span x-text="connectionIssue ? 'Connection interrupted — retaining last confirmed snapshot in memory.' : data.notice"></span>
            </p>
            <button type="button" @click="refresh()" class="font-semibold underline underline-offset-2 hover:opacity-80 shrink-0" :disabled="refreshing">
                Retry Now
            </button>
        </div>
    </div>

    <!-- Main Content Container -->
    <main id="overview" class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <!-- Hero Section: Spot Quote & Overall Bias Consensus -->
        <section class="grid gap-5 lg:grid-cols-[1.3fr_0.7fr]">
            <!-- Spot Gold Quote Card -->
            <article class="panel relative overflow-hidden p-5 sm:p-7">
                <div class="relative grid gap-6 sm:grid-cols-2 sm:items-end">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="eyebrow text-[var(--color-gold-accent)]">Asset Quote</span>
                            <span class="rounded bg-[var(--color-bg-page-secondary)] border border-[var(--color-border-subtle)] px-2 py-0.5 text-xs text-[var(--color-text-muted)] font-medium">OANDA Feed</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2">
                            <h1 class="text-3xl font-bold tracking-tight text-[var(--color-text-primary)] sm:text-4xl">XAU/USD</h1>
                            <span class="text-xs font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">Spot Gold</span>
                        </div>
                        <div class="mt-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <p class="text-3xl sm:text-4xl font-semibold tabular-nums tracking-tight text-[var(--color-text-primary)]" x-text="data.quote.price == null ? '—' : '$' + formatNumber(data.quote.price, 2)"></p>
                            <p x-show="data.quote.change != null" class="text-sm font-semibold tabular-nums" :class="data.quote.change >= 0 ? 'text-[var(--color-bullish-text)]' : 'text-[var(--color-bearish-text)]'">
                                <span x-text="(data.quote.change >= 0 ? '+' : '') + formatNumber(data.quote.change, 2)"></span>
                                <span x-text="'(' + (data.quote.change_percent >= 0 ? '+' : '') + formatNumber(data.quote.change_percent, 2) + '%)'" class="ml-1"></span>
                            </p>
                        </div>
                        <p class="mt-2 text-xs text-[var(--color-text-muted)]">Data as of: <span class="font-medium text-[var(--color-text-secondary)]" x-text="formatTime(data.quote.as_of)"></span></p>
                    </div>

                    <!-- Market Context Details Column -->
                    <div class="sm:border-l sm:border-[var(--color-border-subtle)] sm:pl-6 space-y-3">
                        <p class="eyebrow text-[var(--color-text-muted)]">Multi-Timeframe Agreement</p>
                        <template x-if="data.overall">
                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <span class="rounded-lg border px-3 py-1 text-sm font-bold tracking-tight"
                                          :class="biasBadgeClass(data.overall.label)"
                                          x-text="data.overall.label"></span>
                                    <span class="text-2xl font-bold tabular-nums text-[var(--color-text-primary)]" x-text="(data.overall.score > 0 ? '+' : '') + data.overall.score"></span>
                                    <span class="text-xs text-[var(--color-text-muted)] font-medium">/ 100</span>
                                </div>
                                <!-- Visual Score Bar -->
                                <div class="h-2 w-full overflow-hidden rounded-full bg-[var(--color-bg-page-secondary)] border border-[var(--color-border-subtle)] relative">
                                    <div class="absolute top-0 bottom-0 transition-all duration-500 rounded-full"
                                         :style="{
                                             left: data.overall.score >= 0 ? '50%' : `${50 + (data.overall.score / 2)}%`,
                                             width: `${Math.abs(data.overall.score) / 2}%`,
                                             backgroundColor: data.overall.score > 0 ? 'var(--color-bullish-text)' : (data.overall.score < 0 ? 'var(--color-bearish-text)' : 'var(--color-neutral-text)')
                                         }"></div>
                                    <div class="absolute left-1/2 top-0 bottom-0 w-0.5 -translate-x-1/2 bg-[var(--color-border-strong)]"></div>
                                </div>
                                <p class="text-xs leading-relaxed text-[var(--color-text-secondary)]" x-text="data.overall.summary"></p>
                            </div>
                        </template>
                        <p x-show="!data.overall" class="text-xs text-[var(--color-text-muted)]">Live multi-timeframe analysis is calculating higher-horizon weights.</p>
                    </div>
                </div>
            </article>

            <!-- Methodology Summary Card -->
            <aside class="panel flex flex-col justify-between p-5 sm:p-7">
                <div>
                    <p class="eyebrow text-[var(--color-gold-accent)]">Auditable Framework</p>
                    <h2 class="mt-2 text-base sm:text-lg font-semibold tracking-tight text-[var(--color-text-primary)]">Deterministic Structure</h2>
                    <p class="mt-2 text-xs leading-relaxed text-[var(--color-text-secondary)]">
                        Evaluates EMA alignment, Wilder RSI, confirmed 5-candle pivots, and Donchian breakouts across completed candles only. Higher timeframes carry greater mathematical weight.
                    </p>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2.5 text-xs">
                    <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-page-secondary)] p-3">
                        <span class="block text-[var(--color-text-muted)] text-xs font-semibold uppercase tracking-wider">Horizons</span>
                        <strong class="mt-0.5 block text-sm font-semibold text-[var(--color-text-primary)]">7 Timeframes</strong>
                    </div>
                    <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-page-secondary)] p-3">
                        <span class="block text-[var(--color-text-muted)] text-xs font-semibold uppercase tracking-wider">Repaint Risk</span>
                        <strong class="mt-0.5 block text-sm font-semibold text-[var(--color-bullish-text)]">Zero (Closed Bars)</strong>
                    </div>
                </div>
            </aside>
        </section>

        <!-- Seven-Timeframe Horizon Selector Matrix -->
        <section aria-labelledby="timeframe-heading" class="space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <p class="eyebrow text-[var(--color-gold-accent)]">Timeframe Matrix</p>
                    <h2 id="timeframe-heading" class="mt-0.5 text-lg sm:text-xl font-bold text-[var(--color-text-primary)]">Horizon Bias Breakdown</h2>
                </div>
                <div class="flex items-center gap-2 text-xs text-[var(--color-text-muted)]">
                    <span class="hidden sm:inline">Use keyboard ← → to switch</span>
                    <button type="button" @click="selectPrevFrame()" class="p-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-surface)] hover:border-[var(--color-border-strong)] text-[var(--color-text-secondary)]" aria-label="Previous timeframe">←</button>
                    <button type="button" @click="selectNextFrame()" class="p-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-surface)] hover:border-[var(--color-border-strong)] text-[var(--color-text-secondary)]" aria-label="Next timeframe">→</button>
                </div>
            </div>

            <!-- Responsive 7-Card Grid / Scrollable on narrow mobile -->
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 lg:grid-cols-7" role="tablist" aria-label="Available Timeframes">
                <template x-for="frame in data.timeframes" :key="frame.key">
                    <button type="button"
                            :id="'tf-btn-' + frame.key"
                            @click="selectTimeframe(frame.key)"
                            role="tab"
                            :aria-selected="selectedKey === frame.key"
                            :tabindex="selectedKey === frame.key ? 0 : -1"
                            class="panel-interactive flex flex-col justify-between p-3.5 sm:p-4 text-left min-h-28 focus:outline-none transition-all cursor-pointer"
                            :class="selectedKey === frame.key
                                ? 'ring-2 ring-[var(--color-gold-accent)] border-[var(--color-gold-border)] bg-[var(--color-bg-page-secondary)] shadow-sm'
                                : 'hover:border-[var(--color-border-strong)]'">
                        <div class="flex items-center justify-between w-full">
                            <span class="text-sm font-bold tracking-tight text-[var(--color-text-primary)]" x-text="frame.key"></span>
                            <span x-show="frame.stale" class="rounded bg-[var(--color-neutral-bg)] border border-[var(--color-neutral-border)] px-1.5 py-0.5 text-xs font-semibold text-[var(--color-neutral-text)] uppercase">Stale</span>
                            <span x-show="!frame.stale && selectedKey === frame.key" class="h-2 w-2 rounded-full bg-[var(--color-gold-accent)]"></span>
                        </div>
                        <div class="my-2">
                            <p class="text-lg sm:text-xl font-bold tabular-nums text-[var(--color-text-primary)]" x-text="(frame.score > 0 ? '+' : '') + frame.score"></p>
                            <p class="mt-0.5 text-xs font-semibold"
                               :class="biasTone(frame.bias) === 'bullish' ? 'text-[var(--color-bullish-text)]' : (biasTone(frame.bias) === 'bearish' ? 'text-[var(--color-bearish-text)]' : 'text-[var(--color-neutral-text)]')"
                               x-text="frame.bias"></p>
                        </div>
                        <span class="text-xs text-[var(--color-text-muted)] block border-t border-[var(--color-border-subtle)] pt-1.5 font-medium" x-text="frame.label"></span>
                    </button>
                </template>
            </div>
            <p x-show="!data.timeframes?.length" class="panel p-6 text-sm text-[var(--color-text-muted)] text-center">Awaiting timeframe snapshot data from market provider.</p>
        </section>

        <!-- Chart and Technical Evidence Grid -->
        <section class="grid gap-6 lg:grid-cols-[1.4fr_0.6fr] items-start">
            <!-- TradingView Chart Container -->
            <article class="panel overflow-hidden min-w-0">
                <div class="flex items-center justify-between border-b border-[var(--color-border)] px-4 py-3 sm:px-5 sm:py-3.5 bg-[var(--color-bg-page-secondary)]">
                    <div>
                        <p class="eyebrow text-[var(--color-gold-accent)]">Market Chart</p>
                        <h2 class="text-sm sm:text-base font-semibold text-[var(--color-text-primary)]">XAU/USD Live Technicals</h2>
                    </div>
                    <span class="text-xs text-[var(--color-text-muted)] font-medium">Independent TradingView Display</span>
                </div>

                <!-- Responsive Chart Embed -->
                <div id="tradingview-chart" data-theme="dark" class="tradingview-widget-container relative w-full h-[460px] sm:h-[540px] lg:h-[640px] min-w-0 bg-[var(--color-bg-page-secondary)]">
                    <div class="tradingview-widget-container__widget w-full" style="height: calc(100% - 32px);"></div>
                    <div class="tradingview-widget-copyright px-3 py-1.5 text-xs bg-[var(--color-bg-surface-translucent)] border-t border-[var(--color-border-subtle)] text-[var(--color-text-muted)]">
                        <a href="https://www.tradingview.com/symbols/XAUUSD/" rel="noopener nofollow" target="_blank" class="text-[var(--color-gold-accent)] hover:underline font-medium">XAUUSD chart</a>
                        <span> provided by TradingView</span>
                    </div>
                    <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js" async>
                    {"autosize":true,"symbol":"OANDA:XAUUSD","interval":"60","timezone":"Etc/UTC","theme":"dark","style":"1","locale":"en","allow_symbol_change":false,"calendar":false,"support_host":"https://www.tradingview.com","hide_side_toolbar":false,"withdateranges":true,"save_image":false}
                    </script>
                    <!-- Fallback if chart is blocked -->
                    <div x-show="chartIssue" x-cloak class="absolute inset-0 grid place-items-center bg-[var(--color-bg-surface)] p-6 text-center">
                        <div class="max-w-sm space-y-2">
                            <span class="text-3xl" aria-hidden="true">📊</span>
                            <p class="font-semibold text-[var(--color-text-primary)] text-base">Chart display paused</p>
                            <p class="text-xs text-[var(--color-text-muted)] leading-relaxed">External chart embeds may be restricted by your network or browser privacy settings. Technical bias scoring operates independently and remains fully functional.</p>
                        </div>
                    </div>
                </div>
            </article>

            <!-- Technical Evidence Detail Panel -->
            <aside class="panel p-5 sm:p-6 min-w-0" aria-live="polite" aria-label="Technical Evidence for Selected Timeframe">
                <template x-if="selected">
                    <div class="space-y-5">
                        <!-- Evidence Header -->
                        <div class="flex items-start justify-between border-b border-[var(--color-border-subtle)] pb-4">
                            <div>
                                <p class="eyebrow text-[var(--color-gold-accent)]">Detailed Audit</p>
                                <h2 class="mt-1 text-lg sm:text-xl font-bold text-[var(--color-text-primary)]"><span x-text="selected.label"></span> Evidence</h2>
                                <span class="text-xs text-[var(--color-text-muted)] font-medium" x-text="'Horizon: ' + selected.key"></span>
                            </div>
                            <span class="rounded-lg border px-3 py-1 text-xs font-bold"
                                  :class="biasBadgeClass(selected.bias)"
                                  x-text="selected.bias"></span>
                        </div>

                        <!-- Pillar Breakdown Scores -->
                        <div>
                            <span class="eyebrow text-[var(--color-text-muted)] block mb-2">Score Components</span>
                            <div class="space-y-1">
                                <template x-for="(val, compKey) in selected.component_scores" :key="compKey">
                                    <div class="metric-row !py-2">
                                        <span class="text-xs font-medium capitalize text-[var(--color-text-secondary)]" x-text="compKey"></span>
                                        <span class="text-xs font-bold tabular-nums"
                                              :class="val > 0 ? 'text-[var(--color-bullish-text)]' : (val < 0 ? 'text-[var(--color-bearish-text)]' : 'text-[var(--color-text-muted)]')"
                                              x-text="(val > 0 ? '+' : '') + val"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Categorized Technical Metrics Grid -->
                        <div>
                            <span class="eyebrow text-[var(--color-text-muted)] block mb-2">Indicator Readings</span>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-page-secondary)] p-2.5">
                                    <span class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">RSI 14</span>
                                    <strong class="mt-0.5 block text-sm font-semibold tabular-nums text-[var(--color-text-primary)]" x-text="formatNumber(selected.metrics.rsi14)"></strong>
                                </div>
                                <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-page-secondary)] p-2.5">
                                    <span class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">ADX 14</span>
                                    <strong class="mt-0.5 block text-sm font-semibold tabular-nums text-[var(--color-text-primary)]" x-text="formatNumber(selected.metrics.adx14)"></strong>
                                </div>
                                <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-page-secondary)] p-2.5">
                                    <span class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">ATR 14</span>
                                    <strong class="mt-0.5 block text-sm font-semibold tabular-nums text-[var(--color-text-primary)]" x-text="formatNumber(selected.metrics.atr14)"></strong>
                                </div>
                                <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-page-secondary)] p-2.5">
                                    <span class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">ROC 10</span>
                                    <strong class="mt-0.5 block text-sm font-semibold tabular-nums text-[var(--color-text-primary)]" x-text="formatNumber(selected.metrics.roc10) + '%'"></strong>
                                </div>
                                <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-page-secondary)] p-2.5">
                                    <span class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">EMA 20</span>
                                    <strong class="mt-0.5 block text-sm font-semibold tabular-nums text-[var(--color-text-primary)]" x-text="formatNumber(selected.metrics.ema20)"></strong>
                                </div>
                                <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-page-secondary)] p-2.5">
                                    <span class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">EMA 200</span>
                                    <strong class="mt-0.5 block text-sm font-semibold tabular-nums text-[var(--color-text-primary)]" x-text="formatNumber(selected.metrics.ema200)"></strong>
                                </div>
                            </div>
                        </div>

                        <!-- Qualitative Explanations (Progressive Disclosure) -->
                        <div class="border-t border-[var(--color-border-subtle)] pt-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="eyebrow text-[var(--color-text-muted)]">Conditions Summary</span>
                                <button type="button"
                                        @click="toggleEvidenceDetails()"
                                        class="text-xs font-semibold text-[var(--color-gold-accent)] hover:underline cursor-pointer"
                                        :aria-expanded="showEvidenceDetails">
                                    <span x-text="showEvidenceDetails ? 'Hide details' : 'Show details'"></span>
                                </button>
                            </div>
                            <div x-show="showEvidenceDetails" x-collapse>
                                <ul class="space-y-2 mt-2">
                                    <template x-for="reason in selected.explanations" :key="reason">
                                        <li class="flex items-start gap-2.5 text-xs leading-relaxed text-[var(--color-text-secondary)]">
                                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--color-gold-accent)]"></span>
                                            <span x-text="reason"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>

                        <div class="border-t border-[var(--color-border-subtle)] pt-3 text-xs text-[var(--color-text-muted)]">
                            Completed candle: <span class="text-[var(--color-text-secondary)] font-medium" x-text="formatTime(selected.data_as_of)"></span>
                        </div>
                    </div>
                </template>
                <div x-show="!selected" class="grid place-items-center h-48 text-center p-6 text-sm text-[var(--color-text-muted)]">
                    <p>Select any timeframe horizon above to inspect its component scores and indicators.</p>
                </div>
            </aside>
        </section>

        <!-- Dual-AI Macro Context (Always Separate From Deterministic Technicals) -->
        <section class="panel p-5 sm:p-8 min-w-0" aria-labelledby="macro-heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-[var(--color-border)] bg-[var(--color-bg-page-secondary)] px-3 py-1 text-xs text-[var(--color-text-muted)] font-medium">
                        <span class="status-dot text-[var(--color-gold-accent)]"></span>
                        <span>Gemini + Groq GPT-OSS · Official-feed evidence</span>
                    </div>
                    <h2 id="macro-heading" class="mt-3 text-xl sm:text-2xl font-bold tracking-tight text-[var(--color-text-primary)]">Dual-AI Market Context</h2>
                    <p class="mt-1 text-xs text-[var(--color-text-muted)]">Independent analyses, combined by deterministic Laravel rules — not by model voting.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-lg border px-3 py-1 font-bold capitalize" :class="biasBadgeClass(data.macro.gold_bias)" x-text="data.macro.gold_bias + ' Gold'"></span>
                    <span class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-surface)] px-3 py-1 font-semibold capitalize text-[var(--color-text-secondary)]" x-text="data.macro.usd_strength + ' USD'"></span>
                    <span class="rounded-lg border border-[var(--color-border)] bg-[var(--color-bg-surface)] px-3 py-1 font-semibold text-[var(--color-text-secondary)]" x-text="data.macro.confidence + '% confidence'"></span>
                </div>
            </div>

            <div class="mt-6 grid gap-5 lg:grid-cols-[0.8fr_1.2fr]">
                <!-- Laravel Consensus Summary -->
                <article class="rounded-2xl border border-[var(--color-gold-border)] bg-[var(--color-gold-bg)] p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="eyebrow text-[var(--color-gold-accent)]">Laravel Consensus</p>
                            <span class="rounded-full border border-[var(--color-gold-border)] bg-[var(--color-bg-surface)] px-2.5 py-1 text-xs font-semibold uppercase tracking-wider text-[var(--color-text-secondary)]" x-text="humanize(data.macro.agreement)"></span>
                        </div>
                        <p class="mt-4 text-sm leading-relaxed text-[var(--color-text-primary)] font-medium" x-text="data.macro.summary"></p>
                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-surface)] p-3">
                                <span class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">Risk level</span>
                                <strong class="mt-1 block capitalize text-[var(--color-text-primary)] font-semibold" x-text="data.macro.risk_level"></strong>
                            </div>
                            <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-surface)] p-3">
                                <span class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">Assessment</span>
                                <strong class="mt-1 block capitalize text-[var(--color-text-primary)] font-semibold" x-text="humanize(data.macro.status)"></strong>
                            </div>
                        </div>

                        <!-- Expandable Limitations Accordion -->
                        <div x-show="data.macro.limitations?.length" class="mt-4 border-t border-[var(--color-border-subtle)] pt-3">
                            <button type="button"
                                    @click="toggleAiSection('consensus')"
                                    class="flex items-center justify-between w-full text-xs font-semibold text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)] cursor-pointer"
                                    :aria-expanded="isAiSectionExpanded('consensus')">
                                <span>Limitations & Boundaries (<span x-text="data.macro.limitations?.length ?? 0"></span>)</span>
                                <svg class="h-4 w-4 transition-transform duration-200" :class="isAiSectionExpanded('consensus') ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="isAiSectionExpanded('consensus')" x-collapse>
                                <ul class="mt-2 space-y-1.5 text-xs leading-relaxed text-[var(--color-text-secondary)]">
                                    <template x-for="limitation in data.macro.limitations" :key="limitation">
                                        <li class="flex gap-2"><span class="text-[var(--color-gold-accent)]">•</span><span x-text="limitation"></span></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-[var(--color-text-muted)] border-t border-[var(--color-border-subtle)] pt-3">Updated: <span class="font-medium text-[var(--color-text-secondary)]" x-text="formatTime(data.macro.generated_at)"></span></p>
                </article>

                <!-- Independent Model Analyses (Progressive Disclosure) -->
                <div class="grid gap-3 sm:grid-cols-2">
                    <template x-for="analysis in data.macro.analyses" :key="analysis.provider">
                        <article class="rounded-2xl border border-[var(--color-border)] bg-[var(--color-bg-page-secondary)] p-5 flex flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-bold text-sm sm:text-base text-[var(--color-text-primary)]" x-text="analysis.provider"></h3>
                                        <p class="mt-0.5 break-all text-xs text-[var(--color-text-muted)]" x-text="analysis.model"></p>
                                    </div>
                                    <span class="rounded-md border px-2 py-1 text-xs font-bold capitalize" :class="biasBadgeClass(analysis.gold_bias)" x-text="analysis.gold_bias"></span>
                                </div>
                                <p class="mt-3 text-xs leading-relaxed text-[var(--color-text-secondary)]" x-text="analysis.summary"></p>
                                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                    <div class="rounded-lg border border-[var(--color-border-subtle)] bg-[var(--color-bg-surface)] p-2">
                                        <dt class="text-[var(--color-text-muted)] uppercase text-[0.68rem] font-semibold">USD</dt>
                                        <dd class="mt-0.5 font-semibold capitalize text-[var(--color-text-primary)]" x-text="analysis.usd_strength"></dd>
                                    </div>
                                    <div class="rounded-lg border border-[var(--color-border-subtle)] bg-[var(--color-bg-surface)] p-2">
                                        <dt class="text-[var(--color-text-muted)] uppercase text-[0.68rem] font-semibold">Confidence</dt>
                                        <dd class="mt-0.5 font-semibold text-[var(--color-text-primary)]" x-text="analysis.confidence + '%'"></dd>
                                    </div>
                                </dl>

                                <!-- Progressive Disclosure for Factors -->
                                <div x-show="analysis.supporting_factors?.length || analysis.opposing_factors?.length" class="mt-3 border-t border-[var(--color-border-subtle)] pt-2.5">
                                    <button type="button"
                                            @click="toggleAiSection(analysis.provider)"
                                            class="flex items-center justify-between w-full text-xs font-semibold text-[var(--color-gold-accent)] hover:underline cursor-pointer"
                                            :aria-expanded="isAiSectionExpanded(analysis.provider)">
                                        <span>Supporting & Opposing Context</span>
                                        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="isAiSectionExpanded(analysis.provider) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div x-show="isAiSectionExpanded(analysis.provider)" x-collapse class="space-y-3 mt-2">
                                        <div x-show="analysis.supporting_factors?.length">
                                            <p class="text-xs font-semibold uppercase tracking-wider text-[var(--color-bullish-text)]">Supporting factors</p>
                                            <ul class="mt-1 space-y-1 text-xs leading-relaxed text-[var(--color-text-secondary)]">
                                                <template x-for="factor in analysis.supporting_factors" :key="factor"><li x-text="'• ' + factor"></li></template>
                                            </ul>
                                        </div>
                                        <div x-show="analysis.opposing_factors?.length">
                                            <p class="text-xs font-semibold uppercase tracking-wider text-[var(--color-bearish-text)]">Opposing factors</p>
                                            <ul class="mt-1 space-y-1 text-xs leading-relaxed text-[var(--color-text-secondary)]">
                                                <template x-for="factor in analysis.opposing_factors" :key="factor"><li x-text="'• ' + factor"></li></template>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>
                    <div x-show="!data.macro.analyses?.length" class="col-span-full grid min-h-40 place-items-center rounded-xl border border-dashed border-[var(--color-border)] p-6 text-center">
                        <p class="text-sm text-[var(--color-text-muted)]">No validated AI assessment is available yet.</p>
                    </div>
                </div>
            </div>

            <!-- Verified Citations Section -->
            <div class="mt-7 border-t border-[var(--color-border)] pt-6">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-bold text-[var(--color-text-primary)]">Verified Evidence Cited by Analysts</h3>
                    <span class="text-xs text-[var(--color-text-muted)] font-medium" x-text="(data.macro.events?.length ?? 0) + ' cited item(s)'"></span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-for="event in data.macro.events" :key="event.source_url">
                        <a :href="event.source_url" target="_blank" rel="noopener noreferrer" class="panel-interactive flex flex-col justify-between p-4 group">
                            <div>
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="font-semibold uppercase tracking-wider text-[var(--color-text-muted)]" x-text="event.source_name"></span>
                                    <span class="font-bold capitalize" :class="biasTone(event.direction) === 'bullish' ? 'text-[var(--color-bullish-text)]' : (biasTone(event.direction) === 'bearish' ? 'text-[var(--color-bearish-text)]' : 'text-[var(--color-neutral-text)]')" x-text="event.direction"></span>
                                </div>
                                <h4 class="mt-2 text-xs font-semibold leading-snug text-[var(--color-text-primary)] transition-colors group-hover:text-[var(--color-gold-accent)]" x-text="event.headline"></h4>
                                <p class="mt-2 text-xs leading-relaxed text-[var(--color-text-secondary)]" x-text="event.why_it_matters"></p>
                            </div>
                            <span class="mt-3 block text-xs font-semibold text-[var(--color-gold-accent)] group-hover:underline">Read official source ↗</span>
                        </a>
                    </template>
                    <div x-show="!data.macro.events?.length" class="col-span-full grid min-h-28 place-items-center rounded-xl border border-dashed border-[var(--color-border)] bg-[var(--color-bg-page-secondary)] p-6 text-center">
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-[var(--color-text-secondary)]">No verified evidence was cited</p>
                            <p class="text-xs text-[var(--color-text-muted)]">The assessment may be technical-only or no recent relevant official releases were available.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Transparency & Mandatory Educational Disclaimer -->
        <section class="grid gap-5 lg:grid-cols-2">
            <article class="panel p-5 sm:p-7 space-y-2.5">
                <p class="eyebrow text-[var(--color-gold-accent)]">Scoring Integrity</p>
                <h2 class="text-base sm:text-lg font-bold text-[var(--color-text-primary)]">How Horizon Scores Work</h2>
                <p class="text-xs leading-relaxed text-[var(--color-text-secondary)]">
                    Each horizon computes trend alignment, Wilder momentum, confirmed 5-candle market structure pivots, and Donchian channel breakouts. Higher timeframes receive greater composite weighting. AI macroeconomic context is displayed separately and cannot alter the quantitative technical score.
                </p>
            </article>

            <article class="rounded-2xl border border-[var(--color-neutral-border)] bg-[var(--color-neutral-bg)] p-5 sm:p-7 space-y-2.5">
                <div class="flex items-center gap-2">
                    <span class="text-[var(--color-neutral-text)] text-xs font-bold uppercase tracking-wider">⚠️ Educational Risk Notice</span>
                </div>
                <h2 class="text-base sm:text-lg font-bold text-[var(--color-neutral-text)]">Context, Never a Command</h2>
                <p class="text-xs leading-relaxed text-[var(--color-neutral-text)] opacity-90">
                    HorizonBias provides educational market context and technical bias only. It is not financial advice, a trading signal, or a recommendation to buy or sell. Market and AI-generated information may be delayed, incomplete, or inaccurate. Independently verify all information and make your own risk decisions.
                </p>
            </article>
        </section>
    </main>

    <!-- Footer -->
    <footer class="mt-8 border-t border-[var(--color-border)] bg-[var(--color-bg-surface)] py-6 text-xs text-[var(--color-text-muted)]">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div class="space-y-1">
                <p class="font-medium text-[var(--color-text-secondary)]">© {{ date('Y') }} HorizonBias · Dedicated to XAU/USD Decision Support</p>
                <p class="text-xs text-[var(--color-text-muted)]">Closed bar calculations · UTC timestamps · No execution</p>
            </div>
            <div class="flex items-center gap-6 font-medium">
                <a href="{{ url('/') }}" class="hover:text-[var(--color-gold-accent)] transition">← Overview & Methodology</a>
                <a href="#overview" class="hover:text-[var(--color-gold-accent)] transition">Back to Top ↑</a>
            </div>
        </div>
    </footer>
</body>
</html>
