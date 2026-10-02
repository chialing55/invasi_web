<?php

namespace App\Livewire;

use App\Helpers\CoordinateHelper;
use App\Helpers\DateHelper;
use App\Livewire\Rules\SubPlotEnvFormRules;
use App\Models\FixLog;
use App\Models\HabitatInfo;
use App\Models\PlotHab;
use App\Models\PlotHabitatCompletionException;
use App\Models\PlotList2025;
use App\Models\SubPlotEnv2010;
use App\Models\SubPlotEnv2025;
use App\Models\SubPlotPlant2025;
use App\Models\User;
use App\Services\DataSyncService;
use App\Services\FormAuditService;
use App\Services\PlantIdentityResolver;
use App\Support\HabitatCode;
use App\Support\RecordStateGuard;
use App\Support\TaiwanChecklistQuery;
use App\Support\UploadMime;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
// use Illuminate\Http\Request;
use Throwable;

class EntryEntry extends Component
{
    use SubPlotEnvFormRules;
    use WithFileUploads;

    private const ENV_EDITABLE_FIELDS = [
        'date', 'investigator', 'recorder', 'dd97_x', 'dd97_y', 'gps_error',
        'habitat_code', 'subplot_id', 'subplot_area', 'elevation', 'slope', 'aspect',
        'photo_id', 'env_description', 'original_plot_id',
    ];

    private const ENV_SYSTEM_FIELDS = [
        'team', 'plot', 'plot_full_id', 'year', 'month', 'day', 'tm2_x', 'tm2_y',
    ];

    private const PLANT_EDITABLE_FIELDS = [
        'chname_index', 'spcode', 'coverage', 'flowering', 'fruiting', 'specimen_id', 'note',
    ];

    private const PLANT_SYSTEM_FIELDS = [
        'plot_full_id', 'unidentified', 'data_error',
    ];

    public $countyList = [];

    public $thisCounty;

    public $plotList = [];

    public $subPlotList = [];

    public $noSubplotData = false;

    public $thisPlot;

    public $plotInfo = [];

    public $thisSubPlot;

    public $allPlotInfo = [];

    public $plantList = [];

    public $subPlotEnvForm = [];

    public $subPlotPlantForm = [];

    public array $envRecordVersions = [];

    public array $plantRecordVersions = [];

    public string $envVersionToken = '';

    public string $plantVersionToken = '';

    public string $loadedPlotCensusYear = '';

    public string $loadedPlotYearToken = '';

    public $plotCensusYear = '';

    public string $loadedPlotFileUploadedAt = '';

    public string $loadedPlotFileToken = '';

    public $pendingRestorePlotFullId = '';

    public string $pendingRestoreToken = '';

    public $userOrg;

    public $user;

    public $creatorCode;

    public function mount()
    {
        $user = Auth::user(); // 取代 auth()->user()

        if (! $user) {
            return redirect('/'); // ⬅️ 若未登入，退回首頁
        }

        // 已登入情況
        $this->refreshActor($user);
        $this->countyList = $this->accessiblePlotQuery($user)
            ->distinct()
            ->orderBy('county')
            ->pluck('county')
            ->filter()
            ->values()
            ->toArray();

        $this->showPlotEntryTable = false;
        $this->showPlantEntryTable = false;
        $this->thisPlot = '';

        if (session()->has('query.county')) {
            $county = session()->pull('query.county');
            $plot = session()->pull('query.plot');
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
        $this->thisCounty = (string) $county;
        $this->plotList = $this->accessiblePlotQuery()
            ->where('county', $this->thisCounty)
            ->select('plot')
            ->distinct()
            ->orderBy('plot')
            ->pluck('plot')
            ->toArray();
        $this->showPlotEntryTable = false;
        $this->showPlantEntryTable = false;
        $this->thisPlot = '';
        $this->clearPendingRestore();
        $this->resetPlotVersionState();
        $this->plotHabToken = '';
        $this->resetRecordVersions();
        $this->dispatch('reset_plant_table');
        $this->dispatch('thisPlotUpdated');

    }

    // public $thisPlotHabRatioForm = [];
    // public $habTypeOptions = [];
    // public array $selectedHabitatCodes = []; // 勾選的 habitat_code
    public array $selectedHabitatCodes = []; // 使用者勾選的 habitat_code 陣列

    public string $plotHabToken = '';

    public array $refHabitatCodes = [];      // 2010 參考用代碼

    public array $habTypeOptions = [];       // 全部 habitat_code => label

    public function loadPlotInfo($plot)
    {
        if ((string) $plot === '') {
            $this->thisPlot = '';
            $this->thisSubPlot = '';
            $this->clearPendingRestore();
            $this->resetPlotVersionState();
            $this->plotHabToken = '';
            $this->resetRecordVersions();
            $this->subPlotList = [];
            $this->selectedHabitatCodes = [];
            $this->showPlotEntryTable = false;
            $this->showPlantEntryTable = false;
            $this->dispatch('reset_plant_table');

            return;
        }

        $plotRow = $this->authorizePlot((string) $plot);

        // $this->dispatch('reset_habitat');
        $this->thisPlot = (string) $plotRow->plot;
        $this->thisCounty = (string) $plotRow->county;
        $this->loadedPlotCensusYear = (string) ($plotRow->getRawOriginal('census_year') ?? '');
        $this->plotCensusYear = $this->plotFormYear($plotRow);
        $this->clearPendingRestore();
        $this->loadedPlotYearToken = $this->plotYearToken($plotRow);
        $this->setPlotFileVersion($plotRow);
        $this->plotHabToken = '';
        $this->thisSubPlot = ''; // 清空樣區ID
        $this->resetRecordVersions();
        $this->showPlotEntryTable = false;
        $this->showPlantEntryTable = false;
        $this->dispatch('reset_plant_table');
        // 取得樣區資料
        $this->subPlotList = SubPlotEnv2025::where('plot', $plot)->orderBy('plot_full_id')->pluck('plot_full_id')->toArray();
        $this->selectedHabitatCodes = [];
        $this->loadPlotHab($plot); // 載入生育地類型選項
        $this->loadFileInfo();

        // dd($this->selectedHabitatCodes);
    }

    public function loadPlotHab($plot)
    {
        $this->authorizePlot((string) $plot);

        $habTypeMap = HabitatInfo::pluck('habitat', 'habitat_code')->toArray();

        $this->habTypeOptions = collect($habTypeMap)
            ->mapWithKeys(fn ($habitat, $code) => [$code => $code.' '.$habitat])
            ->sortBy(fn ($label) => $label)
            ->toArray();

        // 從 SubPlotEnv2010 取得 參考用 habitat_code（只顯示顏色，不會選中）
        $this->refHabitatCodes = SubPlotEnv2010::where('PLOT_ID', $plot)
            ->pluck('HAB_TYPE')
            ->unique()
            ->values()    // ✅ 這行會把索引變成連續的 0,1,2,...
            ->toArray();

        // 若有既存選擇（例如 PlotHabRatio），可設定預選
        $selectedRecords = PlotHab::where('plot', $plot)
            ->orderBy('id')
            ->get();
        $this->selectedHabitatCodes = $selectedRecords
            ->pluck('habitat_code')
            ->map(fn ($code) => str_pad((string) $code, 2, '0', STR_PAD_LEFT))
            ->unique()
            ->values()
            ->toArray();
        $this->plotHabToken = $this->plotHabStateToken((string) $plot, $selectedRecords);

    }

    public function saveHabitatSelection()
    {
        $plotRow = $this->authorizePlot();
        $plot = (string) $plotRow->plot;

        try {
            $selected = HabitatCode::normalizeSelectedCodes($this->selectedHabitatCodes);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'selectedHabitatCodes' => $e->getMessage(),
            ]);
        }

        $this->selectedHabitatCodes = $selected;

