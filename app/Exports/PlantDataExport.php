<?php
namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Arrayable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Facades\DB;
use App\Models\SubPlotPlant2025;
use App\Support\TaiwanChecklistQuery;

class PlantDataExport implements FromQuery, WithMapping, WithHeadings, WithCustomCsvSettings, WithTitle
{
    public function __construct(
        protected array  $selectedPlots,
        protected string   $format,
        protected string   $title  = '植物資料',
        protected array    $excluded = [
            'island_category','plot_env','validation_message','created_by','created_at',
            'updated_at','updated_by','file_uploaded_at','file_uploaded_by','data_error'
        ],
        protected ?array   $headings = null // 若想固定表頭，可在建構子直接給
    ) {}

    // === 查詢 ===
    public function query(): Builder
    {
        $query = SubPlotPlant2025::query()
            ->from('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025', 'p.plot_full_id', '=', 'im_splotdata_2025.plot_full_id')
            ->join('plot_list', 'im_splotdata_2025.plot', '=', 'plot_list.plot')
            ->whereIn('im_splotdata_2025.plot', $this->selectedPlots);

        TaiwanChecklistQuery::joinCurrent($query, 'p');

        return $query
            ->select(
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
                'plot_list.county',
                'im_splotdata_2025.plot',
                'im_splotdata_2025.habitat_code',
                'im_splotdata_2025.subplot_id',
                DB::raw('s.taicol_taxon_id as taicol_taxon_id'),
            )
            ->orderBy('p.plot_full_id')
            ->orderBy('p.coverage', 'desc');
            // ⚠️ 不要 ->get()，直接回傳 Builder
    }


    // === 每列映射為陣列（會自動排除不需要的欄位） ===
    public function map($row): array
    {
        if ($row instanceof Arrayable) {
            $arr = $row->toArray();
        } else {
            $arr = (array) $row;
        }
        return collect($arr)->except($this->excluded)->all();
    }

    // === 表頭 ===
    public function headings(): array
    {
        if ($this->headings !== null) return $this->headings;

        // 動態抓第一列的鍵當表頭（跑一次輕量 query）
        $first = $this->query()->clone()->limit(1)->get()->map(fn($r) => $this->map($r))->first();
        return $first ? array_keys($first) : ['資料為空'];
    }

    // === CSV/TXT 設定 ===
    public function getCsvSettings(): array
    {
        return ['delimiter' => $this->format === 'txt' ? "\t" : ','];
    }

    public function title(): string
    {
        return $this->title;
    }
}
