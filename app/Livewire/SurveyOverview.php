<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\PlotList2025;
use App\Models\SubPlotEnv2010;
use App\Models\SubPlotEnv2025;
use App\Models\SubPlotPlant2010;
use App\Models\SubPlotPlant2025;
use App\Models\SpInfo;
use App\Models\HabitatInfo;
use Illuminate\Support\Facades\DB;
use App\Helpers\PlotHelper;

class SurveyOverview extends Component
{
    public $countyList=[];
    public $thisCounty;
    public $plotList=[];
    public $thisPlot='';

    public $totalPlotCount = 0;
    public $completedSubPlotCount = 0;
    public $surveyedPlotCount = 0;

    public $subPlotList = [];
    public $thisSubPlot;
    public $allPlotInfo = [];
    public $showAllPlotInfo = [];
    public $thisListType='';


    public function mount()
    {
        $this->countyList = PlotList2025::select('county')->distinct()->pluck('county')->toArray();
        // 預設顯示全部 plot; 統
        $this->allContyInfo();
        // $this->surveryedPlotInfo('', '');

    }
    public $allContyInfo = [];
    public $showContyInfo = [];
    public $subPlotSummary = [];
    public $subPlotHabList = [];
    public $thisHabitat = '';           // 使用者目前選的 habitat_code
    public $filteredSubPlotSummary = []; // 用來顯示的表格資料
    public function allContyInfo()
    {
        $stats = DB::connection('invasiflora')->select("
            SELECT 
                pl.county AS county,
                pl.team AS team,
                COUNT(DISTINCT pl.plot) AS total_plots,
                COUNT(DISTINCT CASE WHEN pl.file_uploaded_at IS NOT NULL THEN pl.plot END) AS completed_plots,
                COUNT(DISTINCT sp2010.id) AS total_subplots,
                COUNT(DISTINCT sp2025.id) AS completed_subplots
            FROM plot_list pl
            LEFT JOIN im_splotdata_2010 sp2010 ON pl.plot = sp2010.PLOT_ID
            LEFT JOIN im_splotdata_2025 sp2025 ON pl.plot = sp2025.plot
            GROUP BY pl.county, pl.team
            ORDER BY completed_plots desc, completed_subplots desc,total_plots desc
        ");
        $grouped = collect($stats)
            ->map(fn ($row) => (array) $row)
            ->groupBy('county')
            ->map(function ($rows, $county) {
                return [
                    'county' => $county,
                    'teams' => $rows->pluck('team')->unique()->implode(', '),
                    'total_plots' => $rows->sum('total_plots'),
                    'completed_plots' => $rows->sum('completed_plots'),
                    'total_subplots' => $rows->sum('total_subplots'),
                    'completed_subplots' => $rows->sum('completed_subplots'),
                ];
            })
            ->values()
            ->sortByDesc('completed_plots')
            ->toArray();

        $this->allContyInfo = $grouped;
        $this->showContyInfo = $grouped;
    }
//選擇縣市之後
    public function surveryedPlotInfo($thisCounty)
    {
        if ($thisCounty==''){
            $this->showContyInfo = $this->allContyInfo;
            $this->plotList = [];
        } else {
            $this->showContyInfo = collect($this->allContyInfo)
                ->filter(function ($row) use ($thisCounty) {
                    return $row['county'] === $thisCounty;
                })
                ->values()
                ->toArray();
            $this->thisCounty = $thisCounty;
            $plotList = PlotList2025::where('county', $thisCounty)->pluck('plot')->toArray();
            $this->plotList = $plotList;
            $this->filteredSubPlotSummary =[];
            $this->subPlotSummary = [];
            $this->thisPlot = '';
            $this->dispatch('thisPlotUpdated');
            $this->thisPlotFile = null;
            $this->loadAllPlotInfo($plotList);

        }

    }
    public $thisPlotFile= null;

    public function loadAllPlotInfo($plotList)
    {
        $summary = [];
        foreach ($plotList as $plot) {
            
            $data = PlotHelper::getSubPlotInfo($plot);

            $plotHabList = $data['habTypeOptions'];
// dd($plotHabList);

            $relativePath = "invasi_files/plotData/{$this->thisCounty}/{$plot}.pdf";
            $fullPath = public_path($relativePath);

            if (file_exists($fullPath)) {
                $thisPlotFile = asset($relativePath);
            } else {
                $thisPlotFile = null;
            }

            $plotQuery2025 = SubPlotEnv2025::select('habitat_code', DB::raw('count(*) as subplot_count'))
                ->where('plot', $plot)
                ->groupBy('habitat_code')
                ->pluck('subplot_count', 'habitat_code')  // 轉成 key-value 陣列
                ->toArray();

            // 查詢 2010 的 subplot 數量
            $plotQuery2010 = SubPlotEnv2010::select('HAB_TYPE as habitat_code', DB::raw('count(*) as subplot_count'))
                ->where('PLOT_ID', $plot)
                ->groupBy('HAB_TYPE')
                ->pluck('subplot_count', 'habitat_code')
                ->toArray();

            // 組合所有 habitat_code 的結果

            foreach ($plotHabList as $habCode => $habName) {
                $summary[] = [
                    'plot' => $plot,
                    'hab_code' => $habCode,
                    'hab_name' => $habName,
                    'subplot_count_2010' => $plotQuery2010[$habCode] ?? 0,
                    'subplot_count_2025' => $plotQuery2025[$habCode] ?? 0,
                    'plotFile' => $thisPlotFile,
                ];
            }           
        }
        $this->allPlotInfo = $summary;  
        $this->showAllPlotInfo = $this->allPlotInfo;    
    }

//選擇樣區之後
    public function loadPlotInfo($value)
    {        

        $this->thisPlot = $value;
        $this->filteredSubPlotSummary =[];
        $this->subPlotSummary = [];
        $habTypeMap = HabitatInfo::pluck('habitat', 'habitat_code')->toArray(); // code => 中文名
        // $thisPlotList = SubPlotEnv2025::where('plot', $value)->get();
//取得生育地類型列表、小樣區清單
        $data = PlotHelper::getSubPlotInfo($value);
        
        $thisPlotList  = $data['subPlotList'];
        // dd($thisPlotList);
        foreach ($thisPlotList as $subPlot) {
            $plotFullID = $subPlot;
            // 生育地對照
            $habitatCode = substr($plotFullID , 6, 2);  
            $habitat = $habTypeMap[$habitatCode] ?? '未知';

            $plotQuery = SubPlotEnv2025::where('plot_full_id', $plotFullID)->first()?->toArray();
            
            // 查該小樣方的植物資料
            $plantQuery = SubPlotPlant2025::where('plot_full_id', $plotFullID);

            $total = $plantQuery->count();
            $unidentified = (clone $plantQuery)->where('unidentified', 1)->count();
            //包含覆蓋度錯誤(cov_error == 1)與物種重複(cov_error == 2)
            $dataError = (clone $plantQuery)->where('data_error', '!=', 0)->count();

            $this->subPlotSummary[] = [
                'plot_full_id' => $plotFullID,
                'subplot_id' => substr($plotFullID , 8, 2),
                'habitat_code' => $habitatCode,  // ⬅️ 需保留 code 才能篩選
                'habitat' => $habitat,
                'plant_count' => $total,
                'unidentified_count' => $unidentified,
                'data_error_count' => $dataError,
                'date' => $plotQuery['date'] ?? null,
                'uploaded_at' => $plotQuery['file_uploaded_at'] ?? null,
            ];
        }

        // $this->subPlotHabList = collect($thisPlotList)
        //     ->pluck('habitat_code')
        //     ->unique()
        //     ->mapWithKeys(function ($code) use ($habTypeMap) {
        //         return [$code => $habTypeMap[$code] ?? '未知'];
        //     })
        //     ->toArray();
        $this->subPlotHabList = $data['habTypeOptions'];
        $this->filteredSubPlotSummary = $this->subPlotSummary;

    }

    public function reloadPlotInfo($value)
    {
        if ($value === '') {
            // 顯示全部
            $this->filteredSubPlotSummary = $this->subPlotSummary;
        } else {
            // 篩選指定 habitat_code
            $this->filteredSubPlotSummary =[];
            $this->filteredSubPlotSummary = collect($this->subPlotSummary)
                ->where('habitat_code', $value)
                ->values()
                ->toArray();
        }
    }


    public function render()
    {
        return view('livewire.survey-overview');
    }
}
