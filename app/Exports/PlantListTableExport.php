<?php
// =============================================================================
// File: app/Exports/PlantListTableExport.php
// 目的：將 ['headings','rows'] 匯出為工作表；支援 layouts（含 family-merge）
// =============================================================================

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class PlantListTableExport implements FromArray, WithHeadings, WithTitle, WithEvents
{
    private array  $rows;
    private string $title;
    private array  $headings;

    /**
     * @param string|array $layouts 例如：'none' | 'family-merge' | 'row-groups' | ['row-groups','merge-a1b1']
     */
    public function __construct(
        array $rows,
        string $title = '植物名錄',
        ?array $headings = null,
        public string|array $layouts = 'none',
        public array $headerGroups = [],
        public string $csvDelimiter = ","   // ★ 新增：CSV/TXT 分隔符，預設逗號
    ) {
        $this->rows     = array_values($rows);
        $this->title    = $title;
        $this->headings = $headings ?? (array_keys($rows[0] ?? []) ?: ['資料為空']);
    }

    public function title(): string
    { return $this->title; }

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
        // 確保輸出順序依 headings
        $hs = $this->headings();
        return array_map(function ($r) use ($hs) {
            $out = [];
            foreach ($hs as $h) { $out[] = $r[$h] ?? null; }
            return $out;
        }, $this->rows);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                StatsSheetLayouts::apply($this->layouts, $e, $this);
            },
        ];
    }

    public function getCsvSettings(): array
    {
        return ['delimiter' => $this->csvDelimiter];
    }
}
?>
