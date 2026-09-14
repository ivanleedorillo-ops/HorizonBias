/**
 * Pure state normalization and view-model transformations for the Floating Bias Monitor.
 */

export function compactBiasLabel(label = '') {
    const v = (label ?? '').toLowerCase();
    if (v.includes('strong bullish')) return 'SB';
    if (v.includes('bullish')) return 'B';
    if (v.includes('strong bearish')) return 'SBR';
    if (v.includes('bearish')) return 'BR';
    return 'N';
}

export function biasTone(label = '') {
    const value = (label ?? '').toLowerCase();
    if (value.includes('bullish')) return 'bullish';
    if (value.includes('bearish')) return 'bearish';
    return 'neutral';
}

export function biasBadgeClass(label = '') {
    const value = (label ?? '').toLowerCase();
    if (value.includes('strong bullish')) return 'bias-strong-bullish';
    if (value.includes('bullish')) return 'bias-bullish';
    if (value.includes('strong bearish')) return 'bias-strong-bearish';
    if (value.includes('bearish')) return 'bias-bearish';
    return 'bias-neutral';
}

export function humanize(value = '') {
    const s = String(value ?? '').replaceAll('_', ' ').trim();
    if (!s) return '';
    return s.charAt(0).toUpperCase() + s.slice(1);
}

export function formatNumber(value, decimals = 2) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }
    return Number(value).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

export function formatTime(value) {
    if (!value) return 'Awaiting data';
    try {
        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'UTC',
            hour12: false,
        }).format(new Date(value)) + ' UTC';
    } catch (_) {
        return 'Invalid time';
    }
}

/**
 * Derives mode and freshness statuses with strict text separation.
 *
 * Mode: 'Live', 'Demo', 'Unavailable'
 * Freshness describes the deterministic technical snapshot only. AI availability
 * is represented independently by normalizeMonitorAi().
 * Freshness: 'Fresh', 'Stale', 'Connection issue', 'Unavailable', or 'Demo'
 */
export function deriveMonitorStatus(data = {}, connectionIssue = false) {
    const rawMode = data?.mode;
    const mode = rawMode === 'live' ? 'live' : (rawMode === 'demo' ? 'demo' : 'unavailable');
    const modeLabel = mode === 'live' ? 'Live' : (mode === 'demo' ? 'Demo' : 'Unavailable');
    const modeClass = mode === 'live' ? 'bias-bullish' : 'bias-neutral';

    const isOverallAvailable = Boolean(
        data?.overall &&
        data.overall.status !== 'unavailable' &&
        data.overall.score !== null &&
        data.overall.score !== undefined &&
        !Number.isNaN(Number(data.overall.score))
    );

    let freshness = 'unavailable';
    let freshnessLabel = 'Unavailable';
    let freshnessClass = 'bias-neutral';

    if (connectionIssue) {
        freshness = 'connection_issue';
        freshnessLabel = 'Connection issue';
        freshnessClass = 'bias-bearish';
    } else if (mode === 'unavailable' || !isOverallAvailable) {
        freshness = 'unavailable';
        freshnessLabel = 'Unavailable';
        freshnessClass = 'bias-neutral';
    } else if (data.overall?.stale || data.overall?.status === 'stale') {
        freshness = 'stale';
        freshnessLabel = 'Stale';
        freshnessClass = 'bias-neutral';
    } else if (mode === 'live') {
        freshness = 'fresh';
        freshnessLabel = 'Fresh';
        freshnessClass = 'bias-bullish';
    } else if (mode === 'demo') {
        // Demo fixtures are illustrative and must never be labeled 'Fresh' live analysis
        freshness = 'demo';
        freshnessLabel = 'Demo';
        freshnessClass = 'bias-neutral';
    }

    return {
        mode,
        modeLabel,
        modeClass,
        freshness,
        freshnessLabel,
        freshnessClass,
    };
}

/**
 * Normalizes a single timeframe object, guarding against invalid/missing scores.
 */
