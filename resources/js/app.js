import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

const horizonDashboard = () => {
    const initial = JSON.parse(document.getElementById('dashboard-data')?.textContent ?? '{}');
    return ({
    data: initial,
    selectedKey: initial.timeframes?.[3]?.key ?? initial.timeframes?.[0]?.key ?? null,
    connectionIssue: false,
    refreshing: false,
    chartIssue: false,
    timer: null,
    init() {
        this.timer = window.setInterval(() => this.refresh(), 60000);
        window.setTimeout(() => {
            const container = document.querySelector('#tradingview-chart');
            this.chartIssue = !container?.querySelector('iframe');
        }, 10000);
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
    biasTone(label = '') {
        const value = label.toLowerCase();
        if (value.includes('bullish')) return 'bullish';
        if (value.includes('bearish')) return 'bearish';
        return 'neutral';
    },
    formatNumber(value, decimals = 2) {
        return value === null || value === undefined || Number.isNaN(Number(value))
            ? '—'
            : Number(value).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    },
    formatTime(value) {
        if (!value) return 'Awaiting data';
        return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'UTC', hour12: false }).format(new Date(value)) + ' UTC';
    },
    });
};

Alpine.data('horizonDashboard', horizonDashboard);

Alpine.start();
