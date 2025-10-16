<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCharts;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

class StatsFigExport implements FromArray, WithTitle, WithCharts
{
    public function __construct(
        public array  $rows,
        public string $title,
        public ?array $headings = null,
        public array  $chartSpec = []   // 見下方預設
    ) {}

    /* ---------- Safety helpers ---------- */
    private function sanitizeSheetTitle(string $t): string {
        // Excel 禁用 : \ / ? * [ ] 且長度 <= 31
        $t = preg_replace('/[:\\\\\\/\\?\\*\\[\\]]/u', ' ', $t);
        return mb_strlen($t) > 31 ? mb_substr($t, 0, 31) : $t;
    }
    private function quoteSheetForFormula(string $t): string {
        // 公式中的表名：用單引號包住，內含單引號要變兩個
        return "'" . str_replace("'", "''", $t) . "'";
    }
    private function absRef(string $col, int $row): string {
        return '$' . $col . '$' . $row; // $A$1
    }
    private function ensureHeadings(): array {
        if (!empty($this->headings)) return $this->headings;
        $this->headings = array_keys($this->rows[0] ?? []);
        return $this->headings;
    }

    /* ---------- Worksheet title MUST be sanitized ---------- */
    public function title(): string {
        return $this->sanitizeSheetTitle($this->title);
    }

    public function array(): array
    {
        $heads = $this->ensureHeadings();
        $out = [$heads];
        foreach ($this->rows as $r) {
            $row = [];
            foreach ($heads as $h) { $row[] = $r[$h] ?? null; }
            $out[] = $row;
        }
        return $out;
    }

    public function charts(): array
    {
        if (empty($this->rows)) return [];

        $heads    = $this->ensureHeadings();
        $rowCount = count($this->rows) + 1;                 // 含表頭

        // 用「最終工作表標題」作為圖表公式中的表名（與 title() 一致）
        $sheetTitle = $this->sanitizeSheetTitle($this->title);
        $safeSheet  = $this->quoteSheetForFormula($sheetTitle);

        // 預設 chartSpec
        $spec = array_merge([
            'type'       => 'column',                       // column|bar|line|area|pie...
            'category'   => $heads[0] ?? '',
            'series'     => [],                              // [['name'=>'物種數','value'=>'物種數']]
            'legend'     => 'none',                          // none|left|right|top|bottom
            'xTitle'     => '類別',
            'yTitle'     => '',
            'dataLabels' => true,
            'position'   => ['topLeft'=>'D2','bottomRight'=>'M22'],
        ], $this->chartSpec);

        // 欄位工具
        $colIndex  = fn(string $k) => array_search($k, $heads, true) + 1; // 1-based
        $colLetter = fn(int $i) => Coordinate::stringFromColumnIndex($i);

        // 類別範圍（A2:A{n}），若找不到欄位則不產生圖避免壞 XML
        $catIdx = $colIndex((string)$spec['category']);
        if ($catIdx < 1 || $rowCount < 2) return [];
        $catCol = $colLetter($catIdx);

        $categories = [
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                "{$safeSheet}!".$this->absRef($catCol, 2).':'.$this->absRef($catCol, $rowCount),
                null, $rowCount - 1
            )
        ];

        // 序列
        $labels = [];
        $values = [];
        foreach ($spec['series'] as $s) {
            $name = $s['name']  ?? null;
            $key  = $s['value'] ?? '';
            $idx  = $colIndex($key);
            if ($idx < 1) continue;                          // 找不到欄位就跳過

            $col = $colLetter($idx);

            $labelSource = ($name !== null && $name !== '')
                ? '"' . str_replace('"','""',$name) . '"'     // 直接用字串常值
                : "{$safeSheet}!".$this->absRef($col, 1);     // 表頭單格

            $labels[] = new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING, $labelSource, null, 1
            );
            $values[] = new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                "{$safeSheet}!".$this->absRef($col, 2).':'.$this->absRef($col, $rowCount),
                null, $rowCount - 1
            );
        }

        if (!count($values)) return []; // 沒有有效序列就不要產生圖（避免 drawing.xml 損壞）

        // 型態
        $typeConst = match (strtolower($spec['type'])) {
            'bar'    => DataSeries::TYPE_BARCHART,
            'line'   => DataSeries::TYPE_LINECHART,
            'area'   => DataSeries::TYPE_AREACHART,
            'pie'    => DataSeries::TYPE_PIECHART,
            default  => DataSeries::TYPE_BARCHART, // column
        };
        $direction = match (strtolower($spec['type'])) {
            'bar'    => DataSeries::DIRECTION_BAR,
            default  => DataSeries::DIRECTION_COL,
        };

        $series = new DataSeries(
            $typeConst,
            null,
            range(0, count($values)-1),
            $labels,
            $categories,
            $values
        );
        // 直條/橫條要設定方向
        if (in_array($typeConst, [DataSeries::TYPE_BARCHART], true)) {
            $series->setPlotDirection($direction);
        }

        $plot   = new PlotArea(null, [$series]);
        $legend = match ($spec['legend']) {
            'left'   => new Legend(Legend::POSITION_LEFT,   null, false),
            'right'  => new Legend(Legend::POSITION_RIGHT,  null, false),
            'top'    => new Legend(Legend::POSITION_TOP,    null, false),
            'bottom' => new Legend(Legend::POSITION_BOTTOM, null, false),
            default  => null,
        };

        $chartTitle = new Title($this->title);
        $xTitle     = new Title((string)$spec['xTitle']);
        $yTitle     = new Title((string)$spec['yTitle']);

        $chart = new Chart(
            'Chart_' . substr(sha1($sheetTitle.json_encode($heads)), 0, 8),
            $chartTitle, $legend, $plot, true, 0, $xTitle, $yTitle
        );
        $chart->setTopLeftPosition($spec['position']['topLeft']);
        $chart->setBottomRightPosition($spec['position']['bottomRight']);

        return [$chart];
    }
}