export function normalizeMonitorTimeframe(frame, key) {
    const isScoreValid = frame != null && frame.score !== null && frame.score !== undefined && !Number.isNaN(Number(frame.score));
    const isBiasValid = frame != null && typeof frame.bias === 'string' && frame.bias.trim().length > 0;
    const isExplicitUnavailable = frame?.status === 'unavailable';

    if (!frame || isExplicitUnavailable || !isScoreValid || !isBiasValid) {
        return {
            key,
            label: frame?.label || key,
            status: 'unavailable',
            isUnavailable: true,
            isStale: false,
            score: null,
            scoreDisplay: '—',
            bias: 'Unavailable',
            compactBias: '—',
            badgeClass: 'bias-neutral',
            title: `${frame?.label || key}: Unavailable`,
            ariaLabel: `${frame?.label || key}: Unavailable`,
            staleIndicator: false,
        };
    }

    const numScore = Number(frame.score);
    const sign = numScore > 0 ? '+' : '';
    const scoreDisplay = `${sign}${numScore}`;
    const bias = frame.bias;
    const compactBias = compactBiasLabel(bias);
    const badgeClass = biasBadgeClass(bias);
    const isStale = Boolean(frame.stale || frame.status === 'stale');

    if (isStale) {
        return {
            key,
            label: frame.label || key,
            status: 'stale',
            isUnavailable: false,
            isStale: true,
            score: numScore,
            scoreDisplay,
            bias,
            compactBias,
            badgeClass,
            title: `${frame.label || key}: ${bias} (${scoreDisplay}) [Stale]`,
            ariaLabel: `${frame.label || key}: ${bias}, score ${scoreDisplay}, Stale`,
            staleIndicator: true,
        };
    }

    return {
        key,
        label: frame.label || key,
        status: 'ready',
        isUnavailable: false,
        isStale: false,
        score: numScore,
        scoreDisplay,
        bias,
        compactBias,
        badgeClass,
        title: `${frame.label || key}: ${bias} (${scoreDisplay})`,
        ariaLabel: `${frame.label || key}: ${bias}, score ${scoreDisplay}`,
        staleIndicator: false,
    };
}

/**
 * Normalizes macro consensus data, preventing unavailable state from pretending to be neutral.
 */
export function normalizeMonitorAi(macro) {
    if (!macro) {
        return {
            status: 'unavailable',
            biasLabel: 'Unavailable',
            biasClass: 'bias-neutral',
            agreementLabel: '· No consensus',
            confidenceDisplay: '· —',
            riskDisplay: '· —',
            isPartial: false,
            partialNotice: '',
            isStale: false,
            staleNotice: '',
            summary: 'Dual-AI context is unavailable.',
            generatedAt: null,
            timeDisplay: '',
        };
    }

    const analyses = Array.isArray(macro.analyses) ? macro.analyses : [];
    const readyAnalyses = analyses.filter(a => a && a.status !== 'failed' && a.status !== 'unavailable');

    const providerStatuses = Array.isArray(macro.provider_status)
        ? macro.provider_status
        : (macro.provider_status && typeof macro.provider_status === 'object' ? Object.values(macro.provider_status) : []);
    const readyProviders = providerStatuses.filter(p => p && p.status === 'ready');

    const hasExplicitAnalyses = Array.isArray(macro.analyses);
    const hasExplicitProviders = providerStatuses.length > 0;
    const noValidAnalyses = (hasExplicitAnalyses && analyses.length > 0 && readyAnalyses.length === 0)
        || (hasExplicitProviders && readyProviders.length === 0);

    const isExplicitUnavailable = macro.status === 'unavailable';
    const isUnavailable = isExplicitUnavailable || noValidAnalyses;

    if (isUnavailable) {
        return {
            status: 'unavailable',
            biasLabel: 'Unavailable',
            biasClass: 'bias-neutral',
            agreementLabel: '· No consensus',
            confidenceDisplay: '· —',
            riskDisplay: '· —',
            isPartial: false,
            partialNotice: '',
            isStale: false,
            staleNotice: '',
            summary: macro.summary && macro.summary !== 'Dual-AI context is not available yet.' ? macro.summary : 'Dual-AI context is unavailable.',
            generatedAt: macro.generated_at || null,
            timeDisplay: '',
        };
    }

    const isPartial = macro.status === 'partial' ||
        (readyAnalyses.length === 1 && (analyses.length > 1 || providerStatuses.length > 1)) ||
        (readyProviders.length === 1 && providerStatuses.length > 1);

    const isStale = Boolean(macro.stale || macro.status === 'stale');

    let partialNotice = '';
    if (isPartial) {
        let availableProviderName = null;
        if (readyAnalyses.length > 0 && readyAnalyses[0].provider) {
            availableProviderName = readyAnalyses[0].provider;
        } else if (readyProviders.length > 0 && readyProviders[0].provider) {
            availableProviderName = readyProviders[0].provider;
        }
        const safeProvider = availableProviderName ? humanize(availableProviderName) : 'one AI provider';
        partialNotice = `Partial assessment: Only ${safeProvider} returned a valid assessment.`;
    }

    const goldBias = macro.gold_bias || 'neutral';
    const biasLabel = isPartial ? 'Partial' : (macro.gold_bias ? humanize(macro.gold_bias) : 'Neutral');
    const biasClass = isPartial ? 'bias-neutral' : biasBadgeClass(goldBias);

    const agreement = macro.agreement ? humanize(macro.agreement) : 'No consensus';
    const confidence = macro.confidence != null && !Number.isNaN(Number(macro.confidence))
        ? `${Number(macro.confidence)}%`
        : '—';
    const risk = macro.risk_level ? `${humanize(macro.risk_level)} risk` : '—';

    let staleNotice = '';
    if (isStale) {
        staleNotice = 'Stale AI context — retaining last valid assessment.';
    }

    return {
        status: isStale ? 'stale' : (isPartial ? 'partial' : (macro.status || 'ready')),
        biasLabel,
        biasClass,
        agreementLabel: `· ${agreement}`,
        confidenceDisplay: `· ${confidence}`,
        riskDisplay: `· ${risk}`,
        isPartial,
        partialNotice,
        isStale,
        staleNotice,
        summary: macro.summary || 'Dual-AI context is unavailable.',
        generatedAt: macro.generated_at || null,
        timeDisplay: macro.generated_at ? `Generated: ${formatTime(macro.generated_at)}` : '',
    };
}

