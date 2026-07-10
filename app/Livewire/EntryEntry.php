<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\PlotList2025;
use App\Models\SubPlotEnv2025;
use App\Models\SubPlotPlant2025;
use App\Models\SubPlotPlant2010;
use App\Models\HabitatInfo;
use App\Models\PlotHab;
use App\Models\FixLog;

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
use App\Support\TaiwanChecklistQuery;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

// use Illuminate\Http\Request;
use Livewire\WithFileUploads;
class EntryEntry extends Component
{
    use SubPlotEnvFormRules;
    use WithFileUploads;

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

        if (session()->has('query.county')) {
            $county  = session()->pull('query.county');
            $plot    = session()->pull('query.plot');
            $subPlot = session()->pull('query.subPlot');

            $this->fromOverview($county, $plot, $subPlot);
        }

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
        $this->subPlotList = SubPlotEnv2025::where('plot', $plot)->orderBy('plot_full_id')->pluck('plot_full_id')->toArray();
        // $this->plantList=$this->loadPlantList($plot); // 👈 預先跑名錄快取查詢
        $this->selectedHabitatCodes=[];
        $this->loadPlotHab($plot); // 載入生育地類型選項
        $this->loadFileInfo();

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

        // ➤ 1. 原始選擇
        $selected = $this->selectedHabitatCodes;

        // ➤ 2. 自動加入對應的 88/99
        if (in_array('08', $selected)) {
            $selected[] = '88';
        }
        if (in_array('09', $selected)) {
            $selected[] = '99';
        }

        // ➤ 3. 沒有 08/09 則移除 88/99
        if (!in_array('08', $selected)) {
            $selected = array_diff($selected, ['88']);
        }
        if (!in_array('09', $selected)) {
            $selected = array_diff($selected, ['99']);
        }

        // ➤ 4. 整理為乾淨陣列
        $selected = array_values(array_unique($selected));
        $this->selectedHabitatCodes = $selected;

        // ➤ 5. 清空舊資料
        PlotHab::where('plot', $plot)->delete();

        // ➤ 6. 儲存新資料
        foreach ($selected as $code) {
            PlotHab::firstOrCreate(
                ['plot' => $plot, 'habitat_code' => $code],
                ['created_by' => $this->creatorCode]
            );
        }

        // ➤ 7. 成功提示
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
        $this->subPlotEnvForm['census_year'] = date('Y');
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
        $census_year = PlotList2025::where('plot', $this->thisPlot)->value('census_year');

        if ($data) {
            $subPlotEnvForm = $data->toArray(); // 有資料：預填入表單
            $subPlotEnvForm['census_year'] = $census_year;
        } 
        // dd($data);
        // $subPlotAreaMap = config('item_list.sub_plot_area');
        // $islandCategoryMap = config('item_list.island_category');
        // $plotEnvMap = config('item_list.plot_env');
        
        // $subPlotEnvForm['subplot_area'] = $subPlotAreaMap[$subPlotEnvForm['subplot_area']];
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
            ->where('plot_full_id', $subPlot);
        TaiwanChecklistQuery::joinCurrent($data, 'im_spvptdata_2025');
        $data = $data->select(
                'im_spvptdata_2025.*',
                's.chname',
                's.chfamily',
                DB::raw("CONCAT(s.chname, ' / ', s.chfamily) AS hint")
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
            ->where('plot_full_id', $this->thisSubPlot);
        TaiwanChecklistQuery::joinCurrent($data, 'im_spvptdata_2025');
        $data = $data->select(
                'im_spvptdata_2025.*',
                's.chname',
                's.chfamily',
                DB::raw("CONCAT(s.chname, ' / ', s.chfamily) AS hint")
            )
            ->orderBy('id')
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
        $this->loadPhotoInfo();

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
        $this->loadPhotoInfo();
    }
    public $thisPlotFile;

