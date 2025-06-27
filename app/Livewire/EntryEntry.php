<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\PlotList2025;
use App\Models\SubPlotEnv2025;
use App\Models\SubPlotPlant2025;
use App\Models\SubPlotPlant2010;
use App\Models\SpInfo;
use App\Models\HabitatInfo;
use App\Models\PlotHab;

use App\Livewire\Rules\SubPlotEnvFormRules;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

use App\Services\FormAuditService;
use App\Services\FixLogService;
use App\Services\DataSyncService;

use App\Helpers\CoordinateHelper;
use App\Helpers\DateHelper;
use App\Models\SpcodeIndex;
use App\Models\SubPlotEnv2010;

class EntryEntry extends Component
{
    use SubPlotEnvFormRules;

    public $countyList=[];
    public $thisCounty;
    public $plotList=[];
    public $subPlotList=[];
    public $noSubplotData = false;

    public $thisPlot;

    public $plotInfo = [];

    public $thisSubPlot;
    public $allPlotInfo = [];
    public $plantList=[];
    public $subPlotEnvForm = [];
    public $subPlotPlantForm = [];
    public $userOrg;
    public $user;
    public $creatorCode;

public function mount()
{
    $user = Auth::user(); // 取代 auth()->user()

    if (!$user) {
        return redirect('/'); // ⬅️ 若未登入，退回首頁
    }

    // 已登入情況
    $this->userOrg = $user->organization ?? '未知單位';
    $this->creatorCode = explode('@', $user->email)[0];
    $this->user = $user;

    if ($user->role == 'member') {
        $this->countyList = PlotList2025::select('county')
            ->where('team', $this->userOrg)
            ->distinct()
            ->pluck('county')
            ->toArray(); 
    } else {
        $this->countyList = PlotList2025::select('county')
            ->distinct()
            ->pluck('county')
            ->toArray();
    }

    $this->showPlotEntryTable = false;
    $this->showPlantEntryTable = false;
    $this->thisPlot = '';
}


    public function call($method, ...$params)
    {
        session()->forget(['saveMsg', 'form']); // 自動清除 flash session

        return parent::call($method, ...$params); // 呼叫原本的 method
    }


    public function loadPlots($county)
    {

        $this->plotList = PlotList2025::where('county', $county)
            ->select('plot')->distinct()->pluck('plot')->toArray();
        $this->showPlotEntryTable = false;
        $this->showPlantEntryTable = false;
        $this->thisPlot = '';
        $this->dispatch('reset_plant_table');
        $this->dispatch('thisPlotUpdated');
       
    }
    // public $thisPlotHabRatioForm = [];
    // public $habTypeOptions = [];
    // public array $selectedHabitatCodes = []; // 勾選的 habitat_code  
public array $selectedHabitatCodes = []; // 使用者勾選的 habitat_code 陣列
public array $refHabitatCodes = [];      // 2010 參考用代碼
public array $habTypeOptions = [];       // 全部 habitat_code => label
    
    
    public function loadPlotInfo($plot)
    {
        // $this->dispatch('reset_habitat');
        $this->thisPlot = $plot;
        $this->thisSubPlot = ''; // 清空樣區ID
        $this->showPlotEntryTable = false;
        $this->showPlantEntryTable = false;
        $this->dispatch('reset_plant_table');
        // 取得樣區資料
        $this->subPlotList = SubPlotEnv2025::where('plot', $plot)->pluck('plot_full_id')->toArray();
        // $this->plantList=$this->loadPlantList($plot); // 👈 預先跑名錄快取查詢
        $this->selectedHabitatCodes=[];
        $this->loadPlotHab($plot); // 載入生育地類型選項

// dd($this->selectedHabitatCodes);
    }

