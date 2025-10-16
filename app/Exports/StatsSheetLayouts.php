<?php
namespace App\Exports;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class StatsSheetLayouts
{
    public static function apply(string|array $layouts, AfterSheet $e, $export): void
    {
        foreach ((array) $layouts as $name) {
            match ($name) {
                'none'        => null,                    // 什麼都不做
                'header'      => self::header($e),        // 凍結＋表頭置中粗體
                'rowWrap'     => self::rowWrap($e),       // ← 只做換行＋自動列高
                'numbers'     => self::numbers($e, $export), // 數字格式（含 0 顯示）
                'show-zeros'  => self::showZeros($e),     // 僅強制顯示 0
                'row-groups'  => self::rowGroups($e),     // A 欄相同值垂直合併
                'merge-a1b1'  => self::mergeA1B1($e),
                'two-row-group-header' => self::twoRowGroupHeader($e, $export),
                'base'        => ( // 基本款：header + rowWrap + numbers + show-zeros
                    self::header($e)
                    ?? self::rowWrap($e)
                    ?? self::numbers($e, $export)
                    ?? self::showZeros($e)
                ),
                default       => null,
            };
        }
    }

    /** 只做換行＋垂直置中＋自動列高（不含任何表頭/格式） */
    protected static function rowWrap(AfterSheet $e): void
    {
        $s = $e->sheet->getDelegate();
        $lastCol = $s->getHighestDataColumn();
        $lastRow = $s->getHighestDataRow();

        if ($lastRow >= 2) {
            $s->getStyle("A2:{$lastCol}{$lastRow}")
              ->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);

            for ($r = 2; $r <= $lastRow; $r++) {
                $s->getRowDimension($r)->setRowHeight(-1); // auto
            }
        }
    }

    /** 凍結首列＋表頭粗體置中 */
    protected static function header(AfterSheet $e): void
    {
        $s = $e->sheet->getDelegate();
        $lastCol = $s->getHighestDataColumn();

        $s->freezePane('A2');
        $s->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $s->getStyle("A1:{$lastCol}1")
          ->getAlignment()->setHorizontal('center')->setVertical(Alignment::VERTICAL_CENTER);
    }

    /** 僅強制顯示 0 */
    protected static function showZeros(AfterSheet $e): void
    {
        $e->sheet->getDelegate()->getSheetView()->setShowZeros(true);
    }

    /** 數字格式（B2~末欄）；四段格式確保 0 會顯示；特定欄位覆寫為 0.00 */
    protected static function numbers(AfterSheet $e, $export): void
    {
        $s = $e->sheet->getDelegate();
        $lastCol = $s->getHighestDataColumn();
        $lastRow = $s->getHighestDataRow();
        $lastColIdx = Coordinate::columnIndexFromString($lastCol);

        if ($lastRow >= 2 && $lastColIdx >= 2) {
            $range = "B2:{$lastCol}{$lastRow}";
            $s->getStyle($range)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_GENERAL);
            $s->getStyle($range)->getNumberFormat()->setFormatCode('#,##0;-#,##0;0;@');
        }

        if (!empty($export->numberCols)) {
            foreach ($export->numberCols as $name) {
                $idx = array_search($name, $export->headings, true); // 0-based
                if ($idx !== false) {
                    $col = Coordinate::stringFromColumnIndex($idx + 1);
                    if ($col !== 'A' && $lastRow >= 2) {
                        $s->getStyle("{$col}2:{$col}{$lastRow}")
                          ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;0.00;@');
                    }
                }
            }
        }
    }

    /** A 欄連續相同值 → 垂直合併 */
    protected static function rowGroups(AfterSheet $e): void
    {
        $s = $e->sheet->getDelegate();
        $lastRow = $s->getHighestDataRow();
        if ($lastRow < 3) return;

        $s->getColumnDimension('A')->setWidth(8);
        $s->getStyle("A2:A{$lastRow}")
          ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                          ->setVertical(Alignment::VERTICAL_CENTER);

        $start = 2;
        $prev  = (string) $s->getCell("A2")->getValue();
        for ($r = 3; $r <= $lastRow + 1; $r++) {
            $val = ($r <= $lastRow) ? (string) $s->getCell("A{$r}")->getValue() : '__END__';
            if ($val !== $prev) {
                if ($prev !== '' && $r - 1 > $start) {
                    $s->mergeCells("A{$start}:A" . ($r - 1));
                }
                $start = $r;
                $prev  = $val;
            }
        }
    }

    protected static function mergeA1B1(AfterSheet $e): void
    {
        $s = $e->sheet->getDelegate();

        // 保險：至少得有 B 欄
        $hasB = Coordinate::columnIndexFromString($s->getHighestDataColumn()) >= 2;
        if (!$hasB) return;

        $b1 = $s->getCell('B1')->getValue();
        $s->setCellValue('A1', $b1);            // A1 文字 = B1
        $s->mergeCells('A1:B1');                // 直向不動，橫向合併 A1~B1
        $s->getStyle('A1:B1')->getAlignment()
          ->setHorizontal(Alignment::HORIZONTAL_CENTER)
          ->setVertical(Alignment::VERTICAL_CENTER);
    }   
    
    /** 兩層分組表頭：依 $export->headerGroups 把同前綴的欄位合併成一個上層群組 */
    protected static function twoRowGroupHeader(AfterSheet $e, $export): void
    {
        $s = $e->sheet->getDelegate();
        // dd($export->headings);
            // 2) 再嘗試呼叫方法（若有）
        $headings = method_exists($export, 'headings')
            ? $export->headings()
            : array_keys(reset($export->getRows()) ?? []);
        $groups   = $export->headerGroups ?? [];

        if (empty($headings) || empty($groups)) return;

        // 先在第1列前插入一列，原本表頭下移到第2列
        $s->insertNewRowBefore(1, 1);

        // 掃描每個欄位，找出各 group 的連續範圍
        // spans: [ groupLabel => ['prefix' => 'Shannon_', 'start' => 1-based idx, 'end' => idx] ]
        $spans = [];
        foreach ($headings as $i => $name) {
            foreach ($groups as $prefix => $label) {
                if (str_starts_with((string)$name, (string)$prefix)) {
                    $idx = $i + 1; // 1-based
                    if (!isset($spans[$label])) {
                        $spans[$label] = ['prefix' => $prefix, 'start' => $idx, 'end' => $idx];
                    } else {
                        $spans[$label]['end'] = $idx;
                    }
                }
            }
        }



        $lastCol = $s->getHighestDataColumn();
        $lastColIdx = Coordinate::columnIndexFromString($lastCol);

        // 1) 對「不在任何群組」的欄位：直向合併 (col, row1:row2)，並把文字放到第1列
        for ($idx = 1; $idx <= $lastColIdx; $idx++) {
            $col = Coordinate::stringFromColumnIndex($idx);
            // 這格（第2列）目前是原本表頭文字
            $val2 = (string) $s->getCell("{$col}2")->getValue();

            $belongsToSpan = false;
            foreach ($spans as $span) {
                if ($idx >= $span['start'] && $idx <= $span['end']) { $belongsToSpan = true; break; }
            }
            if ($belongsToSpan) continue;

            // 垂直合併：X1:X2，並把第1列的文字設成原本第2列
            $s->setCellValue("{$col}1", $val2);
            $s->mergeCells("{$col}1:{$col}2");
            $s->getStyle("{$col}1:{$col}2")->getAlignment()
              ->setHorizontal(Alignment::HORIZONTAL_CENTER)
              ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // 2) 對每個群組：上層橫向合併，第二列顯示子標（去掉前綴）
        foreach ($spans as $label => $span) {
            $startCol = Coordinate::stringFromColumnIndex($span['start']);
            $endCol   = Coordinate::stringFromColumnIndex($span['end']);
            // 上層合併 + 設群組標題
            $s->mergeCells("{$startCol}1:{$endCol}1");
            $s->setCellValue("{$startCol}1", $label);
            $s->getStyle("{$startCol}1:{$endCol}1")->getAlignment()
              ->setHorizontal(Alignment::HORIZONTAL_CENTER)
              ->setVertical(Alignment::VERTICAL_CENTER);

            // 第二列子標：把 "Shannon_歸化" → "歸化"
            for ($idx = $span['start']; $idx <= $span['end']; $idx++) {
                $col = Coordinate::stringFromColumnIndex($idx);
                $raw = (string) $s->getCell("{$col}2")->getValue();
                $sub = preg_replace('/^'.preg_quote($span['prefix'], '/').'/', '', $raw);
                $s->setCellValue("{$col}2", $sub);
                $s->getStyle("{$col}2")->getAlignment()
                  ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                  ->setVertical(Alignment::VERTICAL_CENTER);
            }
        }

        // 3) 樣式：兩層表頭一起加粗；凍結到第3列
        $lastCol = $s->getHighestDataColumn();
        $s->getStyle("A1:{$lastCol}2")->getFont()->setBold(true);
        $s->freezePane('A3');
    }

    

}
