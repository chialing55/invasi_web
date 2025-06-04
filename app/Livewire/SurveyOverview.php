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
    public $thisPlot;

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
            
        } else {
            // 全部縣市
            $this->totalPlotCount = PlotList2025::count();

            $this->completedSubPlotCount = SubPlotEnv2025::count();

            $this->surveyedPlotCount = SubPlotEnv2025::distinct('plot')->count('plot');
        }

        // 調查完成率
        // $this->surveyRate = $this->totalPlotCount > 0
        //     ? round($this->SurveyedPlotCount / $this->totalPlotCount * 100, 1)
        //     : 0;
    }

    public $thisPlotList = [];

    public function loadPlotInfo($value)
    {
        $this->thisPlot = $value;
        $this->thisPlotList = SubPlotEnv2025::where('plot', $value)->get();


    }


    public function render()
    {
        return view('livewire.survey-overview');
    }
}
