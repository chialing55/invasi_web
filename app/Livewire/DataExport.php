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
use App\Exports\MissingPlotExport;
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
    public $dataType = 'allData';

    public function downloadSelected()
    {

        $dataType = $this->dataType;
        switch ($this->dataType) {
            case 'allData':
                $this->downloadFormat = 'xlsx';
                break;
            case 'statsTable':
                $this->downloadFormat = 'xlsx';
                break;
            case 'reasonsTable':
                $this->downloadFormat = 'xlsx';
                break;
            case 'env.xlsx':
                $this->downloadFormat = 'xlsx';
                break;
            case 'plant.xlsx':
                $this->downloadFormat = 'xlsx';
                break;
            case 'plantList.xlsx':
                $this->downloadFormat = 'xlsx';
                break;
            case 'env.txt':
                $this->downloadFormat = 'txt';
                break;

            case 'plant.txt':
                $this->downloadFormat = 'txt';
                break;

            case 'plantList.txt':
                $this->downloadFormat = 'txt';
                break;
            case 'allPlantList':
                $this->downloadFormat = 'xlsx';
                break;
            default:
                $this->downloadFormat = 'xlsx';
                break;
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
        // 這一行會同時拿到對應的 Export 物件與檔名
        [$export, $filename] = $this->buildExportAndFilename($dataType, $prefix, $ext, $format);

        // 然後就回傳下載（單一出口）
        return ExcelFacade::download($export, $filename, $format);

    }

// 建議：抽一個私有方法產生 [$export, $filename]
    private function buildExportAndFilename(string $dataType, string $prefix, string $ext, $format): array
    {
        // 先把 format 正規化：txt 的幾個子型態前面已改過 this->downloadFormat
        $fmt = $this->downloadFormat;

        return match (true) {
            // === xlsx 類 ===
            $fmt === 'xlsx' && $dataType === 'allData' => [
                new MultiSheetExport($this->selectedPlots, $fmt),
                "$prefix.xlsx",
            ],
            $fmt === 'xlsx' && $dataType === 'allplantlist' => [
                new PlantListMultiSheetExport($this->selectedPlots, $fmt),
                "allPlantList.xlsx",
            ],
            $fmt === 'xlsx' && $dataType === 'statsTable' => [
                new StatsMultiSheetExport($this->selectedPlots, $fmt),
                "$prefix-statsTable.xlsx",
            ],
            $fmt === 'xlsx' && $dataType === 'reasonsTable' => [
                new MissingPlotExport($this->selectedPlots, $fmt, '小樣區未調查原因'),
                "$prefix-unSurveyedSubplotReasons.xlsx",
            ],
            $fmt === 'xlsx' && $dataType === 'env.xlsx' => [
                new PlotExport($this->selectedPlots, $fmt, '環境資料'),
                "$prefix-env.$ext",
            ],
            $fmt === 'xlsx' && $dataType === 'plant.xlsx' => [
                new PlantDataExport($this->selectedPlots, $fmt, '植物資料'),
                "$prefix-plant.$ext",
            ],
            $fmt === 'xlsx' && $dataType === 'plantlist.xlsx' => (function () use ($prefix, $ext) {
                // 特例：plantlist xlsx 需要先算 rows/headings，再用 TableExport + tab 分隔
                $sel = PlantListExport::PlantListDistinctForPlots(
                    selectedPlots: $this->selectedPlots,
                    format: 'xlsx'
                );
                return [
                    new PlantListTableExport(
                        rows: $sel['rows'],
                        title: '植物名錄',
                        headings: $sel['headings'],
                        layouts: '',
                        csvDelimiter: "\t" // 關鍵：tab 分隔
                    ),
                    "{$prefix}-plantlist.$ext",
                ];
            })(),
            // === txt 類 ===
            $fmt === 'txt' && $dataType === 'env.txt' => [
                new PlotExport($this->selectedPlots, $fmt, '環境資料'),
                "$prefix-env.$ext",
            ],
            $fmt === 'txt' && $dataType === 'plant.txt' => [
                new PlantDataExport($this->selectedPlots, $fmt, '植物資料'),
                "$prefix-plant.$ext",
            ],
            $fmt === 'txt' && $dataType === 'plantlist.txt' => (function () use ($prefix, $ext) {
                // 特例：plantlist txt 需要先算 rows/headings，再用 TableExport + tab 分隔
                $sel = PlantListExport::PlantListDistinctForPlots(
                    selectedPlots: $this->selectedPlots,
                    format: 'txt'
                );
                return [
                    new PlantListTableExport(
                        rows: $sel['rows'],
                        title: '植物名錄',
                        headings: $sel['headings'],
                        layouts: '',
                        csvDelimiter: "\t" // 關鍵：tab 分隔
                    ),
                    "{$prefix}-plantlist.$ext",
                ];
            })(),

            default => throw new \RuntimeException("Unsupported export combination: {$fmt} / {$dataType}"),
        };
    }


    public function render()
    {
        return view('livewire.data-export');
    }
}
