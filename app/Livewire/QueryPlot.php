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


class QueryPlot extends Component
{

    public $countyList=[];
    public $thisCounty;
    public $plotList=[];
    public $thisPlot;


    public $subPlotList = [];
    public $thisSubPlot;
    public $allPlotInfo = [];
    public $showAllPlot = true;
    public $thisListType='';

    public function mount()
    {
        $this->countyList = PlotList2025::select('county')->distinct()->pluck('county')->toArray();

    }

    public function loadPlots($county)
    {
        $this->plotList = [];
        $this->plotList = PlotList2025::where('county', $county)
            ->select('plot')->distinct()->pluck('plot')->toArray();
        $this->thisHabType = '';
        $this->thisPlot = '';
        $this->thisListType = '';
        $this->habTypeOptions=[];
        $this->plotplantList=[];
        $this->dispatch('thisPlotUpdated');
        $this->dispatch('thisHabTypeUpdated');
        $this->dispatch('thisSubPlotUpdated');

    }
    public $plotplantList=[];
    public $plotplantListAll=[];
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

        // 取得2010樣區資料
        $plotHab2010 = SubPlotEnv2010::where('PLOT_ID', $plot)
            ->pluck('HAB_TYPE')         // 只取出單欄位
            ->unique()                  // 移除重複值（可省略，pluck 已自動處理）
            ->values()                  // 重新索引（0,1,2...）
            ->toArray(); 

        $plotHab2025 = SubPlotEnv2025::where('plot', $plot)
            ->pluck('habitat_code')         // 只取出單欄位
            ->unique()                  // 移除重複值（可省略，pluck 已自動處理）
            ->values()                  // 重新索引（0,1,2...）
            ->toArray(); 
        
        $plotHabList=array_unique(array_merge($plotHab2010, $plotHab2025));
        $habTypeMap = HabitatInfo::pluck('habitat', 'habitat_code')->toArray();

        $this->habTypeOptions = collect($plotHabList)
            ->filter(fn($code) => isset($habTypeMap[$code]))   // 過濾掉沒有對應名稱的
            ->mapWithKeys(fn($code) => [$code => $habTypeMap[$code]]) // 轉成 code => habitat
            ->sortBy(fn($label, $code) => $label) // 依 habitat 名稱排序（可省略）
            ->toArray();

// 取得小樣區清單
        $subPlotList2010 = SubPlotEnv2010::where('PLOT_ID', $plot)
            ->select('PLOT_ID', 'HAB_TYPE', 'SUB_ID')
            ->get()
            ->map(function ($item) {
                return $item->PLOT_ID . $item->HAB_TYPE . $item->SUB_ID;
            })
            ->unique()
            ->values()
            ->toArray();


        $subPlotList2025 = SubPlotEnv2025::where('plot', $plot)
            ->pluck('plot_full_id')         // 只取出單欄位
            ->unique()                  // 移除重複值（可省略，pluck 已自動處理）
            ->values()                  // 重新索引（0,1,2...）
            ->toArray(); 
        $this->subPlotList=array_unique(array_merge($subPlotList2010, $subPlotList2025));
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
            $this->thisHabType='';
            $this->plotplantList=$this->plotplantListAll;
            $this->thisListType=$this->thisPlot;
            return;
        }


            
        // $this->dispatch('plotIDUpdated', plotID: '');
// dd($plotPlant2025);
        $this->plotplantList = PlantListHelper::getMergedPlotPlantList($this->thisPlot, [
            'hab_type' => $habType
        ]);
        
        // $this->plotplantListAll =  $this->plotplantList;
        // $this->dispatch('SubPlotIDUpdated', subPlotID: $subPlot);
        $this->thisListType=$this->thisPlot." ".$this->habTypeOptions[$habType];
       
        $this->dispatch('thisSubPlotUpdated');
        $this->dispatch('plantListLoaded');

    }

    public function loadSubPlot($thisSubPlot)
    {
        $this->thisSubPlot = $thisSubPlot; // 讓下拉選單同步更新
        
        if ($thisSubPlot === '') {
            // 顯示全部樣區
            $this->thisSubPlot='';
            $this->thisListType=$this->thisPlot;
            $this->plotplantList=$this->plotplantListAll;
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
        $this->thisListType=$thisSubPlot;
       
        $this->dispatch('thisHabTypeUpdated');
        $this->dispatch('plantListLoaded');

    }

    public function render()
    {
        return view('livewire.query-plot');
    }
}
