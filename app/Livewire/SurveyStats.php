<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\PlotList2025;
use App\Models\SubPlotEnv2010;
use App\Models\SubPlotEnv2025;
use App\Models\SubPlotPlant2010;
use App\Models\SubPlotPlant2025;
use App\Models\HabitatInfo;
use App\Helpers\HabHelper;
use Illuminate\Support\Facades\DB;
use App\Support\ScientificNameHelper;
use App\Support\TaiwanChecklistQuery;
use App\Support\HabitatCode;

class SurveyStats extends Component
{
    public $habTypeOptions = [];
    public $selectedPlots = [];
    public $thisHabType = '';
    public $habTypeMap = [];

    public $teamList = [];
    public $countyList = [];
    public $yearList = [];
    public $thisCensusYear;
    public bool $embedded = false;

    public function mount(array $selectedPlots = [], bool $embedded = false)
    {
        $this->selectedPlots = array_values(array_map('strval', $selectedPlots));
        $this->embedded = $embedded;

        if ($this->embedded) {
            // 上層已完成年度／團隊／縣市／樣區篩選；嵌入模式不得再套一次年度條件。
            $this->thisCensusYear = '';
            $this->habTypeOptions('');
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
        $this->habTypeOptions('');
        // dd($habTypeMap);
    }

    public $thisTeam = '';
    public $thisCounty = '';


    public function habTypeOptions($thisCounty){

        if ($thisCounty == 'All'){
            $thisCounty = '';
        }
        if ($this->thisCensusYear == 'All'){
            $this->thisCensusYear = '';
        }

        if ($this->thisTeam == 'All'){
            $this->thisTeam = '';
        }
        if ($this->embedded) {
            $plotHab2025 = SubPlotEnv2025::query()
                ->whereIn('plot', $this->selectedPlots)
                ->pluck('habitat_code')
                ->unique()
                ->values()
                ->toArray();
        } elseif ($thisCounty == '' &&  $this->thisTeam == ''){
            $plotHab2025 = SubPlotEnv2025::select('habitat_code')
                ->pluck('habitat_code')            
                ->unique()
                ->values()
                ->toArray();

        } else {
             $plotHab2025 = SubPlotEnv2025::select('habitat_code')
            ->join('plot_list', 'im_splotdata_2025.plot', '=', 'plot_list.plot')
            ->when(!blank($thisCounty), fn($q) =>
                $q->where('plot_list.county', $thisCounty)
            )
            ->when(!blank($this->thisTeam), fn($q) =>
                $q->where('plot_list.team', $this->thisTeam)
            )
            ->when(!blank($this->thisCensusYear), fn($q) =>
                $q->where('census_year', $this->thisCensusYear)
            )
            ->distinct()->pluck('habitat_code')->toArray();
        }

            $plotHabList = HabitatCode::appendDerivedCodes($plotHab2025);

            $this->habTypeOptions = HabHelper::habitatOptions($plotHabList);        
    }

    //選擇團隊之後
    public function loadCountyList($thisTeam)
    {
        if ($this->thisCounty == 'All'){
            $this->thisCounty = '';
        }
        if ($this->thisCensusYear == 'All'){
            $this->thisCensusYear = '';
        }
        if ($thisTeam == 'All'){
            $thisTeam = '';
        }

        $this->thisTeam = $thisTeam;
        if ($thisTeam === '') {
            $this->countyList = PlotList2025::select('county')
                ->when(!blank($this->thisCensusYear), fn($q) =>
                    $q->where('census_year', $this->thisCensusYear)
                )
                ->distinct()->pluck('county')->toArray();

        } else {
            $this->countyList = PlotList2025::where('team', $thisTeam)
                ->when(!blank($this->thisCensusYear), fn($q) =>
                    $q->where('census_year', $this->thisCensusYear)
                )
                ->select('county')->distinct()->pluck('county')->toArray();

        }

        $this->stats = [];
        $this->thisCounty = '';
        $this->habTypeOptions('');
        $this->dispatch('thisCountyUpdated');
        $this->dispatch('thisHabTypeUpdated');
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
        if ($thisCounty == 'All'){
            $thisCounty = '';
        }
        if ($this->thisCensusYear == 'All'){
            $this->thisCensusYear = '';
        }
        if ($this->thisTeam == 'All'){
            $this->thisTeam = '';
        }

        $this->allPlotInfo = [];

        $this->habTypeOptions($thisCounty);
        $this->stats = [];
        $this->dispatch('thisHabTypeUpdated');
    }
    public $thisPlotFile = null;

    public $habTypeName = '';



    public function habPlantInfo()
    {
        if (empty($this->thisHabType)) {
            $this->habTypeName = '';
            $this->stats = [];
            return;
        }



        $this->habTypeName = $this->habTypeOptions[$this->thisHabType] ?? '';
        $this->getPlantList(); // 你原本撈資料的函式
  
    }
    public string $message = '';
    public $habPlantList = [];
    public function getPlantList()
    {
        if ($this->thisHabType == 'All'){
            $this->thisHabType = '';
        }
        if ($this->thisCounty == 'All'){
            $this->thisCounty = '';
        }
        if ($this->thisCensusYear == 'All'){
            $this->thisCensusYear = '';
        }
        if ($this->thisTeam == 'All'){
            $this->thisTeam = '';
        }
        $hab = trim((string) $this->thisHabType); 
        $thisCounty = $this->thisCounty;

        $this->message = '';
        $base = SubPlotPlant2025::from('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025', 'p.plot_full_id', '=', 'im_splotdata_2025.plot_full_id')
            ->join('plot_list', 'im_splotdata_2025.plot', '=', 'plot_list.plot')
            ->when($this->embedded, fn($q) =>
                $q->whereIn('im_splotdata_2025.plot', $this->selectedPlots)
            )
            ->when(!blank($this->thisTeam), fn($q) => 
                $q->where('plot_list.team',  $this->thisTeam) // 單一值 where
            )
            ->when(!blank($thisCounty), function ($q) use ($thisCounty) {
                $q->where('plot_list.county', $thisCounty); // 單一值 where
            })
            ->when(!blank($hab), function ($q) use ($hab) {
                $q->where('im_splotdata_2025.habitat_code', $hab); // 單一值 where
            })
            ->when(!blank($this->thisCensusYear), fn($q) =>
                $q->where('census_year', $this->thisCensusYear)
        );
        TaiwanChecklistQuery::joinCurrent($base, 'p');
        $base->whereNotNull('s.spcode');
            // ->where('s.growth_form', '!=', '')
        $plantListAll=(clone $base)->select(
                's.spcode',
                's.family',
                's.chfamily',
                DB::raw('s.full_name as latinname'),
                DB::raw('s.canonical_name as simname'),
                's.chname',
                's.growth_form',
                's.genus',
                DB::raw(TaiwanChecklistQuery::nativeExpr('s') . ' AS native'),
                DB::raw(TaiwanChecklistQuery::endemicExpr('s') . ' AS endemic'),
                DB::raw(TaiwanChecklistQuery::naturalizedExpr('s') . ' AS naturalized'),
                DB::raw(TaiwanChecklistQuery::cultivatedExpr('s') . ' AS cultivated'),
                DB::raw('s.IUCN as IUCN'),
            )
            ->distinct()
            ->orderBy('family')
            ->orderBy('s.full_name')
            ->get()
            ->map(function ($r) {
                $r->latinname_html = ScientificNameHelper::italicize($r->latinname ?? '', $r->simname ?? '');
                return $r;
            })
            ->toArray();

// dd($plantListAll);            
            if (empty($plantListAll)) {
                $this->stats = [];
                $this->message = '此生育地類型尚無植物資料。';
                return;
            }
             $this->calculateStats(collect($plantListAll));
             $this->habPlantList = $plantListAll;
// dd($this->habPlantList);
    }
    public array $stats = [];

    public function calculateStats($plantListAll)
    {
        $all = $plantListAll;

        // 1. 科、屬、種
        $this->stats['total_species'] = $all->count();
        $this->stats['total_families'] = $all->pluck('family')->unique()->count();
        $this->stats['total_genera'] = $all->pluck('latinname')->map(fn($n) => explode(' ', $n)[0])->unique()->count();


        // 2. 歸化與原生
        $naturalized = $all->where('naturalized', 1);
        $native = $all->where('native', 1);
        $this->stats['naturalized_species'] = $naturalized->count();
        $this->stats['naturalized_ratio'] = round($this->stats['naturalized_species'] / max($this->stats['total_species'], 1) * 100, 1);

        $this->stats['native_species'] = $native->count();
        $this->stats['endemic_species'] = $native->where('endemic', 1)->count();

        // 3. 全部植物 growth_form 統計，依自訂順序排序

        $growthCounts = $all
            ->filter(fn($item) => !empty($item['growth_form'])) // 🔸 先排除 growth_form 為空字串
            ->groupBy('growth_form')
            ->map->count();
        $growthSorted = collect($growthCounts)->sortDesc(); // 根據數量由多到少排序
        $this->stats['growth_form'] = $growthSorted->map(function ($count, $form) {
            return [
                'growth_form' => $form,
                'growth_form_count' => $count,
            ];
        })->values()->toArray(); // 轉成 0, 1, 2 索引陣列       

        // 4. 歸化植物：科屬種 + growth_form 統計
        $this->stats['naturalized_families'] = $naturalized->pluck('family')->unique()->count();
        $this->stats['naturalized_genera'] = $naturalized->pluck('latinname')->map(fn($n) => explode(' ', $n)[0])->unique()->count();

       $naturalizedGrowthCounts = $naturalized
            ->filter(fn($item) => !empty($item['growth_form'])) // 🔸 先排除 growth_form 為空字串
            ->groupBy('growth_form')
            ->map->count();
        $naturalizedGrowthSorted = collect($naturalizedGrowthCounts)->sortDesc(); // 根據數量由多到少排序
        // $this->stats['naturalized_growth_form'] = $naturalizedGrowthSorted->toArray();
        $this->stats['naturalized_growth_form'] = $naturalizedGrowthSorted->map(function ($count, $form) {
            return [
                'growth_form' => $form,
                'growth_form_count' => $count,
            ];
        })->values()->toArray(); // 轉成 0, 1, 2 索引陣列     
        // dd($this->stats); // 用於除錯，查看統計結果
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

        $this->habPlantList = collect($this->habPlantList)
            ->sort(function ($a, $b) {
                return $this->sortDirection === 'asc'
                    ? $a[$this->sortField] <=> $b[$this->sortField]
                    : $b[$this->sortField] <=> $a[$this->sortField];
            })
            ->values()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.survey-stats');
    }
}