    public function loadPlotHab($plot)
    {
        $habTypeMap = HabitatInfo::pluck('habitat', 'habitat_code')->toArray();

        $this->habTypeOptions = collect($habTypeMap)
            ->mapWithKeys(fn($habitat, $code) => [$code => $code . ' ' . $habitat])
            ->sortBy(fn($label) => $label)
            ->toArray();

        // 從 SubPlotEnv2010 取得 參考用 habitat_code（只顯示顏色，不會選中）
        $this->refHabitatCodes = SubPlotEnv2010::where('PLOT_ID', $plot)
            ->pluck('HAB_TYPE')
            ->unique()
            ->values()    // ✅ 這行會把索引變成連續的 0,1,2,...
            ->toArray();

        // 若有既存選擇（例如 PlotHabRatio），可設定預選
        $this->selectedHabitatCodes = PlotHab::where('plot', $plot)
            ->pluck('habitat_code')
            ->values() 
            ->toArray();

    }

    public function saveHabitatSelection()
    {
        $plot = $this->thisPlot;

        // 清空舊資料（視需求保留或覆蓋）
        PlotHab::where('plot', $plot)->delete();

        foreach ($this->selectedHabitatCodes as $code) {
            PlotHab::create([
                'plot' => $plot,
                'habitat_code' => $code,
                'created_by' => $this->creatorCode,
            ]);
        }

        session()->flash('habSaveMessage', '生育地類型已儲存。');
    }


    public function updatedThisSubPlot($value)
    {
        $this->dispatch('reset_plant_table');
        if ($value) {
            $this->loadSubPlotEnv($value);
            $this->loadSubPlotPlant($value);
        }
        
    }

    public $showPlotEntryTable = false;
    public $showPlantEntryTable = false;

    public function loadEmptyEnvForm()
    {
        $this->thisSubPlot = ''; // 清空樣區ID
        
        $columns = Schema::connection('invasiflora')->getColumnListing('im_splotdata_2025');

        $columns = array_diff($columns, ['created_by','updated_by','created_at', 'updated_at','team', 'date','plot_full_id', 'upload', 'file_uploadad_by', 'file_uploadad_at']);

        $columns = array_values($columns); // 儲存欄位順序
        
        // 產生空白資料
        foreach ($columns as $col) {
            $this->subPlotEnvForm[$col] = '';
        }
        $this->subPlotEnvForm['plot'] = $this->thisPlot;
        // dd($this->subPlotEnvForm);
        $this->showPlotEntryTable = true;
        $this->showPlantEntryTable = false;
        $this->dispatch('reset_plant_table');

    }

    public function loadSubPlotEnv($subPlot)
    {  
        $this->thisSubPlot = $subPlot;
        $subPlotEnvForm=[];

        $data = SubPlotEnv2025::where('plot_full_id', $subPlot)->first();

        if ($data) {
            $subPlotEnvForm = $data->toArray(); // 有資料：預填入表單
        } 
        $subPlotAreaMap = config('item_list.sub_plot_area');
        // $islandCategoryMap = config('item_list.island_category');
        // $plotEnvMap = config('item_list.plot_env');
        $subPlotEnvForm['subplot_area'] = $subPlotAreaMap[$subPlotEnvForm['subplot_area']];
        //  $subPlotEnvForm['plot_env'] = $plotEnvMap[$subPlotEnvForm['plot_env']];
        //   $subPlotEnvForm['island_category'] = $islandCategoryMap[$subPlotEnvForm['island_category']];
        $this->subPlotEnvForm=$subPlotEnvForm;

        $this->showPlotEntryTable = true; // 顯示表單

    }



    public function loadSubPlotPlant($subPlot){

        // if (empty($this->plantList)) {
        //      $this->plantList=$this->loadPlantList($this->thisPlot);
        // }
        // dd($this->plantList);
        // $data = SubPlotPlant2025::where('plot_full_id', $subPlot)->get();
        $data = SubPlotPlant2025::query()
            ->where('plot_full_id', $subPlot)
            ->leftJoin('spinfo', 'im_spvptdata_2025.spcode', '=', 'spinfo.spcode')
            ->select(
                'im_spvptdata_2025.*',
                'spinfo.chname',
                'spinfo.chfamily',
                DB::raw("CONCAT(spinfo.chname, ' / ', spinfo.chfamily) AS hint")
            )
            ->get();        
        if ($data->isNotEmpty()) {
            
            $this->subPlotPlantForm = $this->loadExistingPlantForm();           

            $this->dispatch('plant_table', data: [
                'data' => $this->subPlotPlantForm,
                'thisSubPlot' =>$this->thisSubPlot,
                // 'plantList' =>$this->plantList
            ]);

        } else {
            $this->loadEmptyPlantForm(); // 無資料 → 載入空白列
        }

        $this->showPlantEntryTable = true;
    }

