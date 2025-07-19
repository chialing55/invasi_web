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
            $thisteam = 'all';
        }

        $this->thisTeam = $thisteam;
        if ($thisteam === 'all') {
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
            $this->thisTeam = 'all';
        }
        if ($thisCounty == '') {
            $thisCounty = 'all';
        }

        $this->thisCounty = $thisCounty;

        $plotListQuery = SubPlotEnv2025::select('im_splotdata_2025.plot as plot')
            ->join('plot_list', 'im_splotdata_2025.plot', '=', 'plot_list.plot')
            ->where('year', $this->thisYear);



        if ($thisCounty === 'all') {
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

            $county = PlotList2025::where('plot', $plot)
                ->select('county')->first();
            $relativePath = "invasi_files/plotData/{$this->thisCounty}/{$plot}.pdf";
            $fullPath = public_path($relativePath);

            if (file_exists($fullPath)) {
                $thisPlotFile = asset($relativePath);
            } else {
                $thisPlotFile = null;
            }


            $summary[] = [
                'county' => $county->county,
                'plot' => $plot,
                'plotFile' => $thisPlotFile,

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
            ->whereIn('im_splotdata_2025.plot', $this->selectedPlots)
            ->select(
                'im_spvptdata_2025.*',
                'spinfo.chname',
                'spinfo.chfamily',
                'spinfo.family',
                'spinfo.latinname',
                'spinfo.endemic',
                'spinfo.naturalized',
                'spinfo.cultivated',
                'im_splotdata_2025.plot',
                'im_splotdata_2025.habitat_code',
                'im_splotdata_2025.subplot_id',
                'plot_list.county'
            )
            ->orderby('im_spvptdata_2025.plot_full_id', 'asc')
            ->get()
            ->toArray();
        if ($this->downloadFormat=='txt.1'){
            $this->downloadFormat='txt';
            $dataType = 'env';
        } else if ($this->downloadFormat=='txt.2'){
            $this->downloadFormat='txt';
            $dataType = 'plant';
        } else {
            $dataType = 'all';
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
        if ($this->downloadFormat === 'xlsx') {
            return ExcelFacade::download(
                new MultiSheetExport($envdata, $plantdata, $this->downloadFormat),
                "$prefix.xlsx",
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

        }

    }

    public function render()
    {
        return view('livewire.data-export');
    }
}
