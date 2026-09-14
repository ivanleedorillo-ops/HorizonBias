<?php

namespace App\Services\Reports;

use Phar;
use PharData;
use RuntimeException;

final class DocxReportRenderer
{
    public function __construct(private readonly ReportDocumentContent $content) {}

    public function render(array $report): string
    {
        $temporary = tempnam(storage_path('framework/cache'), 'horizonbias-docx-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to create a temporary Word report.');
        }
        @unlink($temporary);
        $archivePath = $temporary.'.zip';

        try {
            $links = $this->sourceLinks($report);
            $archive = new PharData($archivePath, 0, null, Phar::ZIP);
            $archive->addFromString('[Content_Types].xml', $this->contentTypes());
            $archive->addFromString('_rels/.rels', $this->rootRelationships());
            $archive->addFromString('word/document.xml', $this->document($report, $links));
            $archive->addFromString('word/styles.xml', $this->styles());
            $archive->addFromString('word/_rels/document.xml.rels', $this->documentRelationships($links));
            $archive->addFromString('docProps/core.xml', $this->coreProperties($report));
            $archive->addFromString('docProps/app.xml', $this->appProperties());
            unset($archive);

            $bytes = file_get_contents($archivePath);
            if ($bytes === false) {
                throw new RuntimeException('Unable to read the generated Word report.');
            }

            return $bytes;
        } finally {
            if (is_file($archivePath)) {
                @unlink($archivePath);
            }
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function document(array $report, array $links): string
    {
        $body = '';
        foreach ($this->content->blocks($report) as $block) {
            $body .= $this->paragraph((string) $block['text'], $block['style'] === 'heading' ? 'Heading1' : null);
        }
        if ($links !== []) {
            $body .= $this->paragraph('SOURCE LINKS', 'Heading1');
            foreach ($links as $index => $link) {
                $id = 'rId'.($index + 2);
                $body .= '<w:p><w:hyperlink r:id="'.$id.'"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t>'.$this->xml($link['name'].' — '.$link['url']).'</w:t></w:r></w:hyperlink></w:p>';
            }
        }
        $body .= '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1080" w:right="1080" w:bottom="1080" w:left="1080"/></w:sectPr>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'
            .$body.'</w:body></w:document>';
    }

    private function paragraph(string $text, ?string $style = null): string
    {
        $properties = $style ? '<w:pPr><w:pStyle w:val="'.$style.'"/></w:pPr>' : '';

        return '<w:p>'.$properties.'<w:r><w:t xml:space="preserve">'.$this->xml($text).'</w:t></w:r></w:p>';
    }

    private function sourceLinks(array $report): array
    {
        $links = [];
        foreach ($report['macro']['events'] ?? [] as $event) {
            $url = $event['source_url'] ?? null;
            if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                continue;
            }
            $links[$url] = ['name' => (string) ($event['source_name'] ?? 'Source'), 'url' => $url];
        }

        return array_values($links);
    }

    private function documentRelationships(array $links): string
    {
        $relationships = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        foreach ($links as $index => $link) {
            $relationships .= '<Relationship Id="rId'.($index + 2).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="'.$this->xml($link['url']).'" TargetMode="External"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/><w:sz w:val="20"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:before="240" w:after="120"/></w:pPr><w:rPr><w:b/><w:color w:val="A56A00"/><w:sz w:val="28"/></w:rPr></w:style>'
            .'<w:style w:type="character" w:styleId="Hyperlink"><w:name w:val="Hyperlink"/><w:rPr><w:color w:val="0563C1"/><w:u w:val="single"/></w:rPr></w:style>'
            .'</w:styles>';
    }

    private function coreProperties(array $report): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$this->xml($report['title']).'</dc:title><dc:creator>HorizonBias</dc:creator><dc:description>'.$this->xml($report['report_id']).'</dc:description>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$this->xml($report['generated_at']).'</dcterms:created>'
            .'</cp:coreProperties>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>HorizonBias</Application></Properties>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