    public function loadExistingPlantForm()
    {
        $emptyRow = $this->plantFormEmptyRow();
        $columns = $emptyRow ['columns'];
        $empty = $emptyRow ['empty'];

         $data = SubPlotPlant2025::query()
            ->where('plot_full_id', $this->thisSubPlot)
            ->leftJoin('spinfo', 'im_spvptdata_2025.spcode', '=', 'spinfo.spcode')
            ->select(
                'im_spvptdata_2025.*',
                'spinfo.chname',
                'spinfo.chfamily',
                DB::raw("CONCAT(spinfo.chname, ' / ', spinfo.chfamily) AS hint")
            )
            ->get();  
// dd($data);
        $existingPlantForm = $data->map(function ($item) use ($columns) {
            return collect($item)->only($columns)->toArray();
        })->toArray();
// dd($existingPlantForm);
        for ($i = 0; $i < 15; $i++) {
            $row = $empty;
            $row['plot_full_id'] = $this->thisSubPlot;
            $existingPlantForm[] = $row;
        } 

        return $existingPlantForm;

    }

    public $plantFormColumns;

    public function plantFormEmptyRow(){

        $columns = Schema::connection('invasiflora')->getColumnListing('im_spvptdata_2025');

        $columns = array_diff($columns, [
            'created_by', 'updated_by', 'created_at', 'updated_at'
        ]);

        $columns = array_values($columns); // 儲存欄位順序
        $columns[] = 'hint';
        $booleanFields = ['flowering', 'fruiting']; // 你要預設為 0 的欄位
        $emptyRow = [];
        foreach ($columns as $col) {
            $emptyRow[$col] = in_array($col, $booleanFields) ? 0 : '';
        }

        $this->plantFormColumns=$columns;

            return [
                'columns' => $columns,
                'empty'   => $emptyRow,
            ];

    }

    public function loadEmptyPlantForm()
    {

// dd($columns);
        $emptyRow = $this->plantFormEmptyRow();

        $this->subPlotPlantForm = [];

        for ($i = 0; $i < 15; $i++) {
            $row = $emptyRow['empty'];
            $row['plot_full_id'] = $this->thisSubPlot;
            $this->subPlotPlantForm[] = $row;
        }

        // if (empty($this->plantList)) {
        //     $this->plantList=$this->loadPlantList($this->thisPlot);
        // }

        $this->dispatch('plant_table', data: [
            'data' => $this->subPlotPlantForm,
            'thisSubPlot' =>$this->thisSubPlot,
            'plantList' =>$this->plantList
        ]);

        $this->showPlantEntryTable = true;
    }


