# HorizonBias

HorizonBias is a 100% AI-assisted Laravel 12 decision-support dashboard dedicated to XAU/USD. It combines a deterministic, auditable technical score across seven timeframes with separately displayed Gemini and Groq GPT-OSS macro assessments and a rule-based consensus. It does not provide entries, exits, position sizing, trade execution, or personalized financial advice.

## Architecture

- `MarketDataProvider` normalizes completed candles; the included adapter targets Twelve Data.
- `IndicatorCalculator` calculates EMA, Wilder RSI/ATR/ADX, MACD, ROC, and confirmed pivots locally.
- `BiasScorer` creates component, timeframe, and weighted multi-timeframe scores.
- Canonical history points preserve auditable timeframe and overall scores without counting repeated refreshes of the same completed candle as new evidence.
- The outcome evaluator uses only stored future candles to calculate ATR-normalized historical directional alignment; it never calls a provider or simulates trades.
- Scheduled Artisan commands persist candles, bias snapshots, independent AI assessments, and a deterministic dual-AI consensus. HTTP visitors only read stored state.
- Gemini and Groq GPT-OSS receive the same immutable technical/evidence package. Laravel—not either model—calculates agreement and confidence.
- A local daily request cap and model allowlists keep the workflow intentionally within the configured free-tier boundary; no paid fallback exists.
- The public TradingView widget is display-only and completely separate from HorizonBias calculations.
- Demo mode provides deterministic, explicitly dated illustrative fixtures without needing credentials.

## Local setup (Windows/XAMPP)

Requirements: PHP 8.2+, Composer, Node 20+, MySQL, and the PHP extensions Laravel requires.

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
```

Create a MySQL database named `horizon_bias`, then set:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=horizon_bias
DB_USERNAME=root
DB_PASSWORD=
```

Finish setup:

```powershell
php artisan migrate
npm.cmd install
npm.cmd run build
php artisan serve
```

The default `MARKET_MODE=demo` is intentional. HorizonBias uses `http://127.0.0.1:8088` by default so it does not conflict with XAMPP Apache or other Laravel projects. Open the exact URL printed by `artisan serve`; plain `http://localhost` goes to XAMPP on port 80.

## Live development mode

Twelve Data individual/free access is suitable for development/internal use, not public display. To test locally:

```dotenv
APP_ENV=local
MARKET_MODE=live
MARKET_DATA_PROVIDER=twelve_data
TWELVE_DATA_API_KEY=your_key
MARKET_DATA_EXTERNAL_DISPLAY_LICENSED=false
MARKET_CANDLE_CLOSE_GRACE_SECONDS=30
```

Then populate data and keep it refreshed:

```powershell
php artisan market:refresh-bias
php artisan schedule:work
```

Refresh one horizon with `php artisan market:refresh-bias --timeframe=4h`.

Market timestamps use UTC. Provider candle timestamps represent the start of an interval, while the dashboard displays the calculated completion time. HorizonBias filters candles by their interval end plus `MARKET_CANDLE_CLOSE_GRACE_SECONDS` instead of blindly dropping the provider's newest row. The 5-minute and 15-minute refreshes run one minute after their candle boundaries so completed bars have time to be published. “Dashboard checked” is only the browser's latest stored-data check and is not a market-data timestamp.

## Refresh health and completed-candle changes

The dashboard includes a read-only **Refresh Status Center** and **What Changed?** summary. These features use stored Laravel records only and do not make additional Twelve Data, Gemini, Groq, or official-feed requests.

- Every scheduled or manually invoked market/AI command records a safe success, partial, or failure result in `refresh_runs`. Public output receives only a bounded reason category and sanitized message; raw provider errors and credentials remain in server logs.
- Market and AI status are shown independently with last-success, last-attempt, and next-scheduled UTC timestamps. Twelve Data account-credit usage is not fabricated when the provider does not expose it; only an observed rate-limit failure is reported.
- **What Changed?** compares each latest timeframe with its previous distinct completed-candle snapshot and identifies the largest deterministic component changes. It does not call AI and cannot create an entry recommendation.
- A stable public snapshot identifier is derived from the stored market snapshot set and macro brief. The dashboard, floating monitor, and downloaded report show the same identifier until their source state changes.

After pulling this feature, run `php artisan migrate` before restarting the scheduler.

## Free-tier dual-AI macro context

