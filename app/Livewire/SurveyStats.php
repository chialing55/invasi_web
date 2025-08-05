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
    public function mount()
    {

        $plotHab2025 = SubPlotEnv2025::select('habitat_code')
            ->pluck('habitat_code')
            ->unique()
            ->values()
            ->toArray();
        $plotHabList = $plotHab2025;
        if (in_array('08', $plotHabList)) $plotHabList[] = '88';
        if (in_array('09', $plotHabList)) $plotHabList[] = '99';

        $this->habTypeOptions = HabHelper::habitatOptions($plotHabList);

        // dd($habTypeMap);
    }
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
    public function getPlantList()
    {
        $this->message = '';
        $plantListAll = SubPlotPlant2025::join('spinfo', 'im_spvptdata_2025.spcode', '=', 'spinfo.spcode')
            ->leftjoin('twredlist2017', 'im_spvptdata_2025.spcode', '=', 'twredlist2017.spcode')
            ->join('im_splotdata_2025', 'im_spvptdata_2025.plot_full_id', '=', 'im_splotdata_2025.plot_full_id')
            ->where('im_splotdata_2025.habitat_code', $this->thisHabType)
            ->where('spinfo.growth_form', '!=', '')
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
        $growthCounts = $all->groupBy('growth_form')->map->count();
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

        $naturalizedGrowthCounts = $naturalized->groupBy('growth_form')->map->count();
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


    public function render()
    {
        return view('livewire.survey-stats');
    }
}