    public function envInfoSave(FormAuditService $audit)
    {
        session()->flash('form', 'env');
        $this->validate(
            $this->subPlotEnvRules(),
            $this->subPlotEnvMessages()
        );
        
        $subPlotEnvForm=$this->subPlotEnvForm;

        $subPlotEnvForm['team'] = $this->userOrg;
        $subPlotEnvForm['plot_full_id'] = $subPlotEnvForm['plot'].
            $subPlotEnvForm['habitat_code'].
            $subPlotEnvForm['subplot_id'];
        $subPlotEnvForm['date'] = date('Y-m-d', strtotime($subPlotEnvForm['date']));
        $subPlotEnvForm = array_merge(
            $subPlotEnvForm,
            CoordinateHelper::toTm2($subPlotEnvForm['dd97_x'], $subPlotEnvForm['dd97_y']),
            DateHelper::splitYmd($subPlotEnvForm['date'])
        );

        $subPlotAreaMap = config('item_list.sub_plot_area');
        // $islandCategoryMap = config('item_list.island_category');
        // $plotEnvMap = config('item_list.plot_env');
        $subPlotEnvForm['subplot_area'] = array_search($subPlotEnvForm['subplot_area'], $subPlotAreaMap, true);
        // $subPlotEnvForm['plot_env'] = array_search($subPlotEnvForm['plot_env'], $plotEnvMap, true);
        // $subPlotEnvForm['island_category'] = array_search($subPlotEnvForm['island_category'], $islandCategoryMap, true);
     

        $this->subPlotEnvForm=$subPlotEnvForm;
        if ( $this->thisSubPlot=='') {
            $where = ['plot_full_id' => $subPlotEnvForm['plot_full_id']];
        } else {
            $where = ['plot_full_id' => $this->thisSubPlot];
        }

        $originalData = SubPlotEnv2025::where($where)->get()->toArray();
        $newdata[]=$subPlotEnvForm;
// dd($where);
        if (!empty($originalData) && empty($subPlotEnvForm['id'])) {
            $this->addError('小樣方流水號', '小樣方流水號重複');
            return;
        } 

        // ✅ 根據 habitat_code 判斷是否要額外新增對應筆
        $autoCopyMap = [
            '08' => '88',
            '09' => '99',
        ];

        if (array_key_exists($subPlotEnvForm['habitat_code'], $autoCopyMap)) {
            $newdata[0]['subplot_area'] = 3;   // 強制設定為 5x5

            $copyCode = $autoCopyMap[$subPlotEnvForm['habitat_code']];
            $copiedPlotFullId = $subPlotEnvForm['plot'] . $copyCode . $subPlotEnvForm['subplot_id'];

            $alreadyExists = SubPlotEnv2025::where('plot_full_id', $copiedPlotFullId)->exists();

            if (!$alreadyExists) {
                $copied = $subPlotEnvForm;
                $copied['habitat_code'] = $copyCode;
                $copied['subplot_area'] = 2; // 強制設定為 2x5
                $copied['plot_full_id'] = $copiedPlotFullId;
                $newdata[] = $copied;

                session()->flash('saveMsg2', ', 同時新增 『' . $copiedPlotFullId . '』環境資料');
            }
        }

        $changed = DataSyncService::syncById(
            modelClass: SubPlotEnv2025::class,
            originalData: $originalData,
            newData: $newdata,
            fields: array_keys($subPlotEnvForm),
            createExtra: ['created_by' => $this->creatorCode],
            updateExtra: ['updated_by' => $this->creatorCode],
            requiredFields: ['plot_full_id'],
            userCode: $this->creatorCode
        );

//如果有改plot_full_id'
        if ($subPlotEnvForm['plot_full_id']!=$this->thisSubPlot){
            SubPlotPlant2025::where('plot_full_id', $this->thisSubPlot)->update(['plot_full_id' => $subPlotEnvForm['plot_full_id']]);
        }

        // 可選：狀態提示
        session()->flash('saveMsg', $changed ? '已更新『' . $subPlotEnvForm['plot_full_id'] . '』環境資料' : '無任何變更');

  
        $this->loadPlotInfo($this->thisPlot);
        $this->thisSubPlot=$subPlotEnvForm['plot_full_id'];
        $this->updatedThisSubPlot($subPlotEnvForm['plot_full_id']);
        // $this->loadSubPlotEnv($subPlotEnvForm['plot_full_id']);
    }


    public function plantDataSave()
    {
        $newData = collect($this->subPlotPlantForm)
            ->filter(fn ($row) => !empty($row['chname_index'])) // 只處理有中文名的
            ->map(function ($row) {
                // 比對中文名 → 取得 spcode

                $row['unidentified'] = isset($row['spcode']) && $row['spcode'] !== '' ? 0 : 1;

                // 覆蓋度錯誤標記
                $cov = $row['coverage'] ?? null;
                if (!is_numeric($cov) || $cov < 0 || $cov > 100 || $cov == 0 ) {
                    $row['data_error'] = 1;
                    $row['coverage'] = 0;
                } else {
                    $row['data_error'] = 0;
                }

                foreach (['flowering', 'fruiting'] as $boolField) {
                    $row[$boolField] = isset($row[$boolField]) && $row[$boolField] !== '' ? intval($row[$boolField]) : 0;
                }

                $row['plot_full_id']=$this->thisSubPlot;
                unset($row['hint']); // ❌ 移除 hint 欄位

                return $row;
            })->values()->toArray();

//  dd($newData);

        // 撈出該樣區原本資料
        $originalData = SubPlotPlant2025::where('plot_full_id', $this->thisSubPlot)
            ->get()->toArray();

        $changed = DataSyncService::syncById(
            modelClass: SubPlotPlant2025::class,
            originalData: $originalData,
            newData: $newData,
            fields: $this->plantFormColumns,
            createExtra: ['created_by' => $this->creatorCode],
            updateExtra: ['updated_by' => $this->creatorCode],
            requiredFields: ['chname_index'],
            userCode: $this->creatorCode
        );

        $this->markDuplicateCovError($this->thisSubPlot);
        $this->subPlotPlantForm = $this->loadExistingPlantForm();
        
        // $this->plantList=$this->loadPlantList($this->thisPlot);

        $this->dispatch('plant_table', data: [
            'data' => $this->subPlotPlantForm,
            'thisSubPlot' =>$this->thisSubPlot,
            'plantList' =>$this->plantList
        ]);

     
        session()->flash('plantSaveMessage', $changed ? '植物資料已更新' : '無任何變更'); 

    }