```dotenv
GEMINI_API_KEY=your_key
GEMINI_MODEL=gemini-3.5-flash-lite
GEMINI_SEARCH_GROUNDING=false

DUAL_AI_ENABLED=true
AI_FREE_TIER_ONLY=true
AI_DAILY_REQUEST_CAP=24
GROQ_API_KEY=your_groq_console_key
GROQ_MODEL=openai/gpt-oss-120b

MACRO_REFRESH_MINUTES=180
MACRO_FEED_MAX_AGE_DAYS=14
```

Create the Groq key at `console.groq.com`; an xAI Grok key is a different product and will not work. Never commit either API key. API provider free tiers are rate-limited and may change. `AI_FREE_TIER_ONLY=true` restricts HorizonBias to its configured free-tier model allowlists, `AI_DAILY_REQUEST_CAP=24` stops each provider at 24 application attempts per UTC day, and HorizonBias has no automatic paid-model fallback. Provider account billing settings remain the developer's responsibility.

Run `php artisan ai:refresh-consensus`. The older `php artisan macro:refresh` command remains as a compatible alias. HorizonBias makes at most one Gemini request and one Groq request per refresh, then Laravel calculates agreement, final context, and confidence without another AI call. If one provider is unavailable, confidence is capped and the result is marked partial. If both fail, the last valid brief is retained as stale.

HorizonBias retrieves bounded recent evidence from Federal Reserve monetary-policy releases and speeches, the U.S. Bureau of Economic Analysis, the BLS Public Data API for CPI, payroll employment, unemployment, and producer prices, and the U.S. Treasury's structured press-release catalogue. Both models analyze the same catalogue but may return only server-issued citation IDs. The application hydrates the official headline, evidence timestamp, source name, and allow-listed HTTPS URL; model-generated URLs are never accepted. One unavailable source does not prevent the remaining official sources from being used.

BLS and Treasury evidence requires no API key. BLS API observations explicitly identify their data period and mark their timestamp as retrieval time rather than pretending it is the original release time. Some government sites may temporarily reject automated requests or become unavailable; HorizonBias logs the source-level failure, continues with every working source, and never fabricates replacement news.

The default maximum evidence age is 14 days because major policy and economic releases are not necessarily published every day. If no recent relevant evidence exists, the brief remains technical-only and its event list is empty. Invalid or malformed AI results are rejected. The public dashboard shows both independent assessments, their USD-strength views, the deterministic consensus, disagreement, confidence, limitations, and verified citations.

## Bias history and historical alignment

Apply migrations, canonicalize existing snapshots, and evaluate outcomes already supported by stored candles:

```powershell
php artisan migrate
php artisan bias-history:backfill
php artisan bias-history:evaluate
```

`bias-history:backfill` does not call Twelve Data or either AI provider. Repeated runs are safe. New successful market refreshes automatically capture canonical timeframe and overall history points. The scheduler evaluates newly mature outcomes every 15 minutes and prunes canonical history beyond the configured retention period once daily.

The dashboard lazily reads `GET /api/bias-history` with validated `range` and `scope` parameters. Supported ranges are `24h`, `7d`, `30d`, `90d`, and `1y`; supported scopes are `overall`, `5m`, `15m`, `1h`, `4h`, `1d`, `1w`, and `1mo`.

Historical alignment compares the original bias direction with a later price move normalized by the ATR that was available when the bias was generated. Movement within the configured ATR band is neutral. Alignment remains hidden until the minimum mature sample count is reached and must not be interpreted as profitability, a forecast guarantee, or a trading recommendation.

```dotenv
BIAS_HISTORY_NEUTRAL_ATR=0.25
BIAS_HISTORY_MINIMUM_SAMPLES=20
BIAS_HISTORY_ESTABLISHED_SAMPLES=50
BIAS_HISTORY_RETENTION_DAYS=730
BIAS_HISTORY_MAX_CHART_POINTS=360
```

Gemini and Groq receive the same compact Laravel-calculated historical summary in their existing scheduled requests. This feature does not add a third model or additional routine AI calls, and neither model can change historical statistics or the deterministic technical score.

## Floating Bias Monitor

The Floating Bias Monitor is a compact, responsive decision-support companion that traders can keep visible while operating in external charting software, broker platforms, or other browser tabs.

