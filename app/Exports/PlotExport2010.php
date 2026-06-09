<?php
namespace App\Exports;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use App\Models\SubPlotEnv2010;

class PlotExport2010 implements FromQuery, WithMapping, WithHeadings, WithCustomCsvSettings, WithTitle
{
    public function __construct(
        protected array $selectedPlots,
        protected string $format,
        protected string $title = '2010 環境資料',
        protected array $excluded = ['id'],
        protected ?array $headings = null
    ) {}

    public function query(): Builder
    {
        return SubPlotEnv2010::query()
            ->from('im_splotdata_2010')
            ->whereIn('im_splotdata_2010.PLOT_ID', $this->selectedPlots)
            ->join('plot_list', 'im_splotdata_2010.PLOT_ID', '=', 'plot_list.plot')
            ->selectRaw("
                plot_list.county as county,
                CONCAT(im_splotdata_2010.PLOT_ID, im_splotdata_2010.HAB_TYPE, LPAD(im_splotdata_2010.SUB_ID, 2, '0')) as plot_full_id_2010,
                im_splotdata_2010.*
            ")
            ->orderBy('im_splotdata_2010.PLOT_ID')
            ->orderBy('im_splotdata_2010.HAB_TYPE')
            ->orderBy('im_splotdata_2010.SUB_ID');
    }

    public function map($row): array
    {
        $arr = $row instanceof Arrayable ? $row->toArray() : (array) $row;

        return collect($arr)->except($this->excluded)->all();
    }

    public function headings(): array
    {
        if ($this->headings !== null) {
            return $this->headings;
        }

        $first = $this->query()->clone()->limit(1)->get()->map(fn($row) => $this->map($row))->first();

        return $first ? array_keys($first) : ['資料為空'];
    }

    public function getCsvSettings(): array
    {
        return ['delimiter' => $this->format === 'txt' ? "\t" : ','];
    }

    public function title(): string
    {
        return $this->title;
    }
}
