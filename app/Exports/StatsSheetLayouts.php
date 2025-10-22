<?php
namespace App\Exports;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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
                'pg-groups' => self::insertPgAndFamilyHeaderRows($e),
                'base'        => ( // 基本款：header + rowWrap + numbers + show-zeros
                    self::header($e)
                    ?? self::rowWrap($e)
                    ?? self::numbers($e, $export)
                    ?? self::showZeros($e)
                ),
                'family-merge' => self::mergeSameFamilyVertically($e),
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

    public static function mergeSameFamilyVertically(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        $lastCol = $sheet->getHighestDataColumn();
        $lastIdx = Coordinate::columnIndexFromString($lastCol);

        $targets = ['科名','family'];
        $familyIdx = null;
        for ($i = 1; $i <= $lastIdx; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $val = trim((string)$sheet->getCell("{$col}1")->getValue());
            if (in_array($val, $targets, true)) { $familyIdx = $i; break; }
        }
        if ($familyIdx === null) return;

        $col     = Coordinate::stringFromColumnIndex($familyIdx);
        $highest = $sheet->getHighestDataRow();
        if ($highest <= 2) return;

        $start = 2;
        $prev  = trim((string)$sheet->getCell("{$col}{$start}")->getCalculatedValue());
        for ($r = 3; $r <= $highest; $r++) {
            $cur = trim((string)$sheet->getCell("{$col}{$r}")->getCalculatedValue());
            if ($cur !== $prev) {
                if ($r - 1 > $start) {
                    $range = "{$col}{$start}:{$col}".($r - 1);
                    $sheet->mergeCells($range);
                    $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                }
                $start = $r;
                $prev  = $cur;
            }
        }
        if ($highest >= $start) {
            $range = "{$col}{$start}:{$col}{$highest}";
            $sheet->mergeCells($range);
            $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        }
    }
    
    public static function insertPgAndFamilyHeaderRows_o(AfterSheet $event): void
    {
        $ws = $event->sheet->getDelegate();

        $lastCol = $ws->getHighestDataColumn();
        $lastIdx = Coordinate::columnIndexFromString($lastCol);
        $lastRow = $ws->getHighestDataRow();

        $idx = function (string $header) use ($ws, $lastIdx) {
            for ($i = 1; $i <= $lastIdx; $i++) {
                $col = Coordinate::stringFromColumnIndex($i);
                $val = trim((string)$ws->getCell("{$col}1")->getValue());
                if ($val === $header) return $i;
            }
            return null;
        };

        // $colEmpty = 1; // 第一欄保留空白（若需要）
        $colFamily = $idx('科名') ?? 2;
        $colPg     = $idx('__pg');
        $colFam    = $idx('__fam');
        $colChfam  = $idx('__chfam');
        if (!$colPg || !$colFam) return; // 找不到輔助欄就不處理

        $colPgL    = Coordinate::stringFromColumnIndex($colPg);
        $colFamL   = Coordinate::stringFromColumnIndex($colFam);
        $colChfamL = $colChfam ? Coordinate::stringFromColumnIndex($colChfam) : null;

        // 先把輔助欄抓出來，避免插入列時座標混亂
        $rows = [];
        for ($r = 2; $r <= $lastRow; $r++) {
            $pg    = trim((string)$ws->getCell("{$colPgL}{$r}")->getCalculatedValue());
            $fam   = trim((string)$ws->getCell("{$colFamL}{$r}")->getCalculatedValue());
            $chfam = $colChfamL ? trim((string)$ws->getCell("{$colChfamL}{$r}")->getCalculatedValue()) : '';
            $rows[] = compact('pg','fam','chfam');
        }

        $inserted = 0; $prevPg = null; $prevFam = null;
        for ($i = 0; $i < count($rows); $i++) {
            $cur = $rows[$i];
            $excelRow = 2 + $i + $inserted;

            $needPgRow  = ($cur['pg']  !== $prevPg);
            $needFamRow = ($cur['fam'] !== $prevFam);

            if ($needPgRow) {
                $ws->insertNewRowBefore($excelRow, 1);
                $groupColIdx  = 1; // A 欄
                $bCol     = Coordinate::stringFromColumnIndex($groupColIdx);
                $lastDataCol = Coordinate::stringFromColumnIndex($lastIdx);
                $ws->setCellValue("{$bCol}{$excelRow}", '【'.$cur['pg'].'】');
                $ws->mergeCells("{$bCol}{$excelRow}:{$lastDataCol}{$excelRow}");
                $ws->getStyle("{$bCol}{$excelRow}")->getFont()->setBold(true)->setSize(12);
                $ws->getStyle("{$bCol}{$excelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $ws->getStyle("{$bCol}{$excelRow}:{$lastDataCol}{$excelRow}")
                   ->getFill()->setFillType(Fill::FILL_SOLID)
                   ->getStartColor()->setARGB('FFEFEFEF');
                $inserted++; $excelRow++;
            }

            if ($needFamRow) {
                $ws->insertNewRowBefore($excelRow, 1);
                $famLabel = $cur['fam'].($cur['chfam'] ? ' '.$cur['chfam'] : '');
                $famCol = Coordinate::stringFromColumnIndex($idx('科名') ?? $colFamily);
                $ws->setCellValue("{$famCol}{$excelRow}", $famLabel);
                $ws->getStyle("{$famCol}{$excelRow}")->getFont()->setBold(true);
                $inserted++; $excelRow++;
            }

            // 清空這個 family 區段內的「科名」欄位
            if ($needFamRow) {
                $start = $excelRow;
                $j = $i;
                $famCol = Coordinate::stringFromColumnIndex($colFamily);
                while ($j < count($rows) && $rows[$j]['fam'] === $cur['fam']) {
                    $r = $start + ($j - $i);
                    $ws->setCellValue("{$famCol}{$r}", '');
                    $j++;
                }
            }

            $prevPg  = $cur['pg'];
            $prevFam = $cur['fam'];
        }

        // 隱藏輔助欄
        foreach (['__pg','__fam','__chfam'] as $h) {
            $c = $idx($h);
            if ($c) {
                $col = Coordinate::stringFromColumnIndex($c);
                $ws->getColumnDimension($col)->setVisible(false);
                $ws->getColumnDimension($col)->setWidth(0.1);
            }
        }
    }

    public static function insertPgAndFamilyHeaderRows(AfterSheet $event): void
    {
        $ws = $event->sheet->getDelegate();

        $lastCol = $ws->getHighestDataColumn();
        $lastIdx = Coordinate::columnIndexFromString($lastCol);
        $lastRow = $ws->getHighestDataRow();

        $findCol = function (string $header) use ($ws, $lastIdx) {
            for ($i = 1; $i <= $lastIdx; $i++) {
                $col = Coordinate::stringFromColumnIndex($i);
                $val = trim((string)$ws->getCell("{$col}1")->getValue());
                if ($val === $header) return $i;
            }
            return null;
        };

        $colFamily = $findCol('科名') ?? 1;
        $colGroup  = $findCol('__group');

        if (!$colGroup) return;

        $colFamilyL = Coordinate::stringFromColumnIndex($colFamily);
        $colGroupL  = Coordinate::stringFromColumnIndex($colGroup);
        $colLastL   = Coordinate::stringFromColumnIndex($lastIdx);

        for ($r = 2; $r <= $lastRow; $r++) {
            $type = trim((string)$ws->getCell("{$colGroupL}{$r}")->getCalculatedValue());
            if ($type === 'pg') {
                // 類群列：把 A..最後一欄合併並套樣式
                $ws->mergeCells("A{$r}:{$colLastL}{$r}");
                $ws->getStyle("A{$r}")->getFont()->setBold(true)->setSize(12);
                $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $ws->getStyle("A{$r}:{$colLastL}{$r}")
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFEFEFEF');
            } elseif ($type === 'fam') {
                // Family 標頭列：只讓「科名」欄粗體
                $ws->getStyle("{$colFamilyL}{$r}")->getFont()->setBold(true);
            } else {
                // 一般資料列：不處理
            }
        }

        // 隱藏輔助欄
        foreach (['__group','__pg','__fam','__chfam'] as $h) {
            $c = $findCol($h);
            if ($c) {
                $col = Coordinate::stringFromColumnIndex($c);
                $ws->getColumnDimension($col)->setVisible(false);
                $ws->getColumnDimension($col)->setWidth(0.1);
            }
        }
    }

}