- **Always-on-top Document Picture-in-Picture**: When the browser exposes the `documentPictureInPicture` API, clicking **Floating Monitor** opens an always-on-top window (initial size 380×560 px).
- **Standard popup fallback**: When that API is unavailable or cannot be used, the monitor attempts to open as a standard compact popup labeled "Standard popup", with an explicit notice that always-on-top behavior is unavailable. Runtime feature detection is authoritative because browser support and permissions can change.
- **User-click activation**: In accordance with browser security specifications, the monitor requires an explicit user click and is never opened automatically or on page load.
- **Opener lifecycle synchronization**: Closing or navigating away from the dashboard parent tab automatically closes the floating window. Clicking the header button while the monitor is already active focuses the existing window rather than opening a duplicate.
- **Zero additional provider overhead**: The monitor reads strictly from the dashboard's in-memory Alpine state and existing 60-second `/api/dashboard` polling loop. It makes no direct requests to Twelve Data, Gemini, or Groq, and creates no background polling loops.
- **Data freshness & network resilience**: When a scheduled dashboard poll completes, the floating monitor immediately re-renders. If network connectivity fails, the monitor retains the last confirmed snapshot and displays an inline connection warning.
- **Security & non-signal boundary**: Dynamic fields are bound strictly with safe DOM text assignment (`textContent`). The monitor adheres to the strict Content Security Policy, does not include the TradingView chart, and contains no buy/sell commands, entry prices, or trade execution.

### Manual testing guide

1. In a browser that supports Document Picture-in-Picture, navigate to `/dashboard` and click **Floating Monitor** in the top navigation bar.
2. Confirm the 380×560 always-on-top window displays the current mode, spot quote, overall bias, 7-timeframe matrix, and AI consensus.
3. Toggle the light/dark theme on the dashboard header and confirm the floating window immediately synchronizes its colors.
4. Click **Return to Dashboard** inside the monitor to verify window focus returns to the main dashboard tab.
5. In an unsupported browser or private browsing environment with PiP disabled, click **Floating Monitor** to confirm the standard popup fallback opens with the explanatory badge.

## Downloadable bias reports

The dashboard's **Report** menu exports one frozen view of the currently stored HorizonBias state. Exporting never refreshes Twelve Data, Gemini, Groq, or the official macro feeds, so it uses no provider credits.

- `/reports/current/print` provides a branded, responsive light/dark print preview with browser Save as PDF support.
- `/reports/current/pdf` downloads a compact fixed-layout PDF.
- `/reports/current/docx` downloads an editable Office Open XML Word document.

Every report includes the report ID, UTC timestamps, mode and freshness context, provider label, deterministic score, timeframe evidence, dual-AI context, historical-alignment summary, methodology boundaries, and the educational risk disclaimer. Demo and stale print reports are visibly marked. Export routes are read-only, rate limited, and generated from stored state only.

## Scheduler deployment

During development use `php artisan schedule:work`. In production, run `php artisan schedule:run` every minute with Windows Task Scheduler or cron. Commands use overlap locks, bounded provider timeouts, and last-known-good fallback behavior.

## Production licensing gate

Production will not display proprietary live bias unless both values are set:

```dotenv
MARKET_MODE=live
MARKET_DATA_EXTERNAL_DISPLAY_LICENSED=true
```

Confirm external-display/derived-data rights with the chosen provider before enabling the flag. Without it, production automatically returns clearly labeled demo analysis. Never remove or alter TradingView attribution, and never extract data from its widget.

## Testing and builds

Tests use in-memory SQLite and mocked HTTP calls; they never contact providers.

```powershell
php artisan test
npm.cmd run build
php artisan route:list
```

Provider outages retain the last successful snapshot with a stale status. If no valid data exists, the API reports unavailable rather than fabricating live analysis.

## Public deployment checklist

- Obtain and document external-display rights for market data.
- Configure production database, HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, and secure secrets.
- Run migrations and build versioned frontend assets.
- Install the one-minute scheduler task and application monitoring.
- Verify CSP access to TradingView and retain its attribution.
- Exercise demo, live, stale, rate-limit, and provider-outage states on desktop and mobile.
- Reconfirm market-data, Gemini, and TradingView terms before launch.

## Risk notice

HorizonBias provides educational market context and technical bias only. It is not financial advice, a trading signal, or a recommendation to buy or sell. Market and AI-generated information may be delayed, incomplete, or inaccurate. Independently verify all information and make your own risk decisions.