        DB::connection('invasiflora')->transaction(function () use ($plot, $plotRow, $selected) {
            // 生育地與完成例外都先鎖同一樣區列，避免兩頁交錯寫入。
            $lockedPlot = $this->accessiblePlotQuery()
                ->whereKey($plotRow->getKey())
                ->where('plot', $plot)
                ->lockForUpdate()
                ->first();
            if (! $lockedPlot) {
                throw ValidationException::withMessages([
                    'selectedHabitatCodes' => '樣區資料已變更，請重新選擇樣區後再儲存。',
                ]);
            }

            $existingRecords = PlotHab::where('plot', $plot)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            RecordStateGuard::assertToken(
                $this->plotHabToken,
                'plot-habitats:'.$plot,
                $this->plotHabState($existingRecords),
                'selectedHabitatCodes',
                '生育地類型已由其他分頁變更，請重新選擇樣區後再儲存。'
            );

            [$removedIds, $added] = $this->habitatSelectionChanges($existingRecords, $selected);

            if ($removedIds !== []) {
                PlotHab::where('plot', $plot)->whereIn('id', $removedIds)->delete();
            }

            foreach ($added as $code) {
                PlotHab::create([
                    'plot' => $plot,
                    'habitat_code' => $code,
                    'created_by' => $this->creatorCode,
                ]);
            }

            // 已取消的生育地不應保留舊例外，避免日後重新勾選時意外套用舊門檻。
            $exceptionQuery = PlotHabitatCompletionException::where('plot', $plot);
            if (empty($selected)) {
                $exceptionQuery->delete();
            } else {
                $exceptionQuery->whereNotIn('habitat_code', $selected)->delete();
            }
        });

