<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment; // ← 先在檔頭加這行

class StatsTableExport implements FromArray, WithHeadings, WithTitle, WithColumnFormatting, WithEvents
{
    private array $rows;
    private string $title;
    private array $headings;
    /** 需要套「數字(兩位)」格式的欄位名稱 */
    private array $numberCols;
    private bool $fillEmptyWithZero;

    public function __construct(
        array $rows,
        string $title = '統計表',
        ?array $headings = null,
        array $numberCols = [],  // 例：['歸化種數比例(%)','歸化物種平均覆蓋度(%)','Shannon_歸化','Shannon_原生','Shannon_全部']
        bool $fillEmptyWithZero = false   // ⬅️ 新參數：是否把空值補 0
    ) {
        $this->rows = $rows;
        $this->title = $title;
        $this->headings = $headings ?? (array_keys($rows[0] ?? []) ?: ['資料為空']);
        $this->numberCols = $numberCols;
        $this->fillEmptyWithZero = $fillEmptyWithZero;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        // 保證輸出為索引陣列、欄位順序依 headings
        return array_map(function ($r) {
            $out = [];
            foreach ($this->headings as $i => $h) {
                $v = $r[$h] ?? null;

                // 只有數值欄才補 0（跳過第 1 欄標籤）
                if ($this->fillEmptyWithZero && $i > 0) {
                    if ($v === null || (is_string($v) && trim($v) === '')) {
                        $v = 0;
                    }
                }
                $out[] = $v;
            }
            return $out;
        }, $this->rows);
    }

    public function columnFormats(): array
    {
        // 依欄名找出欄號 → 欄字母，套數字格式（兩位小數）
        $map = [];
        foreach ($this->numberCols as $name) {
            $idx = array_search($name, $this->headings, true);
            if ($idx !== false) {
                $col = Coordinate::stringFromColumnIndex($idx + 1);
                $map[$col] = NumberFormat::FORMAT_NUMBER_00;
            }
        }
        return $map;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $sheet   = $e->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                // 表頭樣式
                $sheet->freezePane('A2');
                $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
                $sheet->getStyle("A1:{$lastCol}1")
                    ->getAlignment()->setHorizontal('center')->setVertical(Alignment::VERTICAL_CENTER);

                // ✅ 讓資料區的 \n 變成換行，並垂直置中
                if ($lastRow >= 2) {
                    $sheet->getStyle("A2:{$lastCol}{$lastRow}")
                        ->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);

                    // 列高自動（或給固定高度）
                    for ($r = 2; $r <= $lastRow; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(-1); // -1 = auto
                        // 或：$sheet->getRowDimension($r)->setRowHeight(36);
                    }
                }
            },
        ];
    }
}
