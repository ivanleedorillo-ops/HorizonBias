import './bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import { buildMonitorViewModel } from './floating-monitor';

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
        history: null,
        historyRange: '7d',
        historyScope: 'overall',
        historyLoading: false,
        historyIssue: false,
        historyRequest: 0,
        historyChart: null,
        showHistoryMethodology: false,
        timer: null,
        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
        showEvidenceDetails: isDesktop,
        expandedAi: {
            consensus: false,
            gemini: false,
            groq: false,
            evidence: false,
        },
        floatingWindow: null,
        floatingMode: null,
        floatingOpen: false,
        floatingOpening: false,
        floatingError: null,
        floatingSupported: typeof window !== 'undefined' && 'documentPictureInPicture' in window && typeof window.documentPictureInPicture?.requestWindow === 'function',
        init() {
            this.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            window.addEventListener('horizon-theme-changed', (e) => {
                this.theme = e.detail.theme;
                this.syncFloatingTheme();
                this.$nextTick(() => this.renderHistoryChart());
            });

            // Close floating monitor if dashboard tab closes
            window.addEventListener('pagehide', () => this.closeFloatingMonitor());
            window.addEventListener('beforeunload', () => this.closeFloatingMonitor());

            // Initialize TradingView chart with current active theme
            initTradingView(this.theme);
            this.refreshHistory();

            this.timer = window.setInterval(() => this.refresh(), 60000);
            window.setTimeout(() => {
                const container = document.querySelector('#tradingview-chart');
                this.chartIssue = !container?.querySelector('iframe');
            }, 10000);
        },
        toggleTheme() {
            this.theme = themeManager.toggle();
            this.syncFloatingTheme();
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
        async openFloatingMonitor() {
            // Guard against rapid duplicate clicks while opening
            if (this.floatingOpening) {
                return;
            }

            // If an active window already exists, bring it to focus
            if (this.floatingWindow && !this.floatingWindow.closed) {
                try {
                    this.floatingWindow.focus();
                } catch (_) {}
                return;
            }

            this.floatingOpening = true;
            this.floatingError = null;

            try {
                // Feature detection for Document Picture-in-Picture
                if ('documentPictureInPicture' in window && typeof window.documentPictureInPicture?.requestWindow === 'function') {
                    try {
                        await this.openDocumentPictureInPicture();
                        return;
                    } catch (err) {
                        // Fall back cleanly if user denied or requestWindow failed
                        console.warn('Document Picture-in-Picture failed or was denied, opening popup fallback:', err);
                    }
                }

                // Fallback for unsupported browsers
                this.openPopupMonitor();
            } catch (_) {
                this.cleanupFloatingMonitor();
                this.floatingError = 'The Floating Bias Monitor could not be opened. Check your browser window permissions and try again.';
            } finally {
                this.floatingOpening = false;
            }
        },
        async openDocumentPictureInPicture() {
            const pipWindow = await window.documentPictureInPicture.requestWindow({
                width: 380,
                height: 560,
            });

            try {
                this.floatingWindow = pipWindow;
                this.floatingMode = 'pip';
                this.floatingOpen = true;

                pipWindow.addEventListener('pagehide', () => {
                    this.cleanupFloatingMonitor(pipWindow);
                });

                this.prepareFloatingDocument(pipWindow.document, 'pip');
                this.renderFloatingMonitor();
            } catch (err) {
                // Partial PiP failure: close orphan window and cleanup state before fallback
                try {
                    pipWindow.close();
                } catch (_) {}
                this.cleanupFloatingMonitor(pipWindow);
                throw err;
            }
        },
        openPopupMonitor() {
            const width = 380;
            const height = 560;
            const left = Math.max(0, Math.round((window.screen.width - width) / 2));
            const top = Math.max(0, Math.round((window.screen.height - height) / 2));

            let popup = null;
            try {
                popup = window.open(
                    '',
                    'horizonbias_monitor',
                    `width=${width},height=${height},top=${top},left=${left},resizable=yes,scrollbars=yes`
                );
            } catch (_) {}

            if (!popup || popup.closed || typeof popup.closed === 'undefined') {
                this.floatingError = 'Floating monitor popup was blocked by your browser. Please allow popups for HorizonBias.';
                this.cleanupFloatingMonitor();
                return;
            }

            try {
                this.floatingWindow = popup;
                this.floatingMode = 'popup';
                this.floatingOpen = true;

                popup.addEventListener('pagehide', () => {
                    this.cleanupFloatingMonitor(popup);
                });

                this.prepareFloatingDocument(popup.document, 'popup');
                this.renderFloatingMonitor();
            } catch (err) {
                try {
                    popup.close();
                } catch (_) {}
                this.cleanupFloatingMonitor(popup);
                throw err;
            }
        },
        prepareFloatingDocument(doc, mode = 'pip') {
            doc.title = 'HorizonBias — XAU/USD Monitor';

            // Meta tags
            if (!doc.querySelector('meta[charset]')) {
                const metaCharset = doc.createElement('meta');
                metaCharset.setAttribute('charset', 'utf-8');
                doc.head.appendChild(metaCharset);
            }
            if (!doc.querySelector('meta[name="viewport"]')) {
                const metaVp = doc.createElement('meta');
                metaVp.name = 'viewport';
                metaVp.content = 'width=device-width, initial-scale=1';
                doc.head.appendChild(metaVp);
            }

            // Copy stylesheets safely
            try {
                [...document.styleSheets].forEach((sheet) => {
                    try {
                        if (sheet.href) {
                            const link = doc.createElement('link');
                            link.rel = 'stylesheet';
                            link.type = sheet.type || 'text/css';
                            link.media = sheet.media?.mediaText || 'all';
                            link.href = sheet.href;
                            doc.head.appendChild(link);
                        } else if (sheet.cssRules) {
                            const style = doc.createElement('style');
                            style.textContent = [...sheet.cssRules].map(r => r.cssText).join('\n');
                            doc.head.appendChild(style);
                        }
                    } catch (_) {
                        // Inaccessible or cross-origin stylesheet rules safely ignored
                    }
                });
            } catch (_) {}

            doc.documentElement.className = this.theme === 'dark' ? 'dark' : '';
            doc.documentElement.style.colorScheme = this.theme;
            doc.body.className = 'bg-[var(--color-bg-page)] text-[var(--color-text-primary)] antialiased m-0 p-0';

            // Clone dedicated template
            const template = document.getElementById('floating-monitor-template');
            if (template) {
                const clone = template.content.cloneNode(true);
                doc.body.replaceChildren(clone);
            }

            // Bind actions safely
            const returnBtn = doc.querySelector('[data-action="focus-dashboard"]');
            if (returnBtn) {
                returnBtn.addEventListener('click', () => this.focusDashboardFromMonitor());
            }
            const closeBtn = doc.querySelector('[data-action="close-monitor"]');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.closeFloatingMonitor());
            }

            // Fallback banner visibility
            const fallbackBanner = doc.querySelector('[data-monitor="fallback-banner"]');
            if (fallbackBanner) {
                if (mode === 'popup') {
                    fallbackBanner.classList.remove('hidden');
                } else {
                    fallbackBanner.classList.add('hidden');
                }
            }
        },
        renderFloatingMonitor() {
            if (!this.floatingWindow || this.floatingWindow.closed) return;
            const doc = this.floatingWindow.document;
            if (!doc || !doc.body) return;

            const vm = buildMonitorViewModel(this.data, this.connectionIssue);

            // Mode badge
            const modeBadge = doc.querySelector('[data-monitor="mode-badge"]');
            if (modeBadge) {
                modeBadge.textContent = vm.status.modeLabel;
                modeBadge.className = 'monitor-badge ' + vm.status.modeClass;
            }

            // Freshness badge
            const freshBadge = doc.querySelector('[data-monitor="freshness-badge"]');
            if (freshBadge) {
                freshBadge.textContent = vm.status.freshnessLabel;
                freshBadge.className = 'monitor-badge ' + vm.status.freshnessClass;
            }

            // Spot Quote
            const priceEl = doc.querySelector('[data-monitor="quote-price"]');
            if (priceEl) {
                priceEl.textContent = vm.quote.priceDisplay;
            }
            const currEl = doc.querySelector('[data-monitor="quote-currency"]');
            if (currEl) {
                currEl.textContent = vm.quote.currencyDisplay;
            }
            const asofEl = doc.querySelector('[data-monitor="quote-asof"]');
            if (asofEl) {
                asofEl.textContent = vm.quote.asOfDisplay;
            }
            const changeEl = doc.querySelector('[data-monitor="quote-change"]');
            if (changeEl) {
                changeEl.textContent = vm.quote.changeDisplay;
                changeEl.className = 'text-xs font-semibold tabular-nums ' + vm.quote.changeClass;
            }

            // Overall Bias
            const overallBadge = doc.querySelector('[data-monitor="overall-badge"]');
            const overallScore = doc.querySelector('[data-monitor="overall-score"]');
            const overallStatus = doc.querySelector('[data-monitor="overall-status"]');

            if (overallBadge) {
                overallBadge.textContent = vm.overall.label;
                overallBadge.className = 'inline-flex items-center rounded-md border px-2.5 py-1 text-xs font-extrabold uppercase tracking-wide ' + vm.overall.badgeClass;
            }
            if (overallScore) {
                overallScore.textContent = vm.overall.scoreDisplay;
            }
            if (overallStatus) {
                overallStatus.textContent = vm.overall.statusDisplay;
            }

            // 7 Timeframes
            vm.timeframes.forEach((tf) => {
                const scoreEl = doc.querySelector(`[data-monitor="tf-score-${tf.key}"]`);
                const badgeEl = doc.querySelector(`[data-monitor="tf-badge-${tf.key}"]`);
                const statusEl = doc.querySelector(`[data-monitor="tf-status-${tf.key}"]`);
                const itemEl = doc.querySelector(`[data-tf="${tf.key}"]`);

                if (scoreEl) scoreEl.textContent = tf.scoreDisplay;
                if (badgeEl) {
                    badgeEl.textContent = tf.compactBias;
                    badgeEl.className = `px-1.5 py-0.5 rounded text-[10px] font-bold uppercase border ${tf.badgeClass}`;
                }
                if (statusEl) {
                    if (tf.isStale) {
                        statusEl.textContent = 'Stale';
                        statusEl.classList.remove('hidden');
                    } else if (tf.isUnavailable) {
                        statusEl.textContent = 'Unavailable';
                        statusEl.classList.remove('hidden');
                    } else {
                        statusEl.classList.add('hidden');
                    }
                }
                if (itemEl) {
                    itemEl.setAttribute('title', tf.title);
                    itemEl.setAttribute('aria-label', tf.ariaLabel);
                    if (tf.isStale || tf.isUnavailable) {
                        itemEl.classList.add('opacity-85');
                    } else {
                        itemEl.classList.remove('opacity-85');
                    }
                }
            });

            // AI Consensus
            const aiBiasEl = doc.querySelector('[data-monitor="ai-bias"]');
            const aiAgreeEl = doc.querySelector('[data-monitor="ai-agreement"]');
            const aiConfEl = doc.querySelector('[data-monitor="ai-confidence"]');
            const aiRiskEl = doc.querySelector('[data-monitor="ai-risk"]');
            const aiPartialEl = doc.querySelector('[data-monitor="ai-partial"]');
            const aiStaleEl = doc.querySelector('[data-monitor="ai-stale"]');
            const aiSummaryEl = doc.querySelector('[data-monitor="ai-summary"]');
            const aiTimeEl = doc.querySelector('[data-monitor="ai-time"]');

            if (aiBiasEl) {
                aiBiasEl.textContent = vm.ai.biasLabel;
                aiBiasEl.className = 'inline-flex items-center rounded border px-2 py-0.5 text-xs font-bold uppercase ' + vm.ai.biasClass;
            }
            if (aiAgreeEl) {
                aiAgreeEl.textContent = vm.ai.agreementLabel;
            }
            if (aiConfEl) {
                aiConfEl.textContent = vm.ai.confidenceDisplay;
            }
            if (aiRiskEl) {
                aiRiskEl.textContent = vm.ai.riskDisplay;
            }

            if (aiPartialEl) {
                if (vm.ai.isPartial && vm.ai.partialNotice) {
                    aiPartialEl.textContent = vm.ai.partialNotice;
                    aiPartialEl.classList.remove('hidden');
                } else {
                    aiPartialEl.classList.add('hidden');
                }
            }

            if (aiStaleEl) {
                if (vm.ai.isStale && vm.ai.staleNotice) {
                    aiStaleEl.textContent = vm.ai.staleNotice;
                    aiStaleEl.classList.remove('hidden');
                } else {
                    aiStaleEl.classList.add('hidden');
                }
            }

            if (aiSummaryEl) {
                aiSummaryEl.textContent = vm.ai.summary;
            }

            if (aiTimeEl) {
                if (vm.ai.timeDisplay) {
                    aiTimeEl.textContent = vm.ai.timeDisplay;
                    aiTimeEl.classList.remove('hidden');
                } else {
                    aiTimeEl.classList.add('hidden');
                }
            }

            // Connection Warning
            const connWarn = doc.querySelector('[data-monitor="connection-warning"]');
            if (connWarn) {
                if (vm.connectionIssue) {
                    connWarn.classList.remove('hidden');
                } else {
                    connWarn.classList.add('hidden');
                }
            }

            // Sync Time
            const lastSync = doc.querySelector('[data-monitor="last-refresh"]');
            if (lastSync) {
                lastSync.textContent = new Intl.DateTimeFormat('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    timeZone: 'UTC',
                    hour12: false,
                }).format(new Date()) + ' UTC';
            }
            const snapshotId = doc.querySelector('[data-monitor="snapshot-id"]');
            if (snapshotId) {
                snapshotId.textContent = vm.snapshotId;
            }
        },
        syncFloatingTheme() {
            if (!this.floatingWindow || this.floatingWindow.closed) return;
            const doc = this.floatingWindow.document;
            if (!doc || !doc.documentElement) return;
            doc.documentElement.className = this.theme === 'dark' ? 'dark' : '';
            doc.documentElement.style.colorScheme = this.theme;
        },
        focusDashboardFromMonitor() {
            try {
                window.focus();
            } catch (_) {}
        },
        closeFloatingMonitor() {
            const win = this.floatingWindow;
            if (win && !win.closed) {
                try {
                    win.close();
                } catch (_) {}
            }
            this.cleanupFloatingMonitor(win);
        },
        cleanupFloatingMonitor(expectedWindow = null) {
            if (expectedWindow && this.floatingWindow !== expectedWindow) {
                return;
            }
            this.floatingWindow = null;
            this.floatingMode = null;
            this.floatingOpen = false;
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
                await this.refreshHistory();
            } catch (_) {
                this.connectionIssue = true;
            } finally {
                this.refreshing = false;
                this.renderFloatingMonitor();
            }
        },
        async refreshHistory() {
            const requestId = ++this.historyRequest;
            this.historyLoading = true;
            try {
                const params = new URLSearchParams({ range: this.historyRange, scope: this.historyScope });
                const response = await fetch(`/api/bias-history?${params}`, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('History refresh failed');
                const result = await response.json();
                if (requestId !== this.historyRequest) return;
                this.history = result;
                this.historyIssue = false;
                this.$nextTick(() => this.renderHistoryChart());
            } catch (_) {
                if (requestId === this.historyRequest) this.historyIssue = true;
            } finally {
                if (requestId === this.historyRequest) this.historyLoading = false;
            }
        },
        selectHistoryRange(range) {
            this.historyRange = range;
            this.refreshHistory();
        },
        selectHistoryScope(scope) {
            this.historyScope = scope;
            this.refreshHistory();
        },
        renderHistoryChart() {
            const canvas = document.getElementById('bias-history-chart');
            if (!canvas || !this.history?.series) return;
            if (this.historyChart) this.historyChart.destroy();

            const styles = getComputedStyle(document.documentElement);
            const textColor = styles.getPropertyValue('--color-text-muted').trim() || '#94a3b8';
            const gridColor = styles.getPropertyValue('--color-border-subtle').trim() || '#253047';
            const goldColor = styles.getPropertyValue('--color-gold-accent').trim() || '#d6ad45';
            const labels = this.history.series.map(point => this.formatChartTime(point.at));
            const scores = this.history.series.map(point => point.score);

            this.historyChart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: `${this.historyScope} bias score`,
                        data: scores,
                        borderColor: goldColor,
                        backgroundColor: `${goldColor}24`,
                        borderWidth: 2,
                        pointRadius: scores.length > 80 ? 0 : 2,
                        pointHoverRadius: 4,
                        fill: true,
                        tension: 0.2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 250 },
                    interaction: { intersect: false, mode: 'index' },
                    scales: {
                        y: { min: -100, max: 100, ticks: { color: textColor }, grid: { color: gridColor } },
                        x: { ticks: { color: textColor, maxTicksLimit: 7, maxRotation: 0 }, grid: { display: false } },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: context => ` Score: ${context.parsed.y}` } },
                    },
                },
            });
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
        statusBadgeClass(status = '') {
            const value = String(status ?? '').toLowerCase();
            if (['ready', 'success'].includes(value)) return 'bias-bullish';
            if (['failed', 'degraded', 'unavailable'].includes(value)) return 'bias-bearish';
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
        formatChartTime(value) {
            if (!value) return '';
            return new Intl.DateTimeFormat('en-US', {
                month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
                timeZone: 'UTC', hour12: false,
            }).format(new Date(value));
        },
        formatDuration(minutes) {
            if (minutes === null || minutes === undefined) return 'Awaiting data';
            if (minutes < 60) return `${minutes}m`;
            if (minutes < 1440) return `${Math.round(minutes / 60)}h`;
            return `${Math.round(minutes / 1440)}d`;
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
