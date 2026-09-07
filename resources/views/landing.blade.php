<!DOCTYPE html>
<html lang="en" class="scroll-smooth bg-[#07090d]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HorizonBias provides auditable multi-timeframe technical bias and macroeconomic context dedicated exclusively to XAU/USD spot gold.">
    <link rel="icon" type="image/png" href="{{ asset('images/horizonbias-logo.png') }}">
    <title>HorizonBias — Multi-Timeframe XAU/USD Market Context</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="horizonLanding" class="min-h-screen w-full max-w-full overflow-x-hidden bg-[#07090d] text-slate-100 antialiased selection:bg-amber-300/30 selection:text-amber-100">
    <!-- Ambient gold glow backgrounds -->
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10 overflow-hidden max-w-full">
        <div class="absolute -top-40 left-1/2 h-[34rem] w-[34rem] -translate-x-1/2 rounded-full bg-amber-400/[0.04] blur-[120px]"></div>
        <div class="absolute top-1/3 right-0 h-[28rem] w-[28rem] rounded-full bg-amber-300/[0.02] blur-[140px]"></div>
        <div class="absolute bottom-1/4 left-0 h-[24rem] w-[24rem] rounded-full bg-emerald-400/[0.015] blur-[130px]"></div>
    </div>

    <!-- Header Navigation -->
    <header class="sticky top-0 z-50 border-b border-white/[0.07] bg-[#07090d]/90 backdrop-blur-xl transition-all">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">
            <!-- Brand Logo -->
            <a href="{{ url('/') }}" class="group flex items-center gap-2.5 sm:gap-3" aria-label="HorizonBias home">
                <span class="relative grid h-9 w-9 sm:h-10 sm:w-10 place-items-center overflow-hidden rounded-xl border border-amber-300/30 bg-gradient-to-b from-amber-300/10 to-transparent p-1 transition-all duration-300 group-hover:border-amber-300/50 group-hover:shadow-[0_0_12px_rgba(251,191,36,0.25)] shrink-0">
                    <img src="{{ asset('images/horizonbias-logo.png') }}" alt="" class="h-full w-full object-contain" width="40" height="40" decoding="async">
                </span>
                <div>
                    <span class="block text-sm sm:text-base font-semibold tracking-tight text-white leading-none">Horizon<span class="text-amber-300">Bias</span></span>
                    <span class="hidden sm:block text-[0.62rem] font-semibold uppercase tracking-[0.24em] text-slate-400 mt-1">XAU/USD Context</span>
                </div>
            </a>

            <!-- Desktop Nav Links -->
            <nav class="hidden md:flex items-center gap-8 text-sm" aria-label="Main Navigation">
                <a href="#timeframes" class="text-slate-300 transition hover:text-amber-200">Timeframes</a>
                <a href="#methodology" class="text-slate-300 transition hover:text-amber-200">Methodology</a>
                <a href="#separation" class="text-slate-300 transition hover:text-amber-200">AI Separation</a>
                <a href="#boundaries" class="text-slate-300 transition hover:text-amber-200">Boundaries</a>
                <a href="#risk-notice" class="text-slate-400 transition hover:text-amber-200">Risk Notice</a>
            </nav>

            <!-- Actions -->
            <div class="flex items-center gap-1.5 sm:gap-3">
                <span class="hidden lg:inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/[0.02] px-3 py-1 text-xs text-slate-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span> Spot Gold Only
                </span>
                <a href="{{ route('dashboard') }}" class="btn-gold !px-2.5 !py-1 text-xs sm:!px-5 sm:!py-2.5 sm:text-sm shrink-0" id="header-cta">
                    <span class="hidden sm:inline">Open </span><span>Dashboard</span>
                    <svg class="h-3 w-3 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>

                <!-- Mobile menu toggle -->
                <button type="button" @click="toggleMobileMenu()" class="md:hidden inline-flex items-center justify-center p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/[0.05] focus:outline-none shrink-0" :aria-expanded="mobileMenuOpen" aria-label="Toggle navigation menu">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path x-show="mobileMenuOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Drawer -->
        <div x-show="mobileMenuOpen" x-cloak class="md:hidden border-b border-white/[0.08] bg-[#0d1118]/98 px-4 py-5 backdrop-blur-xl">
            <nav class="flex flex-col gap-3 text-sm" aria-label="Mobile Navigation">
                <a href="#timeframes" @click="closeMobileMenu()" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-white/[0.04]">7-Timeframe Matrix</a>
                <a href="#methodology" @click="closeMobileMenu()" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-white/[0.04]">Technical Methodology</a>
                <a href="#separation" @click="closeMobileMenu()" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-white/[0.04]">AI & Score Separation</a>
                <a href="#boundaries" @click="closeMobileMenu()" class="rounded-lg px-3 py-2 text-slate-200 hover:bg-white/[0.04]">Product Boundaries</a>
                <a href="#risk-notice" @click="closeMobileMenu()" class="rounded-lg px-3 py-2 text-amber-200/80 hover:bg-white/[0.04]">Risk Notice & Disclaimer</a>
                <div class="mt-2 border-t border-white/[0.08] pt-3">
                    <a href="{{ route('dashboard') }}" class="btn-gold w-full text-center">Open Dashboard</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="space-y-24 sm:space-y-32">
        <!-- Hero Section -->
        <section class="relative overflow-hidden pt-12 pb-16 sm:pt-20 sm:pb-24 lg:pt-28 lg:pb-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid items-center gap-12 lg:grid-cols-[1.1fr_0.9fr]">
                    <div class="space-y-6 text-left">
                        <div class="inline-flex items-center gap-2 rounded-full border border-amber-300/25 bg-amber-300/[0.06] px-3.5 py-1.5 text-xs font-medium text-amber-200">
                            <span class="status-dot text-amber-300"></span>
                            <span>Dedicated XAU/USD Decision Support</span>
                        </div>
                        <h1 class="text-2xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-white leading-snug sm:leading-tight">
                            Multi-Timeframe<br class="sm:hidden">
                            <span class="gold-gradient-text">Technical Bias</span><br class="sm:hidden">
                            for Spot Gold.
                        </h1>
                        <p class="max-w-2xl text-base leading-relaxed text-slate-300 sm:text-lg">
                            HorizonBias synthesizes completed candle market structure across seven horizons into a transparent, deterministic score. Objective technical conditions without noise, predictions, or black boxes.
                        </p>

                        <!-- Honesty boundary badge -->
                        <div class="rounded-xl border border-white/[0.08] bg-white/[0.02] p-4 text-xs leading-relaxed text-slate-400">
                            <strong class="font-medium text-amber-200">Transparent Context, Never Trade Signals:</strong>
                            HorizonBias computes directional alignment from closed candles. We provide objective market structure and technical conditions—never trade triggers, price targets, or execution parameters.
                        </div>

                        <!-- CTA Group -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 pt-2">
                            <a href="{{ route('dashboard') }}" class="btn-gold text-sm sm:text-base px-5 sm:px-6 py-2.5 sm:py-3 w-full sm:w-auto text-center" id="hero-primary-cta">
                                <span>Open Live Dashboard</span>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                            <a href="#methodology" class="btn-ghost text-sm sm:text-base px-5 sm:px-6 py-2.5 sm:py-3 w-full sm:w-auto text-center">
                                <span>Explore Methodology</span>
                            </a>
                        </div>

                        <!-- Key Pillars Micro-strip -->
                        <div class="grid grid-cols-3 gap-2 sm:gap-3 border-t border-white/[0.08] pt-5 text-xs text-slate-400">
                            <div>
                                <span class="block font-semibold text-slate-200 text-[0.75rem] sm:text-xs">7 Horizons</span>
                                <span class="text-[0.65rem] sm:text-[0.7rem] text-slate-400">5m to 1mo</span>
                            </div>
                            <div>
                                <span class="block font-semibold text-slate-200 text-[0.75rem] sm:text-xs">Deterministic</span>
                                <span class="text-[0.65rem] sm:text-[0.7rem] text-slate-400">100% auditable</span>
                            </div>
                            <div>
                                <span class="block font-semibold text-slate-200 text-[0.75rem] sm:text-xs">AI Separation</span>
                                <span class="text-[0.65rem] sm:text-[0.7rem] text-slate-400">Isolated context</span>
                            </div>
                        </div>
                    </div>

                    <!-- Hero Visual Card -->
                    <div class="relative">
                        <div class="panel-glow group overflow-hidden rounded-3xl border border-amber-300/30">
                            <!-- Background artwork -->
                            <div class="relative aspect-[16/10] w-full overflow-hidden bg-[#0d1118]">
                                <img src="{{ asset('images/gold-horizon-hero.jpg') }}" alt="" class="h-full w-full object-cover object-center opacity-85 transition-transform duration-700 group-hover:scale-105" loading="eager" decoding="async">
                                <div class="absolute inset-0 bg-gradient-to-t from-[#0d1118] via-[#0d1118]/40 to-transparent"></div>
                            </div>

                            <!-- Overlay Content on Hero Card -->
                            <div class="relative -mt-16 p-6 space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="status-dot text-emerald-400"></span>
                                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-300">Composite Bias System</span>
                                    </div>
                                    <span class="rounded-lg border border-amber-300/20 bg-amber-300/10 px-2.5 py-1 text-[0.68rem] font-semibold text-amber-200">Illustrative Preview</span>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-xl border border-white/[0.06] bg-[#07090d]/80 p-3.5 backdrop-blur-md">
                                        <span class="block text-[0.65rem] uppercase tracking-wider text-slate-400">Target Asset</span>
                                        <span class="mt-1 block text-lg font-semibold text-white">XAU / USD</span>
                                        <span class="text-xs text-slate-400">OANDA Spot Gold</span>
                                    </div>
                                    <div class="rounded-xl border border-white/[0.06] bg-[#07090d]/80 p-3.5 backdrop-blur-md">
                                        <span class="block text-[0.65rem] uppercase tracking-wider text-slate-400">Horizon Alignment</span>
                                        <span class="mt-1 block text-lg font-semibold text-emerald-300">+45 Bullish</span>
                                        <span class="text-xs text-slate-400">Weighted Consensus</span>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-white/[0.06] bg-[#07090d]/60 p-3 text-xs text-slate-400">
                                    <span class="text-amber-200/90 font-medium">Completed Candles Only:</span> Every calculation waits for the bar to close, preventing repaint artifacts.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Seven-Timeframe Preview Section -->
        <section id="timeframes" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 scroll-mt-24">
            <div class="text-center max-w-3xl mx-auto space-y-4">
                <p class="eyebrow text-amber-300/90">Multi-Timeframe Architecture</p>
                <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                    Seven Horizons. One Structured View.
                </h2>
                <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                    Single-timeframe analysis suffers from tunnel vision. HorizonBias calculates directional alignment across tactical, intermediate, and structural horizons with mathematically defined weights.
                </p>
                <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-xs text-slate-400">
                    <span class="text-amber-300">ℹ</span> Scores shown below are illustrative representations of the UI structure
                </div>
            </div>

            <!-- 7-Timeframe Grid -->
            <div class="mt-12 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                @php
                    $illustrativeCards = [
                        ['key' => '5m', 'label' => '5 Minutes', 'score' => '+20', 'bias' => 'Bullish', 'weight' => '5%', 'role' => 'Micro execution flow'],
                        ['key' => '15m', 'label' => '15 Minutes', 'score' => '+35', 'bias' => 'Bullish', 'weight' => '10%', 'role' => 'Short-term momentum'],
                        ['key' => '1h', 'label' => '1 Hour', 'score' => '+45', 'bias' => 'Bullish', 'weight' => '15%', 'role' => 'Intraday framework'],
                        ['key' => '4h', 'label' => '4 Hours', 'score' => '+65', 'bias' => 'Strong Bullish', 'weight' => '20%', 'role' => 'Swing anchor horizon'],
                        ['key' => '1d', 'label' => '1 Day', 'score' => '+55', 'bias' => 'Bullish', 'weight' => '25%', 'role' => 'Macro daily structure'],
                        ['key' => '1w', 'label' => '1 Week', 'score' => '+25', 'bias' => 'Bullish', 'weight' => '15%', 'role' => 'Multi-week regime'],
                        ['key' => '1mo', 'label' => '1 Month', 'score' => '+10', 'bias' => 'Neutral', 'weight' => '10%', 'role' => 'Secular gold cycle'],
                    ];
                @endphp

                @foreach($illustrativeCards as $card)
                    <div class="panel-interactive flex flex-col justify-between p-4 {{ $card['key'] === '4h' ? 'ring-1 ring-amber-300/40 bg-[#121620]' : '' }}">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-white tracking-tight">{{ $card['key'] }}</span>
                                <span class="rounded bg-white/[0.05] px-1.5 py-0.5 text-[0.62rem] font-medium text-slate-400">Wt. {{ $card['weight'] }}</span>
                            </div>
                            <span class="mt-1 block text-[0.68rem] text-slate-400">{{ $card['label'] }}</span>
                        </div>

                        <div class="my-4">
                            <span class="text-2xl font-bold tabular-nums {{ str_contains($card['bias'], 'Bullish') ? 'text-emerald-300' : (str_contains($card['bias'], 'Bearish') ? 'text-rose-300' : 'text-amber-200') }}">
                                {{ $card['score'] }}
                            </span>
                            <span class="mt-1 block text-xs font-semibold {{ str_contains($card['bias'], 'Bullish') ? 'text-emerald-400' : (str_contains($card['bias'], 'Bearish') ? 'text-rose-400' : 'text-amber-300') }}">
                                {{ $card['bias'] }}
                            </span>
                        </div>

                        <p class="border-t border-white/[0.06] pt-2 text-[0.68rem] leading-4 text-slate-400">
                            {{ $card['role'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- Technical Methodology Section -->
        <section id="methodology" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 scroll-mt-24">
            <div class="grid gap-12 lg:grid-cols-[0.85fr_1.15fr] lg:items-center">
                <div class="space-y-5">
                    <p class="eyebrow text-amber-300/90">Four-Pillar Deterministic Model</p>
                    <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                        How Technical Bias Is Calculated.
                    </h2>
                    <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                        Rather than relying on uninterpretable neural networks or black-box algorithms, HorizonBias utilizes four classic, audited technical pillars evaluated strictly against completed candles.
                    </p>
                    <div class="space-y-3 pt-2">
                        <div class="flex items-start gap-3">
                            <span class="mt-1 h-2 w-2 rounded-full bg-amber-400 shrink-0"></span>
                            <p class="text-xs text-slate-300"><strong class="text-white">Strict Completed Candle Logic:</strong> Calculations never alter after a candle closes. Real-time tick fluctuations do not skew historical scores.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="mt-1 h-2 w-2 rounded-full bg-amber-400 shrink-0"></span>
                            <p class="text-xs text-slate-300"><strong class="text-white">Continuous Normalization:</strong> Scores range from -100 (Maximum Bearish Agreement) to +100 (Maximum Bullish Agreement).</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Pillar 1 -->
                    <div class="panel p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-amber-300">Pillar 1</span>
                            <span class="text-[0.68rem] text-slate-400">Up to 35 pts</span>
                        </div>
                        <h3 class="text-base font-semibold text-white">Trend Alignment</h3>
                        <p class="text-xs leading-relaxed text-slate-300">
                            Exponential Moving Averages (EMA 20, 50, 200) stacked in order. Price relation to key dynamic averages determines base regime strength.
                        </p>
                    </div>

                    <!-- Pillar 2 -->
                    <div class="panel p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-amber-300">Pillar 2</span>
                            <span class="text-[0.68rem] text-slate-400">Up to 25 pts</span>
                        </div>
                        <h3 class="text-base font-semibold text-white">Momentum Strength</h3>
                        <p class="text-xs leading-relaxed text-slate-300">
                            Wilder-smoothed RSI (14 period), MACD histogram trajectory, and 10-period Rate of Change (ROC) identify expansion and exhaustion.
                        </p>
                    </div>

                    <!-- Pillar 3 -->
                    <div class="panel p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-amber-300">Pillar 3</span>
                            <span class="text-[0.68rem] text-slate-400">Up to 25 pts</span>
                        </div>
                        <h3 class="text-base font-semibold text-white">Market Structure</h3>
                        <p class="text-xs leading-relaxed text-slate-300">
                            Rigorous five-candle confirmed swing pivots identify structural higher-highs or lower-lows without lookahead bias or repainting.
                        </p>
                    </div>

                    <!-- Pillar 4 -->
                    <div class="panel p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-amber-300">Pillar 4</span>
                            <span class="text-[0.68rem] text-slate-400">Up to 15 pts</span>
                        </div>
                        <h3 class="text-base font-semibold text-white">Breakouts & Volatility</h3>
                        <p class="text-xs leading-relaxed text-slate-300">
                            20-candle Donchian channel breakouts corroborated by Average True Range (ATR 14) and Average Directional Index (ADX 14).
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Technical and Macro Separation Section -->
        <section id="separation" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 scroll-mt-24">
            <div class="panel-glow p-8 sm:p-12">
                <div class="grid gap-8 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
                    <div class="space-y-4">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-300/30 bg-amber-300/10 px-3 py-1 text-xs font-semibold text-amber-200">
                            Independent Architectural Boundary
                        </span>
                        <h2 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                            Why AI Macro Context Never Touches the Technical Score.
                        </h2>
                        <p class="text-slate-300 text-sm leading-relaxed">
                            HorizonBias incorporates macroeconomic intelligence powered by Google Gemini with verified search grounding. However, by architectural mandate:
                        </p>
                        <ul class="space-y-2 text-xs sm:text-sm text-slate-300">
                            <li class="flex items-start gap-2">
                                <span class="text-amber-300 font-bold">✓</span>
                                <span><strong class="text-white">Zero Model Drift:</strong> The quantitative technical score is 100% deterministic and cannot be diluted or hallucinated by generative AI.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-amber-300 font-bold">✓</span>
                                <span><strong class="text-white">Sourced Event Citations:</strong> Macro summaries cite verifiable financial publications with outbound source links.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-amber-300 font-bold">✓</span>
                                <span><strong class="text-white">Dual Perspective:</strong> Compare the hard chart math against prevailing central bank policy and geopolitical headlines side by side.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-white/[0.08] bg-[#07090d]/90 p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-white/[0.08] pb-3">
                            <span class="text-xs uppercase tracking-wider text-slate-400">Architectural Isolation</span>
                            <span class="text-xs font-semibold text-emerald-400">Enforced</span>
                        </div>
                        <div class="space-y-3 text-xs">
                            <div class="rounded-xl border border-emerald-400/20 bg-emerald-400/[0.05] p-3">
                                <span class="font-semibold text-emerald-300 block">Technical Score Engine</span>
                                <span class="text-slate-400 text-[0.7rem]">Pure PHP/Math · Twelve Data OHLCV · Immutable Rules</span>
                            </div>
                            <div class="flex justify-center text-slate-600 font-mono text-xs">↕ Isolated ↕</div>
                            <div class="rounded-xl border border-amber-300/20 bg-amber-300/[0.05] p-3">
                                <span class="font-semibold text-amber-200 block">AI Macro Context Engine</span>
                                <span class="text-slate-400 text-[0.7rem]">Gemini Flash · Search Grounding · Qualitative Notes</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Transparency & Reliability Section -->
        <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto space-y-4">
                <p class="eyebrow text-amber-300/90">Enterprise-Grade Reliability</p>
                <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                    Transparency at Every Layer.
                </h2>
                <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                    Financial analytics software must communicate its status truthfully—especially when network conditions or market data feeds degrade.
                </p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="panel p-6 space-y-3">
                    <div class="h-8 w-8 rounded-xl border border-amber-300/30 bg-amber-300/10 grid place-items-center text-amber-300 font-bold text-sm">01</div>
                    <h3 class="text-base font-semibold text-white">Stale-State Detection</h3>
                    <p class="text-xs leading-relaxed text-slate-400">
                        Every timeframe card monitors timestamp staleness based on its configured interval. If a feed halts, the UI labels the snapshot as stale immediately.
                    </p>
                </div>
                <div class="panel p-6 space-y-3">
                    <div class="h-8 w-8 rounded-xl border border-amber-300/30 bg-amber-300/10 grid place-items-center text-amber-300 font-bold text-sm">02</div>
                    <h3 class="text-base font-semibold text-white">Last-Known-Good State</h3>
                    <p class="text-xs leading-relaxed text-slate-400">
                        If an API request fails, the dashboard preserves the previous valid snapshot in memory and displays a persistent warning banner rather than crashing.
                    </p>
                </div>
                <div class="panel p-6 space-y-3">
                    <div class="h-8 w-8 rounded-xl border border-amber-300/30 bg-amber-300/10 grid place-items-center text-amber-300 font-bold text-sm">03</div>
                    <h3 class="text-base font-semibold text-white">Explicit Demo Mode</h3>
                    <p class="text-xs leading-relaxed text-slate-400">
                        In environments without commercial display licenses, the system operates in explicit Demo Mode with clearly labeled illustrative fixtures.
                    </p>
                </div>
                <div class="panel p-6 space-y-3">
                    <div class="h-8 w-8 rounded-xl border border-amber-300/30 bg-amber-300/10 grid place-items-center text-amber-300 font-bold text-sm">04</div>
                    <h3 class="text-base font-semibold text-white">Provider Agnostic</h3>
                    <p class="text-xs leading-relaxed text-slate-400">
                        Modular provider contracts allow Twelve Data or backup market vendors to swap without altering scoring mathematics or frontend displays.
                    </p>
                </div>
            </div>
        </section>

        <!-- Product Boundaries Section -->
        <section id="boundaries" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 scroll-mt-24">
            <div class="panel p-8 sm:p-12">
                <div class="max-w-3xl space-y-4">
                    <p class="eyebrow text-rose-400">Strict Product Boundary</p>
                    <h2 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                        What HorizonBias Is <span class="text-rose-300">Not</span>.
                    </h2>
                    <p class="text-slate-300 text-sm leading-relaxed">
                        To maintain compliance, user trust, and analytical integrity, HorizonBias enforces strict functional boundaries:
                    </p>
                </div>

                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4 space-y-2">
                        <span class="text-rose-400 font-semibold text-sm">✕ No Order Execution</span>
                        <p class="text-xs leading-relaxed text-slate-400">There are no broker API keys, wallet connections, or trading buttons. We do not execute trades.</p>
                    </div>
                    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4 space-y-2">
                        <span class="text-rose-400 font-semibold text-sm">✕ No Price Targets or Trade Parameters</span>
                        <p class="text-xs leading-relaxed text-slate-400">We never publish price targets, risk-per-trade recommendations, or trade setup recommendations.</p>
                    </div>
                    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4 space-y-2">
                        <span class="text-rose-400 font-semibold text-sm">✕ No User Accounts</span>
                        <p class="text-xs leading-relaxed text-slate-400">No login, registration, tracking cookies, or subscription paywalls. Completely accessible market context.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Final CTA Section -->
        <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="panel-glow p-8 sm:p-14 text-center space-y-6">
                <span class="status-dot text-amber-300"></span>
                <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                    Ready to Inspect XAU/USD Technical Structure?
                </h2>
                <p class="mx-auto max-w-xl text-slate-300 text-sm sm:text-base leading-relaxed">
                    Access real-time timeframe scores, TradingView charts, and macroeconomic context in a single unified dashboard.
                </p>
                <div class="pt-2">
                    <a href="{{ route('dashboard') }}" class="btn-gold text-base px-8 py-3.5" id="final-cta">
                        <span>Open the XAU/USD Dashboard</span>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- Mandatory Risk Notice & Disclaimer Section -->
        <section id="risk-notice" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 scroll-mt-24">
            <div class="rounded-2xl border border-amber-300/20 bg-amber-300/[0.035] p-6 sm:p-8">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-amber-300 text-sm font-semibold">⚠️ Legal & Risk Notice</span>
                    <span class="text-xs uppercase tracking-wider text-slate-500">· Educational Disclaimer</span>
                </div>
                <h3 class="text-lg font-semibold text-amber-100">Context, Never a Command</h3>
                <p class="mt-3 text-xs sm:text-sm leading-relaxed text-amber-100/70">
                    HorizonBias provides educational market context and technical bias only. It is not financial advice, a trading signal, or a recommendation to buy or sell. Market and AI-generated information may be delayed, incomplete, or inaccurate. Independently verify all information and make your own risk decisions.
                </p>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="mt-20 border-t border-white/[0.07] bg-[#07090d]/80 py-10 text-xs text-slate-400">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <img src="{{ asset('images/horizonbias-logo.png') }}" alt="" class="h-5 w-5 object-contain" width="20" height="20">
                    <strong class="font-semibold text-slate-200">HorizonBias</strong>
                    <span>· Dedicated to XAU/USD</span>
                </div>
                <p class="text-slate-400">UTC timestamps · Completed candle analysis · Zero execution</p>
            </div>
            <div class="flex flex-wrap items-center gap-6">
                <a href="#methodology" class="hover:text-amber-200">Methodology</a>
                <a href="#risk-notice" class="hover:text-amber-200">Risk Notice</a>
                <a href="{{ route('dashboard') }}" class="font-semibold text-amber-300 hover:text-amber-200">Dashboard →</a>
            </div>
        </div>
    </footer>
</body>
</html>
