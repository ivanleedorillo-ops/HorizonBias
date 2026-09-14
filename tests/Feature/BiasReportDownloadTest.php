<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PharData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiasReportDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    #[Test]
    public function dashboard_has_responsive_report_download_controls(): void
    {
        $this->get('/dashboard')->assertOk()
            ->assertSee('Download current bias report', false)
            ->assertSee('Download Bias Report', false)
            ->assertSee('PDF report', false)
            ->assertSee('Word document', false)
            ->assertDontSee('CSV evidence', false)
            ->assertDontSee('JSON evidence', false)
            ->assertSee('No market-data or AI credits are used.', false);
    }

    #[Test]
    public function printable_report_is_self_contained_themeable_and_honest_about_demo_data(): void
    {
        $this->get('/reports/current/print')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('HorizonBias XAU/USD Bias Report')
            ->assertSee('ILLUSTRATIVE DEMO')
            ->assertSee('Seven-timeframe technical evidence')
            ->assertSee('Macroeconomic brief')
            ->assertSee('Bias history and historical alignment')
            ->assertSee('HorizonBias provides educational market context and technical bias only.')
            ->assertSee('Print / Save as PDF')
            ->assertSee('Report color theme', false)
            ->assertSee('data-theme-option="light"', false)
            ->assertSee('data-theme-option="dark"', false)
            ->assertSee("localStorage.setItem('horizon_report_theme'", false)
            ->assertSee('@page { size: A4 landscape;', false);
    }

    #[Test]
    public function csv_and_json_exports_are_not_publicly_available(): void
    {
        $this->get('/reports/current/csv')->assertNotFound();
        $this->get('/reports/current/json')->assertNotFound();
    }

    #[Test]
    public function pdf_export_is_a_downloadable_pdf_document(): void
    {
        $response = $this->get('/reports/current/pdf');
        $content = $response->getContent();

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $content);
        $this->assertStringContainsString('HorizonBias XAU/USD Bias Report', $content);
        $this->assertStringEndsWith("%%EOF\n", $content);
        $this->assertGreaterThan(3000, strlen($content));
    }

    #[Test]
    public function word_export_is_a_downloadable_docx_archive(): void
    {
        $response = $this->get('/reports/current/docx');
        $content = $response->getContent();

        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertStringStartsWith('PK', $content);
        $this->assertStringContainsString('[Content_Types].xml', $content);
        $this->assertStringContainsString('word/document.xml', $content);
        $this->assertGreaterThan(3000, strlen($content));

        $archivePath = storage_path('framework/cache/test-horizonbias-report.zip');
        file_put_contents($archivePath, $content);
        try {
            $archive = new PharData($archivePath);
            $this->assertTrue(isset($archive['[Content_Types].xml']));
            $this->assertTrue(isset($archive['word/document.xml']));
            $this->assertTrue(isset($archive['word/styles.xml']));
            $this->assertStringContainsString('HorizonBias XAU/USD Bias Report', $archive['word/document.xml']->getContent());
            $this->assertStringContainsString('educational market context', $archive['word/document.xml']->getContent());
            unset($archive);
        } finally {
            if (is_file($archivePath)) {
                unlink($archivePath);
            }
        }
    }

    #[Test]
    public function unknown_report_formats_are_not_routable(): void
    {
        $this->get('/reports/current/exe')->assertNotFound();
    }
}
