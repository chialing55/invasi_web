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
use App\Models\PlotHab;
use Illuminate\Support\Facades\DB;
use App\Helpers\HabHelper;
use App\Helpers\PlotHelper;
use App\Helpers\PlotCompletedHelper;
use App\Helpers\PlotCompletedCheckHelper;
use Illuminate\Support\Facades\Auth;

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
    public $thisSelectedHabitat = ''; // 用來記錄目前選擇的 habitat_code
    public $userOrg;
    public $userRole;

    public function mount()
    {
        $this->countyList = PlotList2025::select('county')->distinct()->pluck('county')->toArray();
        // 預設顯示全部 plot; 統
        $this->allContyInfo();
        // $this->surveryedPlotInfo('', '');
        $user = Auth::user(); // 取代 auth()->user()
        $this->userOrg = $user->organization ?? '未知單位';
        $this->userRole = $user->role;

    }
    public $allContyInfo = [];
    public $showContyInfo = [];
    public $allTeamInfo = [];
    public $showTeamInfo = [];
    public $subPlotSummary = [];
    public $subPlotHabList = [];
    public $thisHabitat = '';           // 使用者目前選的 habitat_code
    public $filteredSubPlotSummary = []; // 用來顯示的表格資料
    public $thisPlotWithStatus = [];
    public function allContyInfo()
    {
        // 先取得所有 plot 的基本資料
        $plotList = DB::connection('invasiflora')->table('plot_list')->get();

        $envDataByPlot = SubPlotEnv2025::all()->groupBy('plot');
        $plantDataByPrefix = SubPlotPlant2025::all()->groupBy(function ($row) {
            return substr($row->plot_full_id, 0, 6);
        });
        $plotListByPlot = PlotList2025::all()->keyBy('plot');
        $habDataByPlot = PlotHab::all()->groupBy('plot');

        $plotWithStatus = collect($plotList)->map(function ($row) use (
            $envDataByPlot, $plantDataByPrefix, $plotListByPlot, $habDataByPlot
        ) {
            $row = (array) $row;
            $plot = $row['plot'];

            $status = PlotCompletedCheckHelper::getPlotCompletedInfo_v2(
                $plot, $envDataByPlot, $plantDataByPrefix, $plotListByPlot, $habDataByPlot
            );

            return array_merge($row, $status);
        });
// dd($plotWithStatus->toArray());
        $this->thisPlotWithStatus = $plotWithStatus;
        // group by county
        $grouped_county = $plotWithStatus
            ->groupBy('county')
            ->map(function ($rows, $county) {
                $uniquePlots = $rows->pluck('plot')->unique();
                $completedPlots = $rows->filter(fn ($row) => $row['plotCompleted'] === '1')->pluck('plot')->unique();

                return [
                    'county' => $county,
                    'teams' => $rows->pluck('team')->unique()->implode(', '),
                    'total_plots' => $uniquePlots->count(),
                    'completed_plots' => $completedPlots->count(),
                ];
            })
            ->values()
            ->sortByDesc('completed_plots')
            ->toArray();

// dd($grouped_county);

        // group by team
        $grouped_team = $plotWithStatus
            ->groupBy('team')
            ->map(function ($rows, $team) {
                $uniquePlots = $rows->pluck('plot')->unique();
                $completedPlots = $rows->filter(fn ($row) => $row['plotCompleted'] === '1')->pluck('plot')->unique();

                return [
                    'county' => $rows->pluck('county')->unique()->implode(', '),
                    'team' => $team,
                    'total_plots' => $uniquePlots->count(),
                    'completed_plots' => $completedPlots->count(),
                ];
            })
            ->values()
            ->sortByDesc('completed_plots')
            ->toArray();

        $this->allTeamInfo = $grouped_team;
        $this->showTeamInfo = $grouped_team;
// dd($this->showTeamInfo);
        // 存到元件的 public 屬性
        $this->allContyInfo = $grouped_county;
        $this->showContyInfo = $grouped_county;

    }

    public $subPlotTeam=[];
    public $subPlantTeam=[];

    public $showTeamProgress = false;

    public function showTeamProgressToggle()
    {
        $this->showTeamProgress = !$this->showTeamProgress;
        if ($this->showTeamProgress) {
            $this->teamProgressDetail();
        } else {
            // $this->subPlotTeam = [];
            // $this->subPlantTeam = [];
        }
    }

    public function teamProgressDetail(){
        $this->showTeamProgress = true;
//小樣區數量
        if (empty($this->subPlotTeam) && empty($this->subPlantTeam)) {
           
            $subPlot_team = SubPlotEnv2025::join('plot_list', 'im_splotdata_2025.plot', '=', 'plot_list.plot')
                ->select('plot_list.team', DB::raw('COUNT(DISTINCT im_splotdata_2025.plot_full_id) as total_plots'))
                ->groupBy('plot_list.team')
                ->orderByDesc('total_plots')
                ->get()
                ->toArray();

            $subPlant_team = SubPlotPlant2025::join('im_splotdata_2025', 'im_spvptdata_2025.plot_full_id', '=', 'im_splotdata_2025.plot_full_id')
                ->join('plot_list', 'im_splotdata_2025.plot', '=', 'plot_list.plot')
                ->select('plot_list.team', DB::raw('COUNT(im_spvptdata_2025.id) as total_plants'))
                ->groupBy('plot_list.team')
                ->orderByDesc('total_plants')
                ->get()
                ->toArray();

            $this->subPlotTeam = $subPlot_team;
            $this->subPlantTeam = $subPlant_team;
        }
// dd($subPlant_team);
        $this->dispatch('thisTeamProgress', data:[
            'totalPlantsByTeam' => $this->subPlantTeam,
            'totalSubPlotsByTeam' => $this->subPlotTeam,
        ]);
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
            $this->thisCounty = '';
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
            // $status = PlotCompletedCheckHelper::getPlotCompletedInfo($plot);
            $status = collect($this->thisPlotWithStatus)->firstWhere('plot', $plot);
            // dd($status);
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
            $rawData = SubPlotPlant2025::where('plot_full_id', 'like', $plot . '%')->get();

            $grouped = $rawData->groupBy(function ($item) {
                return substr($item->plot_full_id, 6, 2); // 第 7、8 碼為 habitat code
            });

            $habitatStats = [];

            foreach ($grouped as $habCode => $group) {
                $dataErrorCount = $group->filter(function ($item) {
                    return $item->coverage == 0 || $item->data_error != 0;
                })->count();

                $unidentifiedCount = $group->where('unidentified', 1)->count();

                $habitatStats[$habCode] = [
                    'data_error_count' => $dataErrorCount,
                    'unidentified_count' => $unidentifiedCount,
                ];
            }

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
                        'unidentified_count' => $habitatStats[$habCode]['unidentified_count'] ?? 0,
                        'data_error_count' => $habitatStats[$habCode]['data_error_count'] ?? 0,
                        'completed' => is_array($status) && ($status['plotCompleted'] ?? null) === '1',
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
                    'unidentified_count' => null,
                    'data_error_count' => null,
                    'completed' => null,
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
    public $status = []; // 用來存放 plot 的完成狀態
//選擇樣區之後
    public function loadPlotInfo($value)
    { 
        $this->thisPlot = $value;
        $this->thisHabType = ''; // 清除目前選擇的 habitat_code
        $this->thisSelectedHabitat = ''; 
        $this->refreshKey = now(); // 加一個 dummy key 讓 view 重繪
        // $this->status = PlotCompletedCheckHelper::getPlotCompletedInfo($value);
        $this->status = collect($this->thisPlotWithStatus)->firstWhere('plot', $value);
//   dd($this->status);
        if ($this->allPlotInfo !=[]){
            $index = array_search($value, array_column($this->allPlotInfo, 'plot'));
            $this->thisPlotFile = $index !== false ? $this->allPlotInfo[$index]['plotFile'] : null;            
        }

        $thisPlotTeam=PlotList2025::where('plot', $value)
            ->pluck('team')
            ->first();

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
                //包含覆蓋度錯誤(coverage == 0)與物種重複(cov_error == 2)
                $dataError = (clone $plantQuery)
                    ->where(function ($query) {
                        $query->where('data_error', '!=', 0)
                            ->orWhere('coverage', 0);
                    })
                    ->count();

                $this->subPlotSummary[] = [
                    'team' => $thisPlotTeam,
                    'plot_full_id' => $plotFullID,
                    'subplot_id' => substr($plotFullID , 8, 2),
                    'habitat_code' => $habitatCode,  // ⬅️ 需保留 code 才能篩選
                    'habitat' => $habitat,
                    'plant_count' => $total,
                    'unidentified_count' => $unidentified,
                    'data_error_count' => $dataError,
                    'original_plot_id' =>$plotQuery['original_plot_id'] ?? null,
                    'date' => $plotQuery['date'] ?? null,
                    'uploaded_at' => $plotQuery['file_uploaded_at'] ?? null,
                    'photo_path' => $photopath ?? null,
                ];
            }

            $this->filteredSubPlotSummary = $this->subPlotSummary;

        }

    }

    public $thisHabType=''; // 用來記錄目前選擇的 habitat_code

    public function reloadPlotInfo($value)
    {
        if ($value === '') {
            // 顯示全部
            $this->filteredSubPlotSummary = $this->subPlotSummary;
            $this->thisSelectedHabitat = ''; 
        } else {
            // 篩選指定 habitat_code
            $this->thisHabType = $value; // 更新目前選擇的 habitat_code
            $this->thisSelectedHabitat = "{$value} {$this->subPlotHabList[$value]}"; // 更新目前選擇的 habitat_code
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
