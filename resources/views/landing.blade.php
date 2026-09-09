<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HorizonBias provides auditable multi-timeframe technical bias and macroeconomic context dedicated exclusively to XAU/USD spot gold.">
    <link rel="icon" type="image/png" href="{{ asset('images/horizonbias-logo.png') }}">
    <title>HorizonBias — Multi-Timeframe XAU/USD Market Context</title>
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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="horizonLanding" class="min-h-screen w-full max-w-full overflow-x-hidden antialiased">
    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-50 border-b border-[var(--color-border)] bg-[var(--color-bg-surface-translucent)] backdrop-blur-xl transition-colors">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <!-- Brand Logo -->
            <a href="{{ url('/') }}" class="group flex items-center gap-3" aria-label="HorizonBias home">
                <span class="relative grid h-10 w-10 place-items-center overflow-hidden rounded-xl border border-[var(--color-gold-border)] bg-[var(--color-gold-bg)] p-1 transition-all duration-300 group-hover:scale-105 shrink-0">
                    <img src="{{ asset('images/horizonbias-logo.png') }}" alt="" class="h-full w-full object-contain" width="40" height="40" decoding="async">
                </span>
                <div>
                    <span class="block text-base font-bold tracking-tight text-[var(--color-text-primary)] leading-none">Horizon<span class="text-[var(--color-gold-accent)]">Bias</span></span>
                    <span class="hidden sm:block text-xs font-semibold uppercase tracking-widest text-[var(--color-text-muted)] mt-1">XAU/USD Context</span>
                </div>
            </a>

            <!-- Desktop Nav Links -->
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium" aria-label="Main Navigation">
                <a href="#timeframes" class="text-[var(--color-text-secondary)] transition hover:text-[var(--color-gold-accent)]">Timeframes</a>
                <a href="#methodology" class="text-[var(--color-text-secondary)] transition hover:text-[var(--color-gold-accent)]">Methodology</a>
                <a href="#separation" class="text-[var(--color-text-secondary)] transition hover:text-[var(--color-gold-accent)]">AI Separation</a>
                <a href="#boundaries" class="text-[var(--color-text-secondary)] transition hover:text-[var(--color-gold-accent)]">Boundaries</a>
            </nav>

            <!-- Actions -->
            <div class="flex items-center gap-2 sm:gap-3">
                <span class="hidden lg:inline-flex items-center gap-2 rounded-full border border-[var(--color-border)] bg-[var(--color-bg-surface)] px-3 py-1 text-xs font-medium text-[var(--color-text-muted)]">
                    <span class="status-dot text-[var(--color-gold-accent)]"></span> Spot Gold Only
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

                <a href="{{ route('dashboard') }}" class="btn-gold px-4 py-2 text-sm sm:px-5 sm:py-2.5" id="header-cta">
                    <span>Open Dashboard</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>

                <!-- Mobile menu toggle -->
                <button type="button" @click="toggleMobileMenu()" class="md:hidden inline-flex items-center justify-center p-2 rounded-lg border border-[var(--color-border)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]" :aria-expanded="mobileMenuOpen" aria-label="Toggle navigation menu">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        <path x-show="mobileMenuOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Drawer -->
        <div x-show="mobileMenuOpen" x-cloak class="md:hidden border-b border-[var(--color-border)] bg-[var(--color-bg-surface)] px-4 py-5 shadow-lg">
            <nav class="flex flex-col gap-3 text-sm font-medium" aria-label="Mobile Navigation">
                <a href="#timeframes" @click="closeMobileMenu()" class="rounded-lg px-3 py-2 text-[var(--color-text-secondary)] hover:bg-[var(--color-bg-page-secondary)]">7-Timeframe Matrix</a>
                <a href="#methodology" @click="closeMobileMenu()" class="rounded-lg px-3 py-2 text-[var(--color-text-secondary)] hover:bg-[var(--color-bg-page-secondary)]">Methodology</a>
                <a href="#separation" @click="closeMobileMenu()" class="rounded-lg px-3 py-2 text-[var(--color-text-secondary)] hover:bg-[var(--color-bg-page-secondary)]">AI Separation</a>
                <a href="#boundaries" @click="closeMobileMenu()" class="rounded-lg px-3 py-2 text-[var(--color-text-secondary)] hover:bg-[var(--color-bg-page-secondary)]">Boundaries</a>
                <div class="mt-2 border-t border-[var(--color-border)] pt-3 flex flex-col gap-3">
                    <button type="button" @click="toggleTheme()" class="btn-ghost w-full justify-center">
                        <span x-text="theme === 'dark' ? 'Switch to Light Theme' : 'Switch to Dark Theme'"></span>
                    </button>
                    <a href="{{ route('dashboard') }}" class="btn-gold w-full text-center py-2.5">Open Dashboard</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="space-y-20 sm:space-y-28">
        <!-- 1. Hero Section -->
        <section class="relative pt-12 pb-8 sm:pt-16 sm:pb-12 lg:pt-24 lg:pb-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid items-center gap-10 lg:grid-cols-[1.1fr_0.9fr]">
                    <div class="space-y-6 text-left">
                        <div class="inline-flex items-center gap-2 rounded-full border border-[var(--color-gold-border)] bg-[var(--color-gold-bg)] px-3.5 py-1.5 text-xs font-semibold text-[var(--color-gold-accent)]">
                            <span class="status-dot"></span>
                            <span>Dedicated XAU/USD Decision Support</span>
                        </div>
                        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-[var(--color-text-primary)] leading-tight">
                            Multi-Timeframe <br>
                            <span class="gold-gradient-text">Technical Bias</span> for Spot Gold.
                        </h1>
                        <p class="max-w-2xl text-base sm:text-lg leading-relaxed text-[var(--color-text-secondary)]" style="max-width: 65ch;">
                            HorizonBias synthesizes closed-candle market structure across seven horizons into an auditable, deterministic score. Objective technical analysis without noise, prediction algorithms, or black boxes.
                        </p>

                        <!-- CTA Group -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 pt-1">
                            <a href="{{ route('dashboard') }}" class="btn-gold text-base px-6 py-3 text-center" id="hero-primary-cta">
                                <span>Open Live Dashboard</span>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                            <a href="#methodology" class="btn-ghost text-base px-6 py-3 text-center">
                                <span>How It Works</span>
                            </a>
                        </div>

                        <!-- 3 Compact Trust Indicators -->
                        <div class="grid grid-cols-3 gap-3 border-t border-[var(--color-border)] pt-5">
                            <div>
                                <span class="block font-bold text-sm text-[var(--color-text-primary)]">7 Horizons</span>
                                <span class="text-xs text-[var(--color-text-muted)]">5m to 1mo structure</span>
                            </div>
                            <div>
                                <span class="block font-bold text-sm text-[var(--color-text-primary)]">Deterministic</span>
                                <span class="text-xs text-[var(--color-text-muted)]">Closed-bar math</span>
                            </div>
                            <div>
                                <span class="block font-bold text-sm text-[var(--color-text-primary)]">Isolated AI</span>
                                <span class="text-xs text-[var(--color-text-muted)]">Non-interfering context</span>
                            </div>
                        </div>
                    </div>

                    <!-- Hero Visual Card -->
                    <div class="relative">
                        <div class="panel-glow overflow-hidden">
                            <div class="relative aspect-[16/10] w-full overflow-hidden bg-[var(--color-bg-surface-elevated)]">
                                <img src="{{ asset('images/gold-horizon-hero.jpg') }}" alt="" class="h-full w-full object-cover object-center opacity-90 transition-transform duration-500 hover:scale-105" loading="eager" decoding="async">
                                <div class="absolute inset-0 bg-gradient-to-t from-[var(--color-bg-surface)] via-transparent to-transparent opacity-80"></div>
                            </div>
                            <div class="p-6 space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="status-dot text-[var(--color-bullish-text)]"></span>
                                        <span class="text-xs font-bold uppercase tracking-wider text-[var(--color-text-secondary)]">Composite Bias System</span>
                                    </div>
                                    <span class="rounded-lg border border-[var(--color-gold-border)] bg-[var(--color-gold-bg)] px-2.5 py-1 text-xs font-semibold text-[var(--color-gold-accent)]">Illustrative Preview</span>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="panel-subtle p-3.5">
                                        <span class="block text-xs uppercase tracking-wider text-[var(--color-text-muted)]">Asset</span>
                                        <strong class="mt-1 block text-lg font-bold text-[var(--color-text-primary)]">XAU / USD</strong>
                                        <span class="text-xs text-[var(--color-text-muted)]">OANDA Spot Gold</span>
                                    </div>
                                    <div class="panel-subtle p-3.5">
                                        <span class="block text-xs uppercase tracking-wider text-[var(--color-text-muted)]">Agreement</span>
                                        <strong class="mt-1 block text-lg font-bold text-[var(--color-bullish-text)]">+45 Bullish</strong>
                                        <span class="text-xs text-[var(--color-text-muted)]">Multi-Horizon</span>
                                    </div>
                                </div>
                                <p class="text-xs text-[var(--color-text-muted)]">
                                    <strong class="text-[var(--color-text-secondary)]">Zero Repaint:</strong> Every calculation strictly requires closed candles before computing.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. Seven-Timeframe Capability Overview -->
        <section id="timeframes" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 scroll-mt-24">
            <div class="text-center max-w-3xl mx-auto space-y-3">
                <p class="eyebrow">Multi-Timeframe Architecture</p>
                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-[var(--color-text-primary)]">
                    Seven Horizons. One Structured View.
                </h2>
                <p class="text-[var(--color-text-secondary)] text-base leading-relaxed">
                    Single-timeframe analysis creates blind spots. HorizonBias evaluates completed candles across tactical, intermediate, and structural horizons with mathematically defined weights.
                </p>
            </div>

            <!-- 7-Timeframe Grid -->
            <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
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
                    <div class="panel-interactive flex flex-col justify-between p-4 {{ $card['key'] === '4h' ? 'ring-2 ring-[var(--color-gold-accent)]' : '' }}">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-base font-bold text-[var(--color-text-primary)]">{{ $card['key'] }}</span>
                                <span class="rounded bg-[var(--color-bg-page-secondary)] px-2 py-0.5 text-xs font-semibold text-[var(--color-text-muted)]">{{ $card['weight'] }}</span>
                            </div>
                            <span class="mt-0.5 block text-xs text-[var(--color-text-muted)]">{{ $card['label'] }}</span>
                        </div>

                        <div class="my-4">
                            <span class="text-2xl font-extrabold tabular-nums {{ str_contains($card['bias'], 'Bullish') ? 'text-[var(--color-bullish-text)]' : (str_contains($card['bias'], 'Bearish') ? 'text-[var(--color-bearish-text)]' : 'text-[var(--color-neutral-text)]') }}">
                                {{ $card['score'] }}
                            </span>
                            <span class="mt-1 block text-xs font-bold {{ str_contains($card['bias'], 'Bullish') ? 'text-[var(--color-bullish-text)]' : (str_contains($card['bias'], 'Bearish') ? 'text-[var(--color-bearish-text)]' : 'text-[var(--color-neutral-text)]') }}">
                                {{ $card['bias'] }}
                            </span>
                        </div>

                        <p class="border-t border-[var(--color-border-subtle)] pt-2 text-xs text-[var(--color-text-muted)] leading-tight">
                            {{ $card['role'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- 3. Methodology & Architectural Separation -->
        <section id="methodology" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 scroll-mt-24">
            <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                <!-- Four Deterministic Pillars -->
                <div class="space-y-5">
                    <p class="eyebrow">Deterministic Technical Model</p>
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-[var(--color-text-primary)]">
                        Four Pillars. Closed-Bar Mathematics.
                    </h2>
                    <p class="text-[var(--color-text-secondary)] text-base leading-relaxed">
                        Rather than uninterpretable neural networks, HorizonBias calculates directional alignment using four classic, audited technical pillars evaluated continuously from -100 to +100:
                    </p>

                    <div class="grid gap-3 sm:grid-cols-2 pt-2">
                        <div class="panel-subtle p-4 space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-bold text-[var(--color-gold-accent)]">
                                <span>PILLAR 1</span>
                                <span>35 pts</span>
                            </div>
                            <h3 class="text-sm font-bold text-[var(--color-text-primary)]">Trend Alignment</h3>
                            <p class="text-xs text-[var(--color-text-muted)] leading-relaxed">EMA 20, 50, and 200 stack order and price relation across timeframes.</p>
                        </div>

                        <div class="panel-subtle p-4 space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-bold text-[var(--color-gold-accent)]">
                                <span>PILLAR 2</span>
                                <span>25 pts</span>
                            </div>
                            <h3 class="text-sm font-bold text-[var(--color-text-primary)]">Momentum Strength</h3>
                            <p class="text-xs text-[var(--color-text-muted)] leading-relaxed">Wilder-smoothed RSI (14), MACD histogram trajectory, and 10-bar ROC.</p>
                        </div>

                        <div class="panel-subtle p-4 space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-bold text-[var(--color-gold-accent)]">
                                <span>PILLAR 3</span>
                                <span>25 pts</span>
                            </div>
                            <h3 class="text-sm font-bold text-[var(--color-text-primary)]">Market Structure</h3>
                            <p class="text-xs text-[var(--color-text-muted)] leading-relaxed">Confirmed 5-candle swing pivots identify structural higher-highs or lower-lows.</p>
                        </div>

                        <div class="panel-subtle p-4 space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-bold text-[var(--color-gold-accent)]">
                                <span>PILLAR 4</span>
                                <span>15 pts</span>
                            </div>
                            <h3 class="text-sm font-bold text-[var(--color-text-primary)]">Breakouts & Volatility</h3>
                            <p class="text-xs text-[var(--color-text-muted)] leading-relaxed">20-bar Donchian channel breakouts validated by ATR (14) and ADX (14).</p>
                        </div>
                    </div>
                </div>

                <!-- AI Separation Card -->
                <div id="separation" class="panel p-6 sm:p-8 space-y-5 scroll-mt-24">
                    <div class="inline-flex items-center gap-2 rounded-full border border-[var(--color-bullish-border)] bg-[var(--color-bullish-bg)] px-3 py-1 text-xs font-bold text-[var(--color-bullish-text)]">
                        <span>Architectural Boundary</span>
                    </div>
                    <h2 class="text-2xl font-bold tracking-tight text-[var(--color-text-primary)]">
                        Why AI Context Never Touches the Score.
                    </h2>
                    <p class="text-sm sm:text-base leading-relaxed text-[var(--color-text-secondary)]">
                        HorizonBias incorporates dual-AI market intelligence (Gemini and Groq GPT-OSS) grounded in verified official feeds. However, by architectural mandate:
                    </p>

                    <ul class="space-y-3 text-sm text-[var(--color-text-secondary)]">
                        <li class="flex items-start gap-3">
                            <span class="text-[var(--color-gold-accent)] font-bold">✓</span>
                            <span><strong class="text-[var(--color-text-primary)]">Zero Model Drift:</strong> The technical score is 100% deterministic and cannot be diluted by AI hallucinations.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="text-[var(--color-gold-accent)] font-bold">✓</span>
                            <span><strong class="text-[var(--color-text-primary)]">Verifiable Citations:</strong> Macro events link out to official releases (Federal Reserve, BLS, Treasury).</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="text-[var(--color-gold-accent)] font-bold">✓</span>
                            <span><strong class="text-[var(--color-text-primary)]">Dual Perspective:</strong> Compare the hard math against macroeconomic forces without confusing the two.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- 4. Product Boundaries, Risk Notice & Final CTA -->
        <section id="boundaries" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 scroll-mt-24 space-y-12">
            <!-- Boundaries -->
            <div class="panel p-6 sm:p-10">
                <div class="max-w-2xl space-y-2">
                    <p class="eyebrow text-[var(--color-bearish-text)]">Strict Product Boundaries</p>
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-[var(--color-text-primary)]">
                        What HorizonBias Is Not.
                    </h2>
                    <p class="text-sm text-[var(--color-text-secondary)] leading-relaxed">
                        To maintain analytical integrity and regulatory compliance, HorizonBias enforces strict boundaries:
                    </p>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="panel-subtle p-4 space-y-1.5">
                        <span class="font-bold text-sm text-[var(--color-bearish-text)]">✕ No Order Execution</span>
                        <p class="text-xs text-[var(--color-text-muted)] leading-relaxed">No broker accounts, wallets, or trading commands. We do not execute trades.</p>
                    </div>
                    <div class="panel-subtle p-4 space-y-1.5">
                        <span class="font-bold text-sm text-[var(--color-bearish-text)]">✕ No Price Targets</span>
                        <p class="text-xs text-[var(--color-text-muted)] leading-relaxed">Never publishes buy/sell signals, price predictions, or risk-per-trade recommendations.</p>
                    </div>
                    <div class="panel-subtle p-4 space-y-1.5">
                        <span class="font-bold text-sm text-[var(--color-bearish-text)]">✕ No Paywalls or Tracking</span>
                        <p class="text-xs text-[var(--color-text-muted)] leading-relaxed">No logins, subscription walls, or invasive user tracking. Clean public access.</p>
                    </div>
                </div>
            </div>

            <!-- Mandatory Educational Disclaimer -->
            <div id="risk-notice" class="rounded-2xl border border-[var(--color-gold-border)] bg-[var(--color-gold-bg)] p-6 sm:p-8">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-sm font-bold text-[var(--color-gold-accent)]">⚠️ Educational Market Context Only</span>
                </div>
                <h3 class="text-base font-bold text-[var(--color-text-primary)]">Context, Never a Command</h3>
                <p class="mt-2 text-xs sm:text-sm leading-relaxed text-[var(--color-text-secondary)]">
                    HorizonBias provides educational market context and technical bias only. It is not financial advice, a trading signal, or a recommendation to buy or sell. Market and AI-generated information may be delayed, incomplete, or inaccurate. Independently verify all information and make your own risk decisions.
                </p>
            </div>

            <!-- Final CTA Card -->
            <div class="panel-glow p-8 sm:p-12 text-center space-y-5">
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[var(--color-text-primary)]">
                    Ready to Inspect XAU/USD Market Structure?
                </h2>
                <p class="mx-auto max-w-xl text-sm sm:text-base text-[var(--color-text-secondary)] leading-relaxed">
                    Access closed-bar timeframe scores, TradingView charts, and Dual-AI context in a unified decision-support dashboard.
                </p>
                <div class="pt-2">
                    <a href="{{ route('dashboard') }}" class="btn-gold text-base px-8 py-3.5" id="final-cta">
                        <span>Open Dashboard</span>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="mt-20 border-t border-[var(--color-border)] bg-[var(--color-bg-surface)] py-8 text-xs text-[var(--color-text-muted)] transition-colors">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <img src="{{ asset('images/horizonbias-logo.png') }}" alt="" class="h-5 w-5 object-contain" width="20" height="20">
                    <strong class="font-bold text-[var(--color-text-primary)]">HorizonBias</strong>
                    <span>· Dedicated to XAU/USD</span>
                </div>
                <p>Closed bar calculations · UTC timestamps · Zero execution</p>
            </div>
            <div class="flex flex-wrap items-center gap-6 font-medium">
                <a href="#methodology" class="hover:text-[var(--color-gold-accent)]">Methodology</a>
                <a href="#boundaries" class="hover:text-[var(--color-gold-accent)]">Boundaries</a>
                <a href="{{ route('dashboard') }}" class="text-[var(--color-gold-accent)] font-semibold hover:underline">Open Dashboard →</a>
            </div>
        </div>
    </footer>
</body>
</html>
