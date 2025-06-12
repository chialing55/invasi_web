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
    public $showAllPlot = true;
    public $thisListType='';


    public function mount()
    {
        $this->countyList = PlotList2025::select('county')->distinct()->pluck('county')->toArray();

        $this->surveryedPlotInfo('', '');

    }

    public function surveryedPlotInfo($thisCounty)
    {
        if ($thisCounty) {
            // 找出該縣市的 plot 清單
            $plotList = PlotList2025::where('county', $thisCounty)->pluck('plot')->toArray();

            $this->totalPlotCount = count($plotList);

            $this->completedSubPlotCount = SubPlotEnv2025::whereIn('plot', $plotList)->count();

            $this->surveyedPlotCount = SubPlotEnv2025::whereIn('plot', $plotList)
                ->distinct('plot')
                ->count('plot');

            $this->plotList = $plotList;
            $this->thisPlot='';
            
        } else {
            // 全部縣市
            $this->totalPlotCount = PlotList2025::count();

            $this->completedSubPlotCount = SubPlotEnv2025::count();

            $this->surveyedPlotCount = SubPlotEnv2025::distinct('plot')->count('plot');
            $this->thisPlot='';
            $this->plotList = [];
        }

        $this->dispatch('thisPlotUpdated');

        // 調查完成率
        // $this->surveyRate = $this->totalPlotCount > 0
        //     ? round($this->SurveyedPlotCount / $this->totalPlotCount * 100, 1)
        //     : 0;
    }

    public $subPlotSummary = [];
    public $subPlotHabList = [];
    public $thisHabitat = '';           // 使用者目前選的 habitat_code
    public $filteredSubPlotSummary = []; // 用來顯示的表格資料
    public function loadPlotInfo($value)
    {
        $this->thisPlot = $value;
        $habTypeMap = HabitatInfo::pluck('habitat', 'habitat_code')->toArray(); // code => 中文名
        $thisPlotList = SubPlotEnv2025::where('plot', $value)->get();

        foreach ($thisPlotList as $subPlot) {
            $plotFullID = $subPlot->plot_full_id;

            // 查該小樣區的植物資料
            $plantQuery = SubPlotPlant2025::where('plot_full_id', $plotFullID);

            $total = $plantQuery->count();
            $unidentified = (clone $plantQuery)->where('unidentified', 1)->count();
            //包含覆蓋度錯誤(cov_error == 1)與物種重複(cov_error == 2)
            $covError = (clone $plantQuery)->where('cov_error', '!=', 0)->count();

            // 生育地對照
            $habitatCode = $subPlot->habitat_code;
            $habitat = $habTypeMap[$habitatCode] ?? '未知';

            $this->subPlotSummary[] = [
                'plot_full_id' => $plotFullID,
                'subplot_id' => $subPlot->subplot_id,
                'habitat_code' => $habitatCode,  // ⬅️ 需保留 code 才能篩選
                'habitat' => $habitat,
                'plant_count' => $total,
                'unidentified_count' => $unidentified,
                'cov_error_count' => $covError,
            ];
        }

        $this->subPlotHabList = collect($thisPlotList)
            ->pluck('habitat_code')
            ->unique()
            ->mapWithKeys(function ($code) use ($habTypeMap) {
                return [$code => $habTypeMap[$code] ?? '未知'];
            })
            ->toArray();
        $this->filteredSubPlotSummary = $this->subPlotSummary;

    }

    public function reloadPlotInfo($value)
    {
        if ($value === '') {
            // 顯示全部
            $this->filteredSubPlotSummary = $this->subPlotSummary;
        } else {
            // 篩選指定 habitat_code
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