        $this->loadPlotHab($plot);
        session()->flash('habSaveMessage', '生育地類型已儲存。');
    }

    public function savePlotCensusYear(): void
    {
        $plotRow = $this->authorizePlot();
        $this->validate([
            'plotCensusYear' => 'required|integer|min:2025|max:'.(date('Y') + 1),
        ], [
            'plotCensusYear.required' => '請填寫樣區計畫年度。',
            'plotCensusYear.integer' => '樣區計畫年度必須為四位整數。',
            'plotCensusYear.min' => '樣區計畫年度不得小於 2025 年。',
            'plotCensusYear.max' => '樣區計畫年度最多可預做至下一年。',
        ]);

        $year = (int) $this->plotCensusYear;
        $changed = false;
        $lockedPlot = DB::connection('invasiflora')->transaction(function () use ($plotRow, $year, &$changed) {
            $record = PlotList2025::whereKey($plotRow->id)->lockForUpdate()->firstOrFail();
            $this->assertPlotYearState($record);
            $originalYear = (int) ($record->census_year ?? 0);

            if ($originalYear !== $year) {
                $record->census_year = $year;
                $record->updated_by = $this->creatorCode;
                $record->save();
                $changed = true;

                FixLog::create([
                    'table_name' => 'plot_list',
                    'record_id' => $record->id,
                    'changes' => ['census_year' => ['old' => $originalYear, 'new' => $year]],
                    'modified_by' => $this->creatorCode,
                    'modified_at' => now(),
                ]);
            }

            return $record;
        });

        $this->plotCensusYear = (string) $year;
        $this->loadedPlotCensusYear = (string) $year;
        $this->loadedPlotYearToken = $this->plotYearToken($lockedPlot);
        session()->flash('yearSaveMessage', $changed ? '計畫年度已儲存。' : '計畫年度無任何變更。');
    }

    private function plotHabStateToken(string $plot, Collection $records): string
    {
        return RecordStateGuard::token('plot-habitats:'.$plot, $this->plotHabState($records));
    }

    private function plotHabState(Collection $records): array
    {
        return ['rows' => RecordStateGuard::snapshot($records, ['habitat_code'])];
    }

    /** @return array{0: array<int, int>, 1: array<int, string>} */
    private function habitatSelectionChanges(Collection $existingRecords, array $selected): array
    {
        $currentCodes = $existingRecords
            ->pluck('habitat_code')
            ->map(fn ($code) => str_pad((string) $code, 2, '0', STR_PAD_LEFT))
            ->unique()
            ->all();
        $removedIds = $existingRecords
            ->filter(fn ($record) => ! in_array(
                str_pad((string) $record->habitat_code, 2, '0', STR_PAD_LEFT),
                $selected,
                true
            ))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return [$removedIds, array_values(array_diff($selected, $currentCodes))];
    }

    public function updatedThisSubPlot($value)
    {
        $this->clearPendingRestore();
        $this->dispatch('reset_plant_table');
        if ($value) {
            $this->authorizeSubPlot((string) $value);
            $this->loadSubPlotEnv($value);
            $this->loadSubPlotPlant($value);
        } else {
            $this->resetRecordVersions();
        }

    }

    public $showPlotEntryTable = false;

    public $showPlantEntryTable = false;

    public function loadEmptyEnvForm()
    {
        $this->authorizePlot();
        $this->thisSubPlot = ''; // 清空樣區ID
        $this->clearPendingRestore();
        $this->resetRecordVersions();

        $columns = Schema::connection('invasiflora')->getColumnListing('im_splotdata_2025');

        $columns = array_diff($columns, ['created_by', 'updated_by', 'created_at', 'updated_at', 'team', 'date', 'plot_full_id', 'upload', 'file_uploadad_by', 'file_uploadad_at']);

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
        $data = $this->authorizeSubPlot((string) $subPlot);
        $this->thisSubPlot = (string) $data->plot_full_id;
        $subPlotEnvForm = [];

        $this->authorizePlot((string) $this->thisPlot);

        if ($data) {
            $subPlotEnvForm = $data->toArray(); // 有資料：預填入表單
        }
        // dd($data);
        // $subPlotAreaMap = config('item_list.sub_plot_area');
        // $islandCategoryMap = config('item_list.island_category');
        // $plotEnvMap = config('item_list.plot_env');

        // $subPlotEnvForm['subplot_area'] = $subPlotAreaMap[$subPlotEnvForm['subplot_area']];
        //  $subPlotEnvForm['plot_env'] = $plotEnvMap[$subPlotEnvForm['plot_env']];
        //   $subPlotEnvForm['island_category'] = $islandCategoryMap[$subPlotEnvForm['island_category']];
        $this->subPlotEnvForm = $subPlotEnvForm;
        $environmentRecords = collect([$data]);

        $habitat = (string) $data->habitat_code;
        if (HabitatCode::isWood($habitat)) {
            $understoryId = substr((string) $data->plot_full_id, 0, 6)
                .HabitatCode::understoryFor($habitat)
                .substr((string) $data->plot_full_id, 8);
            $understory = SubPlotEnv2025::where('plot_full_id', $understoryId)->first();
            if ($understory) {
                $environmentRecords->push($understory);
            }
        }
        $this->setEnvironmentRecordVersions($environmentRecords);

        $this->showPlotEntryTable = true; // 顯示表單

    }

    public function loadSubPlotPlant($subPlot)
    {
        $authorizedSubPlot = $this->authorizeSubPlot((string) $subPlot);
        $this->thisSubPlot = (string) $authorizedSubPlot->plot_full_id;

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
                'thisSubPlot' => $this->thisSubPlot,
                // 'plantList' =>$this->plantList
            ]);

        } else {
            $this->loadEmptyPlantForm(); // 無資料 → 載入空白列
        }

        $this->showPlantEntryTable = true;
    }

    public function deleteSubPlot(): void
    {
        $plotFullId = (string) $this->thisSubPlot;

        if ($plotFullId === '' || $this->thisPlot === '') {
            session()->flash('deleteMsg', '找不到要刪除的小樣方資料。');

            return;
        }

        $this->authorizeSubPlot($plotFullId);

        $deletedPlantCount = DB::connection('invasiflora')
            ->transaction(fn () => $this->deleteSubPlotBatch($plotFullId));

        $this->thisSubPlot = '';
        $this->subPlotEnvForm = [];
        $this->subPlotPlantForm = [];
        $this->resetRecordVersions();
        $this->showPlotEntryTable = false;
        $this->showPlantEntryTable = false;
        $this->dispatch('reset_plant_table');
        $this->loadPlotInfo($this->thisPlot);

        session()->flash(
            'deleteMsg',
            "已刪除小樣方 {$plotFullId}，並刪除 {$deletedPlantCount} 筆植物調查資料。"
        );
    }

    private function deleteSubPlotBatch(string $plotFullId): int
    {
        $subPlot = SubPlotEnv2025::where('plot_full_id', $plotFullId)
            ->where('plot', $this->thisPlot)
            ->lockForUpdate()
            ->firstOrFail();
        $this->assertRecordVersionToken('environment', $this->envRecordVersions, $this->envVersionToken, '小樣方環境資料');
        $this->assertRecordVersion($subPlot, $this->envRecordVersions, '小樣方環境資料');

        $plantRecords = SubPlotPlant2025::where('plot_full_id', $plotFullId)
            ->lockForUpdate()
            ->get();
        $this->assertRecordVersionState(
            'plants',
            $plantRecords,
            $this->plantRecordVersions,
            $this->plantVersionToken,
            '植物調查資料'
        );
        $deletionBatchId = (string) Str::uuid();

        SubPlotPlant2025::where('plot_full_id', $plotFullId)
            ->update([
                'deleted_by' => $this->creatorCode,
                'deletion_batch_id' => $deletionBatchId,
            ]);
        SubPlotPlant2025::where('plot_full_id', $plotFullId)->delete();

        $subPlot->deleted_by = $this->creatorCode;
        $subPlot->deletion_batch_id = $deletionBatchId;
        $subPlot->save();
        $subPlot->delete();

        return $plantRecords->count();
    }

    public function restoreDeletedSubPlot(): void
    {
        session()->flash('form', 'env');
        $plotFullId = (string) $this->pendingRestorePlotFullId;

        if ($plotFullId === '' || $this->thisPlot === '' || $this->pendingRestoreToken === '') {
            throw ValidationException::withMessages([
                '小樣方流水號' => '找不到可安全還原的小樣方資料，請重新輸入小樣方編號。',
            ]);
        }

        $this->authorizeSubPlot($plotFullId, true);

        $restoredPlantCount = DB::connection('invasiflora')
            ->transaction(fn () => $this->restoreDeletedSubPlotBatch($plotFullId));

        $this->clearPendingRestore();
        $this->loadPlotInfo($this->thisPlot);
        $this->thisSubPlot = $plotFullId;
        $this->updatedThisSubPlot($plotFullId);

        session()->flash(
            'deleteMsg',
            "已還原小樣方 {$plotFullId}，並還原 {$restoredPlantCount} 筆植物調查資料。"
        );
    }

    private function restoreDeletedSubPlotBatch(string $plotFullId): int
    {
        $subPlot = SubPlotEnv2025::onlyTrashed()
            ->where('plot_full_id', $plotFullId)
            ->where('plot', $this->thisPlot)
            ->lockForUpdate()
            ->firstOrFail();

        $batchId = (string) ($subPlot->deletion_batch_id ?? '');
        if (! Str::isUuid($batchId)) {
            throw ValidationException::withMessages([
                '小樣方流水號' => '此小樣方沒有刪除批次紀錄，無法安全自動還原。',
            ]);
        }

        $plants = SubPlotPlant2025::onlyTrashed()
            ->where('plot_full_id', $plotFullId)
            ->where('deletion_batch_id', $batchId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        RecordStateGuard::assertToken(
            $this->pendingRestoreToken,
            'entry-restore:'.$this->thisPlot.':'.$plotFullId,
            $this->deletedSubPlotState($subPlot, $plants),
            '小樣方流水號',
            '待還原資料已變更，請重新輸入小樣方編號後再試。'
        );

        if (SubPlotEnv2025::where('plot_full_id', $plotFullId)->exists()) {
            throw ValidationException::withMessages([
                '小樣方流水號' => '小樣方編號已被使用，無法還原。',
            ]);
        }

        $restoredPlantCount = $plants->count();
        if ($restoredPlantCount > 0) {
            SubPlotPlant2025::onlyTrashed()
                ->whereIn('id', $plants->pluck('id')->all())
                ->where('deletion_batch_id', $batchId)
                ->update([
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'deletion_batch_id' => null,
                ]);
        }

        $subPlot->deleted_by = null;
        $subPlot->deletion_batch_id = null;
        $subPlot->restore();

        return $restoredPlantCount;
    }

    public function cancelRestoreSubPlot(): void
    {
        session()->flash('form', 'env');
        $this->clearPendingRestore();
        $this->addError('小樣方流水號', '已取消新增，請修改小樣方編號後再儲存。');
    }

    private function clearPendingRestore(): void
    {
        $this->pendingRestorePlotFullId = '';
        $this->pendingRestoreToken = '';
    }

    private function deletedSubPlotState(SubPlotEnv2025 $subPlot, Collection $plants): array
    {
        return [
            'environment' => RecordStateGuard::snapshot(collect([$subPlot]), [
                'plot', 'plot_full_id', 'deleted_at', 'deletion_batch_id',
            ]),
            'plants' => RecordStateGuard::snapshot($plants, [
                'plot_full_id', 'deleted_at', 'deletion_batch_id',
            ]),
        ];
    }

    public function loadExistingPlantForm()
    {
        $this->authorizeSubPlot((string) $this->thisSubPlot);
        $emptyRow = $this->plantFormEmptyRow();
        $columns = $emptyRow['columns'];
        $empty = $emptyRow['empty'];

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
        $this->setPlantRecordVersions($data);
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

    public function plantFormEmptyRow()
    {

        $columns = Schema::connection('invasiflora')->getColumnListing('im_spvptdata_2025');

        $columns = array_diff($columns, [
            'created_by', 'updated_by', 'created_at', 'updated_at',
        ]);

        $columns = array_values($columns); // 儲存欄位順序
        $columns[] = 'hint';
        $booleanFields = ['flowering', 'fruiting']; // 你要預設為 0 的欄位
        $emptyRow = [];
        foreach ($columns as $col) {
            $emptyRow[$col] = in_array($col, $booleanFields) ? 0 : '';
        }

        $this->plantFormColumns = $columns;

        return [
            'columns' => $columns,
            'empty' => $emptyRow,
        ];

    }

    public function loadEmptyPlantForm()
    {
        $this->authorizeSubPlot((string) $this->thisSubPlot);
        $this->plantRecordVersions = [];
        $this->plantVersionToken = $this->recordVersionToken('plants', []);

        // dd($columns);
        $emptyRow = $this->plantFormEmptyRow();

        $this->subPlotPlantForm = [];

        for ($i = 0; $i < 15; $i++) {
            $row = $emptyRow['empty'];
            $row['plot_full_id'] = $this->thisSubPlot;
            $this->subPlotPlantForm[] = $row;
        }

        $this->dispatch('plant_table', data: [
            'data' => $this->subPlotPlantForm,
            'thisSubPlot' => $this->thisSubPlot,
            'plantList' => $this->plantList,
        ]);

        $this->showPlantEntryTable = true;
        $this->loadPhotoInfo();
    }

    public $thisPlotFile;

    public function loadFileInfo()
    {
        $this->authorizePlot();

        $relativePath = "plotData/{$this->thisCounty}/{$this->thisPlot}.pdf";
        $disk = Storage::disk('invasi_files');

        if ($disk->exists($relativePath)) {
            $this->thisPlotFile = route('file.plot', ['plot' => $this->thisPlot]).'?v='.$disk->lastModified($relativePath);
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
        return "subPlotPhoto/{$this->thisCounty}/{$this->thisPlot}/{$hab}";
    }

    private function findPhotoPath(string $subPlot): ?string
    {
        $hab = substr($subPlot, 6, 2);
        $baseDir = $this->photoRelativeDir($hab);

        foreach ($this->photoExts as $ext) {
            $path = "{$baseDir}/{$subPlot}.{$ext}";
            if (Storage::disk('invasi_files')->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public $thisPhoto;

    public function loadPhotoInfo()
    {
        $this->authorizeSubPlot((string) $this->thisSubPlot);
        $path = $this->findPhotoPath((string) $this->thisSubPlot);
        $this->thisPhoto = $path
            ? route('file.subplot-photo', ['plotFullId' => $this->thisSubPlot])
            : null;
    }

    public $hasUnderData = '';

    public function envInfoSave(FormAuditService $audit)
    {
        $this->clearPendingRestore();
        $plotRow = $this->authorizePlot();
        $authorizedSubPlot = $this->thisSubPlot !== ''
            ? $this->authorizeSubPlot((string) $this->thisSubPlot)
            : null;
        $this->subPlotEnvForm['plot'] = (string) $plotRow->plot;
        if ($authorizedSubPlot) {
            $this->subPlotEnvForm['id'] = $authorizedSubPlot->id;
        } else {
            unset($this->subPlotEnvForm['id']);
        }

        $this->hasUnderData = '';
        session()->flash('form', 'env');

        // 接受單碼數字輸入，驗證及組合完整樣區編號前統一為兩碼文字。
        foreach (['habitat_code', 'subplot_id'] as $field) {
            $value = $this->subPlotEnvForm[$field] ?? null;
            if ((is_string($value) || is_int($value)) && preg_match('/^[0-9]{1,2}$/D', (string) $value)) {
                $this->subPlotEnvForm[$field] = str_pad((string) $value, 2, '0', STR_PAD_LEFT);
            }
        }

        $this->validate($this->subPlotEnvRules(), $this->subPlotEnvMessages());
        $msg = '';
        $subPlotEnvForm = collect($this->subPlotEnvForm)
            ->only(self::ENV_EDITABLE_FIELDS)
            ->toArray();
        if ($authorizedSubPlot) {
            $subPlotEnvForm['id'] = $authorizedSubPlot->id;
        }

        $subPlotEnvForm['team'] = (string) $plotRow->team;
        $subPlotEnvForm['plot'] = (string) $plotRow->plot;
        $subPlotEnvForm['plot_full_id'] = $subPlotEnvForm['plot'].
            $subPlotEnvForm['habitat_code'].
            $subPlotEnvForm['subplot_id'];
        $subPlotEnvForm['date'] = date('Y-m-d', strtotime($subPlotEnvForm['date']));
        $subPlotEnvForm = array_merge(
            $subPlotEnvForm,
            CoordinateHelper::toTm2($subPlotEnvForm['dd97_x'], $subPlotEnvForm['dd97_y']),
            DateHelper::splitYmd($subPlotEnvForm['date'])
        );
        if (! $this->environmentTargetsAvailable($subPlotEnvForm, $authorizedSubPlot)) {
            return;
        }

        $connection = DB::connection('invasiflora');
        $photoOperations = [];
        $connection->beginTransaction();

        try {
            PlotList2025::whereKey($plotRow->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($authorizedSubPlot) {
                $lockedEnvRecords = SubPlotEnv2025::whereIn(
                    'id',
                    array_map('intval', array_keys($this->envRecordVersions))
                )
                    ->lockForUpdate()
                    ->get();
                $this->assertRecordVersionState(
                    'environment',
                    $lockedEnvRecords,
                    $this->envRecordVersions,
                    $this->envVersionToken,
                    '小樣方環境資料'
                );
            }

            $newdata[] = $subPlotEnvForm;

            $this->subPlotEnvForm = $subPlotEnvForm;
            if ($this->thisSubPlot == '') {  // 新增小樣方

                $newdata = $this->addUnderstoryPlot($subPlotEnvForm);
                $plotFullIds = (array) $subPlotEnvForm['plot_full_id'];
            } else {  // 修改小樣方資料
                // 如果是更改樣區編號  1.更改生育地  2. 更改小樣方
                // 先處理更改編號
                $plot = $subPlotEnvForm['plot'];
                $o_habitat_code = substr($this->thisSubPlot, 6, 2);
                $o_subplot_id = substr($this->thisSubPlot, 8, 2);
                $plotFullIds = (array) $subPlotEnvForm['plot_full_id'];

                if ($subPlotEnvForm['plot_full_id'] != $this->thisSubPlot) {
                    $photoOperations = $this->movePhotosForSubPlotRename(
                        (string) $this->thisSubPlot,
                        (string) $subPlotEnvForm['plot_full_id'],
                        $o_habitat_code,
                        (string) $subPlotEnvForm['habitat_code']
                    );

                    // 2. 更改小樣方編號
                    SubPlotEnv2025::where('plot_full_id', $this->thisSubPlot)
                        ->update([
                            'plot_full_id' => $subPlotEnvForm['plot_full_id'],
                            'habitat_code' => $subPlotEnvForm['habitat_code'],
                            'subplot_id' => $subPlotEnvForm['subplot_id'],
                            'updated_by' => $this->creatorCode,
                        ]);
                    $updatedCount = SubPlotPlant2025::where('plot_full_id', $this->thisSubPlot)->update(['plot_full_id' => $subPlotEnvForm['plot_full_id'], 'updated_by' => $this->creatorCode]);

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
                    if ($updatedCount > 1) {
                        FixLog::create([
                            'table_name' => 'im_spvptdata_2025',
                            'record_id' => 0,
                            'changes' => $diff['plot_full_id'],
                            'modified_by' => $this->creatorCode,
                            'modified_at' => now(),
                        ]);
                    }
                    // 若新生育地是木本主類型，一併更改對應地被編號。
                    $msg = '已更新『'.$this->thisSubPlot.'』樣區編號為『'.$subPlotEnvForm['plot_full_id'].'』。';

                    if (HabitatCode::isWood($subPlotEnvForm['habitat_code'])) {
                        $extraHabitat_o = HabitatCode::understoryFor($o_habitat_code) ?? '00';
                        $extraHabitat_n = HabitatCode::understoryFor($subPlotEnvForm['habitat_code']);

                        $related_full_id_o = $plot.$extraHabitat_o.$o_subplot_id;
                        $related_full_id_n = $plot.$extraHabitat_n.$subPlotEnvForm['subplot_id'];

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

                            session()->flash('saveMsg2', '同時更新 『'.$related_full_id_o.'』樣區編號為 『'.$related_full_id_n.'』。');

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
                            if ($updatedCount2 > 1) {
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
                    if (HabitatCode::isWood($o_habitat_code) && ! HabitatCode::isWood($subPlotEnvForm['habitat_code']) && $o_habitat_code != $subPlotEnvForm['habitat_code']) {
                        $extraHabitat = HabitatCode::understoryFor($o_habitat_code);
                        $related_full_id_o = $plot.$extraHabitat.$o_subplot_id;
                        session()->flash('saveMsg2', '保留原有 『'.$related_full_id_o.'』環境、植物資料，如需刪除請洽管理員。');

                    }

                } else {

                }
                $newdata = $this->addUnderstoryPlot($subPlotEnvForm);
            }

            $originalData = SubPlotEnv2025::whereIn('plot_full_id', $plotFullIds)->get()->toArray();

            //   dd($originalData);

            // dd($where);

            // dd($subPlotEnvForm);
            // dd($newdata);
            $changed = DataSyncService::syncById(
                modelClass: SubPlotEnv2025::class,
                originalData: $originalData,
                newData: $newdata,
                fields: array_merge(self::ENV_EDITABLE_FIELDS, self::ENV_SYSTEM_FIELDS),
                createExtra: ['created_by' => $this->creatorCode],
                updateExtra: ['updated_by' => $this->creatorCode],
                requiredFields: ['plot_full_id'],
                userCode: $this->creatorCode
            );

            if ($changed) {
                $msg .= '已更新/新增『'.$subPlotEnvForm['plot_full_id'].'』環境資料。';
                if ($this->hasUnderData != '') {
                    $msg .= '同時更新/新增『'.$this->hasUnderData.'』環境資料。';
                }
            } else {
                $msg .= '環境資料無任何變更。';
            }

            $connection->commit();
        } catch (QueryException $e) {
            $connection->rollBack();
            $this->rollbackPhotoOperations($photoOperations);

            if (($e->errorInfo[1] ?? null) === 1062
                && str_contains($e->getMessage(), 'im_splotdata_2025_plot_full_id_unique')) {
                throw ValidationException::withMessages([
                    '小樣方流水號' => '小樣方流水號重複，可能已由其他使用者新增，請重新載入後再試。',
                ]);
            }

            throw $e;
        } catch (Throwable $e) {
            $connection->rollBack();
            $this->rollbackPhotoOperations($photoOperations);

            throw $e;
        }

        session()->flash('saveMsg', $msg);

        $this->loadPlotInfo($this->thisPlot);
        $this->thisSubPlot = $subPlotEnvForm['plot_full_id'];
        $this->updatedThisSubPlot($subPlotEnvForm['plot_full_id']);
        // $this->loadSubPlotEnv($subPlotEnvForm['plot_full_id']);
    }

    private function environmentTargetsAvailable(array $form, ?SubPlotEnv2025 $authorizedSubPlot): bool
    {
        $targetPlotFullId = (string) $form['plot_full_id'];
        $targetConflict = SubPlotEnv2025::withTrashed()
            ->where('plot_full_id', $targetPlotFullId)
            ->when($authorizedSubPlot, fn ($query) => $query->where('id', '!=', $authorizedSubPlot->id))
            ->first();

        if ($targetConflict) {
            if (! $authorizedSubPlot && $targetConflict->trashed()) {
                $batchId = (string) ($targetConflict->deletion_batch_id ?? '');
                if (! Str::isUuid($batchId)) {
                    $this->addError(
                        '小樣方流水號',
                        '此編號屬於舊版刪除資料，無法安全自動還原，請洽管理員。'
                    );

                    return false;
                }

                $plants = SubPlotPlant2025::onlyTrashed()
                    ->where('plot_full_id', $targetPlotFullId)
                    ->where('deletion_batch_id', $batchId)
                    ->orderBy('id')
                    ->get();
                $this->pendingRestorePlotFullId = $targetPlotFullId;
                $this->pendingRestoreToken = RecordStateGuard::token(
                    'entry-restore:'.$this->thisPlot.':'.$targetPlotFullId,
                    $this->deletedSubPlotState($targetConflict, $plants)
                );

                return false;
            }

            $this->addError('小樣方流水號', '小樣方流水號重複。');

            return false;
        }

        if (! HabitatCode::isWood((string) $form['habitat_code'])) {
            return true;
        }

        $targetUnderstoryId = (string) $form['plot']
            .HabitatCode::understoryFor((string) $form['habitat_code'])
            .(string) $form['subplot_id'];
        $oldUnderstoryRecord = null;

        if ($authorizedSubPlot && HabitatCode::isWood((string) $authorizedSubPlot->habitat_code)) {
            $oldUnderstoryId = (string) $form['plot']
                .HabitatCode::understoryFor((string) $authorizedSubPlot->habitat_code)
                .(string) $authorizedSubPlot->subplot_id;
            $oldUnderstoryRecord = SubPlotEnv2025::where('plot_full_id', $oldUnderstoryId)->first();
        }

        $understoryConflict = SubPlotEnv2025::withTrashed()
            ->where('plot_full_id', $targetUnderstoryId)
            ->when($oldUnderstoryRecord, fn ($query) => $query->where('id', '!=', $oldUnderstoryRecord->id))
            ->exists();

        if ($understoryConflict) {
            $this->addError(
                '小樣方流水號',
                "對應地被小樣方 {$targetUnderstoryId} 已存在，請更換流水號。"
            );

            return false;
        }

        return true;
    }

    private function addUnderstoryPlot($subPlotEnvForm)
    {
        // ✅ 根據 habitat_code 判斷是否要額外新增對應筆
        $autoCopyMap = HabitatCode::pairs();

        $newdata = [];

        // 加入原始小樣方資料

        $newdata[] = $subPlotEnvForm;

        if (array_key_exists($subPlotEnvForm['habitat_code'], $autoCopyMap)) {
            $subPlotEnvForm['subplot_area'] = 3; // 強制設定為 5x5
            $copyCode = $autoCopyMap[$subPlotEnvForm['habitat_code']];
            $copiedPlotFullId = $subPlotEnvForm['plot'].$copyCode.$subPlotEnvForm['subplot_id'];

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
        $this->authorizeSubPlot((string) $this->thisSubPlot);

        $allowedIds = SubPlotPlant2025::where('plot_full_id', $this->thisSubPlot)
            ->pluck('id')
            ->map(fn ($id) => (string) $id);
        $submittedIds = collect($this->subPlotPlantForm)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id);

        if ($submittedIds->diff($allowedIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'subPlotPlantForm' => '送出的植物資料不屬於目前小樣方，請重新載入後再試。',
            ]);
        }

        $newData = collect($this->subPlotPlantForm)
            ->filter(fn ($row) => ! empty($row['chname_index'])) // 只處理有中文名的
            ->map(function ($row) {
                $row = collect($row)
                    ->only(array_merge(['id'], self::PLANT_EDITABLE_FIELDS))
                    ->toArray();

                // 比對中文名 → 取得 spcode

                $row['unidentified'] = isset($row['spcode']) && $row['spcode'] !== '' ? 0 : 1;

                // 覆蓋度錯誤標記
                $cov = $row['coverage'] ?? null;
                if (! is_numeric($cov) || $cov < 0 || $cov > 100 || $cov == 0) {
                    $row['data_error'] = 1;
                    $row['coverage'] = 0;
                } else {
                    $row['data_error'] = 0;
                }

                foreach (['flowering', 'fruiting'] as $boolField) {
                    $row[$boolField] = isset($row[$boolField]) && $row[$boolField] !== '' ? intval($row[$boolField]) : 0;
                }

                $row['plot_full_id'] = $this->thisSubPlot;
                unset($row['hint']); // ❌ 移除 hint 欄位

                return $row;
            })->values();

        $newData = PlantIdentityResolver::resolve($newData)->values()->all();

        //  dd($newData);

        $changed = DB::connection('invasiflora')->transaction(function () use ($newData) {
            $currentRecords = SubPlotPlant2025::where('plot_full_id', $this->thisSubPlot)
                ->lockForUpdate()
                ->get();
            $this->assertRecordVersionState(
                'plants',
                $currentRecords,
                $this->plantRecordVersions,
                $this->plantVersionToken,
                '植物調查資料'
            );
            $originalData = $currentRecords->toArray();

            $synced = DataSyncService::syncById(
                modelClass: SubPlotPlant2025::class,
                originalData: $originalData,
                newData: $newData,
                fields: array_merge(self::PLANT_EDITABLE_FIELDS, self::PLANT_SYSTEM_FIELDS),
                createExtra: ['created_by' => $this->creatorCode],
                updateExtra: ['updated_by' => $this->creatorCode],
                requiredFields: ['chname_index'],
                userCode: $this->creatorCode
            );

            return $this->recalculatePlantDataErrors($this->thisSubPlot) || $synced;
        });
        $this->subPlotPlantForm = $this->loadExistingPlantForm();

        $this->dispatch('plant_table', data: [
            'data' => $this->subPlotPlantForm,
            'thisSubPlot' => $this->thisSubPlot,
            'plantList' => $this->plantList,
        ]);

        session()->flash('plantSaveMessage', $changed ? '植物資料已更新' : '無任何變更');

    }

    private function recordVersion($model): string
    {
        return (string) ($model->getRawOriginal('updated_at') ?? '');
    }

    private function plotYearToken(PlotList2025 $plotRow): string
    {
        return RecordStateGuard::token(
            'entry-plot-year:'.$plotRow->getKey().':'.(string) $plotRow->plot,
            ['year' => (string) ($plotRow->getRawOriginal('census_year') ?? '')]
        );
    }

    private function assertPlotYearState(PlotList2025 $lockedPlot): void
    {
        $scope = 'entry-plot-year:'.$lockedPlot->getKey().':'.(string) $lockedPlot->plot;
        RecordStateGuard::assertToken(
            $this->loadedPlotYearToken,
            $scope,
            ['year' => $this->loadedPlotCensusYear],
            'concurrentEdit',
            '樣區計畫年度載入狀態已變更，請重新載入後再修改。'
        );

        if ((string) ($lockedPlot->getRawOriginal('census_year') ?? '') !== $this->loadedPlotCensusYear) {
            throw ValidationException::withMessages([
                'concurrentEdit' => '樣區計畫年度已由其他使用者更新，請重新載入後再修改。',
            ]);
        }
    }

    private function plotFileToken(PlotList2025 $plotRow, string $uploadedAt): string
    {
        return RecordStateGuard::token(
            'entry-plot-file:'.$plotRow->getKey().':'.(string) $plotRow->plot,
            ['file_uploaded_at' => $uploadedAt]
        );
    }

    private function setPlotFileVersion(PlotList2025 $plotRow): void
    {
        $this->loadedPlotFileUploadedAt = (string) ($plotRow->getRawOriginal('file_uploaded_at') ?? '');
        $this->loadedPlotFileToken = $this->plotFileToken($plotRow, $this->loadedPlotFileUploadedAt);
    }

    private function assertPlotFileState(PlotList2025 $lockedPlot): void
    {
        $scope = 'entry-plot-file:'.$lockedPlot->getKey().':'.(string) $lockedPlot->plot;
        RecordStateGuard::assertToken(
            $this->loadedPlotFileToken,
            $scope,
            ['file_uploaded_at' => $this->loadedPlotFileUploadedAt],
            'plotFile',
            '樣區 PDF 載入狀態已變更，請重新選擇樣區後再上傳。'
        );

        if ((string) ($lockedPlot->getRawOriginal('file_uploaded_at') ?? '') !== $this->loadedPlotFileUploadedAt) {
            throw ValidationException::withMessages([
                'plotFile' => '樣區 PDF 已由其他使用者更新，請重新選擇樣區後再上傳。',
            ]);
        }
    }

    private function resetRecordVersions(): void
    {
        $this->envRecordVersions = [];
        $this->plantRecordVersions = [];
        $this->envVersionToken = '';
        $this->plantVersionToken = '';
    }

    private function resetPlotVersionState(): void
    {
        $this->loadedPlotCensusYear = '';
        $this->plotCensusYear = '';
        $this->loadedPlotYearToken = '';
        $this->loadedPlotFileUploadedAt = '';
        $this->loadedPlotFileToken = '';
    }

    private function recordVersionMap(Collection $records): array
    {
        return $records
            ->mapWithKeys(fn ($record) => [(string) $record->id => $this->recordVersion($record)])
            ->all();
    }

    private function setEnvironmentRecordVersions(Collection $records): void
    {
        $this->envRecordVersions = $this->recordVersionMap($records);
        $this->envVersionToken = $this->recordVersionToken('environment', $this->envRecordVersions);
    }

    private function setPlantRecordVersions(Collection $records): void
    {
        $this->plantRecordVersions = $this->recordVersionMap($records);
        $this->plantVersionToken = $this->recordVersionToken('plants', $this->plantRecordVersions);
    }

    private function recordVersionToken(string $kind, array $versions): string
    {
        ksort($versions, SORT_NATURAL);

        return RecordStateGuard::token(
            'entry:'.$kind.':'.(string) $this->thisSubPlot,
            ['versions' => $versions]
        );
    }

    private function assertRecordVersionToken(
        string $kind,
        array $versions,
        string $token,
        string $label,
        string $field = 'concurrentEdit'
    ): void
    {
        ksort($versions, SORT_NATURAL);
        RecordStateGuard::assertToken(
            $token,
            'entry:'.$kind.':'.(string) $this->thisSubPlot,
            ['versions' => $versions],
            $field,
            "{$label}載入狀態已變更，請重新載入後再修改。"
        );
    }

    private function assertRecordVersionState(
        string $kind,
        Collection $records,
        array $versions,
        string $token,
        string $label,
        string $field = 'concurrentEdit'
    ): void
    {
        $this->assertRecordVersionToken($kind, $versions, $token, $label, $field);
        $this->assertVersionSet($records, $versions, $label, $field);
    }

    private function plotFormYear(PlotList2025 $plotRow): string
    {
        $year = trim((string) ($plotRow->census_year ?? ''));
        if ($year !== '' && (int) $year >= 2025) {
            return $year;
        }

        return date('Y');
    }

    private function assertRecordVersion($model, array $versions, string $label, string $field = 'concurrentEdit'): void
    {
        $id = (string) $model->getKey();
        if (! array_key_exists($id, $versions) || $versions[$id] !== $this->recordVersion($model)) {
            throw ValidationException::withMessages([
                $field => "{$label}已由其他使用者更新，請重新載入後再修改。",
            ]);
        }
    }

    private function assertVersionSet(
        Collection $records,
        array $versions,
        string $label,
        string $field = 'concurrentEdit'
    ): void
    {
        $currentIds = $records->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all();
        $loadedIds = collect(array_keys($versions))->map(fn ($id) => (string) $id)->sort()->values()->all();

        if ($currentIds !== $loadedIds) {
            throw ValidationException::withMessages([
                $field => "{$label}筆數已由其他使用者變更，請重新載入後再修改。",
            ]);
        }

        foreach ($records as $record) {
            $this->assertRecordVersion($record, $versions, $label, $field);
        }
    }

    public function markDuplicateCovError(string $plotFullId)
    {
        $this->authorizeSubPlot($plotFullId);

        $this->recalculatePlantDataErrors($plotFullId);
    }

    private function recalculatePlantDataErrors(string $plotFullId): bool
    {
        $records = SubPlotPlant2025::where('plot_full_id', $plotFullId)
            ->get(['id', 'chname_index', 'spcode', 'coverage', 'data_error']);
        $nameCounts = $records
            ->pluck('chname_index')
            ->filter(fn ($name) => trim((string) $name) !== '')
            ->countBy();
        $spcodeCounts = $records
            ->pluck('spcode')
            ->filter(fn ($spcode) => trim((string) $spcode) !== '')
            ->countBy();
        $idsByError = [0 => [], 1 => [], 2 => []];
        $changed = false;

        foreach ($records as $record) {
            $duplicateName = trim((string) $record->chname_index) !== ''
                && ($nameCounts[$record->chname_index] ?? 0) > 1;
            $duplicateSpcode = trim((string) $record->spcode) !== ''
                && ($spcodeCounts[$record->spcode] ?? 0) > 1;
            $invalidCoverage = ! is_numeric($record->coverage)
                || (float) $record->coverage <= 0
                || (float) $record->coverage > 100;
            $error = ($duplicateName || $duplicateSpcode) ? 2 : ($invalidCoverage ? 1 : 0);
            if ((int) $record->data_error !== $error) {
                $idsByError[$error][] = $record->id;
                $changed = true;
            }
        }

        foreach ($idsByError as $error => $ids) {
            if ($ids !== []) {
                SubPlotPlant2025::whereIn('id', $ids)->update([
                    'data_error' => $error,
                    'updated_by' => $this->creatorCode,
                ]);
            }
        }

        return $changed;
    }

    public $photo;

    public function clickUploadPhoto()
    {
        $this->authorizeSubPlot((string) $this->thisSubPlot);
        $this->resetErrorBag();

        // 1) 驗證（20MB、限定常見圖片格式）
        $this->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|mimetypes:image/jpeg,image/png,image/webp|max:20480',
        ], [
            'photo.required' => '請先選擇檔案',
            'photo.image' => '檔案必須是圖片格式',
            'photo.mimes' => '只接受 JPG、PNG 或 WEBP',
            'photo.mimetypes' => '只接受 JPG、PNG 或 WEBP',
            'photo.max' => '檔案不可超過 20 MB',
        ]);

        $ext = UploadMime::imageExtension($this->photo->getMimeType());
        if ($ext === null) {
            $this->addError('photo', '無法辨識圖片格式，只接受 JPG、PNG 或 WEBP。');

            return;
        }

        $hab = substr($this->thisSubPlot, 6, 2);
        $basename = $this->thisSubPlot; // 不含副檔名

        $relativeDir = $this->photoRelativeDir($hab);
        $filename = "{$basename}.{$ext}";
        $targetPath = "{$relativeDir}/{$filename}";

        $disk = Storage::disk('invasi_files');
        $connection = DB::connection('invasiflora');
        $backups = [];
        $createdPaths = [];
        $tmpPaths = [];
        $updatedEnvironmentRecords = collect();

        try {
            $connection->beginTransaction();
            $lockedEnvRecords = SubPlotEnv2025::whereIn(
                'id',
                array_map('intval', array_keys($this->envRecordVersions))
            )
                ->lockForUpdate()
                ->get();
            $this->assertRecordVersionState(
                'environment',
                $lockedEnvRecords,
                $this->envRecordVersions,
                $this->envVersionToken,
                '小樣方環境資料',
                'photo'
            );
            $this->ensureDirectory($disk, $relativeDir);

            $tmpName = $filename.'.tmp_'.Str::random(8);
            $tmpPath = "{$relativeDir}/{$tmpName}";
            $tmpPaths[] = $tmpPath;
            if ($disk->putFileAs($relativeDir, $this->photo, $tmpName) === false) {
                throw new \RuntimeException('無法寫入照片暫存檔。');
            }

            $existingPaths = array_map(
                fn ($oldExt) => "{$relativeDir}/{$basename}.{$oldExt}",
                $this->photoExts
            );
            $mirrorPath = null;
            $mirrorSubPlot = null;

            if (HabitatCode::isWood($hab)) {
                $mirrorHab = HabitatCode::understoryFor($hab);
                $mirrorSubPlot = substr($basename, 0, 6).$mirrorHab.substr($basename, 8);
                $mirrorDir = $this->photoRelativeDir($mirrorHab);
                $mirrorPath = "{$mirrorDir}/{$mirrorSubPlot}.{$ext}";
                $this->ensureDirectory($disk, $mirrorDir);

                foreach ($this->photoExts as $oldExt) {
                    $existingPaths[] = "{$mirrorDir}/{$mirrorSubPlot}.{$oldExt}";
                }
            }

            $this->backupExistingFiles($disk, $existingPaths, Str::random(12), $backups);
            if (! $disk->move($tmpPath, $targetPath)) {
                throw new \RuntimeException('無法將照片暫存檔轉為正式檔。');
            }
            $createdPaths[] = $targetPath;
            $tmpPaths = [];

            if ($mirrorPath !== null) {
                if (! $disk->copy($targetPath, $mirrorPath)) {
                    throw new \RuntimeException('無法建立對應地被照片。');
                }
                $createdPaths[] = $mirrorPath;
            }

            $updated = SubPlotEnv2025::where('plot_full_id', $basename)->update([
                'file_uploaded_at' => now(),
                'file_uploaded_by' => $this->creatorCode,
            ]);
            if ($updated === 0) {
                throw new \RuntimeException("找不到小樣方資料：{$basename}");
            }

            if ($mirrorSubPlot !== null) {
                SubPlotEnv2025::where('plot_full_id', $mirrorSubPlot)->update([
                    'file_uploaded_at' => now(),
                    'file_uploaded_by' => $this->creatorCode,
                ]);
            }

            $updatedEnvironmentRecords = SubPlotEnv2025::whereIn(
                'plot_full_id',
                array_values(array_filter([$basename, $mirrorSubPlot]))
            )->get();

            $connection->commit();
        } catch (ValidationException $e) {
            $this->rollbackUploadAttempt($connection, $disk, $createdPaths, $backups, $tmpPaths);

            throw $e;
        } catch (Throwable $e) {
            $this->rollbackUploadAttempt($connection, $disk, $createdPaths, $backups, $tmpPaths);

            FixLog::create([
                'table_name' => 'upload_photo_error',
                'record_id' => 0,
                'changes' => $this->thisCounty.'_'.$this->thisPlot.'_'.$this->thisSubPlot.' Error: '.$e->getMessage(),
                'modified_by' => $this->creatorCode,
                'modified_at' => now(),
            ]);

            $this->addError('photo', '上傳失敗，請稍後再試或聯絡管理者。');

            return;
        }

        $this->deleteUploadBackups($disk, $backups);
        $this->setEnvironmentRecordVersions($updatedEnvironmentRecords);
        $this->loadPhotoInfo();
        session()->flash('photoUploadSuccess', '上傳成功！');
        $this->photo = null;
    }

    public $plotFile;

    protected $rules = [
        'plotFile' => 'required|file|mimes:pdf|mimetypes:application/pdf|max:20480',
    ];

    public function clickUploadFile()
    {
        $authorizedPlot = $this->authorizePlot();
        $this->resetErrorBag('plotFile');

        $rules = [
            'plotFile' => 'required|file|mimes:pdf|mimetypes:application/pdf|max:20480', // 20MB (= 20*1024 KB)
        ];
        $messages = [
            'plotFile.required' => '請先選擇檔案',
            'plotFile.file' => '檔案格式不正確',
            'plotFile.mimes' => '只接受 PDF 檔',
            'plotFile.mimetypes' => '只接受 PDF 檔',
            'plotFile.max' => '檔案不可超過 20 MB',
        ];
        // dd('test');
        // 1) 先做表單驗證（這一步的錯誤會自動進到 $errors）
        $this->validate($rules, $messages);

        if (! UploadMime::isPdf($this->plotFile->getMimeType())) {
            $this->addError('plotFile', '無法辨識 PDF 檔案格式。');

            return;
        }

        // 2) 準備路徑與檔名
        $filename = $this->thisPlot.'.pdf';
        $relativeDir = "plotData/{$this->thisCounty}";
        $targetPath = "{$relativeDir}/{$filename}";
        $disk = Storage::disk('invasi_files');
        $connection = DB::connection('invasiflora');
        $backups = [];
        $createdPaths = [];
        $tmpPaths = [];

        try {
            $connection->beginTransaction();
            $lockedPlot = PlotList2025::whereKey($authorizedPlot->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertPlotFileState($lockedPlot);
            $this->ensureDirectory($disk, $relativeDir);

            $tmpName = $this->thisPlot.'.tmp_'.Str::random(8).'.pdf';
            $tmpPath = "{$relativeDir}/{$tmpName}";
            $tmpPaths[] = $tmpPath;
            if ($disk->putFileAs($relativeDir, $this->plotFile, $tmpName) === false) {
                throw new \RuntimeException('無法寫入 PDF 暫存檔。');
            }

            $this->backupExistingFiles($disk, [$targetPath], Str::random(12), $backups);
            if (! $disk->move($tmpPath, $targetPath)) {
                throw new \RuntimeException('無法將 PDF 暫存檔轉為正式檔。');
            }
            $createdPaths[] = $targetPath;
            $tmpPaths = [];

            // 5) 寫入資料庫（成功寫檔後才更新）
            $lockedPlot->file_uploaded_at = now();
            $lockedPlot->file_uploaded_by = $this->creatorCode;
            $lockedPlot->save();

            $connection->commit();
        } catch (ValidationException $e) {
            $this->rollbackUploadAttempt($connection, $disk, $createdPaths, $backups, $tmpPaths);

            throw $e;
        } catch (Throwable $e) {
            $this->rollbackUploadAttempt($connection, $disk, $createdPaths, $backups, $tmpPaths);

            // 後台 log（方便追查）
            FixLog::create([
                'table_name' => 'upload_photo_error',
                'record_id' => $this->thisCounty.'_'.$this->thisPlot.'_'.$this->thisSubPlot,
                'changes' => 'Error: '.$e->getMessage(),
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

            return;
        }

        $this->deleteUploadBackups($disk, $backups);
        $this->setPlotFileVersion($lockedPlot);
        $this->loadFileInfo();
        session()->flash('fileUploadSuccess', '上傳成功！');
        $this->plotFile = null;
    }

    private function ensureDirectory(FilesystemAdapter $disk, string $directory): void
    {
        if (! $disk->exists($directory) && ! $disk->makeDirectory($directory)) {
            throw new \RuntimeException("無法建立檔案目錄：{$directory}");
        }
    }

    private function movePhotosForSubPlotRename(
        string $oldPlotFullId,
        string $newPlotFullId,
        string $oldHabitat,
        string $newHabitat
    ): array {
        $disk = Storage::disk('invasi_files');
        $moves = $this->photoMovePlan($oldPlotFullId, $newPlotFullId, $oldHabitat, $newHabitat);
        $copies = [];

        if (! HabitatCode::isWood($oldHabitat) && HabitatCode::isWood($newHabitat)) {
            $newUnderstoryHabitat = HabitatCode::understoryFor($newHabitat);
            $newUnderstoryId = substr($newPlotFullId, 0, 6)
                .$newUnderstoryHabitat
                .substr($newPlotFullId, 8);

            foreach ($moves as $move) {
                $extension = pathinfo($move['to'], PATHINFO_EXTENSION);
                $copies[] = [
                    'from' => $move['to'],
                    'to' => $this->subPlotPhotoPath($newUnderstoryId, $newUnderstoryHabitat, $extension),
                ];
            }
        }

        if (HabitatCode::isWood($oldHabitat) && HabitatCode::isWood($newHabitat)) {
            $oldUnderstoryHabitat = HabitatCode::understoryFor($oldHabitat);
            $newUnderstoryHabitat = HabitatCode::understoryFor($newHabitat);
            $oldUnderstoryId = substr($oldPlotFullId, 0, 6)
                .$oldUnderstoryHabitat
                .substr($oldPlotFullId, 8);
            $newUnderstoryId = substr($newPlotFullId, 0, 6)
                .$newUnderstoryHabitat
                .substr($newPlotFullId, 8);
            $moves = array_merge(
                $moves,
                $this->photoMovePlan(
                    $oldUnderstoryId,
                    $newUnderstoryId,
                    $oldUnderstoryHabitat,
                    $newUnderstoryHabitat
                )
            );
        }

        foreach (array_merge($moves, $copies) as $operation) {
            if ($operation['from'] !== $operation['to'] && $disk->exists($operation['to'])) {
                throw ValidationException::withMessages([
                    '小樣方流水號' => '新小樣方編號已有照片，為避免覆蓋檔案，請先確認資料後再試。',
                ]);
            }
        }

        $completed = [];
        try {
            foreach ($moves as $move) {
                $this->ensureDirectory($disk, dirname($move['to']));
                if (! $disk->move($move['from'], $move['to'])) {
                    throw new \RuntimeException('無法同步移動小樣方照片。');
                }
                $completed[] = ['type' => 'move'] + $move;
            }

            foreach ($copies as $copy) {
                $this->ensureDirectory($disk, dirname($copy['to']));
                if (! $disk->copy($copy['from'], $copy['to'])) {
                    throw new \RuntimeException('無法建立對應地被照片。');
                }
                $completed[] = ['type' => 'copy'] + $copy;
            }
        } catch (Throwable $e) {
            $this->rollbackPhotoOperations($completed);

            throw $e;
        }

        return $completed;
    }

    private function photoMovePlan(
        string $oldPlotFullId,
        string $newPlotFullId,
        string $oldHabitat,
        string $newHabitat
    ): array {
        $disk = Storage::disk('invasi_files');
        $moves = [];

        foreach ($this->photoExts as $extension) {
            $oldPath = $this->subPlotPhotoPath($oldPlotFullId, $oldHabitat, $extension);
            if ($disk->exists($oldPath)) {
                $moves[] = [
                    'from' => $oldPath,
                    'to' => $this->subPlotPhotoPath($newPlotFullId, $newHabitat, $extension),
                ];
            }
        }

        return $moves;
    }

    private function subPlotPhotoPath(string $plotFullId, string $habitat, string $extension): string
    {
        return "subPlotPhoto/{$this->thisCounty}/{$this->thisPlot}/{$habitat}/{$plotFullId}.{$extension}";
    }

    private function rollbackPhotoOperations(array $operations): void
    {
        $disk = Storage::disk('invasi_files');

        foreach (array_reverse($operations) as $operation) {
            if ($operation['type'] === 'copy') {
                if ($disk->exists($operation['to'])) {
                    $disk->delete($operation['to']);
                }

                continue;
            }

            if ($disk->exists($operation['to']) && ! $disk->exists($operation['from'])) {
                $this->ensureDirectory($disk, dirname($operation['from']));
                $disk->move($operation['to'], $operation['from']);
            }
        }
    }

    private function backupExistingFiles(
        FilesystemAdapter $disk,
        array $paths,
        string $token,
        array &$backups
    ): void
    {
        foreach (array_unique($paths) as $path) {
            if (! $disk->exists($path)) {
                continue;
            }

            $backupPath = "{$path}.bak_{$token}";
            if (! $disk->move($path, $backupPath)) {
                throw new \RuntimeException("無法備份既有檔案：{$path}");
            }
            $backups[$path] = $backupPath;
        }
    }

    private function restoreUploadFiles(
        FilesystemAdapter $disk,
        array $createdPaths,
        array $backups,
        array $tmpPaths
    ): void {
        foreach (array_unique(array_merge($createdPaths, $tmpPaths)) as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }

        foreach ($backups as $originalPath => $backupPath) {
            if (! $disk->exists($backupPath)) {
                continue;
            }
            if ($disk->exists($originalPath)) {
                $disk->delete($originalPath);
            }
            $disk->move($backupPath, $originalPath);
        }
    }

    private function rollbackUploadAttempt(
        Connection $connection,
        FilesystemAdapter $disk,
        array $createdPaths,
        array $backups,
        array $tmpPaths
    ): void {
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
        $this->restoreUploadFiles($disk, $createdPaths, $backups, $tmpPaths);
    }

    private function deleteUploadBackups(FilesystemAdapter $disk, array $backups): void
    {
        foreach ($backups as $backupPath) {
            if ($disk->exists($backupPath)) {
                $disk->delete($backupPath);
            }
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

    private function refreshActor(User $user): void
    {
        $this->user = $user;
        $this->userOrg = (string) ($user->organization ?? '');
        $this->creatorCode = explode('@', (string) $user->email)[0];
    }

    private function accessiblePlotQuery(?User $user = null)
    {
        $user ??= Auth::user();
        abort_unless($user, 403);
        $this->refreshActor($user);

        return PlotList2025::query()
            ->when(
                $user->role !== 'admin',
                fn ($query) => $query->where('team', (string) $user->organization)
            );
    }

    private function authorizePlot(?string $plot = null): PlotList2025
    {
        $plot = $plot ?? (string) $this->thisPlot;
        abort_if($plot === '', 403);

        $plotRow = $this->accessiblePlotQuery()
            ->where('plot', $plot)
            ->orderByDesc('census_year')
            ->first();
        abort_unless($plotRow, 403);

        $this->thisPlot = (string) $plotRow->plot;
        $this->thisCounty = (string) $plotRow->county;

        return $plotRow;
    }

    private function authorizeSubPlot(string $plotFullId, bool $onlyTrashed = false): SubPlotEnv2025
    {
        $plotRow = $this->authorizePlot();
        abort_if($plotFullId === '', 403);

        $query = $onlyTrashed
            ? SubPlotEnv2025::onlyTrashed()
            : SubPlotEnv2025::query();
        $subPlot = $query
            ->where('plot_full_id', $plotFullId)
            ->where('plot', (string) $plotRow->plot)
            ->first();
        abort_unless($subPlot, 403);

        return $subPlot;
    }

    public function render()
    {
        return view('livewire.entry-entry');
    }
}
