<?php

namespace App\Livewire;

use Livewire\Component;

use App\Models\PlotList2025;

use App\Models\SubPlotEnv2025;
use App\Helpers\PlotCompletedCheckHelper;
use Illuminate\Support\Facades\Auth;

class DataExport extends Component
{

    public $countyList = [];
    public $teamList = [];
    public $plotInfo = [];
    public $thisCounty = '';
    public $thisTeam ='';
    public $yearList = [];
    public $thisCensusYear = null;
    public bool $embedded = false;

    public function mount(
        array $selectedPlots = [],
        bool $embedded = false,
        string $thisTeam = '',
        string $thisCounty = '',
        string $thisCensusYear = ''
    )
    {
        $user = Auth::user(); // 取代 auth()->user()

        if (!$user) {
            return redirect('/'); // ⬅️ 若未登入，退回首頁
        }

        $this->embedded = $embedded;
        if ($this->embedded) {
            $this->selectedPlots = array_values(array_map('strval', $selectedPlots));
            $this->allPlotIds = $this->selectedPlots;
            $this->thisTeam = $thisTeam;
            $this->thisCounty = $thisCounty;
            $this->thisCensusYear = $thisCensusYear;
            return;
        }

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

        $this->message = '';
        if ($thisteam == 'All') {
            $thisteam = '';
        }

        if ($this->thisCensusYear == 'All'){
            $this->thisCensusYear = '';
        }

        $this->thisTeam = $thisteam;
        if ($thisteam === '') {
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

    public bool  $selectAll = true;
    public function surveryedPlotInfo($thisCounty)
    {
        $this->message = '';
        $this->allPlotInfo = [];
        if ($this->thisTeam == 'All') {
            $this->thisTeam = '';
        }
        if ($thisCounty == 'All') {
            $thisCounty = '';
        }

        if ($this->thisCensusYear == 'All'){
            $this->thisCensusYear = '';
        }

        $this->thisCounty = $thisCounty;

        $plotListQuery = SubPlotEnv2025::select('im_splotdata_2025.plot as plot')
            ->join('plot_list', 'im_splotdata_2025.plot', '=', 'plot_list.plot')
            ->when(!blank($this->thisCensusYear), fn($q) =>
                $q->where('census_year', $this->thisCensusYear)
            )
            ->when(!blank($this->thisTeam), fn($q) =>
                $q->where('plot_list.team', $this->thisTeam)
            )
            ->when(!blank($thisCounty), fn($q) =>
                $q->where('plot_list.county', $thisCounty)
            );

        $plotList = $plotListQuery
            ->distinct()
            ->pluck('plot')
            ->toArray();
// dd($plotList);

        $this->plotList = $plotList;
        // $this->dispatch('thisPlotUpdated');
        $this->thisPlotFile = null;

        $this->loadAllPlotInfo($plotList);
    }
    public $thisPlotFile = null;
    public $selectedPlots = []; // 用於存儲選中的樣區
    public $message='';
    public array $allPlotIds = []; 

    public function loadAllPlotInfo($plotList)
    {
        $this->message = '';
        $plotList = array_values(array_unique($plotList));

        if (empty($plotList)) {
            $this->allPlotInfo = [];
            $this->selectedPlots = [];
            $this->allPlotIds = [];
            $this->message = '尚未有調查資料。';
            return;
        }

        $summary = PlotCompletedCheckHelper::getPlotCompletedInfoForPlots($plotList)
            ->map(fn ($row) => [
                'county' => $row['county'] ?? null,
                'plot' => $row['plot'],
                'completed' => $row['plotCompleted'] == '1',
            ])
            ->sortByDesc(fn ($item) => !is_null($item['plot']))
            ->values()
            ->toArray();

        if (empty($summary)) {
            $this->allPlotInfo = [];
            $this->message = '尚未有調查資料。';
            return;
        }

        $this->allPlotInfo = $summary;
        $this->selectedPlots = $plotList;
        $this->allPlotIds = $plotList;
    }

    public function updatedSelectAll($value)
    {
        $this->selectedPlots = $value ? $this->allPlotIds : [];

    }
    public function updatedSelectedPlots()
    {
        // 保持僅包含目前列表存在的 id（避免舊值殘留）
        $this->selectedPlots = array_values(array_intersect($this->selectedPlots, $this->allPlotIds));

        $this->selectAll = count($this->allPlotIds) > 0 && count($this->selectedPlots) === count($this->allPlotIds);
    }

    public function toggleRow(string $id): void
    {
        $id = (string) $id;
        if (in_array($id, $this->selectedPlots, true)) {
            $this->selectedPlots = array_values(array_diff($this->selectedPlots, [$id]));
        } else {
            $this->selectedPlots[] = $id;
        }

        // 與全選狀態同步（若你已有這段可共用）
        $this->updatedSelectedPlots();
    }


    public $dataType = 'env.xlsx';


    public function render()
    {
        return view('livewire.data-export');
    }
}