/**
 * Builds the complete monitor view model from dashboard state.
 */
export function buildMonitorViewModel(data = {}, connectionIssue = false) {
    const status = deriveMonitorStatus(data, connectionIssue);
    const quote = data?.quote || {};
    const overall = data?.overall || null;
    const timeframesList = data?.timeframes || [];
    const macro = data?.macro || null;

    // Spot quote
    const priceDisplay = quote.price != null && !Number.isNaN(Number(quote.price))
        ? `$${formatNumber(quote.price, 2)}`
        : '$—';
    const currencyDisplay = quote.currency || 'USD';
    const completedAt = quote.completed_at || quote.as_of;
    const asOfDisplay = completedAt ? `Through ${formatTime(completedAt)}` : 'Awaiting candle';

    let changeDisplay = '—';
    let changeClass = 'text-[var(--color-text-muted)]';
    if (quote.change != null && quote.change_percent != null && !Number.isNaN(Number(quote.change))) {
        const sign = Number(quote.change) >= 0 ? '+' : '';
        changeDisplay = `${sign}$${formatNumber(quote.change, 2)} (${sign}${formatNumber(quote.change_percent, 2)}%)`;
        changeClass = Number(quote.change) >= 0 ? 'text-[var(--color-bullish-text)]' : 'text-[var(--color-bearish-text)]';
    }

    // Overall bias
    let overallLabel = 'Unavailable';
    let overallBadgeClass = 'bias-neutral';
    let overallScoreDisplay = '—';
    let overallStatusDisplay = 'Unavailable — awaiting required horizons';

    if (overall && typeof overall.score === 'number') {
        overallLabel = overall.label || 'Neutral';
        overallBadgeClass = biasBadgeClass(overallLabel);
        const sign = overall.score > 0 ? '+' : '';
        overallScoreDisplay = `${sign}${overall.score}`;
        overallStatusDisplay = overall.stale
            ? 'Stale observation'
            : (overall.generated_at ? formatTime(overall.generated_at) : 'Calculated');
    }

    // 7 Timeframes
    const tfKeys = ['5m', '15m', '1h', '4h', '1d', '1w', '1mo'];
    const timeframes = tfKeys.map(key => {
        const frame = Array.isArray(timeframesList) ? timeframesList.find(f => f && f.key === key) : null;
        return normalizeMonitorTimeframe(frame, key);
    });

    // AI
    const ai = normalizeMonitorAi(macro);

    return {
        status,
        quote: {
            priceDisplay,
            currencyDisplay,
            asOfDisplay,
            changeDisplay,
            changeClass,
        },
        overall: {
            label: overallLabel,
            badgeClass: overallBadgeClass,
            scoreDisplay: overallScoreDisplay,
            statusDisplay: overallStatusDisplay,
            isAvailable: Boolean(overall),
        },
        timeframes,
        ai,
        connectionIssue: Boolean(connectionIssue),
    };
}
