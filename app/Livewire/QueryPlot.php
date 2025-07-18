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
use App\Helpers\PlantListHelper;
use App\Helpers\PlotHelper;

class QueryPlot extends Component
{

    public $countyList = [];
    public $thisCounty;
    public $plotList = [];
    public $thisPlot;


    public $subPlotList = [];
    public $thisSubPlot;
    public $allPlotInfo = [];
    public $showAllPlot = true;
    public $thisListType = '';

    public function mount()
    {
        $this->countyList = PlotList2025::select('county')->distinct()->pluck('county')->toArray();

        if (session()->has('query.county')) {
            $county  = session()->pull('query.county');
            $plot    = session()->pull('query.plot');
            $subPlot = session()->pull('query.subPlot');

            $this->fromOverview($county, $plot, $subPlot);
        }
    }

    public function loadPlots($county)
    {
        $this->plotList = [];
        $this->plotList = PlotList2025::where('county', $county)
            ->select('plot')->distinct()->pluck('plot')->toArray();
        $this->thisHabType = '';
        $this->thisPlot = '';
        $this->thisListType = '';
        $this->habTypeOptions = [];
        $this->plotplantList = [];
        $this->dispatch('thisPlotUpdated');
        $this->dispatch('thisHabTypeUpdated');
        $this->dispatch('thisSubPlotUpdated');
    }
    public $plotplantList = [];
    public $plotplantListAll = [];
    public $habTypeOptions;


    // 樣區內所有生育地類型與全部植物名錄
    public function loadPlotInfo($plot)
    {
        $this->thisPlot = $plot;
        $this->thisHabType = '';
        $this->thisListType = $plot;
        $this->habTypeOptions = [];
        // $this->thisSubPlot = ''; // 清空樣區ID
        // $this->subPlotList = []; // 清空樣區ID
        // $this->thisSubPlotInfo = []; // 清空樣區ID資料
        // $this->plotInfo2025 = []; // 清空樣區資料

        // 取得樣區資料
        if (empty($plot)) {
            return;
        }
        //取得生育地類型列表、小樣區清單
        $data = PlotHelper::getSubPlotInfo($plot);
        $this->habTypeOptions = $data['habTypeOptions'];
        $this->subPlotList = $data['subPlotList'];
        // dd($plotPlant2025);

        // $this->dispatch('plotIDUpdated', plotID: '');

        $this->plotplantList = PlantListHelper::getMergedPlotPlantList($this->thisPlot);
        // dd( $this->plotplantList);
        $this->plotplantListAll =  $this->plotplantList;

        $this->dispatch('thisHabTypeUpdated');
        $this->dispatch('thisSubPlotUpdated');
        $this->dispatch('plantListLoaded');
    }

    public $thisSubPlotPlant2010 = [];
    public $thisSubPlotPlant2025 = [];
    public $thisHabType;

    //單一個生育地資料
    public function loadPlotHab($habType)
    {
        $this->thisHabType = $habType; // 讓下拉選單同步更新

        if ($habType === '') {
            // 顯示全部樣區
            $this->thisHabType = '';
            $this->plotplantList = $this->plotplantListAll;
            $this->thisListType = $this->thisPlot;
            return;
        }



        // $this->dispatch('plotIDUpdated', plotID: '');
        // dd($plotPlant2025);
        $this->plotplantList = PlantListHelper::getMergedPlotPlantList($this->thisPlot, [
            'hab_type' => $habType
        ]);

        // $this->plotplantListAll =  $this->plotplantList;
        // $this->dispatch('SubPlotIDUpdated', subPlotID: $subPlot);
        $this->thisListType = $this->thisPlot . " " . $this->habTypeOptions[$habType];

        $this->dispatch('thisSubPlotUpdated');
        $this->dispatch('plantListLoaded');
    }

    public function loadSubPlot($thisSubPlot)
    {
        $this->thisSubPlot = $thisSubPlot; // 讓下拉選單同步更新

        if ($thisSubPlot === '') {
            // 顯示全部樣區
            $this->thisSubPlot = '';
            $this->thisListType = $this->thisPlot;
            $this->plotplantList = $this->plotplantListAll;
            return;
        }

        $this->thisHabType = ''; // 讓下拉選單同步更新
        //  dd($thisSubPlot);
        $habType = substr($thisSubPlot, 6, 2);   // 第 7,8 位（index 從 0 開始）
        $sub_id  = substr($thisSubPlot, -2);     // 最後兩位



        $this->plotplantList = PlantListHelper::getMergedPlotPlantList($this->thisPlot, [
            'hab_type' => $habType,
            'sub_id' => $sub_id,
            'sub_plot' => $thisSubPlot, // 給 2025 用
        ]);
        // dd($this->plotplantList);
        // $this->plotplantListAll =  $this->plotplantList;
        // $this->dispatch('SubPlotIDUpdated', subPlotID: $subPlot);
        $this->thisListType = $thisSubPlot;

        $this->dispatch('thisHabTypeUpdated');
        $this->dispatch('plantListLoaded');
    }

    public function fromOverview($county, $plot, $subPlot)
    {
        $this->thisCounty = $county;
        $this->loadPlots($county);
        $this->loadPlotInfo($plot);

        $this->loadSubPlot($subPlot);
    }

    public $sortField = 'cov_2025';
    public $sortDirection = 'asc';

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->plotplantList = collect($this->plotplantList)
            ->sortBy($this->sortField, SORT_REGULAR, $this->sortDirection === 'desc')
            ->values()
            ->toArray();
    }
    public $downloadFormat = 'csv'; // 預設為 .csv

    public function render()
    {
        return view('livewire.query-plot');
    }
}
