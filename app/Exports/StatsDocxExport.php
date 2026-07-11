<?php

namespace App\Exports;

use App\Models\PlotList2025;
use App\Support\StatsTablesBuilder;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use ZipArchive;

class StatsDocxExport
{
    private const FONT_EAST_ASIA = '標楷體';
    private const FONT_ASCII = 'Times New Roman';
    private const FONT_SIZE = 24; // half-points: 24 = 12pt
    private const PORTRAIT_WIDTH = 11906;
    private const LANDSCAPE_WIDTH = 16838;
    private const PAGE_HEIGHT = 16838;
    private const MARGIN_TOP_BOTTOM = 1440; // 2.54 cm
    private const MARGIN_LEFT_RIGHT = 1803; // 3.18 cm

    private array $media = [];

    public function __construct(private array $selectedPlots) {}

    public function download(string $filename)
    {
        $content = $this->build();

        return Response::streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function build(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'stats-docx-');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $documentXml = $this->documentXml();
        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('word/_rels/document.xml.rels', $this->documentRelsXml());
        $zip->addFromString('word/styles.xml', $this->stylesXml());
        foreach ($this->media as $media) {
            $zip->addFromString('word/media/' . $media['name'], $media['content']);
        }
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        $content = file_get_contents($tmp);
        @unlink($tmp);

        return $content ?: '';
    }

