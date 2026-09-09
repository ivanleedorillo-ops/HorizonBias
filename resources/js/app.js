import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Accessible Theme Management System
const themeManager = {
    get() {
        try {
            const stored = localStorage.getItem('horizon_theme');
            if (stored === 'dark' || stored === 'light') return stored;
        } catch (_) {}
        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    },
    set(theme) {
        const active = theme === 'dark' ? 'dark' : 'light';
        try {
            localStorage.setItem('horizon_theme', active);
        } catch (_) {}
        if (active === 'dark') {
            document.documentElement.classList.add('dark');
            document.documentElement.style.colorScheme = 'dark';
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.style.colorScheme = 'light';
        }
        window.dispatchEvent(new CustomEvent('horizon-theme-changed', { detail: { theme: active } }));
        return active;
    },
    toggle() {
        const current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        return this.set(next);
    },
    init() {
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                try {
                    if (!localStorage.getItem('horizon_theme')) {
                        themeManager.set(e.matches ? 'dark' : 'light');
                    }
                } catch (_) {}
            });
        }
    }
};

themeManager.init();

// TradingView Advanced Chart Lifecycle & Dynamic Re-theming
let tvDebounceTimer = null;
function initTradingView(theme = null) {
    const host = document.getElementById('tradingview-chart');
    if (!host) return;

    clearTimeout(tvDebounceTimer);
    tvDebounceTimer = setTimeout(() => {
        const activeTheme = theme || (document.documentElement.classList.contains('dark') ? 'dark' : 'light');

        if (host.dataset.theme === activeTheme && host.querySelector('iframe')) {
            return;
        }

        // Clean previous widget and iframe instances to avoid duplicates or memory leaks
        const existingWidget = host.querySelector('.tradingview-widget-container__widget');
        if (existingWidget) existingWidget.remove();
        const existingScripts = host.querySelectorAll('script[src*="embed-widget-advanced-chart.js"]');
        existingScripts.forEach(s => s.remove());
        const existingIframes = host.querySelectorAll('iframe');
        existingIframes.forEach(f => f.remove());

        // Create fresh widget mount point before copyright attribution
        const widgetSlot = document.createElement('div');
        widgetSlot.className = 'tradingview-widget-container__widget w-full';
        widgetSlot.style.height = 'calc(100% - 32px)';
        widgetSlot.style.width = '100%';
        const copyright = host.querySelector('.tradingview-widget-copyright');
        if (copyright) {
            host.insertBefore(widgetSlot, copyright);
        } else {
            host.appendChild(widgetSlot);
        }

        const isMobile = window.innerWidth < 768;

        const config = {
            autosize: true,
            symbol: 'OANDA:XAUUSD',
            interval: '60',
            timezone: 'Etc/UTC',
            theme: activeTheme,
            style: '1',
            locale: 'en',
            allow_symbol_change: false,
            calendar: false,
            support_host: 'https://www.tradingview.com',
            hide_side_toolbar: isMobile,
            withdateranges: !isMobile,
            save_image: false,
        };

        const script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = 'https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js';
        script.async = true;
        script.text = JSON.stringify(config);
        host.appendChild(script);
        host.dataset.theme = activeTheme;
    }, 100);
}

// Reconstruct TradingView chart when site theme changes
window.addEventListener('horizon-theme-changed', (e) => {
    initTradingView(e.detail.theme);
});

