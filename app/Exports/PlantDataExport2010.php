<?php
namespace App\Exports;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use App\Models\SubPlotPlant2010;
use App\Support\TaiwanChecklistQuery;

class PlantDataExport2010 implements FromQuery, WithMapping, WithHeadings, WithCustomCsvSettings, WithTitle
{
    public function __construct(
        protected array $selectedPlots,
        protected string $format,
        protected string $title = '2010 植物資料',
        protected array $excluded = ['id'],
        protected ?array $headings = null
    ) {}

    public function query(): Builder
    {
        $query = SubPlotPlant2010::query()
            ->from('im_spvptdata_2010 as p')
            ->whereIn('p.PLOT_ID', $this->selectedPlots)
            ->join('plot_list', 'p.PLOT_ID', '=', 'plot_list.plot');

        TaiwanChecklistQuery::joinCurrent($query, 'p');

        return $query
            ->select(
                'plot_list.county',
                DB::raw("CONCAT(p.PLOT_ID, p.HAB_TYPE, LPAD(p.SUB_ID, 2, '0')) as plot_full_id_2010"),
                'p.*',
                's.family',
                's.chfamily',
                DB::raw('s.full_name as latinname'),
                DB::raw('s.canonical_name as simname'),
                's.chname',
                DB::raw(TaiwanChecklistQuery::nativeExpr('s') . ' AS native'),
                DB::raw(TaiwanChecklistQuery::endemicExpr('s') . ' AS endemic'),
                DB::raw(TaiwanChecklistQuery::naturalizedExpr('s') . ' AS naturalized'),
                DB::raw(TaiwanChecklistQuery::cultivatedExpr('s') . ' AS cultivated'),
                DB::raw('s.IUCN as IUCN'),
                DB::raw('s.taicol_taxon_id as taicol_taxon_id')
            )
            ->orderBy('p.PLOT_ID')
            ->orderBy('p.HAB_TYPE')
            ->orderBy('p.SUB_ID')
            ->orderBy('p.COV', 'desc');
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
