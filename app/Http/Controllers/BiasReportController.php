<?php

namespace App\Http\Controllers;

use App\Services\Reports\BiasReportService;
use App\Services\Reports\DocxReportRenderer;
use App\Services\Reports\SimplePdfReportRenderer;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BiasReportController extends Controller
{
    public function __invoke(
        string $format,
        BiasReportService $reports,
        DocxReportRenderer $docx,
        SimplePdfReportRenderer $pdf,
    ): Response|View {
        $report = $reports->build();

        return match ($format) {
            'print' => response()->view('reports.bias', ['report' => $report])
                ->header('Cache-Control', 'no-store, private'),
            'docx' => response($docx->render($report), 200, $this->downloadHeaders(
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                $reports->filename($report, 'docx'),
            )),
            'pdf' => response($pdf->render($report), 200, $this->downloadHeaders(
                'application/pdf',
                $reports->filename($report, 'pdf'),
            )),
        };
    }

    private function downloadHeaders(string $contentType, string $filename): array
    {
        return [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private',
        ];
    }
}
