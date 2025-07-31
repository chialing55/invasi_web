<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PlotExport implements FromCollection, WithHeadings, WithCustomCsvSettings, WithTitle, WithEvents
{
    protected $data;
    protected $format;
    protected $title;
    protected $mergeFamily;
    protected $excluded = ['island_category', 'plot_env', 'validation_message', 'created_by', 'created_at', 'updated_at', 'updated_by', 'file_uploaded_at', 'file_uploaded_by','data_error'];

    public function __construct(array $data, string $format, string $title = 'Sheet', bool $mergeFamily = false)
    {
        $this->format = $format;
        $this->title = $title;
        $this->mergeFamily = $mergeFamily;

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

    public function registerEvents(): array
    {
        if ($this->format !== 'xlsx') return [];

        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;
                $dataCount = count($this->data);
                if ($dataCount === 0) return;

                $headings = $this->headings();

                // ✅ 若 mergeFamily 為 true，則執行合併欄位
                if ($this->mergeFamily) {
                    $familyIndex = array_search('family', $headings);
                    if ($familyIndex !== false) {
                        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($familyIndex + 1);
                        $startRow = 2;
                        $currentFamily = $this->data[0]['family'];

                        for ($row = 3; $row <= $dataCount + 1; $row++) {
                            $thisFamily = $this->data[$row - 2]['family'] ?? null;

                            if ($thisFamily !== $currentFamily) {
                                $endRow = $row - 1;
                                if ($startRow < $endRow) {
                                    $range = "$columnLetter{$startRow}:$columnLetter{$endRow}";
                                    $sheet->mergeCells($range);
                                    $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                                }
                                $startRow = $row;
                                $currentFamily = $thisFamily;
                            }
                        }

                        if ($startRow <= $dataCount + 1) {
                            $range = "$columnLetter{$startRow}:$columnLetter" . ($dataCount + 1);
                            $sheet->mergeCells($range);
                            $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                        }
                    }
                }

                // ✅ 所有格式都執行：自動欄寬
                $columnCount = count($headings);
                for ($i = 1; $i <= $columnCount; $i++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                    $sheet->getDelegate()->getColumnDimension($colLetter)->setAutoSize(true);
                }
            }
        ];
    }

}
