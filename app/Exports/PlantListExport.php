<?php
namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Arrayable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\DB;
use App\Models\SubPlotPlant2025;
use App\Models\PlotList2025;
use App\Models\SubPlotEnv2025;

class PlantListExport implements FromQuery, WithMapping, WithHeadings, WithCustomCsvSettings, WithTitle
{
    public function __construct(
        protected array  $selectedPlots,
        protected string $type, // 可傳入外部準備好的 Builder；也可先給 null，之後自己實作 query()
        protected string   $format,
        protected string   $title  = '植物名錄',
        protected bool     $mergeFamily, // ← 只有名錄需要
        protected array    $excluded = [
            'island_category','plot_env','validation_message','created_by','created_at',
            'updated_at','updated_by','file_uploaded_at','file_uploaded_by','data_error'
        ],
        protected ?array   $headings = null
    ) {}

    public function query(): Builder
    {
        if ($this->type == '1'){  //全部資料
            return SubPlotPlant2025::join('spinfo', 'im_spvptdata_2025.spcode', '=', 'spinfo.spcode')
            ->leftjoin('twredlist2017', 'im_spvptdata_2025.spcode', '=', 'twredlist2017.spcode')
            ->select(
                // 'spinfo.spcode',
                DB::raw("CONCAT(spinfo.family, ' ', spinfo.chfamily) AS family"),
                'spinfo.latinname',
                'spinfo.chname',                
                // 'spinfo.apgfamily',
                'spinfo.plantgroup',                
                'spinfo.growth_form',
                DB::raw("
                    CASE 
                        WHEN spinfo.naturalized != '1' 
                            AND spinfo.cultivated != '1' 
                            AND (spinfo.uncertain IS NULL OR spinfo.uncertain != '1')
                        THEN 1 
                        ELSE 0 
                    END AS native
                "),
                'spinfo.endemic',
                'spinfo.naturalized',
                'spinfo.cultivated',                
                DB::raw("
                    CASE 
                        WHEN spinfo.naturalized = '1' OR spinfo.cultivated = '1' THEN 'NA'
                        ELSE twredlist2017.IUCN
                    END AS IUCN
                "),
                // 'twredlist2017.origin_type as origin_type_redlist'
            )
            ->distinct()
            ->orderBy('family')
            ->orderBy('spinfo.latinname');
        } else {
            return SubPlotPlant2025::query()
                // 用 join 取代子查詢，效率與可讀性都更好
                ->join('im_splotdata_2025 as e', 'im_spvptdata_2025.plot_full_id', '=', 'e.plot_full_id')
                ->leftJoin('spinfo as s', 'im_spvptdata_2025.spcode', '=', 's.spcode')       // 建議 leftJoin，避免被吃光
                ->leftJoin('twredlist2017 as r', 'im_spvptdata_2025.spcode', '=', 'r.spcode')
                ->whereNotNull('s.spcode')   // 或 whereNotNull('s.family')
                ->whereIn('e.plot', $this->selectedPlots)

                // 只取名錄需要的欄位（避免 DISTINCT 負擔）
                ->select([
                    // 's.spcode',  // 若要唯一鍵可以加
                    's.plantgroup',
                    's.family',
                    's.chfamily',
                    's.latinname',
                    's.chname',
                    's.growth_form',
                    DB::raw("
                        CASE 
                        WHEN s.naturalized != '1' 
                        AND s.cultivated  != '1' 
                        AND (s.uncertain IS NULL OR s.uncertain != '1')
                        THEN 1 ELSE 0 
                        END AS native
                    "),
                    's.endemic',
                    's.naturalized',
                    's.cultivated',
                    DB::raw("
                        CASE 
                        WHEN s.naturalized = '1' OR s.cultivated = '1' THEN 'NA'
                        ELSE r.IUCN
                        END AS IUCN
                    "),
                ])
                ->distinct()                       // 取唯一物種列
                ->orderBy('s.family')
                ->orderBy('s.latinname');
        }
    }

    public function map($row): array
    {
        if ($row instanceof Arrayable) {
            $arr = $row->toArray();
        } else {
            $arr = (array) $row;
        }
        return collect($arr)->except($this->excluded)->all();
    }

    public function headings(): array
    {
        if ($this->headings !== null) return $this->headings;

        $first = $this->query()->clone()->limit(1)->get()->map(fn($r) => $this->map($r))->first();
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

    public function registerEvents(): array
    {
        if ($this->format !== 'xlsx' || !$this->mergeFamily) return [];

        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 找出 family 欄的位置
                $heads = $this->headings();
                $familyIdx = array_search('family', $heads, true);
                if ($familyIdx === false) return;

                $familyCol = Coordinate::stringFromColumnIndex($familyIdx + 1);
                $highestRow = $sheet->getHighestRow();
                if ($highestRow <= 2) return; // 沒資料

                $start = 2;
                $prev  = (string) $sheet->getCell("{$familyCol}{$start}")->getValue();

                for ($r = 3; $r <= $highestRow; $r++) {
                    $cur = (string) $sheet->getCell("{$familyCol}{$r}")->getValue();

                    if ($cur !== $prev) {
                        // 合併 [start, r-1]
                        if ($r - 1 > $start) {
                            $range = "{$familyCol}{$start}:{$familyCol}".($r - 1);
                            $sheet->mergeCells($range);
                            $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                        }
                        $start = $r;
                        $prev  = $cur;
                    }
                }
                // 收尾：合併 [start, highestRow]
                if ($highestRow >= $start) {
                    $range = "{$familyCol}{$start}:{$familyCol}{$highestRow}";
                    $sheet->mergeCells($range);
                    $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                }
            }
        ];
    }
}
