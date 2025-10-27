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
use App\Helpers\HabHelper;
use Illuminate\Support\Facades\DB;

class SurveyStats extends Component
{
    public $habTypeOptions;
    public $selectedPlots = [];
    public $thisHabType = '';
    public $habTypeMap = [];

    public $teamList = [];
    public $countyList = [];
    public $yearList = [];
    public $thisCensusYear;

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
        $this->habTypeOptions('All');
        // dd($habTypeMap);
    }

    public $thisTeam;
    public $thisCounty;


    public function habTypeOptions($thisCounty){
        if ($thisCounty == '') {
            $thisCounty = 'All';
        }
        if ($thisCounty == 'All' ){
            $plotHab2025 = SubPlotEnv2025::select('habitat_code')
                ->pluck('habitat_code')
                ->unique()
                ->values()
                ->toArray();

        } else {
             $plotHab2025 = SubPlotEnv2025::select('habitat_code')
            ->join('plot_list', 'im_splotdata_2025.plot', '=', 'plot_list.plot')
            ->where('plot_list.county', $thisCounty)
            ->distinct()->pluck('habitat_code')->toArray();
        }

            $plotHabList = $plotHab2025;
            if (in_array('08', $plotHabList)) $plotHabList[] = '88';
            if (in_array('09', $plotHabList)) $plotHabList[] = '99';

            $this->habTypeOptions = HabHelper::habitatOptions($plotHabList);        
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

        $this->habTypeOptions($thisCounty);

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
        $hab = trim((string) $this->thisHabType); 

        $this->message = '';
        $plantListAll = SubPlotPlant2025::join('spinfo', 'im_spvptdata_2025.spcode', '=', 'spinfo.spcode')
            ->leftjoin('twredlist2017', 'im_spvptdata_2025.spcode', '=', 'twredlist2017.spcode')
            ->join('im_splotdata_2025', 'im_spvptdata_2025.plot_full_id', '=', 'im_splotdata_2025.plot_full_id')
            ->when($hab !== '' && strcasecmp($hab, 'All') !== 0, function ($q) use ($hab) {
                $q->where('im_splotdata_2025.habitat_code', $hab); // 單一值 where
            })
            // ->where('spinfo.growth_form', '!=', '')
            ->select(
                // 'spinfo.spcode',
                'spinfo.family',
                'spinfo.chfamily',
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
