<?php

namespace App\Exports;

use Illuminate\Support\Facades\Response;
use ZipArchive;

class PlantListDocxExport
{
    public function __construct(
        private array $rows,
        private string $title = '植物名錄',
    ) {}

    public function download(string $filename)
    {
        return Response::streamDownload(function () {
            $path = tempnam(sys_get_temp_dir(), 'plant-list-docx-');
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('無法建立 Word 檔案。');
            }
            $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
            $zip->addFromString('_rels/.rels', $this->relsXml());
            $zip->addFromString('word/styles.xml', $this->stylesXml());
            $zip->addFromString('word/document.xml', $this->documentXml());
            $zip->close();
            readfile($path);
            @unlink($path);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    private function documentXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . $this->paragraph($this->title, 'center', true)
            . $this->tableXml()
            . $this->paragraph('●：符合該植物來源類別', 'left')
            . '<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720"/></w:sectPr>'
            . '</w:body></w:document>';
    }

    private function tableXml(): string
    {
        $headings = ['行號', '科名', '學名', '中文名', '原生', '特有', '外來', '栽培', 'IUCN'];
        $widths = [520, 2000, 4800, 2300, 800, 800, 800, 800, 900];
        $xml = '<w:tbl><w:tblPr><w:tblBorders>'
            . '<w:top w:val="single" w:sz="6" w:color="000000"/><w:left w:val="single" w:sz="4" w:color="000000"/>'
            . '<w:bottom w:val="single" w:sz="6" w:color="000000"/><w:right w:val="single" w:sz="4" w:color="000000"/>'
            . '<w:insideH w:val="single" w:sz="4" w:color="000000"/><w:insideV w:val="single" w:sz="4" w:color="000000"/>'
            . '</w:tblBorders></w:tblPr><w:tblGrid>';
        foreach ($widths as $width) $xml .= '<w:gridCol w:w="' . $width . '"/>';
        $xml .= '</w:tblGrid><w:tr><w:trPr><w:tblHeader/></w:trPr>';
        foreach ($headings as $i => $heading) {
            $xml .= $this->cell($heading === '行號' ? '' : $heading, $widths[$i], 'center', true, false, null, $heading === '行號');
        }
        $xml .= '</w:tr>';

        $spans = $this->familySpans();
        foreach (array_values($this->rows) as $index => $row) {
            $wordRow = $index + 2;
            $xml .= '<w:tr>';
            foreach ($headings as $i => $heading) {
                $merge = $heading === '科名' ? ($spans[$wordRow] ?? null) : null;
                $text = $merge === 'continue' ? '' : (string) ($row[$heading] ?? '');
                $center = in_array($heading, ['原生', '特有', '外來', '栽培', 'IUCN'], true);
                $xml .= $this->cell($text, $widths[$i], $heading === '行號' ? 'right' : ($center ? 'center' : 'left'), false, $heading === '學名', $merge, $heading === '行號');
            }
            $xml .= '</w:tr>';
        }
        return $xml . '</w:tbl>';
    }

    private function familySpans(): array
    {
        $groups = [];
        foreach (array_values($this->rows) as $i => $row) $groups[(string) ($row['科名'] ?? '')][] = $i + 2;
        $spans = [];
        foreach ($groups as $family => $positions) {
            if ($family === '' || count($positions) < 2) continue;
            $spans[$positions[0]] = 'restart';
            foreach (array_slice($positions, 1) as $position) $spans[$position] = 'continue';
        }
        return $spans;
    }

    private function cell(string $text, int $width, string $align, bool $bold = false, bool $italic = false, ?string $merge = null, bool $rowNumber = false): string
    {
        $mergeXml = $merge === 'restart' ? '<w:vMerge w:val="restart"/>' : ($merge === 'continue' ? '<w:vMerge/>' : '');
        $borderXml = $rowNumber
            ? '<w:tcBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tcBorders>'
            : '';
        return '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/>' . $mergeXml . $borderXml . '<w:vAlign w:val="top"/></w:tcPr>'
            . $this->paragraph($text, $align, $bold, $italic, $rowNumber ? '777777' : null, $rowNumber ? 18 : 24) . '</w:tc>';
    }

    private function paragraph(string $text, string $align, bool $bold = false, bool $italic = false, ?string $color = null, int $fontSize = 24): string
    {
        $colorXml = $color !== null ? '<w:color w:val="' . $color . '"/>' : '';
        return '<w:p><w:pPr><w:jc w:val="' . $align . '"/></w:pPr><w:r><w:rPr>'
            . '<w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="標楷體"/>'
            . '<w:sz w:val="' . $fontSize . '"/>' . $colorXml . ($bold ? '<w:b/>' : '') . ($italic ? '<w:i/>' : '')
            . '</w:rPr><w:t xml:space="preserve">' . $this->escape($text) . '</w:t></w:r></w:p>';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="標楷體"/><w:sz w:val="24"/></w:rPr></w:rPrDefault></w:docDefaults></w:styles>';
    }
}