    private function documentXml(): string
    {
        $body = [];
        $sections = array_values(array_filter(
            StatsTablesBuilder::build($this->selectedPlots),
            fn($section) => !$this->isSkippedSection($section)
        ));
        $lastIndex = count($sections) - 1;
        $tableNo = 1;

        foreach ($sections as $index => $section) {
            $orientation = $this->sectionOrientation($section);

            if ($this->isHabitatIvSection($section)) {
                $body[] = $this->caption('表 ' . $tableNo . ' ' . $this->sectionTitle($section, $tableNo));
                $body[] = $this->habitatIvTable($section, 0, 7);

                $habitatTotal = max(0, count(($section['headings'] ?? [])) - 1);
                for ($offset = 7; $offset < $habitatTotal; $offset += 4) {
                    $body[] = $this->paragraph('');
                    $body[] = $this->caption('續表' . $tableNo . $this->sectionTitle($section, $tableNo));
                    $body[] = $this->habitatIvTable($section, $offset, min(4, $habitatTotal - $offset));
                }
                $tableNo++;
            } else {
                $body[] = $this->caption('表 ' . $tableNo . ' ' . $this->sectionTitle($section, $tableNo));
                $body[] = $this->sectionTable($section, $tableNo);
                $tableNo++;
            }
            $body[] = $this->paragraph('');

            if ($index < $lastIndex) {
                $body[] = $this->sectionBreak($orientation);
            } else {
                $body[] = $this->sectionProperties($orientation);
            }
        }

        if (empty($sections)) {
            $body[] = $this->paragraph('無資料');
            $body[] = $this->sectionProperties('portrait');
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<w:body>' . implode('', $body) . '</w:body></w:document>';
    }

    private function isSkippedSection(array $section): bool
    {
        return !empty($section['chartSpec']);
    }

    private function isHabitatIvSection(array $section): bool
    {
        return ($section['title'] ?? '') === '生育地歸化物種IV';
    }

    private function sectionOrientation(array $section): string
    {
        return $this->isHabitatIvSection($section) ? 'landscape' : 'portrait';
    }

    private function sectionTitle(array $section, int $tableNo): string
    {
        $county = $this->countyLabel();

        return match ((string) ($section['title'] ?? '')) {
            '類群×特性（全部）' => $county . '調查記錄之全部植物習性統計',
            '類群×特性（歸化）' => $county . '調查記錄之歸化物種習性統計',
            '生育地多樣性指數' => $county . '地區各生育地類型之原生、歸化物種各項統計一覽表',
            '生育地歸化物種IV' => $county . '地區各生育地歸化物種（依重要值排序）',
            '草本小樣方歸化物種重要數值表' => $county . '地區草本小樣方之歸化物種重要數值一覽表（依 IVI 重要值排序）',
            '木本小樣方歸化物種重要數值表' => $county . '地區木本小樣方之歸化物種重要數值一覽表（依 IVI 重要值排序）',
            default => $this->dynamicSectionTitle($section),
        };
    }

    private function dynamicSectionTitle(array $section): string
    {
        $title = (string) ($section['title'] ?? '');
        if (str_ends_with($title, '低海拔IVI比較')) {
            $county = (string) ($section['countyLabel'] ?? $this->countyLabel());
            $plotCount = (int) ($section['plotCount'] ?? 0);
            $countText = $plotCount > 0 ? $plotCount . '處' : '';
            return '針對' . $county . '海拔500 m以下的' . $countText . '平地樣區，比較本次調查全部物種與前次調查的優勢度排序情形。';
        }

        return $title;
    }

    private function countyLabel(): string
    {
        $plots = array_values(array_filter($this->selectedPlots, fn($plot) => $plot !== null && $plot !== ''));
        if (empty($plots)) {
            return '選取縣市';
        }

        $counties = PlotList2025::query()
            ->whereIn('plot', $plots)
            ->whereNotNull('county')
            ->distinct()
            ->orderBy('county')
            ->pluck('county')
            ->filter()
            ->values()
            ->all();

        $allCounties = PlotList2025::query()
            ->whereNotNull('county')
            ->distinct()
            ->orderBy('county')
            ->pluck('county')
            ->filter()
            ->values()
            ->all();

        if (!empty($allCounties) && $counties === $allCounties) {
            return '全部縣市';
        }

        return !empty($counties) ? implode('、', $counties) : '選取縣市';
    }

    private function sectionBreak(string $orientation): string
    {
        return '<w:p><w:pPr>' . $this->sectionProperties($orientation, true) . '</w:pPr></w:p>';
    }

    private function sectionProperties(string $orientation, bool $nextPage = false): string
    {
        $type = $nextPage ? '<w:type w:val="nextPage"/>' : '';
        $pageSize = $orientation === 'landscape'
            ? '<w:pgSz w:w="' . self::LANDSCAPE_WIDTH . '" w:h="' . self::PORTRAIT_WIDTH . '" w:orient="landscape"/>'
            : '<w:pgSz w:w="' . self::PORTRAIT_WIDTH . '" w:h="' . self::PAGE_HEIGHT . '"/>';

        return '<w:sectPr>' . $type . $pageSize
            . '<w:pgMar w:top="' . self::MARGIN_TOP_BOTTOM . '" w:right="' . self::MARGIN_LEFT_RIGHT . '" w:bottom="' . self::MARGIN_TOP_BOTTOM . '" w:left="' . self::MARGIN_LEFT_RIGHT . '" w:header="720" w:footer="720" w:gutter="0"/>'
            . '</w:sectPr>';
    }

    private function sectionTable(array $section, int $tableNo): string
    {
        $title = (string) ($section['title'] ?? '');
        $headings = $section['headings'] ?? null;
        $rows = $section['rows'] ?? [];

        if (is_array($headings) && in_array('分組', $headings, true) && in_array('隸屬特性', $headings, true)) {
            return $this->groupedTaxonTable($headings, $rows);
        }

        if ($title === '生育地多樣性指數') {
            return $this->shannonTable($rows);
        }

        if (in_array($title, ['草本小樣方歸化物種重要數值表', '木本小樣方歸化物種重要數值表'], true)) {
            return $this->iviTable($headings, $rows);
        }

        if (str_ends_with($title, '低海拔IVI比較')) {
            return $this->iviComparisonTable($rows);
        }

        return $this->table($headings, $rows, null, 'portrait');
    }

    private function groupedTaxonTable(array $headings, array $rows): string
    {
        if (empty($rows)) {
            return $this->paragraph('無資料');
        }

        $valueHeadings = array_values(array_filter($headings, fn($h) => !in_array($h, ['分組', '隸屬特性'], true)));
        $groupCounts = [];
        foreach ($rows as $row) {
            $group = (string) ((array) $row)['分組'];
            $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
        }
        $seenGroups = [];
        $widths = $this->widthsFromPercentages([5.1, 15.3, 14.2, 14.2, 14.2, 14.2, 14.2, 8.3], 'portrait');

        $xml = $this->tableStart($widths);
        $xml .= '<w:tr>'
            . $this->cell('隸屬特性', ['gridSpan' => 2, 'width' => $widths[0] + $widths[1], 'align' => 'center'])
            . implode('', array_map(fn($index, $heading) => $this->cell($this->headerText($heading), ['width' => $widths[$index + 2], 'align' => 'center']), array_keys($valueHeadings), $valueHeadings))
            . '</w:tr>';

        foreach ($rows as $row) {
            $row = (array) $row;
            $group = (string) ($row['分組'] ?? '');
            $isFirst = !isset($seenGroups[$group]);
            $seenGroups[$group] = true;

            $xml .= '<w:tr>';
            $xml .= $isFirst
                ? $this->cell($group, ['vMerge' => 'restart', 'width' => $widths[0], 'align' => 'center'])
                : $this->cell('', ['vMerge' => 'continue', 'width' => $widths[0], 'align' => 'center']);
            $xml .= $this->cell($row['隸屬特性'] ?? '', ['width' => $widths[1]]);
            foreach ($valueHeadings as $index => $heading) {
                $xml .= $this->cell($row[$heading] ?? '', ['width' => $widths[$index + 2]]);
            }
            $xml .= '</w:tr>';
        }

        return $xml . '</w:tbl>';
    }

    private function shannonTable(array $rows): string
    {
        if (empty($rows)) {
            return $this->paragraph('無資料');
        }

        $baseHeadings = [
            ['key' => '生育地類型', 'label' => "生育地\n類型"],
            ['key' => '原生種數', 'label' => "原生\n種數"],
            ['key' => '歸化種數', 'label' => "歸化\n種數"],
            ['key' => '栽培種數', 'label' => "栽培\n種數"],
            ['key' => '歸化種數比例(%)', 'label' => "歸化種\n數比例\n（%）"],
            ['key' => '歸化物種平均覆蓋度(%)', 'label' => "歸化物\n種平均\n覆蓋度\n（%）"],
        ];
        $shannonHeadings = [
            ['key' => 'Shannon_原生物種', 'label' => "原生\n物種"],
            ['key' => 'Shannon_歸化物種', 'label' => "歸化\n物種"],
            ['key' => 'Shannon_全部物種', 'label' => "全部\n物種"],
        ];
        $widths = $this->widthsFromPercentages([18, 9.5, 9.5, 9.5, 11.7, 11.7, 10.03, 10.03, 10.04], 'portrait');

        $xml = $this->tableStart($widths);
        $xml .= '<w:tr>';
        foreach ($baseHeadings as $i => $heading) {
            $xml .= $this->cell($heading['label'], ['vMerge' => 'restart', 'width' => $widths[$i], 'align' => 'center']);
        }
        $xml .= $this->cell('Shannon index', ['gridSpan' => 3, 'width' => $widths[6] + $widths[7] + $widths[8], 'align' => 'center']);
        $xml .= '</w:tr>';

        $xml .= '<w:tr>';
        foreach ($baseHeadings as $i => $_) {
            $xml .= $this->cell('', ['vMerge' => 'continue', 'width' => $widths[$i], 'align' => 'center']);
        }
        foreach ($shannonHeadings as $i => $heading) {
            $xml .= $this->cell($heading['label'], ['width' => $widths[$i + 6], 'align' => 'center']);
        }
        $xml .= '</w:tr>';

        foreach ($rows as $row) {
            $row = (array) $row;
            $xml .= '<w:tr>';
            foreach (array_merge($baseHeadings, $shannonHeadings) as $i => $heading) {
                $xml .= $this->cell($row[$heading['key']] ?? '', ['width' => $widths[$i]]);
            }
            $xml .= '</w:tr>';
        }

        return $xml . '</w:tbl>';
    }

    private function habitatIvTable(array $section, int $habitatOffset, int $habitatCount): string
    {
        $headings = array_values($section['headings'] ?? []);
        $rows = $section['rows'] ?? [];
        if (empty($headings) || empty($rows)) {
            return $this->paragraph('無資料');
        }

        $habitats = array_slice(array_values(array_slice($headings, 1)), $habitatOffset, $habitatCount);
        $percentages = array_merge([3.7], array_fill(0, count($habitats), 13.7));
        $widths = $this->widthsFromPercentages($percentages, 'landscape');
        $lastFilled = [];
        foreach ($habitats as $habitat) {
            $last = 0;
            foreach ($rows as $row) {
                $row = (array) $row;
                $rank = (int) ($row['排名'] ?? 0);
                if (!$this->isBlankHabitatIvValue($row[$habitat] ?? '')) {
                    $last = max($last, $rank);
                }
            }
            $lastFilled[$habitat] = $last;
        }

        $xml = $this->tableStart($widths, $habitatOffset > 0 ? 'left' : 'center');
        $xml .= '<w:tr>' . $this->cell('', ['width' => $widths[0], 'align' => 'center']);
        foreach ($habitats as $i => $habitat) {
            $xml .= $this->cell($habitat, ['width' => $widths[$i + 1], 'align' => 'center']);
        }
        $xml .= '</w:tr>';

        for ($rank = 1; $rank <= 10; $rank++) {
            $row = collect($rows)->first(fn($item) => (int) (((array) $item)['排名'] ?? 0) === $rank);
            $row = (array) ($row ?? ['排名' => $rank]);
            $xml .= '<w:tr>' . $this->cell($rank, ['width' => $widths[0], 'align' => 'center']);
            foreach ($habitats as $i => $habitat) {
                $value = $row[$habitat] ?? '';
                if ($rank > ($lastFilled[$habitat] ?? 0)) {
                    $merge = $rank === (($lastFilled[$habitat] ?? 0) + 1) ? 'restart' : 'continue';
                    $xml .= $this->cell('', [
                        'width' => $widths[$i + 1],
                        'vMerge' => $merge,
                        'diagonal' => $merge === 'restart',
                        'align' => 'center',
                    ]);
                } else {
                    $xml .= $this->cell($value, ['width' => $widths[$i + 1], 'align' => 'center']);
                }
            }
            $xml .= '</w:tr>';
        }

        return $xml . '</w:tbl>';
    }


    private function iviTable(?array $headings, array $rows): string
    {
        if (empty($rows)) {
            return $this->paragraph('無資料');
        }

        $headings = $headings ?: array_keys((array) reset($rows));
        $widths = $this->widthsFromPercentages([20.4, 32.2, 11.8, 11.8, 11.8, 11.8], 'portrait');

        return $this->table($headings, $rows, $widths, 'portrait', [
            '中文名' => 'left',
            '學名' => 'left',
            '平均覆蓋度(%)' => 'right',
            '相對覆蓋度(%)' => 'right',
            '相對頻度(%)' => 'right',
            'IVI 重要值(%)' => 'right',
        ], true);
    }


    private function iviComparisonTable(array $rows): string
    {
        if (empty($rows)) {
            return $this->paragraph('無資料');
        }

        $widths = $this->widthsFromPercentages([15.3, 20.5, 8, 8, 8, 8, 8, 8, 8, 8], 'portrait');
        $subHeadings = ["相對\n覆蓋\n度(%)", "相對\n頻度\n(%)", "IVI\n重要\n值(%)", '名次'];

        $xml = $this->tableStart($widths, 'center', true, true);
        $xml .= $this->trStart(true)
            . $this->cell('中文名', ['vMerge' => 'restart', 'width' => $widths[0], 'align' => 'center'])
            . $this->cell('學名', ['vMerge' => 'restart', 'width' => $widths[1], 'align' => 'center'])
            . $this->cell('本次調查', ['gridSpan' => 4, 'width' => array_sum(array_slice($widths, 2, 4)), 'align' => 'center', 'bottomBorder' => true])
            . $this->cell('前次調查', ['gridSpan' => 4, 'width' => array_sum(array_slice($widths, 6, 4)), 'align' => 'center', 'shading' => 'D9D9D9', 'bottomBorder' => true])
            . '</w:tr>';

        $xml .= $this->trStart(true)
            . $this->cell('', ['vMerge' => 'continue', 'width' => $widths[0], 'align' => 'center', 'bottomBorder' => true])
            . $this->cell('', ['vMerge' => 'continue', 'width' => $widths[1], 'align' => 'center', 'bottomBorder' => true]);
        foreach ($subHeadings as $i => $heading) {
            $xml .= $this->cell($heading, ['width' => $widths[$i + 2], 'align' => 'center', 'bottomBorder' => true]);
        }
        foreach ($subHeadings as $i => $heading) {
            $xml .= $this->cell($heading, ['width' => $widths[$i + 6], 'align' => 'center', 'shading' => 'D9D9D9', 'bottomBorder' => true]);
        }
        $xml .= '</w:tr>';

        foreach ($rows as $row) {
            $row = (array) $row;
            $xml .= '<w:tr>'
                . $this->cell($row['中文名'] ?? '', ['width' => $widths[0], 'align' => 'left', 'vAlign' => 'top'])
                . $this->cell($row['學名'] ?? '', ['width' => $widths[1], 'align' => 'left', 'vAlign' => 'top'])
                . $this->cell($row['本次調查_相對覆蓋度(%)'] ?? '', ['width' => $widths[2], 'align' => 'right', 'vAlign' => 'top'])
                . $this->cell($row['本次調查_相對頻度(%)'] ?? '', ['width' => $widths[3], 'align' => 'right', 'vAlign' => 'top'])
                . $this->cell($row['本次調查_IVI重要值(%)'] ?? '', ['width' => $widths[4], 'align' => 'right', 'vAlign' => 'top'])
                . $this->cell($row['本次調查_名次'] ?? '', ['width' => $widths[5], 'align' => 'center', 'vAlign' => 'top'])
                . $this->cell($row['前次調查_相對覆蓋度(%)'] ?? '', ['width' => $widths[6], 'align' => 'right', 'shading' => 'D9D9D9', 'vAlign' => 'top'])
                . $this->cell($row['前次調查_相對頻度(%)'] ?? '', ['width' => $widths[7], 'align' => 'right', 'shading' => 'D9D9D9', 'vAlign' => 'top'])
                . $this->cell($row['前次調查_IVI重要值(%)'] ?? '', ['width' => $widths[8], 'align' => 'right', 'shading' => 'D9D9D9', 'vAlign' => 'top'])
                . $this->cell($row['前次調查_名次'] ?? '', ['width' => $widths[9], 'align' => 'center', 'shading' => 'D9D9D9', 'vAlign' => 'top'])
                . '</w:tr>';
        }

        return $xml . '</w:tbl>';
    }

    private function table(?array $headings, array $rows, ?array $widths, string $orientation, array $bodyAlignments = [], bool $repeatHeader = false): string
    {
        if (empty($rows)) {
            return $this->paragraph('無資料');
        }

        $headings = $headings ?: array_keys((array) reset($rows));
        $headings = array_values($headings);
        $widths ??= $this->equalWidths(count($headings), $orientation);

        $xml = $this->tableStart($widths);
        $xml .= $this->trStart($repeatHeader);
        foreach ($headings as $i => $heading) {
            $xml .= $this->cell($this->headerText($heading), ['width' => $widths[$i] ?? null, 'align' => 'center']);
        }
        $xml .= '</w:tr>';

        foreach ($rows as $row) {
            $row = (array) $row;
            $xml .= '<w:tr>';
            if ($this->isIviGroupRow($row, $headings)) {
                $xml .= $this->cell($row[$headings[0]] ?? '', [
                    'width' => array_sum($widths),
                    'gridSpan' => count($headings),
                    'align' => 'left',
                ]);
            } else {
                foreach ($headings as $i => $heading) {
                    $xml .= $this->cell($row[$heading] ?? '', array_filter(['width' => $widths[$i] ?? null, 'align' => $bodyAlignments[$heading] ?? null]));
                }
            }
            $xml .= '</w:tr>';
        }

        return $xml . '</w:tbl>';
    }

    private function isIviGroupRow(array $row, array $headings): bool
    {
        $firstHeading = $headings[0] ?? null;
        if ($firstHeading === null) {
            return false;
        }

        $label = trim($this->plainText($row[$firstHeading] ?? ''));
        if (!preg_match('/^\[[^\]]+\]$/u', $label)) {
            return false;
        }

        foreach (array_slice($headings, 1) as $heading) {
            if (trim($this->plainText($row[$heading] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function tableStart(array $widths, string $alignment = 'center', bool $noVerticalBorders = false, bool $noInsideHorizontal = false): string
    {
        $grid = '<w:tblGrid>';
        foreach ($widths as $width) {
            $grid .= '<w:gridCol w:w="' . (int) $width . '"/>';
        }
        $grid .= '</w:tblGrid>';

        $cellMar = $noVerticalBorders
            ? '<w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="0" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar>'
            : '<w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="108" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="142" w:type="dxa"/></w:tblCellMar>';
        $verticalBorder = $noVerticalBorders ? 'none' : 'single';
        $insideHBorder = $noInsideHorizontal ? 'none' : 'single';

        return '<w:tbl><w:tblPr>'
            . '<w:jc w:val="' . $this->escape($alignment) . '"/>'
            . '<w:tblW w:w="0" w:type="auto"/>'
            . '<w:tblLayout w:type="fixed"/>'
            . $cellMar
            . '<w:tblBorders>'
            . '<w:top w:val="single" w:sz="6" w:space="0" w:color="000000"/>'
            . '<w:left w:val="' . $verticalBorder . '" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="single" w:sz="6" w:space="0" w:color="000000"/>'
            . '<w:right w:val="' . $verticalBorder . '" w:sz="0" w:space="0" w:color="auto"/>'
            . '<w:insideH w:val="' . $insideHBorder . '" w:sz="' . ($noInsideHorizontal ? '0' : '6') . '" w:space="0" w:color="' . ($noInsideHorizontal ? 'auto' : '000000') . '"/>'
            . '<w:insideV w:val="' . $verticalBorder . '" w:sz="0" w:space="0" w:color="auto"/>'
            . '</w:tblBorders><w:tblLook w:val="04A0" w:firstRow="1" w:noHBand="1" w:noVBand="1"/></w:tblPr>'
            . $grid;
    }


    private function trStart(bool $repeatHeader = false): string
    {
        return $repeatHeader ? '<w:tr><w:trPr><w:tblHeader/></w:trPr>' : '<w:tr>';
    }

    private function cell(mixed $value, array $options = []): string
    {
        $width = isset($options['width']) ? (int) $options['width'] : 1800;
        $vAlign = $options['vAlign'] ?? 'center';
        $tcPr = '<w:tcW w:w="' . $width . '" w:type="dxa"/><w:vAlign w:val="' . $this->escape((string) $vAlign) . '"/>';
        if (isset($options['gridSpan'])) {
            $tcPr .= '<w:gridSpan w:val="' . (int) $options['gridSpan'] . '"/>';
        }
        if (($options['vMerge'] ?? null) === 'restart') {
            $tcPr .= '<w:vMerge w:val="restart"/>';
        } elseif (($options['vMerge'] ?? null) === 'continue') {
            $tcPr .= '<w:vMerge/>';
        }
        $cellBorders = [];
        if (!empty($options['diagonal'])) {
            $cellBorders[] = '<w:tl2br w:val="single" w:sz="6" w:space="0" w:color="000000"/>';
        }
        if (!empty($options['bottomBorder'])) {
            $cellBorders[] = '<w:bottom w:val="single" w:sz="6" w:space="0" w:color="000000"/>';
        }
        if (!empty($cellBorders)) {
            $tcPr .= '<w:tcBorders>' . implode('', $cellBorders) . '</w:tcBorders>';
        }
        if (!empty($options['textDirection'])) {
            $tcPr .= '<w:textDirection w:val="' . $this->escape((string) $options['textDirection']) . '"/>';
        }
        if (!empty($options['shading'])) {
            $tcPr .= '<w:shd w:val="clear" w:color="auto" w:fill="' . $this->escape((string) $options['shading']) . '"/>';
        }

        $align = $options['align'] ?? $this->cellAlignment($value);
        $runs = $this->runs($value);

        return '<w:tc><w:tcPr>' . $tcPr . '</w:tcPr>'
            . '<w:p><w:pPr><w:jc w:val="' . $align . '"/><w:spacing w:before="0" w:after="0" w:line="300" w:lineRule="auto"/></w:pPr>'
            . implode('', $runs)
            . '</w:p></w:tc>';
    }

    private function cellAlignment(mixed $value): string
    {
        $text = $this->plainText($value);
        if ($text !== '' && preg_match('/\p{Han}/u', $text)) {
            return 'center';
        }
        if ($text !== '' && preg_match('/^-?\d+(?:\.\d+)?%?$/u', trim($text))) {
            return 'right';
        }

        return 'left';
    }

    private function isBlankHabitatIvValue(mixed $value): bool
    {
        $text = trim($this->plainText($value));

        return $text === '' || $text === '-';
    }

    private function caption(string $text): string
    {
        return $this->paragraph($text, 'Caption');
    }

    private function imageParagraph(string $rid, int $cx, int $cy): string
    {
        return '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="0" w:after="80"/></w:pPr>'
            . '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<wp:effectExtent l="0" t="0" r="0" b="0"/>'
            . '<wp:docPr id="' . (100 + count($this->media)) . '" name="Family chart"/>'
            . '<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic><pic:nvPicPr><pic:cNvPr id="0" name="family-chart.png"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . $this->escape($rid) . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }

    private function paragraph(string $text, ?string $style = null, bool $center = false): string
    {
        $styleXml = $style ? '<w:pStyle w:val="' . $this->escape($style) . '"/>' : '';
        $jcXml = $center ? '<w:jc w:val="center"/>' : '';

        return '<w:p><w:pPr>' . $styleXml . $jcXml . '<w:spacing w:before="0" w:after="120"/></w:pPr>'
            . ($text === '' ? '' : $this->run($text))
            . '</w:p>';
    }

    private function headerText(string $heading): string
    {
        return match ($heading) {
            '石松類植物' => "石松類\n植物",
            '蕨類植物' => "蕨類\n植物",
            '裸子植物' => "裸子\n植物",
            '雙子葉植物' => "雙子葉\n植物",
            '單子葉植物' => "單子葉\n植物",
            '合計' => "合\n計",
            '平均覆蓋度(%)' => "平均\n覆蓋度\n（%）",
            '相對覆蓋度(%)' => "相對\n覆蓋度\n（%）",
            '相對頻度(%)' => "相對\n頻度\n（%）",
            'IVI 重要值(%)' => "IVI\n重要值\n（%）",
            default => $heading,
        };
    }

    private function verticalLabel(string $label): string
    {
        return implode("\n", preg_split('//u', $label, -1, PREG_SPLIT_NO_EMPTY) ?: [$label]);
    }

    private function runs(mixed $value): array
    {
        if ($value instanceof RichText) {
            $runs = [];
            foreach ($value->getRichTextElements() as $element) {
                $italic = method_exists($element, 'getFont') && $element->getFont()?->getItalic();
                $runs[] = $this->run($element->getText(), false, (bool) $italic);
            }
            return $runs ?: [$this->run('')];
        }

        $text = $this->plainText($value);
        $parts = preg_split("/(\r\n|\r|\n)/", $text);
        $runs = [];
        foreach ($parts as $i => $part) {
            if ($i > 0) {
                $runs[] = '<w:r><w:br/></w:r>';
            }
            $runs[] = $this->run($part);
        }

        return $runs;
    }

    private function run(string $text, bool $bold = false, bool $italic = false): string
    {
        return '<w:r><w:rPr>'
            . '<w:rFonts w:ascii="' . self::FONT_ASCII . '" w:hAnsi="' . self::FONT_ASCII . '" w:eastAsia="' . self::FONT_EAST_ASIA . '" w:cs="' . self::FONT_ASCII . '"/>'
            . '<w:sz w:val="' . self::FONT_SIZE . '"/><w:szCs w:val="' . self::FONT_SIZE . '"/>'
            . ($bold ? '<w:b/>' : '')
            . ($italic ? '<w:i/>' : '')
            . '</w:rPr><w:t xml:space="preserve">'
            . $this->escape($text)
            . '</w:t></w:r>';
    }

    private function plainText(mixed $value): string
    {
        if ($value instanceof RichText) {
            return $value->getPlainText();
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
        }
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
    }

    private function widthsFromPercentages(array $percentages, string $orientation): array
    {
        $available = ($orientation === 'landscape' ? self::LANDSCAPE_WIDTH : self::PORTRAIT_WIDTH) - (2 * self::MARGIN_LEFT_RIGHT);

        return array_map(fn($percentage) => (int) round($available * ((float) $percentage / 100)), $percentages);
    }

    private function equalWidths(int $columns, string $orientation): array
    {
        $available = ($orientation === 'landscape' ? self::LANDSCAPE_WIDTH : self::PORTRAIT_WIDTH) - (2 * self::MARGIN_LEFT_RIGHT);
        $width = (int) floor($available / max(1, $columns));

        return array_fill(0, max(1, $columns), $width);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';
    }

    private function documentRelsXml(): string
    {
        $rels = '';
        foreach ($this->media as $media) {
            $rels .= '<Relationship Id="' . $this->escape($media['rid']) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/' . $this->escape($media['name']) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
    }

    private function stylesXml(): string
    {
        $fontXml = '<w:rFonts w:ascii="' . self::FONT_ASCII . '" w:hAnsi="' . self::FONT_ASCII . '" w:eastAsia="' . self::FONT_EAST_ASIA . '" w:cs="' . self::FONT_ASCII . '"/><w:sz w:val="' . self::FONT_SIZE . '"/><w:szCs w:val="' . self::FONT_SIZE . '"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr>' . $fontXml . '</w:rPr></w:rPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:styleId="Normal"><w:name w:val="Normal"/><w:rPr>' . $fontXml . '</w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Caption"><w:name w:val="Caption"/><w:pPr><w:spacing w:before="0" w:after="80"/></w:pPr><w:rPr>' . $fontXml . '</w:rPr></w:style>'
            . '</w:styles>';
    }
}
