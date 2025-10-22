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
        return SubPlotPlant2025::query()
            ->leftJoin('spinfo', 'im_spvptdata_2025.spcode', '=', 'spinfo.spcode')
            ->join('im_splotdata_2025', 'im_spvptdata_2025.plot_full_id', '=', 'im_splotdata_2025.plot_full_id')
            ->join('plot_list', 'im_splotdata_2025.plot', '=', 'plot_list.plot')
            ->leftJoin('twredlist2017', 'im_spvptdata_2025.spcode', '=', 'twredlist2017.spcode')
            ->whereIn('im_splotdata_2025.plot', $this->selectedPlots)
            ->select(
                'im_spvptdata_2025.*',
                'spinfo.family',
                'spinfo.chfamily',
                'spinfo.latinname',
                'spinfo.chname',
                DB::raw("
                    CASE 
                        WHEN spinfo.naturalized != '1' 
                          AND spinfo.cultivated  != '1' 
                          AND (spinfo.uncertain IS NULL OR spinfo.uncertain != '1')
                        THEN 1 ELSE 0 
                    END AS native
                "),
                'spinfo.endemic',
                'spinfo.naturalized',
                DB::raw("
                    CASE 
                        WHEN spinfo.naturalized != '1' 
                          AND spinfo.cultivated  == '1' 
                          AND (spinfo.uncertain IS NULL OR spinfo.uncertain != '1')
                        THEN 1 ELSE 0 
                    END AS cultivated
                "),
                DB::raw("
                    CASE 
                        WHEN spinfo.naturalized = '1' OR spinfo.cultivated = '1' THEN 'NA'
                        ELSE twredlist2017.IUCN
                    END AS IUCN
                "),
                'plot_list.county',
                'im_splotdata_2025.plot',
                'im_splotdata_2025.habitat_code',
                'im_splotdata_2025.subplot_id',
            )
            ->orderBy('im_spvptdata_2025.plot_full_id')
            ->orderBy('im_spvptdata_2025.coverage', 'desc');
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
