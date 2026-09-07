<!DOCTYPE html>
<html lang="en" class="bg-[#07090d]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Auditable multi-timeframe technical bias and macro context for XAU/USD.">
    <link rel="icon" type="image/png" href="{{ asset('images/horizonbias-logo.png') }}">
    <title>HorizonBias — XAU/USD Market Context</title>
    <script type="application/json" id="dashboard-data">{!! json_encode($dashboard, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="horizonDashboard" x-init="init()" class="min-h-screen overflow-x-hidden">
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-48 left-1/3 h-[30rem] w-[30rem] rounded-full bg-amber-300/[0.045] blur-3xl"></div>
        <div class="absolute right-0 top-1/3 h-80 w-80 rounded-full bg-emerald-400/[0.025] blur-3xl"></div>
    </div>

    <header class="border-b border-white/[0.07] bg-[#07090d]/85 backdrop-blur-xl">
        <div class="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <a href="#overview" class="group flex items-center gap-3" aria-label="HorizonBias home">
                <span class="relative grid h-10 w-10 place-items-center overflow-hidden rounded-xl border border-amber-300/25 bg-amber-300/[0.06] p-0.5">
                    <img src="{{ asset('images/horizonbias-logo.png') }}" alt="" class="h-full w-full object-contain drop-shadow-[0_0_8px_rgba(251,191,36,0.22)]" width="40" height="40" decoding="async">
                </span>
                <span><strong class="block text-base tracking-tight">Horizon<span class="text-amber-200">Bias</span></strong><small class="block text-[0.62rem] uppercase tracking-[0.24em] text-slate-500">Gold intelligence</small></span>
            </a>
            <div class="flex items-center gap-2">
                <span class="hidden rounded-full border border-white/10 px-3 py-1.5 text-xs text-slate-400 sm:inline">XAU / USD only</span>
                <span class="rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-wider" :class="data.mode === 'live' ? 'bias-bullish' : 'bias-neutral'">
                    <span class="status-dot mr-1.5"></span><span x-text="data.mode"></span>
                </span>
            </div>
        </div>
    </header>

    <div x-show="data.notice || connectionIssue" x-cloak class="border-b border-amber-300/15 bg-amber-300/[0.06]">
        <div class="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-4 py-2.5 text-xs text-amber-100/80 sm:px-6 lg:px-8">
            <p><span class="status-dot mr-2 text-amber-300"></span><span x-text="connectionIssue ? 'Connection interrupted — retaining the last displayed snapshot.' : data.notice"></span></p>
            <button type="button" @click="refresh()" class="font-semibold text-amber-200 hover:text-amber-100" :disabled="refreshing" x-text="refreshing ? 'Refreshing…' : 'Refresh'"></button>
        </div>
    </div>

    <main id="overview" class="mx-auto max-w-[1440px] space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <section class="grid gap-5 lg:grid-cols-[1.35fr_.65fr]">
            <article class="panel relative overflow-hidden p-6 sm:p-8">
                <div aria-hidden="true" class="absolute right-0 top-0 h-56 w-56 translate-x-1/3 -translate-y-1/3 rounded-full bg-amber-200/[0.06] blur-3xl"></div>
                <div class="relative grid gap-7 sm:grid-cols-2 sm:items-end">
                    <div>
                        <p class="eyebrow">Spot gold · US dollar</p>
                        <div class="mt-3 flex items-baseline gap-2"><h1 class="text-3xl font-semibold tracking-[-0.04em] sm:text-5xl">XAU/USD</h1><span class="text-sm text-slate-500">USD</span></div>
                        <div class="mt-6 flex flex-wrap items-end gap-x-5 gap-y-2">
                            <p class="text-4xl font-medium tabular-nums tracking-[-0.04em]" x-text="data.quote.price == null ? '—' : '$' + formatNumber(data.quote.price, 2)"></p>
                            <p x-show="data.quote.change != null" class="pb-1 text-sm tabular-nums" :class="data.quote.change >= 0 ? 'text-emerald-300' : 'text-rose-300'">
                                <span x-text="(data.quote.change >= 0 ? '+' : '') + formatNumber(data.quote.change)"></span>
                                <span x-text="'(' + (data.quote.change_percent >= 0 ? '+' : '') + formatNumber(data.quote.change_percent) + '%)'" class="ml-1"></span>
                            </p>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Data as of <span x-text="formatTime(data.quote.as_of)"></span></p>
                    </div>
                    <div class="sm:border-l sm:border-white/[0.08] sm:pl-8">
                        <p class="eyebrow">Weighted technical bias</p>
                        <template x-if="data.overall">
                            <div>
                                <div class="mt-3 flex items-center gap-3"><span class="rounded-lg border px-3 py-1.5 text-sm font-semibold" :class="'bias-' + biasTone(data.overall.label)" x-text="data.overall.label"></span><span class="text-2xl font-semibold tabular-nums" x-text="(data.overall.score > 0 ? '+' : '') + data.overall.score"></span><span class="text-xs text-slate-600">/ 100</span></div>
                                <p class="mt-4 max-w-md text-sm leading-6 text-slate-400" x-text="data.overall.summary"></p>
                            </div>
                        </template>
                        <p x-show="!data.overall" class="mt-3 text-sm leading-6 text-slate-400">Waiting for enough valid higher-timeframe snapshots.</p>
                    </div>
                </div>
            </article>

            <aside class="panel flex flex-col justify-between p-6">
                <div><p class="eyebrow">What this measures</p><h2 class="mt-3 text-xl font-semibold tracking-tight">Structure before speculation.</h2><p class="mt-3 text-sm leading-6 text-slate-400">A transparent blend of trend, momentum, confirmed pivots, and range behavior—calculated only from completed candles.</p></div>
                <div class="mt-6 grid grid-cols-2 gap-3 text-xs"><div class="rounded-xl bg-white/[0.035] p-3"><span class="block text-slate-500">Coverage</span><strong class="mt-1 block text-slate-200">7 timeframes</strong></div><div class="rounded-xl bg-white/[0.035] p-3"><span class="block text-slate-500">Execution</span><strong class="mt-1 block text-slate-200">None</strong></div></div>
            </aside>
        </section>

        <section aria-labelledby="timeframe-heading">
            <div class="mb-3 flex items-end justify-between"><div><p class="eyebrow">Multi-timeframe map</p><h2 id="timeframe-heading" class="mt-1 text-xl font-semibold">Bias by horizon</h2></div><p class="hidden text-xs text-slate-600 sm:block">Select a card for its evidence</p></div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
                <template x-for="frame in data.timeframes" :key="frame.key">
                    <button type="button" @click="selectedKey = frame.key" class="panel group min-h-32 p-4 text-left transition hover:-translate-y-0.5 hover:border-white/15" :class="selectedKey === frame.key ? 'ring-1 ring-amber-200/50' : ''" :aria-pressed="selectedKey === frame.key">
                        <div class="flex items-center justify-between"><span class="text-sm font-semibold text-slate-200" x-text="frame.key"></span><span x-show="frame.stale" class="text-[0.6rem] uppercase tracking-wider text-amber-300">Stale</span></div>
                        <p class="mt-6 text-xl font-semibold tabular-nums" x-text="(frame.score > 0 ? '+' : '') + frame.score"></p>
                        <p class="mt-1 text-xs font-medium" :class="biasTone(frame.bias) === 'bullish' ? 'text-emerald-300' : (biasTone(frame.bias) === 'bearish' ? 'text-rose-300' : 'text-amber-200')" x-text="frame.bias"></p>
                    </button>
                </template>
            </div>
            <p x-show="!data.timeframes?.length" class="panel mt-3 p-6 text-sm text-slate-400">No timeframe snapshots are available yet.</p>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.45fr_.55fr]">
            <article class="panel overflow-hidden">
                <div class="flex items-center justify-between border-b border-white/[0.07] px-5 py-4"><div><p class="eyebrow">Live market display</p><h2 class="mt-1 font-semibold">XAU/USD chart</h2></div><span class="text-[0.65rem] text-slate-600">Chart data is separate from bias calculations</span></div>
                <div id="tradingview-chart" class="tradingview-widget-container relative h-[560px] w-full bg-[#0d1118]">
                    <div class="tradingview-widget-container__widget h-[calc(100%-32px)] w-full"></div>
                    <div class="tradingview-widget-copyright px-3 py-1 text-[11px]"><a href="https://www.tradingview.com/symbols/XAUUSD/" rel="noopener nofollow" target="_blank" class="text-sky-400">XAUUSD chart</a><span class="text-slate-500"> by TradingView</span></div>
                    <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js" async>
                    {"autosize":true,"symbol":"OANDA:XAUUSD","interval":"60","timezone":"Etc/UTC","theme":"dark","style":"1","locale":"en","allow_symbol_change":false,"calendar":false,"support_host":"https://www.tradingview.com","hide_side_toolbar":false,"withdateranges":true,"save_image":false}
                    </script>
                    <div x-show="chartIssue" x-cloak class="absolute inset-0 grid place-items-center bg-[#0d1118]"><div class="max-w-sm text-center"><p class="font-semibold">Chart could not be loaded</p><p class="mt-2 text-sm text-slate-500">Check the network or content-blocking settings. Bias data remains available independently.</p></div></div>
                </div>
            </article>

            <aside class="panel p-5" aria-live="polite">
                <template x-if="selected"><div>
                    <div class="flex items-start justify-between"><div><p class="eyebrow">Technical evidence</p><h2 class="mt-1 text-xl font-semibold"><span x-text="selected.label"></span> view</h2></div><span class="rounded-lg border px-2.5 py-1 text-xs font-semibold" :class="'bias-' + biasTone(selected.bias)" x-text="selected.bias"></span></div>
                    <div class="mt-6"><template x-for="(value, key) in selected.component_scores" :key="key"><div class="metric-row"><span class="text-sm capitalize text-slate-400" x-text="key"></span><strong class="text-sm tabular-nums" :class="value > 0 ? 'text-emerald-300' : (value < 0 ? 'text-rose-300' : 'text-slate-300')" x-text="(value > 0 ? '+' : '') + value"></strong></div></template></div>
                    <div class="mt-6 grid grid-cols-2 gap-2"><template x-for="metric in [['RSI 14','rsi14'],['ADX 14','adx14'],['ATR 14','atr14'],['ROC 10','roc10'],['EMA 20','ema20'],['EMA 200','ema200']]" :key="metric[1]"><div class="rounded-xl bg-white/[0.035] p-3"><span class="block text-[0.65rem] uppercase tracking-wider text-slate-600" x-text="metric[0]"></span><strong class="mt-1 block text-sm tabular-nums" x-text="formatNumber(selected.metrics[metric[1]])"></strong></div></template></div>
                    <ul class="mt-6 space-y-2"><template x-for="reason in selected.explanations" :key="reason"><li class="flex gap-2 text-xs leading-5 text-slate-400"><span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-amber-200/70"></span><span x-text="reason"></span></li></template></ul>
                    <p class="mt-5 border-t border-white/[0.06] pt-4 text-[0.65rem] text-slate-600">Completed candle: <span x-text="formatTime(selected.data_as_of)"></span></p>
                </div></template>
                <p x-show="!selected" class="text-sm text-slate-400">Select an available timeframe to inspect its evidence.</p>
            </aside>
        </section>

        <section class="panel p-6 sm:p-8" aria-labelledby="macro-heading">
            <div class="grid gap-8 lg:grid-cols-[.55fr_1.45fr]">
                <div><p class="eyebrow">AI-assisted · separately scored</p><h2 id="macro-heading" class="mt-2 text-2xl font-semibold">Macro & event context</h2><div class="mt-4 flex gap-2"><span class="rounded-lg border px-2.5 py-1 text-xs font-semibold capitalize" :class="'bias-' + biasTone(data.macro.stance)" x-text="data.macro.stance"></span><span class="rounded-lg border border-white/10 px-2.5 py-1 text-xs capitalize text-slate-400"><span x-text="data.macro.risk_level"></span> risk</span></div><p class="mt-5 text-sm leading-7 text-slate-400" x-text="data.macro.summary"></p><p class="mt-4 text-[0.65rem] text-slate-600">Updated <span x-text="formatTime(data.macro.generated_at)"></span></p></div>
                <div class="grid gap-3 sm:grid-cols-2"><template x-for="event in data.macro.events" :key="event.source_url"><a :href="event.source_url" target="_blank" rel="noopener noreferrer" class="rounded-xl border border-white/[0.07] bg-white/[0.025] p-4 transition hover:border-white/15"><div class="flex items-center justify-between gap-3"><span class="text-[0.65rem] uppercase tracking-wider text-slate-500" x-text="event.source_name"></span><span class="text-[0.65rem] capitalize" :class="biasTone(event.direction) === 'bullish' ? 'text-emerald-300' : (biasTone(event.direction) === 'bearish' ? 'text-rose-300' : 'text-amber-200')" x-text="event.direction"></span></div><h3 class="mt-3 text-sm font-semibold leading-5" x-text="event.headline"></h3><p class="mt-2 text-xs leading-5 text-slate-500" x-text="event.why_it_matters"></p></a></template><div x-show="!data.macro.events?.length" class="col-span-full grid min-h-36 place-items-center rounded-xl border border-dashed border-white/10 text-center"><div><p class="text-sm text-slate-400">No verified event cards available</p><p class="mt-1 text-xs text-slate-600">Configure Gemini grounding for sourced current context.</p></div></div></div>
            </div>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <article class="panel p-6"><p class="eyebrow">Methodology</p><h2 class="mt-2 text-lg font-semibold">How the bias is built</h2><p class="mt-3 text-sm leading-6 text-slate-400">Each horizon scores trend alignment, Wilder-smoothed momentum, confirmed five-candle pivots, and 20-candle breakouts. Higher timeframes receive more weight. AI context is displayed separately and cannot change the technical result.</p></article>
            <article class="rounded-2xl border border-amber-300/15 bg-amber-300/[0.045] p-6"><p class="eyebrow text-amber-200/60">Risk notice</p><h2 class="mt-2 text-lg font-semibold text-amber-50">Context, never a command.</h2><p class="mt-3 text-sm leading-6 text-amber-50/60">HorizonBias provides educational market context and technical bias only. It is not financial advice, a trading signal, or a recommendation to buy or sell. Market and AI-generated information may be delayed, incomplete, or inaccurate. Independently verify all information and make your own risk decisions.</p></article>
        </section>
    </main>

    <footer class="mt-4 border-t border-white/[0.07]"><div class="mx-auto flex max-w-[1440px] flex-col gap-2 px-4 py-6 text-xs text-slate-600 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"><p>© {{ date('Y') }} HorizonBias · Educational analysis for XAU/USD</p><p>UTC timestamps · No accounts · No execution</p></div></footer>
</body>
</html>
