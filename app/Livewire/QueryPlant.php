<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\SubPlotPlant2010;
use App\Models\SubPlotPlant2025;
use App\Models\PlotList2025;
use App\Models\SpInfo;
use App\Models\HabitatInfo;
use App\Models\SpcodeIndex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\DataSyncService;
use App\Helpers\PlantStatHelper;
use App\Helpers\PlantSearchHelper;

class QueryPlant extends Component
{

    public $search;
    public $plantName = 'test';
    public $plantCode = null;

    public $test;

    public $suggestions = [];
    public $spnameInfo=[];

    public function mount(){

    }


    public function updatedPlantName($value)
    {
        
        // dd($value);
        $value = trim($value);
    // dd($this->chnameIndex);
        if ($value === '') {
            $this->suggestions = [];
            return;
        }

        $this->suggestions = PlantSearchHelper::plantNameSearchHelper($this->plantName);

        // if($this->suggestions){
        //     // $this->plantInfo($this->plantCode);
        //     $match = collect($this->suggestions)->firstWhere('label', $value);
        //     if ($match) {
        //         $this->plantCode = $match['spcode'];
        //         $this->plantInfo($this->plantCode);
        //     }  
        // }
        
    }

    public $comparisonTable = [];
    public $chnameIndex = [];
    public $chnameIndexOriginal = [];


    public bool $showTable = false;
    public function toggle()
    {
        $this->showTable = !$this->showTable;
        if ($this->showTable) {
            $this->dispatchIndex($this->plantCode);
        } 
        
    }    

    public $countyList = [];
    public $habList = [];
    public $thisCounty = '';
    public $thisHabType = '';
    public $filteredComparisonTable = [];
    public function plantInfo($value)
    {
        
// 植物資訊
        $this->countyList=[];
        $this->habList=[];
        $this->thisCounty = '';
        $this->thisHabType = '';
 
        $this->spnameInfo = SpInfo::select()->where('spcode',$value)->first()->toArray();
    // // dd($this->spnameInfo);
        $this->plantName = $this->spnameInfo['chname'];
        $this->suggestions = []; // 清空建議即可
        
        $this->comparisonTable = PlantStatHelper::summarizeByCountyAndHabitat($value);
        // dd($this->comparisonTable);
        $this->plantCode = $value;
        $this->showTable = true;

        // 取得所有縣市
        $this->countyList = collect($this->comparisonTable)
            ->pluck('county')
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $this->habList = collect($this->comparisonTable)
            ->pluck('habitat')
            ->unique()
            ->sort()
            ->values()
            ->toArray();


        $this->filteredComparisonTable = $this->comparisonTable; // 初始化為全部資料


// dd($this->comparisonTable);
        $this->dispatch('plant-name-selected');        
        // dd($this->chnameIndex);
        $this->searchChnameIndex($value);

        $this->showTable = false;

    }    


    public function reloadPlantInfoCounty($thisCounty) {

        $this->filteredComparisonTable = collect($this->comparisonTable)
            ->when($thisCounty !== '', fn($collection) => $collection->where('county', $thisCounty))
            ->values()
            ->toArray();
        $this->dispatch('updateHabType'); 

    }

    public function reloadPlantInfoHab($thisHabType) {

        $this->filteredComparisonTable = collect($this->comparisonTable)
            ->when($thisHabType !== '', fn($collection) => $collection->where('habitat', $thisHabType))
            ->values()
            ->toArray();
        $this->dispatch('updateCounty'); 
    }

    public function searchChnameIndex($value)
    {
// chnameIndex
// dd($value);
        $this->chnameIndex =[];

        $chnameIndex = SpcodeIndex::where('spcode', $value)->get()
            ->map(fn($row) => [
                'spcode' => $row->spcode,
                'chname_index' => $row->chname_index,
                'note' => $row->note ?? '',
                'id' => $row->id,
            ])
            ->toArray();

        $this->chnameIndex = array_merge($chnameIndex,
            array_fill(0, 2, [
                'spcode' => $value,
                'chname_index' => '',
                'note' => '',
                'id' => '',
            ])
        );
        
        $this->chnameIndexOriginal = $chnameIndex;

        // dd($this->chnameIndexOriginal);
    }

    public function dispatchIndex($value)
    {
       
        $this->dispatch('chname_index_table', data: [
            'data' => $this->chnameIndex,
            'spcode' => $value,
        ]);
    }

    public function saveChnameIndex()
    {

        $user = Auth::user();
        $creatorCode = explode('@', $user->email)[0]; // 取出 email 前綴

        //  dd($this->chnameIndex);

        $changed = DataSyncService::syncById(
            modelClass: SpcodeIndex::class,
            originalData: $this->chnameIndexOriginal,
            newData: $this->chnameIndex,
            fields: ['spcode', 'chname_index', 'note'],
            createExtra: ['created_by' => $creatorCode],
            updateExtra: ['updated_by' => $creatorCode],
            requiredFields: ['chname_index', 'spcode'], // ✅ 沒有 chname_index 就不新增
            userCode: $creatorCode
        );

        $this->chnameIndex = SpcodeIndex::where('spcode', $this->plantCode)->get()
            ->map(fn($row) => [
                'spcode' => $row->spcode,
                'chname_index' => $row->chname_index,
                'note' => $row->note ?? '',
                'id' => $row->id,
            ])
            ->toArray();
            
        $this->chnameIndexOriginal = $this->chnameIndex;
        $this->chnameIndex = array_merge($this->chnameIndex,
            array_fill(0, 2, [
                'spcode' => $this->plantCode,
                'chname_index' => '',
                'note' => '',
                'id' => '',
            ])
        );
       

        if ($changed) {
            session()->flash('chIndexMessage', '中文別名已更新！');
        
            $this->dispatch('sync-complete', data: [
                'data' => $this->chnameIndex,
                'spcode' => $this->plantCode,
            ]); 
        }
        
 
    }


    public $sortField = 'county';
    public $sortDirection = 'asc';

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->filteredComparisonTable = collect($this->filteredComparisonTable)
            ->sortBy($this->sortField, SORT_REGULAR, $this->sortDirection === 'desc')
            ->values()
            ->toArray();
    }




    public function render()
    {
        return view('livewire.query-plant');
    }
}
