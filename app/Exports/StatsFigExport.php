<?php
namespace App\Exports;

use App\Support\FamilyChartImage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class StatsFigExport implements FromArray, WithTitle, WithDrawings
{
    private ?string $imagePath = null;

    public function __construct(
        public array $rows,
        public string $title,
        public ?array $headings = null,
        public array $chartSpec = []
    ) {}

    private function sanitizeSheetTitle(string $title): string
    {
        $title = preg_replace('/[:\\\\\/\?\*\[\]]/u', ' ', $title);
        return mb_strlen($title) > 31 ? mb_substr($title, 0, 31) : $title;
    }

    private function ensureHeadings(): array
    {
        if (!empty($this->headings)) {
            return $this->headings;
        }

        $this->headings = array_keys($this->rows[0] ?? []);
        return $this->headings;
    }

    public function title(): string
    {
        return $this->sanitizeSheetTitle($this->title);
    }

    public function array(): array
    {
        $heads = $this->ensureHeadings();
        $out = [$heads];
        foreach ($this->rows as $row) {
            $out[] = array_map(fn($heading) => $row[$heading] ?? null, $heads);
        }

        return $out;
    }

    public function drawings(): Drawing
    {
        $this->imagePath = tempnam(sys_get_temp_dir(), 'family-chart-') . '.png';
        FamilyChartImage::render($this->rows, $this->imagePath);

        $drawing = new Drawing();
        $drawing->setName($this->title);
        $drawing->setDescription($this->title);
        $drawing->setPath($this->imagePath);
        $drawing->setCoordinates('D2');
        $drawing->setWidth(760);
        $drawing->setResizeProportional(true);

        return $drawing;
    }

    public function __destruct()
    {
        if ($this->imagePath && is_file($this->imagePath)) {
            @unlink($this->imagePath);
        }
    }
}
