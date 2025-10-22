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
use App\Support\AnalysisHelper;
use App\Exports\PlantListTableExport;
use App\Exports\PlotExport;
use App\Exports\PlantDataExport;
use App\Exports\PlantListExport;
use App\Exports\MultiSheetExport;
use App\Exports\StatsMultiSheetExport;
use App\Exports\PlantListMultiSheetExport;
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
    public $thisCensusYear = null;
    public function mount()
    {
        $this->teamList = PlotList2025::select('team')->distinct()->pluck('team')->toArray();
        $this->countyList = PlotList2025::select('county')->distinct()->pluck('county')->toArray();
        $this->yearList = PlotList2025::where('census_year', '>=', 2025)
                ->distinct()
                ->orderByDesc('census_year')
                ->pluck('census_year')
                ->toArray();
        $this->thisCensusYear ??= date('Y'); // 如果未指定才設定
    }
    //選擇團隊之後
    public function loadCountyList($thisteam)
    {

        if ($thisteam == '') {
            $thisteam = 'All';
        }

        $this->thisTeam = $thisteam;
        if ($thisteam === 'All') {
            $this->countyList = PlotList2025::select('county')
                ->when(!blank($this->thisCensusYear), fn($q) =>
                    $q->where('census_year', $this->thisCensusYear)
                )
                ->distinct()->pluck('county')->toArray();

        } else {
            $this->countyList = PlotList2025::where('team', $thisteam)
                ->when(!blank($this->thisCensusYear), fn($q) =>
                    $q->where('census_year', $this->thisCensusYear)
                )
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
            ->where('year', $this->thisCensusYear);

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
        } else if ($this->downloadFormat=='xlsx.2'){
            $this->downloadFormat='xlsx';
            $dataType = 'statsTable';
        } else {
            $dataType = 'plotinfo';
        }

        $formatConstants = [
            'xlsx' => Excel::XLSX,
            // 'csv'  => Excel::CSV,
            'txt'  => Excel::CSV // txt 實際上用 CSV 格式 + 自訂分隔符
        ];

        // $rows = AnalysisHelper::buildHabitatShannonIndex($envdata, $plantdata);
        // dd($rows);

        $format = $formatConstants[$this->downloadFormat] ?? Excel::CSV;
        $ext = $this->downloadFormat;

        $prefix = $this->thisTeam . '_' . $this->thisCounty . '_' . date('Ymd');

        // ✅ 如果為 xlsx，使用多工作表
        if ($this->downloadFormat === 'xlsx' && $dataType=='plotinfo') {
            return ExcelFacade::download(
                new MultiSheetExport($this->selectedPlots, $this->downloadFormat),
                "$prefix.xlsx",
                $format
            );
        } else if ($this->downloadFormat === 'xlsx' && $dataType=='allplantlist') {
            // ✅ 全部植物名錄
            return ExcelFacade::download(
                new PlantListMultiSheetExport($this->selectedPlots, $this->downloadFormat),
                "allPlantList.xlsx",
                $format
            );
        } else if ($this->downloadFormat === 'xlsx' && $dataType=='statsTable') {
            // ✅ 統計表格
            return ExcelFacade::download(
                new StatsMultiSheetExport($this->selectedPlots, $this->downloadFormat),
                "$prefix-statsTable.xlsx",
                $format
            );
        } else if ($this->downloadFormat === 'txt' && $dataType=='env') {
            // ✅ 環境資料 txt
            return ExcelFacade::download(
                new PlotExport($this->selectedPlots, $this->downloadFormat, '環境資料'),
                "$prefix-env.$ext",
                $format
            );
        } else if ($this->downloadFormat === 'txt' && $dataType=='plant') {
            // ✅ 植物資料 txt
            return ExcelFacade::download(
                new PlantDataExport($this->selectedPlots, $this->downloadFormat, '植物資料'),
                "$prefix-plant.$ext",
                $format
            );

        } else if ($this->downloadFormat === 'txt' && $dataType=='plantlist') {
            // ✅ 植物名錄 txt
            // return ExcelFacade::download(
            //     new PlantListExport($this->selectedPlots, '2', $this->downloadFormat, '植物名錄', false),
            //     "$prefix-plantlist.$ext",
            //     $format
            // );
            $sel = PlantListExport::PlantListDistinctForPlots(
                selectedPlots: $this->selectedPlots,
                format: 'txt'
            );

            // 匯出為「CSV writer + tab 分隔」，副檔名給 .txt
            return ExcelFacade::download(
                new PlantListTableExport(
                    rows: $sel['rows'],
                    title: '植物名錄',
                    headings: $sel['headings'],
                    layouts: '',
                    csvDelimiter: "\t" // ★ 關鍵：改成 tab
                ),
                "{$prefix}-plantlist.$ext",
                $format
            );
        } 

    }

    public function render()
    {
        return view('livewire.data-export');
    }
}
