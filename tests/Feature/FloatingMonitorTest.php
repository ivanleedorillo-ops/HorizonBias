<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FloatingMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function floating_monitor_button_is_rendered_with_accessible_attributes_on_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertOk()
            ->assertSee('id="floating-monitor-btn"', false)
            ->assertSee('aria-label="Open Floating Bias Monitor"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('aria-busy="false"', false)
            ->assertSee(':aria-label="floatingOpening ? \'Opening Floating Bias Monitor\' : (floatingOpen ? \'Focus Floating Bias Monitor\' : \'Open Floating Bias Monitor\')"', false)
            ->assertSee(':aria-pressed="floatingOpen ? \'true\' : \'false\'"', false)
            ->assertSee(':aria-busy="floatingOpening ? \'true\' : \'false\'"', false)
            ->assertSee(':disabled="floatingOpening"', false)
            ->assertSee('Floating Monitor', false)
            ->assertSee('Opening…', false)
            ->assertSee('Monitor Open', false)
            ->assertSee('openFloatingMonitor()', false)
            ->assertSee('Dashboard checked:', false)
            ->assertSee('Technical data completed through:', false)
            ->assertDontSee('OANDA Feed', false);
    }

    #[Test]
    public function dedicated_floating_monitor_template_exists_with_required_structure(): void
    {
        $response = $this->get('/dashboard');
        $content = $response->getContent();

        $this->assertStringContainsString('id="floating-monitor-template"', $content);
        $this->assertStringContainsString('id="monitor-root"', $content);
        $this->assertStringContainsString('data-monitor="mode-badge"', $content);
        $this->assertStringContainsString('data-monitor="freshness-badge"', $content);
        $this->assertStringContainsString('data-monitor="fallback-banner"', $content);
        $this->assertStringContainsString('data-monitor="quote-price"', $content);
        $this->assertStringContainsString('data-monitor="quote-currency"', $content);
        $this->assertStringContainsString('data-monitor="quote-asof"', $content);
        $this->assertStringContainsString('data-monitor="overall-badge"', $content);
        $this->assertStringContainsString('data-monitor="overall-score"', $content);

        // Check exactly 7 timeframes exist in monitor template with score, badge, and stale elements
        foreach (['5m', '15m', '1h', '4h', '1d', '1w', '1mo'] as $tf) {
            $this->assertStringContainsString('data-monitor="tf-score-'.$tf.'"', $content);
            $this->assertStringContainsString('data-monitor="tf-badge-'.$tf.'"', $content);
            $this->assertStringContainsString('data-monitor="tf-status-'.$tf.'"', $content);
        }

        // Check dual-AI consensus structure
        $this->assertStringContainsString('data-monitor="ai-bias"', $content);
        $this->assertStringContainsString('data-monitor="ai-agreement"', $content);
        $this->assertStringContainsString('data-monitor="ai-confidence"', $content);
        $this->assertStringContainsString('data-monitor="ai-risk"', $content);
        $this->assertStringContainsString('data-monitor="ai-partial"', $content);
        $this->assertStringContainsString('data-monitor="ai-stale"', $content);
        $this->assertStringContainsString('data-monitor="ai-summary"', $content);
        $this->assertStringContainsString('data-monitor="ai-time"', $content);

        // Check action buttons
        $this->assertStringContainsString('data-action="focus-dashboard"', $content);
        $this->assertStringContainsString('data-action="close-monitor"', $content);
        $this->assertStringContainsString('Return to Dashboard', $content);
        $this->assertStringContainsString('Educational bias only — not a trading signal or recommendation. Verify independently.', $content);
        $this->assertSame(7, substr_count($content, 'class="monitor-tf-item'));
        $this->assertStringContainsString('data-tf="5m" role="group"', $content);
    }

    #[Test]
    public function monitor_template_does_not_contain_tradingview_or_indicator_breakdown(): void
    {
        $response = $this->get('/dashboard');
        $content = $response->getContent();

        // Extract template content
        preg_match('/<template id="floating-monitor-template">(.*?)<\/template>/s', $content, $matches);
        $this->assertNotEmpty($matches, 'Template #floating-monitor-template was not found.');

        $templateHtml = $matches[1];

        // TradingView must NOT be in the floating monitor
        $this->assertStringNotContainsString('tradingview', strtolower($templateHtml));
        $this->assertStringNotContainsString('embed-widget-advanced-chart', $templateHtml);
        $this->assertStringNotContainsString('bias-history-chart', $templateHtml);

        // Trade execution must NOT be present
        foreach (['buy now', 'sell now', 'stop-loss', 'take-profit', 'position sizing'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, strtolower($templateHtml));
        }
    }

    #[Test]
    public function frontend_source_implements_document_pip_detection_and_popup_fallback(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($js);

        // Feature detection
        $this->assertStringContainsString("'documentPictureInPicture' in window", $js);
        $this->assertStringContainsString('documentPictureInPicture.requestWindow', $js);

        // Fallback implementation
        $this->assertStringContainsString('openPopupMonitor', $js);
        $this->assertStringContainsString('window.open', $js);
        $this->assertStringContainsString('Standard popup', file_get_contents(resource_path('views/dashboard.blade.php')));

        // Safe DOM assignment using textContent
        $this->assertStringContainsString('textContent =', $js);
        $this->assertStringContainsString('replaceChildren', $js);
        $this->assertStringNotContainsString('innerHTML', $js);

        // Race protection and stale window cleanup
        $this->assertStringContainsString('floatingOpening', $js);
        $this->assertStringContainsString('cleanupFloatingMonitor(expectedWindow', $js);
        $this->assertStringContainsString('Floating Bias Monitor could not be opened', $js);

        // Uses pure view model builder
        $this->assertStringContainsString('buildMonitorViewModel', $js);

        // Theme sync
        $this->assertStringContainsString('syncFloatingTheme', $js);
        $this->assertStringContainsString('focusDashboardFromMonitor', $js);
        $this->assertStringContainsString('closeFloatingMonitor', $js);
        $this->assertStringContainsString('cleanupFloatingMonitor', $js);
    }

    #[Test]
    public function pure_monitor_module_and_node_test_runner_are_configured(): void
    {
        $modulePath = resource_path('js/floating-monitor.js');
        $this->assertFileExists($modulePath);

        $moduleContent = file_get_contents($modulePath);
        $this->assertStringContainsString('export function deriveMonitorStatus', $moduleContent);
        $this->assertStringContainsString('export function normalizeMonitorTimeframe', $moduleContent);
        $this->assertStringContainsString('export function normalizeMonitorAi', $moduleContent);
        $this->assertStringContainsString('export function buildMonitorViewModel', $moduleContent);

        $packageJson = json_decode(file_get_contents(base_path('package.json')), true);
        $this->assertIsArray($packageJson);
        $this->assertArrayHasKey('test:js', $packageJson['scripts'] ?? []);
        $this->assertSame('node --test tests/js/*.test.js', $packageJson['scripts']['test:js']);

        $testPath = base_path('tests/js/floating-monitor.test.js');
        $this->assertFileExists($testPath);
    }

    #[Test]
    public function no_unauthorized_routes_or_third_ai_providers_exist(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());

        // No authentication routes
        $this->assertFalse($routes->contains(fn ($route) => str_contains($route->uri(), 'login') || str_contains($route->uri(), 'register')));

        // Only allowed GET routes for application
        $appRoutes = $routes->filter(fn ($route) => in_array($route->uri(), ['/', 'dashboard', 'api/dashboard', 'api/bias-history'], true));
        $this->assertTrue($appRoutes->every(fn ($route) => array_diff($route->methods(), ['GET', 'HEAD']) === []));

        // No new public refresh endpoint
        $this->assertFalse($routes->contains(fn ($route) => str_contains($route->uri(), 'refresh')));

        // Check API response does not contain third AI provider
        $api = $this->getJson('/api/dashboard');
        $api->assertOk();
        $aiProvider = $api->json('system.ai_provider');
        $this->assertStringNotContainsString('Cerebras', $aiProvider);
        $this->assertStringNotContainsString('BazaarLink', $aiProvider);
        $this->assertStringNotContainsString('Claude', $aiProvider);
    }

    #[Test]
    public function educational_disclaimer_remains_present_on_dashboard(): void
    {
        $disclaimer = 'HorizonBias provides educational market context and technical bias only. It is not financial advice, a trading signal, or a recommendation to buy or sell.';

        $this->get('/dashboard')->assertOk()->assertSee($disclaimer, false);
    }
}