const horizonDashboard = () => {
    const initial = JSON.parse(document.getElementById('dashboard-data')?.textContent ?? '{}');
    const isDesktop = window.innerWidth >= 1024;

    return {
        data: initial,
        selectedKey: initial.timeframes?.[3]?.key ?? initial.timeframes?.[0]?.key ?? null,
        connectionIssue: false,
        refreshing: false,
        chartIssue: false,
        timer: null,
        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
        showEvidenceDetails: isDesktop,
        expandedAi: {
            consensus: false,
            gemini: false,
            groq: false,
            evidence: false,
        },
        init() {
            this.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            window.addEventListener('horizon-theme-changed', (e) => {
                this.theme = e.detail.theme;
            });

            // Initialize TradingView chart with current active theme
            initTradingView(this.theme);

            this.timer = window.setInterval(() => this.refresh(), 60000);
            window.setTimeout(() => {
                const container = document.querySelector('#tradingview-chart');
                this.chartIssue = !container?.querySelector('iframe');
            }, 10000);
        },
        toggleTheme() {
            this.theme = themeManager.toggle();
        },
        toggleAiSection(section) {
            this.expandedAi[section] = !this.expandedAi[section];
        },
        isAiSectionExpanded(section) {
            return Boolean(this.expandedAi[section]);
        },
        toggleEvidenceDetails() {
            this.showEvidenceDetails = !this.showEvidenceDetails;
        },
        async refresh() {
            if (this.refreshing) return;
            this.refreshing = true;
            try {
                const response = await fetch('/api/dashboard', { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Dashboard refresh failed');
                this.data = await response.json();
                if (!this.data.timeframes?.some(frame => frame.key === this.selectedKey)) {
                    this.selectedKey = this.data.timeframes?.[0]?.key ?? null;
                }
                this.connectionIssue = false;
            } catch (_) {
                this.connectionIssue = true;
            } finally {
                this.refreshing = false;
            }
        },
        get selected() {
            return this.data.timeframes?.find(frame => frame.key === this.selectedKey) ?? null;
        },
        get currentIndex() {
            return this.data.timeframes?.findIndex(frame => frame.key === this.selectedKey) ?? -1;
        },
        selectTimeframe(key) {
            this.selectedKey = key;
            this.$nextTick(() => {
                const activeBtn = document.getElementById(`tf-btn-${key}`);
                if (activeBtn) {
                    activeBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            });
        },
        selectPrevFrame() {
            const frames = this.data.timeframes ?? [];
            if (!frames.length) return;
            const idx = this.currentIndex;
            const prev = idx > 0 ? idx - 1 : frames.length - 1;
            this.selectTimeframe(frames[prev].key);
        },
        selectNextFrame() {
            const frames = this.data.timeframes ?? [];
            if (!frames.length) return;
            const idx = this.currentIndex;
            const next = idx < frames.length - 1 ? idx + 1 : 0;
            this.selectTimeframe(frames[next].key);
        },
        biasTone(label = '') {
            const value = (label ?? '').toLowerCase();
            if (value.includes('bullish')) return 'bullish';
            if (value.includes('bearish')) return 'bearish';
            return 'neutral';
        },
        biasBadgeClass(label = '') {
            const value = (label ?? '').toLowerCase();
            if (value.includes('strong bullish')) return 'bias-strong-bullish';
            if (value.includes('bullish')) return 'bias-bullish';
            if (value.includes('strong bearish')) return 'bias-strong-bearish';
            if (value.includes('bearish')) return 'bias-bearish';
            return 'bias-neutral';
        },
        formatNumber(value, decimals = 2) {
            return value === null || value === undefined || Number.isNaN(Number(value))
                ? '—'
                : Number(value).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        },
        formatTime(value) {
            if (!value) return 'Awaiting data';
            return new Intl.DateTimeFormat('en-US', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                timeZone: 'UTC',
                hour12: false
            }).format(new Date(value)) + ' UTC';
        },
        humanize(value = '') {
            return String(value ?? '').replaceAll('_', ' ');
        },
    };
};

const horizonLanding = () => ({
    mobileMenuOpen: false,
    theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
    init() {
        this.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        window.addEventListener('horizon-theme-changed', (e) => {
            this.theme = e.detail.theme;
        });
    },
    toggleTheme() {
        this.theme = themeManager.toggle();
    },
    toggleMobileMenu() {
        this.mobileMenuOpen = !this.mobileMenuOpen;
    },
    closeMobileMenu() {
        this.mobileMenuOpen = false;
    }
});

Alpine.data('horizonDashboard', horizonDashboard);
Alpine.data('horizonLanding', horizonLanding);

Alpine.start();
