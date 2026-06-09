<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Support\StatsTablesBuilder;

class StatsMultiSheetExport implements WithMultipleSheets
{
    public function __construct(
        protected array $selectedPlots,
        protected string $format
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        foreach (StatsTablesBuilder::build($this->selectedPlots) as $section) {
            if (!empty($section['chartSpec'])) {
                $sheets[] = new StatsFigExport(
                    rows: $section['rows'],
                    title: $section['title'],
                    headings: $section['headings'],
                    chartSpec: $section['chartSpec'],
                );
                continue;
            }

            $sheets[] = new StatsTableExport(
                rows: $section['rows'],
                title: $section['title'],
                headings: $section['headings'],
                numberCols: $section['numberCols'] ?? [],
                fillEmptyWithZero: $section['fillEmptyWithZero'] ?? false,
                layouts: $section['layouts'] ?? [],
                headerGroups: $section['headerGroups'] ?? [],
            );
        }

        return $sheets;
    }
}
