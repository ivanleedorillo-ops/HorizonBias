<!DOCTYPE html>
<html lang="en" class="scroll-smooth bg-[#07090d]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Auditable multi-timeframe technical bias and macro context dedicated exclusively to XAU/USD.">
    <link rel="icon" type="image/png" href="{{ asset('images/horizonbias-logo.png') }}">
    <title>HorizonBias — XAU/USD Decision Support Dashboard</title>
    <script type="application/json" id="dashboard-data">{!! json_encode($dashboard, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="horizonDashboard" x-init="init()" class="min-h-screen overflow-x-hidden bg-[#07090d] text-slate-100 antialiased selection:bg-amber-300/30 selection:text-amber-100">
    <!-- Ambient ambient glow effects -->
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-48 left-1/3 h-[32rem] w-[32rem] rounded-full bg-amber-400/[0.04] blur-[120px]"></div>
        <div class="absolute right-0 top-1/3 h-88 w-88 rounded-full bg-emerald-400/[0.02] blur-[130px]"></div>
        <div class="absolute bottom-10 left-10 h-72 w-72 rounded-full bg-amber-300/[0.02] blur-[110px]"></div>
    </div>

    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-50 border-b border-white/[0.07] bg-[#07090d]/90 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-1.5 px-3 py-2 sm:px-6 sm:py-3 lg:px-8">
            <!-- Brand & Back to Overview Navigation -->
            <div class="flex items-center gap-1.5 sm:gap-4 shrink-0">
                <a href="{{ url('/') }}" class="group flex items-center gap-2 sm:gap-3" aria-label="Return to HorizonBias landing page">
                    <span class="relative grid h-8 w-8 sm:h-10 sm:w-10 place-items-center overflow-hidden rounded-xl border border-amber-300/30 bg-gradient-to-b from-amber-300/10 to-transparent p-1 transition-all duration-300 group-hover:border-amber-300/50 group-hover:shadow-[0_0_12px_rgba(251,191,36,0.25)] shrink-0">
                        <img src="{{ asset('images/horizonbias-logo.png') }}" alt="" class="h-full w-full object-contain" width="40" height="40" decoding="async">
                    </span>
                    <div>
                        <strong class="block text-xs sm:text-base tracking-tight text-white leading-none">Horizon<span class="text-amber-300">Bias</span></strong>
                        <small class="hidden sm:block text-[0.62rem] font-semibold uppercase tracking-[0.24em] text-slate-400 mt-1">XAU/USD Intelligence</small>
                    </div>
                </a>

                <div class="hidden sm:block h-5 w-px bg-white/10 mx-1"></div>

                <!-- Return to Landing Link -->
                <a href="{{ url('/') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/[0.02] px-2.5 py-1 text-xs font-medium text-slate-300 transition hover:bg-white/[0.06] hover:text-amber-200" id="back-to-home">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Overview</span>
                </a>
            </div>

            <!-- Status Badges & Controls -->
            <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
                <!-- Mobile Return Link -->
                <a href="{{ url('/') }}" class="sm:hidden inline-flex items-center gap-1 rounded-lg border border-white/10 bg-white/[0.03] px-2 py-1 text-[0.68rem] text-slate-300">
                    <span>← Overview</span>
                </a>

                <span class="hidden md:inline-flex items-center gap-1.5 rounded-full border border-white/10 px-3 py-1 text-xs text-slate-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span> Spot Gold Only
                </span>

                <!-- Mode Indicator Badge -->
                <span class="inline-flex items-center rounded-full border px-2 py-0.5 sm:px-3 sm:py-1 text-[0.65rem] sm:text-xs font-semibold uppercase tracking-wider"
                      :class="data.mode === 'live' ? 'bias-bullish' : 'bias-neutral'">
                    <span class="status-dot mr-1 sm:mr-1.5"></span>
                    <span x-text="data.mode === 'live' ? 'Live' : 'Demo'"></span>
                </span>

                <!-- Manual Refresh Trigger -->
                <button type="button" @click="refresh()" class="btn-ghost !p-1.5 sm:!px-3 sm:!py-1 text-[0.7rem] sm:text-xs" :disabled="refreshing" aria-label="Refresh dashboard data">
                    <svg class="h-3.5 w-3.5 text-slate-400" :class="refreshing ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span class="hidden sm:inline" x-text="refreshing ? 'Refreshing…' : 'Sync'"></span>
                </button>
            </div>
        </div>
    </header>

    <!-- Notice / Offline / Demo Warning Banner -->
    <div x-show="data.notice || connectionIssue" x-cloak class="border-b border-amber-300/20 bg-amber-300/[0.08] backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-2.5 text-xs text-amber-100 sm:px-6 lg:px-8">
            <p class="flex items-center gap-2">
                <span class="status-dot text-amber-300 shrink-0"></span>
                <span x-text="connectionIssue ? 'Connection interrupted — retaining last confirmed snapshot in memory.' : data.notice"></span>
            </p>
            <button type="button" @click="refresh()" class="font-semibold text-amber-200 underline underline-offset-2 hover:text-white" :disabled="refreshing">
                Retry Now
            </button>
        </div>
    </div>

    <!-- Main Content Container -->
    <main id="overview" class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <!-- Hero Section: Spot Quote & Overall Bias Consensus -->
        <section class="grid gap-5 lg:grid-cols-[1.3fr_0.7fr]">
            <!-- Spot Gold Quote Card -->
            <article class="panel relative overflow-hidden p-6 sm:p-8">
                <div aria-hidden="true" class="absolute right-0 top-0 h-48 w-48 translate-x-1/3 -translate-y-1/3 rounded-full bg-amber-300/[0.06] blur-3xl"></div>
                <div class="relative grid gap-6 sm:grid-cols-2 sm:items-end">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="eyebrow text-amber-300/90">Asset Quote</span>
                            <span class="rounded bg-white/[0.06] px-1.5 py-0.5 text-[0.62rem] text-slate-400">OANDA Feed</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2">
                            <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">XAU/USD</h1>
                            <span class="text-xs font-semibold uppercase text-slate-400">Spot Gold</span>
                        </div>
                        <div class="mt-4 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                            <p class="text-3xl font-medium tabular-nums tracking-tight text-white sm:text-4xl" x-text="data.quote.price == null ? '—' : '$' + formatNumber(data.quote.price, 2)"></p>
                            <p x-show="data.quote.change != null" class="text-sm font-semibold tabular-nums" :class="data.quote.change >= 0 ? 'text-emerald-300' : 'text-rose-300'">
                                <span x-text="(data.quote.change >= 0 ? '+' : '') + formatNumber(data.quote.change, 2)"></span>
                                <span x-text="'(' + (data.quote.change_percent >= 0 ? '+' : '') + formatNumber(data.quote.change_percent, 2) + '%)'" class="ml-1"></span>
                            </p>
                        </div>
                        <p class="mt-2 text-[0.72rem] text-slate-400">Data as of: <span x-text="formatTime(data.quote.as_of)"></span></p>
                    </div>

                    <!-- Market Context Details Column -->
                    <div class="sm:border-l sm:border-white/[0.08] sm:pl-6 space-y-3">
                        <p class="eyebrow text-slate-400">Multi-Timeframe Agreement</p>
                        <template x-if="data.overall">
                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <span class="rounded-lg border px-3 py-1 text-sm font-bold tracking-tight"
                                          :class="biasBadgeClass(data.overall.label)"
                                          x-text="data.overall.label"></span>
                                    <span class="text-2xl font-bold tabular-nums text-white" x-text="(data.overall.score > 0 ? '+' : '') + data.overall.score"></span>
                                    <span class="text-xs text-slate-400">/ 100</span>
                                </div>
                                <!-- Visual Score Bar -->
                                <div class="h-2 w-full overflow-hidden rounded-full bg-white/[0.07] relative">
                                    <div class="absolute top-0 bottom-0 transition-all duration-500 rounded-full"
                                         :style="{
                                             left: data.overall.score >= 0 ? '50%' : `${50 + (data.overall.score / 2)}%`,
                                             width: `${Math.abs(data.overall.score) / 2}%`,
                                             backgroundColor: data.overall.score > 0 ? '#34d399' : (data.overall.score < 0 ? '#fb7185' : '#fbbf24')
                                         }"></div>
                                    <div class="absolute left-1/2 top-0 bottom-0 w-0.5 -translate-x-1/2 bg-white/40"></div>
                                </div>
                                <p class="text-xs leading-relaxed text-slate-300" x-text="data.overall.summary"></p>
                            </div>
                        </template>
                        <p x-show="!data.overall" class="text-xs text-slate-400">Live multi-timeframe analysis is calculating higher-horizon weights.</p>
                    </div>
                </div>
            </article>

            <!-- Methodology Summary Card -->
            <aside class="panel flex flex-col justify-between p-6">
                <div>
                    <p class="eyebrow text-amber-300/90">Auditable Framework</p>
                    <h2 class="mt-2 text-lg font-semibold tracking-tight text-white">Deterministic Structure</h2>
                    <p class="mt-2 text-xs leading-relaxed text-slate-300">
                        Evaluates EMA alignment, Wilder RSI, confirmed 5-candle pivots, and Donchian breakouts across completed candles only. Higher timeframes carry greater mathematical weight.
                    </p>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2.5 text-xs">
                    <div class="rounded-xl border border-white/[0.06] bg-white/[0.025] p-3">
                        <span class="block text-slate-400 text-[0.65rem] uppercase tracking-wider">Horizons</span>
                        <strong class="mt-0.5 block text-slate-200">7 Timeframes</strong>
                    </div>
                    <div class="rounded-xl border border-white/[0.06] bg-white/[0.025] p-3">
                        <span class="block text-slate-400 text-[0.65rem] uppercase tracking-wider">Repaint Risk</span>
                        <strong class="mt-0.5 block text-emerald-400">Zero (Closed Bars)</strong>
                    </div>
                </div>
            </aside>
        </section>

        <!-- Seven-Timeframe Horizon Selector Matrix -->
        <section aria-labelledby="timeframe-heading" class="space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <p class="eyebrow text-amber-300/90">Timeframe Matrix</p>
                    <h2 id="timeframe-heading" class="mt-0.5 text-xl font-semibold text-white">Horizon Bias Breakdown</h2>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <span class="hidden sm:inline">Use keyboard ← → to switch</span>
                    <button type="button" @click="selectPrevFrame()" class="p-1 rounded bg-white/[0.04] hover:bg-white/[0.08] text-slate-300" aria-label="Previous timeframe">←</button>
                    <button type="button" @click="selectNextFrame()" class="p-1 rounded bg-white/[0.04] hover:bg-white/[0.08] text-slate-300" aria-label="Next timeframe">→</button>
                </div>
            </div>

            <!-- Responsive 7-Card Grid -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7" role="tablist" aria-label="Available Timeframes">
                <template x-for="frame in data.timeframes" :key="frame.key">
                    <button type="button"
                            @click="selectedKey = frame.key"
                            role="tab"
                            :aria-selected="selectedKey === frame.key"
                            :tabindex="selectedKey === frame.key ? 0 : -1"
                            class="panel-interactive flex flex-col justify-between p-4 text-left min-h-32 focus:outline-none transition-all"
                            :class="selectedKey === frame.key
                                ? 'ring-2 ring-amber-300/80 bg-[#121722] border-amber-300/40 shadow-[0_0_15px_rgba(251,191,36,0.15)]'
                                : 'hover:border-white/20'">
                        <div class="flex items-center justify-between w-full">
                            <span class="text-sm font-bold tracking-tight text-white" x-text="frame.key"></span>
                            <span x-show="frame.stale" class="rounded bg-amber-400/20 px-1.5 py-0.5 text-[0.62rem] font-semibold text-amber-300 uppercase">Stale</span>
                            <span x-show="!frame.stale && selectedKey === frame.key" class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                        </div>
                        <div class="my-2">
                            <p class="text-xl font-bold tabular-nums text-white" x-text="(frame.score > 0 ? '+' : '') + frame.score"></p>
                            <p class="mt-0.5 text-xs font-semibold"
                               :class="biasTone(frame.bias) === 'bullish' ? 'text-emerald-400' : (biasTone(frame.bias) === 'bearish' ? 'text-rose-400' : 'text-amber-300')"
                               x-text="frame.bias"></p>
                        </div>
                        <span class="text-[0.68rem] text-slate-400 block border-t border-white/[0.06] pt-1.5" x-text="frame.label"></span>
                    </button>
                </template>
            </div>
            <p x-show="!data.timeframes?.length" class="panel p-6 text-sm text-slate-400 text-center">Awaiting timeframe snapshot data from market provider.</p>
        </section>

        <!-- Chart and Technical Evidence Grid -->
        <section class="grid gap-5 lg:grid-cols-[1.4fr_0.6fr]">
            <!-- TradingView Chart Container -->
            <article class="panel overflow-hidden">
                <div class="flex items-center justify-between border-b border-white/[0.07] px-5 py-3.5 bg-white/[0.01]">
                    <div>
                        <p class="eyebrow text-amber-300/90">Market Chart</p>
                        <h2 class="text-base font-semibold text-white">XAU/USD Live Technicals</h2>
                    </div>
                    <span class="text-[0.68rem] text-slate-400">Independent TradingView Display</span>
                </div>

                <!-- Responsive Chart Embed -->
                <div id="tradingview-chart" class="tradingview-widget-container relative h-[400px] sm:h-[480px] lg:h-[560px] w-full bg-[#0d1118]">
                    <div class="tradingview-widget-container__widget h-[calc(100%-32px)] w-full"></div>
                    <div class="tradingview-widget-copyright px-3 py-1.5 text-[11px] bg-[#07090d]/80 border-t border-white/[0.05]">
                        <a href="https://www.tradingview.com/symbols/XAUUSD/" rel="noopener nofollow" target="_blank" class="text-amber-300 hover:underline">XAUUSD chart</a>
                        <span class="text-slate-400"> provided by TradingView</span>
                    </div>
                    <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js" async>
                    {"autosize":true,"symbol":"OANDA:XAUUSD","interval":"60","timezone":"Etc/UTC","theme":"dark","style":"1","locale":"en","allow_symbol_change":false,"calendar":false,"support_host":"https://www.tradingview.com","hide_side_toolbar":false,"withdateranges":true,"save_image":false}
                    </script>
                    <!-- Fallback if chart is blocked -->
                    <div x-show="chartIssue" x-cloak class="absolute inset-0 grid place-items-center bg-[#0d1118] p-6 text-center">
                        <div class="max-w-sm space-y-2">
                            <span class="text-2xl">📊</span>
                            <p class="font-semibold text-white text-base">Chart display paused</p>
                            <p class="text-xs text-slate-400 leading-relaxed">External chart embeds may be restricted by your network or browser privacy settings. Technical bias scoring operates independently and remains fully functional.</p>
                        </div>
                    </div>
                </div>
            </article>

            <!-- Technical Evidence Detail Panel -->
            <aside class="panel p-5 sm:p-6" aria-live="polite" aria-label="Technical Evidence for Selected Timeframe">
                <template x-if="selected">
                    <div class="space-y-6">
                        <!-- Evidence Header -->
                        <div class="flex items-start justify-between border-b border-white/[0.08] pb-4">
                            <div>
                                <p class="eyebrow text-amber-300/90">Detailed Audit</p>
                                <h2 class="mt-1 text-xl font-bold text-white"><span x-text="selected.label"></span> Evidence</h2>
                                <span class="text-xs text-slate-400" x-text="'Horizon: ' + selected.key"></span>
                            </div>
                            <span class="rounded-lg border px-3 py-1 text-xs font-bold"
                                  :class="biasBadgeClass(selected.bias)"
                                  x-text="selected.bias"></span>
                        </div>

                        <!-- Pillar Breakdown Scores -->
                        <div>
                            <span class="eyebrow text-slate-400 block mb-2">Score Components</span>
                            <div class="space-y-2">
                                <template x-for="(val, compKey) in selected.component_scores" :key="compKey">
                                    <div class="metric-row">
                                        <span class="text-xs font-medium capitalize text-slate-300" x-text="compKey"></span>
                                        <span class="text-xs font-bold tabular-nums"
                                              :class="val > 0 ? 'text-emerald-400' : (val < 0 ? 'text-rose-400' : 'text-slate-400')"
                                              x-text="(val > 0 ? '+' : '') + val"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Categorized Technical Metrics Grid -->
                        <div>
                            <span class="eyebrow text-slate-400 block mb-2">Indicator Readings</span>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <div class="rounded-xl border border-white/[0.05] bg-white/[0.025] p-2.5">
                                    <span class="block text-[0.62rem] uppercase tracking-wider text-slate-400">RSI 14</span>
                                    <strong class="mt-0.5 block text-sm font-semibold tabular-nums text-white" x-text="formatNumber(selected.metrics.rsi14)"></strong>
                                </div>
                                <div class="rounded-xl border border-white/[0.05] bg-white/[0.025] p-2.5">
                                    <span class="block text-[0.62rem] uppercase tracking-wider text-slate-400">ADX 14</span>
                                    <strong class="mt-0.5 block text-sm font-semibold tabular-nums text-white" x-text="formatNumber(selected.metrics.adx14)"></strong>
                                </div>
                                <div class="rounded-xl border border-white/[0.05] bg-white/[0.025] p-2.5">
                                    <span class="block text-[0.62rem] uppercase tracking-wider text-slate-400">ATR 14</span>
                                    <strong class="mt-0.5 block text-sm font-semibold tabular-nums text-white" x-text="formatNumber(selected.metrics.atr14)"></strong>
                                </div>
                                <div class="rounded-xl border border-white/[0.05] bg-white/[0.025] p-2.5">
                                    <span class="block text-[0.62rem] uppercase tracking-wider text-slate-400">ROC 10</span>
                                    <strong class="mt-0.5 block text-sm font-semibold tabular-nums text-white" x-text="formatNumber(selected.metrics.roc10) + '%'"></strong>
                                </div>
                                <div class="rounded-xl border border-white/[0.05] bg-white/[0.025] p-2.5">
                                    <span class="block text-[0.62rem] uppercase tracking-wider text-slate-400">EMA 20</span>
                                    <strong class="mt-0.5 block text-sm font-semibold tabular-nums text-white" x-text="formatNumber(selected.metrics.ema20)"></strong>
                                </div>
                                <div class="rounded-xl border border-white/[0.05] bg-white/[0.025] p-2.5">
                                    <span class="block text-[0.62rem] uppercase tracking-wider text-slate-400">EMA 200</span>
                                    <strong class="mt-0.5 block text-sm font-semibold tabular-nums text-white" x-text="formatNumber(selected.metrics.ema200)"></strong>
                                </div>
                            </div>
                        </div>

                        <!-- Qualitative Explanations -->
                        <div>
                            <span class="eyebrow text-slate-400 block mb-2">Conditions Summary</span>
                            <ul class="space-y-2">
                                <template x-for="reason in selected.explanations" :key="reason">
                                    <li class="flex items-start gap-2.5 text-xs leading-relaxed text-slate-300">
                                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                                        <span x-text="reason"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <div class="border-t border-white/[0.06] pt-3 text-[0.7rem] text-slate-400">
                            Completed candle: <span class="text-slate-300 font-medium" x-text="formatTime(selected.data_as_of)"></span>
                        </div>
                    </div>
                </template>
                <div x-show="!selected" class="grid place-items-center h-48 text-center p-6 text-sm text-slate-400">
                    <p>Select any timeframe horizon above to inspect its component scores and indicators.</p>
                </div>
            </aside>
        </section>

        <!-- AI Macro Context Section (Separately Scored) -->
        <section class="panel p-6 sm:p-8" aria-labelledby="macro-heading">
            <div class="grid gap-8 lg:grid-cols-[0.45fr_0.55fr]">
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-xs text-slate-300">
                        <span class="status-dot text-amber-300"></span>
                        <span>AI Grounded Context · Independent from Technicals</span>
                    </div>
                    <h2 id="macro-heading" class="text-2xl font-bold text-white tracking-tight">Macroeconomic Brief</h2>
                    <div class="flex items-center gap-2">
                        <span class="rounded-lg border px-3 py-1 text-xs font-bold capitalize"
                              :class="biasBadgeClass(data.macro.stance)"
                              x-text="data.macro.stance + ' Stance'"></span>
                        <span class="rounded-lg border border-white/10 bg-white/[0.02] px-2.5 py-1 text-xs font-semibold capitalize text-slate-300">
                            <span x-text="data.macro.risk_level"></span> Risk
                        </span>
                    </div>
                    <p class="text-sm leading-relaxed text-slate-300" x-text="data.macro.summary"></p>
                    <p class="text-[0.72rem] text-slate-400">
                        Updated: <span x-text="formatTime(data.macro.generated_at)"></span>
                    </p>
                </div>

                <!-- Verified Macro Events Grid -->
                <div class="grid gap-3 sm:grid-cols-2">
                    <template x-for="event in data.macro.events" :key="event.source_url">
                        <a :href="event.source_url" target="_blank" rel="noopener noreferrer"
                           class="panel-interactive flex flex-col justify-between p-4 group">
                            <div>
                                <div class="flex items-center justify-between text-[0.68rem] text-slate-400">
                                    <span class="font-semibold uppercase tracking-wider text-slate-400" x-text="event.source_name"></span>
                                    <span class="font-bold capitalize"
                                          :class="biasTone(event.direction) === 'bullish' ? 'text-emerald-400' : (biasTone(event.direction) === 'bearish' ? 'text-rose-400' : 'text-amber-300')"
                                          x-text="event.direction"></span>
                                </div>
                                <h3 class="mt-2 text-xs font-semibold leading-snug text-white group-hover:text-amber-200 transition-colors" x-text="event.headline"></h3>
                                <p class="mt-2 text-[0.75rem] leading-relaxed text-slate-400" x-text="event.why_it_matters"></p>
                            </div>
                            <span class="mt-3 block text-[0.68rem] font-medium text-amber-300/80 group-hover:underline">
                                Read Source ↗
                            </span>
                        </a>
                    </template>
                    <div x-show="!data.macro.events?.length" class="col-span-full grid min-h-36 place-items-center rounded-xl border border-dashed border-white/10 bg-white/[0.01] p-6 text-center">
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-slate-300">No verified event cards available</p>
                            <p class="text-xs text-slate-400">Configure Gemini search grounding for real-time news citations.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Transparency & Mandatory Legal Disclaimer -->
        <section class="grid gap-5 lg:grid-cols-2">
            <article class="panel p-6 sm:p-7 space-y-3">
                <p class="eyebrow text-amber-300/90">Scoring Integrity</p>
                <h2 class="text-lg font-semibold text-white">How Horizon Scores Work</h2>
                <p class="text-xs leading-relaxed text-slate-300">
                    Each horizon computes trend alignment, Wilder momentum, confirmed 5-candle market structure pivots, and Donchian channel breakouts. Higher timeframes receive greater composite weighting. AI macroeconomic context is displayed separately and cannot alter the quantitative technical score.
                </p>
            </article>

            <article class="rounded-2xl border border-amber-300/20 bg-amber-300/[0.035] p-6 sm:p-7 space-y-3">
                <div class="flex items-center gap-2">
                    <span class="text-amber-300 text-xs font-bold uppercase tracking-wider">⚠️ Educational Risk Notice</span>
                </div>
                <h2 class="text-lg font-semibold text-amber-100">Context, Never a Command</h2>
                <p class="text-xs leading-relaxed text-amber-100/70">
                    HorizonBias provides educational market context and technical bias only. It is not financial advice, a trading signal, or a recommendation to buy or sell. Market and AI-generated information may be delayed, incomplete, or inaccurate. Independently verify all information and make your own risk decisions.
                </p>
            </article>
        </section>
    </main>

    <!-- Footer -->
    <footer class="mt-8 border-t border-white/[0.07] bg-[#07090d]/80 py-8 text-xs text-slate-400">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div class="space-y-1">
                <p class="font-medium text-slate-300">© {{ date('Y') }} HorizonBias · Dedicated to XAU/USD Decision Support</p>
                <p class="text-[0.7rem] text-slate-400">Closed bar calculations · UTC timestamps · No execution</p>
            </div>
            <div class="flex items-center gap-6">
                <a href="{{ url('/') }}" class="hover:text-amber-200">← Overview & Methodology</a>
                <a href="#overview" class="hover:text-amber-200">Back to Top ↑</a>
            </div>
        </div>
    </footer>
</body>
</html>
