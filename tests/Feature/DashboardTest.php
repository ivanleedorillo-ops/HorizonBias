<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function the_public_landing_page_renders_successfully(): void
    {
        $response = $this->get('/');
        $response->assertOk()
            ->assertSee('HorizonBias')
            ->assertSee('Multi-Timeframe')
            ->assertSee('XAU/USD')
            ->assertSee(route('dashboard'), false)
            ->assertSee('HorizonBias provides educational market context and technical bias only.', false);
    }

    #[Test]
    public function the_landing_page_cta_links_to_dashboard(): void
    {
        $response = $this->get('/');
        $response->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('Open Dashboard');
    }

    #[Test]
    public function the_dashboard_renders_honest_demo_mode_and_links_back_to_home(): void
    {
        config(['horizon.market_mode' => 'demo']);
        $response = $this->get('/dashboard');
        $response->assertOk()
            ->assertSee('HorizonBias')
            ->assertSee('Illustrative demo data')
            ->assertSee('OANDA:XAUUSD', false)
            ->assertSee('href="'.url('/').'"', false)
            ->assertSee('HorizonBias provides educational market context and technical bias only.', false);
    }

    #[Test]
    public function it_persists_session_with_database_session_driver(): void
    {
        config(['session.driver' => 'database']);
        $this->get('/dashboard')->assertOk();
        $this->assertDatabaseCount('sessions', 1);
    }

    #[Test]
    public function the_dashboard_api_has_a_stable_demo_contract(): void
    {
        $this->getJson('/api/dashboard')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertJsonPath('mode', 'demo')
            ->assertJsonPath('symbol', 'XAU/USD')
            ->assertJsonCount(7, 'timeframes')
            ->assertJsonStructure([
                'mode', 'notice', 'symbol',
                'quote' => ['price', 'currency', 'change', 'change_percent', 'as_of'],
                'overall' => ['score', 'label', 'summary', 'generated_at', 'stale'],
                'timeframes',
                'macro' => [
                    'stance', 'gold_bias', 'usd_strength', 'risk_level', 'confidence',
                    'agreement', 'summary', 'limitations', 'analyses', 'provider_status',
                    'events', 'generated_at', 'stale', 'status',
                ],
                'system',
            ]);
        $this->getJson('/api/dashboard')
            ->assertJsonCount(2, 'macro.analyses')
            ->assertJsonPath('system.ai_provider', 'Gemini + Groq GPT-OSS (illustrative)');
    }

    #[Test]
    public function production_requires_the_external_display_license_flag_for_live_mode(): void
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';
        config(['horizon.market_mode' => 'live', 'horizon.external_display_licensed' => false]);
        $this->getJson('/api/dashboard')->assertJsonPath('mode', 'demo')->assertJsonPath('system.licensing_gate_applied', true);
        $this->app['env'] = $original;
    }

    #[Test]
    public function there_are_no_authentication_or_public_mutation_routes(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());
        $this->assertFalse($routes->contains(fn ($route) => str_contains($route->uri(), 'login') || str_contains($route->uri(), 'register')));
        $applicationRoutes = $routes->filter(fn ($route) => in_array($route->uri(), ['/', 'dashboard', 'api/dashboard'], true));
        $this->assertTrue($applicationRoutes->every(fn ($route) => array_diff($route->methods(), ['GET', 'HEAD']) === []));
        $this->assertFalse($routes->contains(fn ($route) => str_contains($route->uri(), 'refresh')));
    }

    #[Test]
    public function the_pages_do_not_offer_executable_trade_instructions(): void
    {
        $landingContent = strtolower($this->get('/')->getContent());
        $dashboardContent = strtolower($this->get('/dashboard')->getContent());

        foreach (['buy now', 'sell now', 'stop-loss', 'take-profit', 'position sizing'] as $phrase) {
            $this->assertStringNotContainsString($phrase, $landingContent);
            $this->assertStringNotContainsString($phrase, $dashboardContent);
        }
    }

    #[Test]
    public function logo_asset_is_present_on_filesystem(): void
    {
        $this->assertFileExists(public_path('images/horizonbias-logo.png'));
    }
}
