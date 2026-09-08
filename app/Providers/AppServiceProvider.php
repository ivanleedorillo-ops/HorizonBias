<?php

namespace App\Providers;

use App\Contracts\MarketDataProvider;
use App\Services\Market\TwelveDataMarketDataProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MarketDataProvider::class, TwelveDataMarketDataProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('dashboard-api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
