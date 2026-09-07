# HorizonBias

HorizonBias is a Laravel 12 decision-support dashboard dedicated to XAU/USD. It combines a deterministic, auditable technical score across seven timeframes with a separately displayed Gemini macro brief. It does not provide entries, exits, position sizing, trade execution, or personalized financial advice.

## Architecture

- `MarketDataProvider` normalizes completed candles; the included adapter targets Twelve Data.
- `IndicatorCalculator` calculates EMA, Wilder RSI/ATR/ADX, MACD, ROC, and confirmed pivots locally.
- `BiasScorer` creates component, timeframe, and weighted multi-timeframe scores.
- Scheduled Artisan commands persist candles, bias snapshots, and Gemini macro briefs. HTTP visitors only read stored state.
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
```

Then populate data and keep it refreshed:

```powershell
php artisan market:refresh-bias
php artisan schedule:work
```

Refresh one horizon with `php artisan market:refresh-bias --timeframe=4h`.

## Gemini macro context

```dotenv
GEMINI_API_KEY=your_key
GEMINI_MODEL=gemini-3.7-flash
GEMINI_SEARCH_GROUNDING=true
MACRO_REFRESH_MINUTES=180
```

Run `php artisan macro:refresh`. Grounding availability and billing depend on the Gemini project. Invalid, uncited, unsafe, or malformed results are rejected; the previous valid brief is retained as stale.

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
