<?php

namespace App\Livewire;

use Livewire\Component;

use App\Models\PlotList2025;
use App\Models\SubPlotEnv2010;
use App\Models\SubPlotEnv2025;
use App\Models\SubPlotPlant2010;
use App\Models\SubPlotPlant2025;
use App\Models\Twredlist2017;   
use App\Models\SpInfo;
use App\Models\HabitatInfo;
use Illuminate\Support\Facades\DB;
use App\Helpers\HabHelper;
use App\Helpers\PlotHelper;
use App\Helpers\PlotCompletedHelper;
use App\Helpers\PlotCompletedCheckHelper;
use Illuminate\Support\Facades\Auth;

use App\Exports\PlotExport;
use App\Exports\MultiSheetExport;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Maatwebsite\Excel\Excel;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DataExport extends Component
{

    public $countyList = [];
    public $teamList = [];
    public $plotInfo = [];
    public $thisCounty;
    public $thisTeam;
    public $thisPlot;
    public $yearList = [];
    public $thisYear = null;
    public function mount()
    {
        $this->teamList = PlotList2025::select('team')->distinct()->pluck('team')->toArray();
        $this->countyList = PlotList2025::select('county')->distinct()->pluck('county')->toArray();
        $this->yearList = SubPlotEnv2025::selectRaw('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();
        $this->thisYear ??= date('Y'); // 如果未指定才設定
    }
    //選擇團隊之後
    public function loadCountyList($thisteam)
    {

        if ($thisteam == '') {
            $thisteam = 'All';
        }

        $this->thisTeam = $thisteam;
        if ($thisteam === 'All') {
            $this->countyList = PlotList2025::select('county')->distinct()->pluck('county')->toArray();

        } else {
            $this->countyList = PlotList2025::where('team', $thisteam)
                ->select('county')->distinct()->pluck('county')->toArray();

        }


        $this->thisCounty = '';
        $this->dispatch('thisCountyUpdated');
        $this->allPlotInfo = [];
    }
    public $plotList = [];
    public $allPlotInfo = [];
    public $showAllPlotInfo = [];
    public $allContyInfo = [];
    public $showContyInfo = [];
    public $allTeamInfo = [];
    public $showTeamInfo = [];
    public $subPlotSummary = [];
    public $subPlotHabList = [];
    public $thisHabitat = '';           // 使用者目前選的 habitat_code
    public $filteredSubPlotSummary = []; // 用來顯示的表格資料
    //選擇縣市之後
    public function surveryedPlotInfo($thisCounty)
    {
        $this->allPlotInfo = [];
        if ($this->thisTeam == '') {
            $this->thisTeam = 'All';
        }
        if ($thisCounty == '') {
            $thisCounty = 'All';
        }

        $this->thisCounty = $thisCounty;

        $plotListQuery = SubPlotEnv2025::select('im_splotdata_2025.plot as plot')
            ->join('plot_list', 'im_splotdata_2025.plot', '=', 'plot_list.plot')
            ->where('year', $this->thisYear);

// dd($thisCounty);

        if ($thisCounty === 'All') {
            $plotListQuery->whereIn('plot_list.county', $this->countyList);
        } else {
            $plotListQuery->where('plot_list.county', $thisCounty);
        }

        $plotList = $plotListQuery
            ->distinct()
            ->pluck('plot')
            ->toArray();


        $this->plotList = $plotList;
        // $this->dispatch('thisPlotUpdated');
        $this->thisPlotFile = null;

        $this->loadAllPlotInfo($plotList);
    }
    public $thisPlotFile = null;
    public $selectedPlots = []; // 用於存儲選中的樣區
    public function loadAllPlotInfo($plotList)
    {

        $summary = [];
        foreach ($plotList as $plot) {

            $county = PlotList2025::where('plot', $plot)->value('county');
            $status = PlotCompletedCheckHelper::getPlotCompletedInfo($plot);

            $summary[] = [
                'county' => $county,
                'plot' => $plot,
                'completed' => $status['plotCompleted'] == '1' ? true : false,

            ];
        }

        $this->allPlotInfo = collect($summary)
            ->sortByDesc(fn($item) => !is_null($item['plot']))
            ->values()
            ->toArray();
        $this->selectedPlots = $plotList;
        // dd($this->allPlotInfo);
        //  dd($summary);
        // $this->allPlotInfo = $summary;
        // $this->showAllPlotInfo = $this->allPlotInfo;


    }

    public $downloadFormat = 'xlsx'; // 預設下載格式為 xlsx

    public function downloadSelected()
    {

        $envdata = SubPlotEnv2025::whereIn('im_splotdata_2025.plot', $this->selectedPlots)
            ->join('plot_list', 'im_splotdata_2025.plot', '=', 'plot_list.plot')
            ->select(
                'im_splotdata_2025.*',
                'plot_list.county',
            )
            ->orderby('im_splotdata_2025.plot_full_id', 'asc')
            ->get()->toArray();
        $plantdata = SubPlotPlant2025::leftjoin('spinfo', 'im_spvptdata_2025.spcode', '=', 'spinfo.spcode')
            ->join('im_splotdata_2025', 'im_spvptdata_2025.plot_full_id', '=', 'im_splotdata_2025.plot_full_id')
            ->join('plot_list', 'im_splotdata_2025.plot', '=', 'plot_list.plot')
            ->leftjoin('twredlist2017', 'im_spvptdata_2025.spcode', '=', 'twredlist2017.spcode')
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
                            AND spinfo.cultivated != '1' 
                            AND (spinfo.uncertain IS NULL OR spinfo.uncertain != '1')
                        THEN 1 
                        ELSE 0 
                    END AS native
                "),
                'spinfo.endemic',
                'spinfo.naturalized',
                'spinfo.cultivated',
                'twredlist2017.IUCN',
                // 'twredlist2017.origin_type as origin_type_redlist',
                'plot_list.county',
                'im_splotdata_2025.plot',
                'im_splotdata_2025.habitat_code',
                'im_splotdata_2025.subplot_id',
            )
            ->orderby('im_spvptdata_2025.plot_full_id', 'asc')
            ->orderby('im_spvptdata_2025.coverage', 'desc')
            ->get()
            ->toArray();

        $plantList = SubPlotPlant2025::join('spinfo', 'im_spvptdata_2025.spcode', '=', 'spinfo.spcode')
            ->leftjoin('twredlist2017', 'im_spvptdata_2025.spcode', '=', 'twredlist2017.spcode')
            ->whereIn('im_spvptdata_2025.plot_full_id', function ($query) {
                $query->select('plot_full_id')
                    ->from('im_splotdata_2025')
                    ->whereIn('plot', $this->selectedPlots);
            })
            ->select(
                // 'spinfo.spcode',
                'spinfo.family',
                'spinfo.chfamily',
                'spinfo.latinname',
                'spinfo.chname',                
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
            ->orderBy('spinfo.family')
            ->orderBy('spinfo.latinname')
            ->get()
            ->toArray();

        $plantListAll = SubPlotPlant2025::join('spinfo', 'im_spvptdata_2025.spcode', '=', 'spinfo.spcode')
            ->leftjoin('twredlist2017', 'im_spvptdata_2025.spcode', '=', 'twredlist2017.spcode')
            ->select(
                // 'spinfo.spcode',
                DB::raw("CONCAT(spinfo.family, ' ', spinfo.chfamily) AS family"),
                'spinfo.latinname',
                'spinfo.chname',                
                // 'spinfo.family',                
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
            ->orderBy('spinfo.latinname')
            ->get()
            ->toArray();

        if ($this->downloadFormat=='txt.1'){
            $this->downloadFormat='txt';
            $dataType = 'env';
        } else if ($this->downloadFormat=='txt.2'){
            $this->downloadFormat='txt';
            $dataType = 'plant';
        } else if ($this->downloadFormat=='txt.3'){
            $this->downloadFormat='txt';
            $dataType = 'plantlist';
        } else if ($this->downloadFormat=='xlsx.1'){
            $this->downloadFormat='xlsx';
            $dataType = 'allplantlist';
        } else {
            $dataType = 'plotinfo';
        }

        $formatConstants = [
            'xlsx' => Excel::XLSX,
            // 'csv'  => Excel::CSV,
            'txt'  => Excel::CSV // txt 實際上用 CSV 格式 + 自訂分隔符
        ];


        $format = $formatConstants[$this->downloadFormat] ?? Excel::CSV;
        $ext = $this->downloadFormat;

        $prefix = $this->thisTeam . '_' . $this->thisCounty . '_' . date('Ymd');

        // ✅ 如果為 xlsx，使用多工作表
        if ($this->downloadFormat === 'xlsx' && $dataType=='plotinfo') {
            return ExcelFacade::download(
                new MultiSheetExport($envdata, $plantdata, $plantList, $this->downloadFormat),
                "$prefix.xlsx",
                $format
            );
        } else if ($this->downloadFormat === 'xlsx' && $dataType=='allplantlist') {
            // ✅ 全部植物名錄
            return ExcelFacade::download(
                new PlotExport($plantListAll, $this->downloadFormat, '植物名錄', true), // 👉 第四個參數為 true → 合併 family
                "allplantlist.xlsx",
                $format
            );
        } else if ($this->downloadFormat === 'txt' && $dataType=='env') {
            // ✅ 環境資料 txt
            return ExcelFacade::download(
                new PlotExport($envdata, $this->downloadFormat),
                "$prefix-env.$ext",
                $format
            );
        } else if ($this->downloadFormat === 'txt' && $dataType=='plant') {
            // ✅ 植物資料 txt
            return ExcelFacade::download(
                new PlotExport($plantdata, $this->downloadFormat),
                "$prefix-plant.$ext",
                $format
            );

        } else if ($this->downloadFormat === 'txt' && $dataType=='plantlist') {
            // ✅ 植物名錄 txt
            return ExcelFacade::download(
                new PlotExport($plantList, $this->downloadFormat),
                "$prefix-plantlist.$ext",
                $format
            );

        } 

    }

    public function render()
    {
        return view('livewire.data-export');
    }
}
