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
use App\Helpers\HabHelper;
use App\Helpers\PlotHelper;
use App\Helpers\PlotCompletedHelper;

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
    public $allTeamInfo = [];
    public $showTeamInfo = [];
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
                COUNT(DISTINCT CASE WHEN pl.file_uploaded_at IS NOT NULL THEN pl.plot END) AS completed_plots
            FROM plot_list pl
            GROUP BY pl.county, pl.team
            ORDER BY total_plots desc
        ");

    // $stats = DB::connection('invasiflora')->select("
    //     SELECT 
    //         pl.county AS county,
    //         pl.team AS team,
    //         COUNT(DISTINCT pl.plot) AS total_plots,
    //         COUNT(DISTINCT CASE WHEN pl.file_uploaded_at IS NOT NULL THEN pl.plot END) AS completed_plots,
    //         COUNT(DISTINCT sp2010.id) AS total_subplots,
    //         COUNT(DISTINCT sp2025.id) AS completed_subplots
    //     FROM plot_list pl
    //     LEFT JOIN im_splotdata_2010 sp2010 ON pl.plot = sp2010.PLOT_ID
    //     LEFT JOIN im_splotdata_2025 sp2025 ON pl.plot = sp2025.plot
    //     GROUP BY pl.county, pl.team
    //     ORDER BY pl.county
    // ");
        $grouped_county = collect($stats)
            ->map(fn ($row) => (array) $row)
            ->groupBy('county')
            ->map(function ($rows, $county) {
                return [
                    'county' => $county,
                    'teams' => $rows->pluck('team')->unique()->implode(', '),
                    'total_plots' => $rows->sum('total_plots'),
                    'completed_plots' => $rows->sum('completed_plots'),
                ];
            })
            ->values()
            ->sortByDesc('completed_plots')
            ->toArray();
        $grouped_team = collect($stats)
            ->map(fn ($row) => (array) $row)
            ->groupBy('team')
            ->map(function ($rows, $team) {
                return [
                    'county' => $rows->pluck('county')->unique()->implode(', '),
                    'team' => $team,
                    'total_plots' => $rows->sum('total_plots'),
                    'completed_plots' => $rows->sum('completed_plots'),
                    // 'total_subplots' => $rows->sum('total_subplots'),
                    // 'completed_subplots' => $rows->sum('completed_subplots'),
                ];
            })
            ->values()
            ->sortByDesc('completed_plots')
            ->toArray();

        $this->allContyInfo = $grouped_county;
        $this->showContyInfo = $grouped_county;

        $this->allTeamInfo = $grouped_team;
        $this->showTeamInfo = $grouped_team;
        // dd($this->showTeamInfo);        
    }
//選擇縣市之後
    public function surveryedPlotInfo($thisCounty)
    {
        if ($thisCounty==''){
            $this->showContyInfo = $this->allContyInfo;
            $this->showAllPlotInfo = [];  
            $this->plotList = [];
            $this->allPlotInfo=[];
            $this->filteredSubPlotSummary =[];
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
            
            $data = PlotCompletedHelper::plotCompleted($plot);

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


            // 組合所有 habitat_code 的結果

            // 如果 habList 有資料，就正常處理
            if (!empty($plotHabList)) {
                foreach ($plotHabList as $habCode => $habName) {
                    $summary[] = [
                        'plot' => $plot,
                        'hab_code' => $habCode,
                        'hab_name' => $habName,
                        'subplot_count_2025' => $plotQuery2025[$habCode] ?? 0,
                        'plotFile' => $thisPlotFile,
                    ];
                }
            } else {
                // 補上一筆空白的 habitat
                $summary[] = [
                    'plot' => $plot,
                    'hab_code' => null,
                    'hab_name' => null,
                    'subplot_count_2025' => null,
                    'plotFile' => $thisPlotFile,
                ];
            }          
        }

        $this->allPlotInfo = collect($summary)
            ->sortByDesc(fn($item) => !is_null($item['hab_code']))
            ->values()
            ->toArray();

        //  dd($summary);       
        // $this->allPlotInfo = $summary; 
        $this->showAllPlotInfo = $this->allPlotInfo;   


    }
    public $refreshKey;
    public $subPlotinfomessage = ''; // 用來顯示小樣方資料的訊息

//選擇樣區之後
    public function loadPlotInfo($value)
    { 
        $this->thisPlot = $value;
        $this->refreshKey = now(); // 加一個 dummy key 讓 view 重繪
  
        if ($this->allPlotInfo !=[]){
            $index = array_search($value, array_column($this->allPlotInfo, 'plot'));
            $this->thisPlotFile = $index !== false ? $this->allPlotInfo[$index]['plotFile'] : null;            
        }

        $this->filteredSubPlotSummary =[];
        if ($value==''){
            $this->showAllPlotInfo = $this->allPlotInfo; 
            $this->filteredSubPlotSummary =[];
            $this->subPlotSummary = [];
        } else {
            $this->showAllPlotInfo=[];
            $this->subPlotSummary = [];
            // $habTypeMap = HabitatInfo::pluck('habitat', 'habitat_code')->toArray(); // code => 中文名
    //取得生育地類型列表、小樣區清單
            $subPlotList2025 = SubPlotEnv2025::where('plot', $value)
                ->pluck('plot_full_id')
                ->unique()
                ->sort()     // 加上這行
                ->values()
                ->toArray();

            if (empty($subPlotList2025)) {
                $this->subPlotinfomessage = "此樣區 {$value} 尚未輸入小樣方資料。";
                return;
            } 

            $this->subPlotHabList  = collect($this->allPlotInfo)
                ->filter(fn($item) => $item['plot'] === $value && !is_null($item['hab_code']))
                ->mapWithKeys(fn($item) => [
                    $item['hab_code'] => $item['hab_name']
                ])
                ->sortKeys()
                ->toArray();

            // dd($thisPlotList);
            foreach ($subPlotList2025 as $subPlot) {
                $plotFullID = $subPlot;
                // 生育地對照
                $habitatCode = substr($plotFullID , 6, 2);  
                $habTypeMap = HabitatInfo::pluck('habitat', 'habitat_code')->toArray();
                $habitat = $habTypeMap[$habitatCode] ?? '未知';

                $plotQuery = SubPlotEnv2025::where('plot_full_id', $plotFullID)->first()?->toArray();
                
                // 查該小樣方的植物資料
                $plantQuery = SubPlotPlant2025::where('plot_full_id', $plotFullID);
                if ($plotQuery['file_uploaded_at']!= null) {
                    $relativePath = "invasi_files/subPlotPhoto/{$this->thisCounty}/{$this->thisPlot}/{$habitatCode}/{$plotFullID}.jpg";
                    $fullPath = public_path($relativePath);

                    if (file_exists($fullPath)) {
                        $photopath = asset($relativePath);
                    } else {
                        $photopath = null;
                    }
                }
    

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
                    'photo_path' => $photopath ?? null,
                ];
            }

            $this->filteredSubPlotSummary = $this->subPlotSummary;

        }

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
            if (empty($this->filteredSubPlotSummary)) {
                $this->subPlotinfomessage = "此生育地類型 {$value} {$this->subPlotHabList[$value]} 尚未輸入小樣方資料。";
            } else {
                $this->subPlotinfomessage = '';
            }
            }
    }


    public function render()
    {
        return view('livewire.survey-overview');
    }
}