    public function loadFileInfo(){

        $relativePath = "invasi_files/plotData/{$this->thisCounty}/{$this->thisPlot}.pdf";
        $fullPath = public_path($relativePath);

        if (file_exists($fullPath)) {
            $this->thisPlotFile = route('file.view', ['path' => $relativePath]) . '?v=' . filemtime($fullPath);
        } else {
            $this->thisPlotFile = null;
        }        
// dd($this->thisPlotFile);
        // $this->thisPhoto = asset($relativePath);
        // dd($this->thisPhoto);
    }

    private array $photoExts = ['jpg', 'jpeg', 'png', 'webp'];

    private function photoRelativeDir(string $hab): string
    {
        return "invasi_files/subPlotPhoto/{$this->thisCounty}/{$this->thisPlot}/{$hab}";
    }

    private function findPhotoPath(string $subPlot): ?string
    {
        $hab = substr($subPlot, 6, 2);
        $baseDir = $this->photoRelativeDir($hab);

        foreach ($this->photoExts as $ext) {
            $path = "{$baseDir}/{$subPlot}.{$ext}";
            if (Storage::disk('public')->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public $thisPhoto;

    public function loadPhotoInfo(){
        $path = $this->findPhotoPath((string) $this->thisSubPlot);
        $this->thisPhoto = $path ? asset($path) : null;
    }
    public $hasUnderData = '';
    public function envInfoSave(FormAuditService $audit)
    {
        $this->hasUnderData = '';
        session()->flash('form', 'env');
        $this->validate(
            $this->subPlotEnvRules(),
            $this->subPlotEnvMessages()
        );
        $msg = '';
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

        $newdata[]=$subPlotEnvForm;
  
        $this->subPlotEnvForm=$subPlotEnvForm;
        if ( $this->thisSubPlot=='') {  //新增小樣方
            $newdata = $this->addUnderstoryPlot($subPlotEnvForm);
            $plotFullIds = (array) $subPlotEnvForm['plot_full_id'];
        } else {  // 修改小樣方資料
            //如果是更改樣區編號  1.更改生育地  2. 更改小樣方
            //先處理更改編號
            $plot = $subPlotEnvForm['plot'];
            $o_habitat_code = substr($this->thisSubPlot, 6, 2);
            $o_subplot_id = substr($this->thisSubPlot, 8, 2);
            $plotFullIds = (array) $subPlotEnvForm['plot_full_id'];

            if ($subPlotEnvForm['plot_full_id'] != $this->thisSubPlot) {
                //1. 檢查是否重號
                $originalData = SubPlotEnv2025::where(['plot_full_id' => $subPlotEnvForm['plot_full_id']])->get()->toArray();
                if (!empty($originalData)) {
                    $this->addError('小樣方流水號', '小樣方流水號重複');
                    return;
                }

                //2. 更改小樣方編號
                SubPlotEnv2025::where('plot_full_id', $this->thisSubPlot)
                    ->update([
                        'plot_full_id' => $subPlotEnvForm['plot_full_id'],
                        'habitat_code' => $subPlotEnvForm['habitat_code'],
                        'subplot_id' => $subPlotEnvForm['subplot_id'],
                        'updated_by' => $this->creatorCode,
                    ]);
                $updatedCount = SubPlotPlant2025::where('plot_full_id', $this->thisSubPlot)->update(['plot_full_id' => $subPlotEnvForm['plot_full_id'], 'updated_by' => $this->creatorCode,]);

                $diff['plot_full_id'] = [
                    'old' => $this->thisSubPlot,
                    'new' => $subPlotEnvForm['plot_full_id'],
                ];
                $diff['habitat_code'] = [
                    'old' => $o_habitat_code,
                    'new' => $subPlotEnvForm['habitat_code'],
                ];
                $diff['subplot_id'] = [
                    'old' => $o_subplot_id,
                    'new' => $subPlotEnvForm['subplot_id'],
                ];
                
                FixLog::create([
                    'table_name' => 'im_splotdata_2025',
                    'record_id' => $subPlotEnvForm['id'],
                    'changes' => $diff,
                    'modified_by' => $this->creatorCode,
                    'modified_at' => now(),
                ]);
                if ($updatedCount >1){
                    FixLog::create([
                        'table_name' => 'im_spvptdata_2025',
                        'record_id' => 0,
                        'changes' => $diff['plot_full_id'],
                        'modified_by' => $this->creatorCode,
                        'modified_at' => now(),
                    ]);
                }
                //3. 如果新生育地類型是08或09
                //3.1  如果已有原本相對應的88和89，一起更改編號
                $msg = '已更新『' . $this->thisSubPlot . '』樣區編號為『' . $subPlotEnvForm['plot_full_id'] . '』。';

                if (in_array($subPlotEnvForm['habitat_code'], ['08', '09'])) {
                        // 08 對應 88，09 對應 99
                    $extraHabitat_o = match ($o_habitat_code) {
                        '08' => '88',
                        '09' => '99',
                        default => '00',
                    };
                    $extraHabitat_n = $subPlotEnvForm['habitat_code'] === '08' ? '88' : '99';

                    $related_full_id_o = $plot . $extraHabitat_o . $o_subplot_id;
                    $related_full_id_n = $plot . $extraHabitat_n . $subPlotEnvForm['subplot_id'];

                    $exists = SubPlotEnv2025::where('plot_full_id', $related_full_id_o)->first();

                    if ($exists) {
                        // 若已存在，更新
                        SubPlotEnv2025::where('plot_full_id', $related_full_id_o)
                            ->update([
                                'habitat_code' => $extraHabitat_n,
                                'subplot_id' => $subPlotEnvForm['subplot_id'],
                                'plot_full_id' => $related_full_id_n,
                                'updated_by' => $this->creatorCode,
                            ]);

                        $updatedCount2 = SubPlotPlant2025::where('plot_full_id', $related_full_id_o)
                            ->update(['plot_full_id' => $related_full_id_n, 'updated_by' => $this->creatorCode]);

                        session()->flash('saveMsg2', '同時更新 『' . $related_full_id_o . '』樣區編號為 『' . $related_full_id_n . '』。');

                        $diff['plot_full_id'] = [
                            'old' => $related_full_id_o,
                            'new' => $related_full_id_n,
                        ];
                        $diff['habitat_code'] = [
                            'old' => $extraHabitat_o,
                            'new' => $extraHabitat_n,
                        ];
                        $diff['subplot_id'] = [
                            'old' => $o_subplot_id,
                            'new' => $subPlotEnvForm['subplot_id'],
                        ];

                        FixLog::create([
                            'table_name' => 'im_splotdata_2025',
                            'record_id' => $exists->id,
                            'changes' => $diff,
                            'modified_by' => $this->creatorCode,
                            'modified_at' => now(),
                        ]);
                        if ($updatedCount2>1){
                            FixLog::create([
                                'table_name' => 'im_spvptdata_2025',
                                'record_id' => 0,
                                'changes' => $diff['plot_full_id'],
                                'modified_by' => $this->creatorCode,
                                'modified_at' => now(),
                            ]);
                        }

                    } 
                    // $newdata = $this->addUnderstoryPlot($subPlotEnvForm);
                    $plotFullIds[] = $related_full_id_n;
                    
                }
                if (in_array($o_habitat_code, ['08', '09']) && !in_array($subPlotEnvForm['habitat_code'], ['08', '09']) && $o_habitat_code != $subPlotEnvForm['habitat_code']){
                    $extraHabitat = $o_habitat_code === '08' ? '88' : '99';
                    $related_full_id_o = $plot . $extraHabitat . $o_subplot_id;
                    session()->flash('saveMsg2', '保留原有 『' . $related_full_id_o . '』環境、植物資料，如需刪除請洽管理員。');
                    
                }
                
            } else {
                
            }
            $newdata = $this->addUnderstoryPlot($subPlotEnvForm);            
        }
    
        $originalData = SubPlotEnv2025::whereIn('plot_full_id', $plotFullIds)->get()->toArray();

//   dd($originalData);    

// dd($where);
        if (!empty($originalData) && empty($subPlotEnvForm['id'])) {
            $this->addError('小樣方流水號', '小樣方流水號重複');
            return;
        } 

// dd($subPlotEnvForm);
// dd($newdata);
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

        if ($changed) {
            $msg .= '已更新/新增『' . $subPlotEnvForm['plot_full_id'] . '』環境資料。';
            if ($this->hasUnderData != ''){
                $msg .='同時更新/新增『' . $this->hasUnderData . '』環境資料。';
            }
        } else {
            $msg .= '環境資料無任何變更。';
        }


        //更新調查年度

        $upyear = $subPlotEnvForm['census_year'];
        $record = PlotList2025::where('plot', $this->thisPlot)->first();

        if ($record) {
            $originalCensusYear = $record->census_year;
            $recordId = $record->id;
        } else {
            $originalCensusYear = null; // 或預設值
            $recordId = null;
        }

        if ($upyear < '2025') {
            $msg .= " 調查年度不得小於 2025 年。";
            return;
        } else if ($upyear != $originalCensusYear) {
            PlotList2025::where('plot', $this->thisPlot)
                ->update(['census_year' => $upyear, 'updated_by' => $this->creatorCode]); 
            if ($originalCensusYear != '0'){
            $msg .= " 已將樣區調查年度更新為 {$upyear} 。";

            $diff['census_year'] = [
                'old' => $originalCensusYear,
                'new' => $upyear,
            ];
            FixLog::create([
                'table_name' => 'plot_list',
                'record_id' => $recordId,
                'changes' => $diff,
                'modified_by' => $this->creatorCode,
                'modified_at' => now(),
            ]);

            }
        } 


        session()->flash('saveMsg', $msg);
 
        $this->loadPlotInfo($this->thisPlot);
        $this->thisSubPlot=$subPlotEnvForm['plot_full_id'];
        $this->updatedThisSubPlot($subPlotEnvForm['plot_full_id']);
        // $this->loadSubPlotEnv($subPlotEnvForm['plot_full_id']);
    }


    public function addUnderstoryPlot($subPlotEnvForm){
                // ✅ 根據 habitat_code 判斷是否要額外新增對應筆
        $autoCopyMap = [
            '08' => '88',
            '09' => '99',
        ];

        $newdata = [];

        // 加入原始小樣方資料
        
        $newdata[] = $subPlotEnvForm;

        if (array_key_exists($subPlotEnvForm['habitat_code'], $autoCopyMap)) {
            $subPlotEnvForm['subplot_area'] = 3; // 強制設定為 5x5
            $copyCode = $autoCopyMap[$subPlotEnvForm['habitat_code']];
            $copiedPlotFullId = $subPlotEnvForm['plot'] . $copyCode . $subPlotEnvForm['subplot_id'];

            $existingRecord = SubPlotEnv2025::where('plot_full_id', $copiedPlotFullId)->first();

            $copied = $subPlotEnvForm;
            $copied['habitat_code'] = $copyCode;
            $copied['subplot_area'] = 2; // 強制設定為 2x5
            $copied['plot_full_id'] = $copiedPlotFullId;
            $copied['id'] = $existingRecord ? $existingRecord->id : '';

            $newdata[] = $copied;

            $this->hasUnderData = $copiedPlotFullId;

            // session()->flash(
            //     'saveMsg2',
            //     '同時' . ($existingRecord ? '更新' : '新增') . ' 『' . $copiedPlotFullId . '』環境資料'
            // );
        }
        return $newdata;
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

//已棄用
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

    public $photo;

    public function clickUploadPhoto()
    {
        $this->resetErrorBag();

        // 1) 驗證（20MB、限定常見圖片格式）
        $this->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:20480',
        ], [
            'photo.required' => '請先選擇檔案',
            'photo.image'    => '檔案必須是圖片格式',
            'photo.mimes'    => '只接受 JPG、PNG 或 WEBP',
            'photo.max'      => '檔案不可超過 20 MB',
        ]);

        $hab      = substr($this->thisSubPlot, 6, 2);
        $basename = $this->thisSubPlot; // 不含副檔名
        $ext      = strtolower($this->photo->getClientOriginalExtension() ?: $this->photo->extension() ?: 'jpg');

        $relativeDir = $this->photoRelativeDir($hab);
        $filename    = "{$basename}.{$ext}";
        $targetPath  = "{$relativeDir}/{$filename}";

        $disk        = Storage::disk('public'); // config/filesystems.php 目前指向 public_path()
        $allExts     = $this->photoExts;

        try {
            DB::beginTransaction();

            // 2) 確保目錄存在（同一個 disk）
            $disk->makeDirectory($relativeDir);

            // 3) 先寫暫存檔，成功後才刪舊檔，避免失敗時舊照片消失。
            $tmpName = $filename . '.tmp_' . Str::random(8);
            $tmpPath = "{$relativeDir}/{$tmpName}";
            $disk->putFileAs($relativeDir, $this->photo, $tmpName);

            // 4) 清掉「同名不同副檔名」舊檔，避免殘留多份。
            foreach ($allExts as $oldExt) {
                $old = "{$relativeDir}/{$basename}.{$oldExt}";
                if ($disk->exists($old)) {
                    $disk->delete($old);
                }
            }

            $disk->move($tmpPath, $targetPath);

            // 5) 更新 DB（成功寫檔後再更新）
            $updated = SubPlotEnv2025::where('plot_full_id', $basename)->update([
                'file_uploaded_at' => now(),
                'file_uploaded_by' => $this->creatorCode,
            ]);

            if ($updated === 0) {
                throw new \RuntimeException("找不到小樣方資料：{$basename}");
            }

            // 6) 若 08/09，鏡像到 88/99（檔名也要改成對應小樣區 ID）
            if ($hab === '08' || $hab === '09') {
                $mirrorHab      = $hab === '08' ? '88' : '99';
                $mirrorSubPlot  = substr($basename, 0, 6) . $mirrorHab . substr($basename, 8);
                $mirrorDir      = $this->photoRelativeDir($mirrorHab);
                $mirrorFilename = "{$mirrorSubPlot}.{$ext}";
                $mirrorPath     = "{$mirrorDir}/{$mirrorFilename}";

                // 建目錄
                $disk->makeDirectory($mirrorDir);

                // 清掉鏡像目標的舊副檔名
                foreach ($allExts as $oldExt) {
                    $old = "{$mirrorDir}/{$mirrorSubPlot}.{$oldExt}";
                    if ($disk->exists($old)) {
                        $disk->delete($old);
                    }
                }

                // 複製並改檔名（同 disk copy）
                $disk->copy($targetPath, $mirrorPath);

                // 更新鏡像小樣區 DB；若 88/99 資料不存在，不阻斷主照片上傳。
                SubPlotEnv2025::where('plot_full_id', $mirrorSubPlot)->update([
                    'file_uploaded_at' => now(),
                    'file_uploaded_by' => $this->creatorCode,
                ]);
            }

            DB::commit();

            // 7) 重新載入預覽（內部已做多副檔名偵測的話可直接用）
            $this->loadPhotoInfo();
            session()->flash('photoUploadSuccess', '上傳成功！');
            $this->photo = null;

        } catch (Throwable $e) {
            DB::rollBack();

            FixLog::create([
                'table_name'  => 'upload_photo_error',
                'record_id'   => 0,
                'changes'     => $this->thisCounty . "_" . $this->thisPlot . "_" . $this->thisSubPlot . ' Error: ' . $e->getMessage(),
                'modified_by' => $this->creatorCode,
                'modified_at' => now(),
            ]);

            $this->addError('photo', '上傳失敗，請稍後再試或聯絡管理者。');
        }
    }






    public $plotFile;

    protected $rules = [
        'plotFile' => 'required|file|mimes:pdf|max:20480',
    ];

    public function clickUploadFile()
    {
        $this->resetErrorBag('plotFile');

        $rules = [
            'plotFile' => 'required|file|mimes:pdf|max:20480', // 20MB (= 20*1024 KB)
        ];
        $messages = [
            'plotFile.required' => '請先選擇檔案',
            'plotFile.file'     => '檔案格式不正確',
            'plotFile.mimes'    => '只接受 PDF 檔',
            'plotFile.max'      => '檔案不可超過 20 MB',
        ];
        // dd('test');
        // 1) 先做表單驗證（這一步的錯誤會自動進到 $errors）
        $this->validate($rules, $messages);

        // 2) 準備路徑與檔名
        $filename     = $this->thisPlot . '.pdf';
        $relativeDir  = "invasi_files/plotData/{$this->thisCounty}";
        $targetPath   = "{$relativeDir}/{$filename}";
        $disk         = Storage::disk('public'); // 對應 storage/app/public

        try {
            DB::beginTransaction();

            // 3) 確保目錄存在（用 Storage，不要混 public_path）
            $disk->makeDirectory($relativeDir);

            // 4) 原子寫入：先存暫存檔，再 rename 成正式檔（避免半成品）
            $tmpName = $this->thisPlot . '.tmp_' . Str::random(8) . '.pdf';
            $disk->putFileAs($relativeDir, $this->plotFile, $tmpName);

            // 若已有舊檔可先刪除（或保留歷史就改成 rename 加時間戳）
            if ($disk->exists($targetPath)) {
                $disk->delete($targetPath);
            }
            $disk->move("{$relativeDir}/{$tmpName}", $targetPath);

            // 5) 寫入資料庫（成功寫檔後才更新）
            PlotList2025::where('plot', $this->thisPlot)->update([
                'file_uploaded_at' => now(),
                'file_uploaded_by' => $this->creatorCode,
                // 若資料表有路徑欄，建議一起更新
                // 'file_path' => $targetPath,
            ]);

            DB::commit();

            // 6) UI 狀態
            $this->loadFileInfo();
            session()->flash('fileUploadSuccess', '上傳成功！');
            $this->plotFile = null;

        } catch (Throwable $e) {
            DB::rollBack();

            // 後台 log（方便追查）
            FixLog::create([
                'table_name' => 'upload_photo_error',
                'record_id' => $this->thisCounty."_".$this->thisPlot."_".$this->thisSubPlot,
                'changes' => 'Error: ' . $e->getMessage(),
                'modified_by' => $this->creatorCode,
                'modified_at' => now(),
            ]);

            // 友善前端錯誤（依常見訊息翻譯）
            $msg = $e->getMessage();
            $friendly = '上傳失敗，請稍後再試或聯絡管理者。';

            if (str_contains($msg, 'No space left on device')) {
                $friendly = '伺服器磁碟空間不足，請通知管理者釋放空間。';
            } elseif (str_contains($msg, 'Permission denied')) {
                $friendly = '伺服器寫入權限不足，請通知管理者檢查目錄權限。';
            } elseif (str_contains($msg, 'exceeds the upload_max_filesize')
                || str_contains($msg, 'POST Content-Length exceeds post_max_size')) {
                $friendly = '檔案超過伺服器限制，請確認上限已設定為 20MB 並已重啟服務。';
            }

            // 綁在欄位錯誤或一般錯誤都可，這裡綁欄位比較直覺
            $this->addError('plotFile', $friendly);
        }

    }    

    public function fromOverview($county, $plot, $subPlot)
    {
        $this->thisCounty = $county;
        $this->loadPlots($county);
        $this->loadPlotInfo($plot);
        $this->thisSubPlot = $subPlot;
        $this->loadSubPlotEnv($subPlot);
        $this->loadSubPlotPlant($subPlot);
        $this->showPlotEntryTable = true;
        $this->showPlantEntryTable = true;  

    }


    public function render()
    {
        return view('livewire.entry-entry');
    }
}