    public function loadPlantList($plot)

    {
        // cache()->forget('plant_list_all'); // ⬅️ 清掉之前那個只含一筆的快取
        // $this->plantList = cache()->remember('plant_list_all', 86400, function () {
        //     $plantList1 = Spinfo::select('spcode', 'chname')->get(); // Collection
        //     $plantList2 = SpcodeIndex::select('spcode', 'chname_index as chname')->get(); // Collection

        //     return $plantList1
        //         ->merge($plantList2)
        //         ->sortBy('chname')           // ⬅️ 用 Collection 內建排序
        //         ->values()                   // ⬅️ 重新索引（變成 0,1,2...）
        //         ->toArray();                 // ⬅️ 若你前端要用 array，可加上
        // });
// $this->plantList = cache()->remember('plant_list_all', 86400, function () {
        
            $usedSpcodes1 = SubPlotPlant2010::distinct()->where('PLOT_ID', $plot)->pluck('spcode')->toArray();  // → Collection of used spcodes

            $prefix = substr($plot, 0, 6);

            $usedSpcodes2 = SubPlotPlant2025::distinct()
                ->whereRaw('LEFT(plot_full_id, 6) = ?', [$prefix])
                ->pluck('spcode')
                ->toArray();

            $usedSpcodes = array_unique(array_merge($usedSpcodes1, $usedSpcodes2));
            // 撈出對應中文名

            // dd($usedSpcodes);
            $list1 = Spinfo::whereIn('spcode', $usedSpcodes)
                        ->select('chname')
                        ->get();
//加入全部這次給予的spcodeIndex，因為可能會有用
            $fullChnameIndex = SpcodeIndex::select('chname_index as chname')
                ->get();

            return $list1
                ->concat($fullChnameIndex)
                ->sortBy('chname')
                ->values()
                ->toArray();
  
        

        // dd($this->plantList);

    }

    public function markDuplicateCovError(string $plotFullId)
    {
        // 取得該 plot_full_id 的所有資料
        $records = SubPlotPlant2025::where('plot_full_id', $plotFullId)->get();

        $duplicates = [
            'chname_index' => [],
            'spcode' => [],
        ];

        $seenChname = [];
        $seenSpcode = [];

        // 找出重複的 chname_index 和 spcode
        foreach ($records as $record) {
            // chname_index 重複檢查
            if (isset($seenChname[$record->chname_index])) {
                $duplicates['chname_index'][$record->chname_index] = true;
            } else {
                $seenChname[$record->chname_index] = true;
            }

            // spcode 重複檢查
            if (!empty($record->spcode) && isset($seenSpcode[$record->spcode])) {
                $duplicates['spcode'][$record->spcode] = true;
            } elseif (!empty($record->spcode)) {
                $seenSpcode[$record->spcode] = true;
            }
        }

        // 更新重複資料的 data_error = 2
        foreach ($records as $record) {
            if (
                isset($duplicates['chname_index'][$record->chname_index]) ||
                isset($duplicates['spcode'][$record->spcode])
            ) {
                if ($record->data_error != 2) {
                    $record->data_error = 2;
                    $record->save();
                }
            }
        }
    }


    public function render()
    {
        return view('livewire.entry-entry');
    }
}
