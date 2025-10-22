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
use App\Exports\StatsSheetLayouts;

class StatsTableExport implements FromArray, WithHeadings, WithTitle, WithColumnFormatting, WithEvents
{
    private array $rows;
    private string $title;
    private array $headings;
    /** 需要套「數字(兩位)」格式的欄位名稱 */
    private array $numberCols;
    private bool $fillEmptyWithZero;
    private bool $familyMerge = false;

    public function __construct(
        array $rows,
        string $title = '統計表',
        ?array $headings = null,
        array $numberCols = [],  // 例：['歸化種數比例(%)','歸化物種平均覆蓋度(%)','Shannon_歸化','Shannon_原生','Shannon_全部']
        bool $fillEmptyWithZero = false,   // ⬅️ 新參數：是否把空值補 0
        public string|array $layouts = 'none',   // ← 新增：'none' | 'base' | 'row-groups' | ...
        public array         $headerGroups = [],      // ← 新增：給群組表頭用
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
        if ($this->headings === null) {
            $first = reset($this->rows) ?: [];
            $this->headings = array_keys($first);
        }

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

        dd($this->rows);
    }

    private function buildNumberFormat(int $decimals): string
    {
        if ($decimals <= 0) {
            return '#,##0;-#,##0;0;@';
        }
        $zs = str_repeat('0', $decimals);
        return "#,##0.{$zs};-#,##0.{$zs};0.{$zs};@";
    }

    /** 將 numberCols 正規化為 [[欄名, 格式碼], ...] */
    private function normalizedNumberCols(): array
    {
        $out = [];
        $defaultDecimals = 2;

        foreach ($this->numberCols as $k => $v) {
            if (is_int($k)) {
                // 舊寫法：純欄名，給預設兩位
                $name = (string)$v;
                $fmt  = $this->buildNumberFormat($defaultDecimals);
            } else {
                $name = (string)$k;
                if (is_int($v) || (is_string($v) && ctype_digit($v))) {
                    $fmt = $this->buildNumberFormat((int)$v);
                } elseif (is_string($v) && $v !== '') {
                    // 直接當成自訂格式碼
                    $fmt = $v;
                } else {
                    $fmt = $this->buildNumberFormat($defaultDecimals);
                }
            }
            $out[] = [$name, $fmt];
        }
        return $out;
    }

    public function columnFormats(): array
    {
        $map = [];
        foreach ($this->normalizedNumberCols() as [$name, $fmt]) {
            $idx = array_search($name, $this->headings, true);
            if ($idx !== false) {
                $col = Coordinate::stringFromColumnIndex($idx + 1);
                $map[$col] = $fmt; // 依欄別套用不同小數位（四段格式，0 也會顯示）
            }
        }
        return $map;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                StatsSheetLayouts::apply($this->layouts, $e, $this);
            },
        ];
    }


}
