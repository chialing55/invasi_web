<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PlotExport implements FromCollection, WithHeadings, WithCustomCsvSettings, WithTitle
{
    protected $data;
    protected $format; // 匯出格式判斷用
    protected $title;
    // ✅ 欲排除的欄位
    protected $excluded = ['island_category', 'plot_env', 'validation_message', 'created_by', 'created_at', 'updated_at', 'updated_by', 'file_uploaded_at', 'file_uploaded_by','data_error']; // 你可自行調整

    public function __construct(array $data, string $format, string $title = 'Sheet')
    {
        $this->format = $format;
        $this->title = $title;
        $this->data = array_map(function ($row) {
            return collect($row)->except($this->excluded)->toArray();
        }, $data);
    }

    public function collection()
    {
        return collect($this->data);
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => $this->format === 'txt' ? "\t" : ',',
        ];
    }
    public function headings(): array
    {
        $first = $this->data[0] ?? [];
        return !empty($first) ? array_keys($first) : ['資料為空'];
    }

    public function title(): string
    {
        return $this->title;
    }
}
