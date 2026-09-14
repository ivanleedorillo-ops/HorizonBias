<?php

namespace App\Services\Reports;

final class SimplePdfReportRenderer
{
    public function __construct(private readonly ReportDocumentContent $content) {}

    public function render(array $report): string
    {
        $lines = [];
        foreach ($this->content->blocks($report) as $block) {
            $style = (string) $block['style'];
            $width = match ($style) {
                'title' => 52,
                'heading', 'key' => 76,
                'matrix', 'meta', 'source' => 105,
                default => 92,
            };
            foreach ($this->wrap((string) $block['text'], $width) as $index => $text) {
                $lines[] = ['text' => $text, 'style' => $style, 'continuation' => $index > 0];
            }
        }

        $pages = $this->paginate($lines);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $pageReferences = [];

        foreach ($pages as $index => $pageLines) {
            $pageObject = 5 + ($index * 2);
            $contentObject = $pageObject + 1;
            $pageReferences[] = "{$pageObject} 0 R";
            $stream = $this->pageStream($pageLines, $index + 1, count($pages), $report['report_id']);
            $objects[$pageObject] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObject} 0 R >>";
            $objects[$contentObject] = '<< /Length '.strlen($stream).">>\nstream\n{$stream}\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ', $pageReferences).'] >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($number = 1; $number <= count($objects); $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    private function paginate(array $lines): array
    {
        $pages = [];
        $page = [];
        $used = 0;
        foreach ($lines as $line) {
            $height = $this->lineHeight($line['style'], $line['continuation']);
            if ($page !== [] && $used + $height > 690) {
                $pages[] = $page;
                $page = [];
                $used = 0;
            }
            $page[] = $line;
            $used += $height;
        }
        if ($page !== [] || $pages === []) {
            $pages[] = $page;
        }

        return $pages;
    }

    private function pageStream(array $lines, int $page, int $totalPages, string $reportId): string
    {
        $commands = [
            '0.035 0.055 0.095 rg 0 790 595 52 re f',
            '0.92 0.65 0.12 rg 0 787 595 3 re f',
            '1 1 1 rg BT /F2 13 Tf 1 0 0 1 40 814 Tm (HorizonBias) Tj ET',
            '0.72 0.77 0.84 rg BT /F1 8 Tf 1 0 0 1 455 814 Tm (XAU/USD REPORT) Tj ET',
        ];
        $y = 760;

        foreach ($lines as $line) {
            $style = $line['style'];
            $continuation = (bool) $line['continuation'];
            $text = $line['text'];
            $height = $this->lineHeight($style, $continuation);

            if ($style === 'heading' && ! $continuation) {
                $y -= 9;
                $commands[] = "0.92 0.65 0.12 rg 40 ".($y - 2).' 4 14 re f';
                $commands[] = '0.08 0.11 0.18 rg BT /F2 10 Tf 1 0 0 1 51 '.$y.' Tm ('.$this->escape($text).') Tj ET';
            } else {
                [$font, $size, $color, $x] = match ($style) {
                    'title' => ['F2', 20, '0.08 0.11 0.18', 40],
                    'subtitle' => ['F1', 10, '0.35 0.40 0.49', 40],
                    'meta' => ['F1', 8, '0.39 0.44 0.52', 40],
                    'key' => ['F2', 9.5, '0.08 0.11 0.18', 40],
                    'matrix' => ['F1', 8.2, '0.18 0.23 0.32', 46],
                    'bullet' => ['F1', 9, '0.18 0.23 0.32', $continuation ? 52 : 46],
                    'source' => ['F1', 7.5, '0.12 0.35 0.58', 40],
                    'notice' => ['F2', 8.5, '0.50 0.34 0.04', 46],
                    default => ['F1', 9, '0.18 0.23 0.32', 40],
                };
                if ($style === 'bullet' && ! $continuation) {
                    $commands[] = '0.92 0.65 0.12 rg 40 '.($y + 2).' 3 3 re f';
                }
                if ($style === 'notice' && ! $continuation) {
                    $commands[] = '1 0.97 0.88 rg 40 '.($y - 4).' 515 15 re f';
                }
                $commands[] = "{$color} rg BT /{$font} {$size} Tf 1 0 0 1 {$x} {$y} Tm (".$this->escape($text).') Tj ET';
            }
            $y -= $height;
        }

        $footer = $this->escape("{$reportId}  |  Page {$page} of {$totalPages}  |  Educational context only");
        $commands[] = '0.82 0.84 0.88 RG 40 40 m 555 40 l S';
        $commands[] = "0.39 0.44 0.52 rg BT /F1 7.5 Tf 1 0 0 1 40 25 Tm ({$footer}) Tj ET";

        return implode("\n", $commands);
    }

    private function lineHeight(string $style, bool $continuation): int
    {
        if ($continuation) {
            return match ($style) {
                'title' => 23,
                'heading' => 14,
                default => 12,
            };
        }

        return match ($style) {
            'title' => 30,
            'subtitle' => 18,
            'heading' => 27,
            'key' => 17,
            'notice' => 18,
            'meta', 'source' => 12,
            default => 14,
        };
    }

    private function wrap(string $text, int $width): array
    {
        if ($text === '') {
            return [''];
        }

        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if ($current !== '' && mb_strwidth($candidate) > $width) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [''];
    }

    private function escape(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        $encoded = $encoded === false ? $text : $encoded;
        $encoded = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $encoded) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }
}
