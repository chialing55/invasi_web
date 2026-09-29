<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\SubPlotPlant2010;
use App\Models\SubPlotPlant2025;
use App\Models\PlotList2025;
use App\Models\TaiwanChecklist;
use App\Models\HabitatInfo;
use App\Models\SpcodeIndex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Helpers\PlantStatHelper;
use App\Helpers\PlantSearchHelper;
use App\Support\PlantStatusHelper;
use App\Support\RecordStateGuard;
use App\Support\ScientificNameHelper;
use App\Models\FixLog;
class QueryPlant extends Component
{

    public $search;
    public $plantName = 'test';
    public $plantCode = null;

    public $test;

    public $suggestions = [];
    public string $searchMessage = '';
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
            $this->searchMessage = '';
            return;
        }

        $this->suggestions = PlantSearchHelper::plantNameSearchHelper($value);
        $this->searchMessage = $this->suggestions === []
            ? "查無符合「{$value}」的植物名稱，請嘗試其他中名、學名、別名或科名。"
            : '';

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
    public string $chnameIndexStateToken = '';


    public bool $showTable = false;
    public function toggle()
    {
        $this->showTable = !$this->showTable;
        if ($this->showTable) {
            $this->dispatchIndex($this->plantCode);
        } 
        
    }    

    public $countyList = [];
    public $allCountyList = [];
    public $habList = [];
    public $allHabList = [];
    public $thisCounty = '';
    public $thisHabType = '';
    public $filteredComparisonTable = [];

    public function plantInfo($value)
    {
        
// 植物資訊
        $this->countyList=[];
        $this->allCountyList=[];
        $this->habList=[];
        $this->allHabList=[];
        $this->thisCounty = '';
        $this->thisHabType = '';
 
        $plant = TaiwanChecklist::where('spcode', $value)->first()
            ?? TaiwanChecklist::where('spcode_current', $value)->first();

        if (!$plant) {
            $this->spnameInfo = [];
            $this->suggestions = [];
            $this->searchMessage = '查無此植物資料，請重新輸入植物名稱。';
            return;
        }

        $currentSpcode = trim((string) ($plant->spcode_current ?: $plant->spcode));
        $plant = TaiwanChecklist::where('spcode', $currentSpcode)->first() ?: $plant;
        $relatedSpcodes = TaiwanChecklist::where('spcode_current', $currentSpcode)
            ->pluck('spcode')
            ->push($currentSpcode)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $flags = PlantStatusHelper::flags($plant->origin_status, $plant->is_endemic);
        $this->spnameInfo = array_merge($plant->toArray(), [
            'latinname' => $plant->full_name,
            'simname' => $plant->canonical_name,
            'endemic' => $flags['endemic'],
            'native' => $flags['native'],
            'naturalized' => $flags['naturalized'],
            'cultivated' => $flags['cultivated'],
            'uncertain' => $flags['uncertain'],
            'origin_status_label' => PlantStatusHelper::label($plant->origin_status),
            'status_labels' => PlantStatusHelper::labels($plant->origin_status, $plant->is_endemic),
            'scientific_name_html' => ScientificNameHelper::italicize($plant->full_name, $plant->canonical_name),
        ]);
        $this->plantName = $this->spnameInfo['chname'];
        $this->suggestions = []; // 清空建議即可
        $this->searchMessage = '';
        
        $this->comparisonTable = PlantStatHelper::summarizeByCountyAndHabitat($relatedSpcodes);
        // dd($this->comparisonTable);
        $this->plantCode = $currentSpcode;
        $this->showTable = true;

        // 取得所有縣市
        $this->allCountyList = collect($this->comparisonTable)
            ->pluck('county')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
        $this->countyList = $this->allCountyList;

        $this->allHabList = collect($this->comparisonTable)
            ->filter(fn ($row) => filled($row['hab_code'] ?? null))
            ->unique('hab_code')
            ->sortBy('hab_code')
            ->mapWithKeys(fn ($row) => [(string) $row['hab_code'] => $row['habitat']])
            ->all();
        $this->habList = $this->allHabList;

        $this->filteredComparisonTable = $this->comparisonTable; // 初始化為全部資料


// dd($this->comparisonTable);
        $this->dispatch('plant-name-selected');        
        // dd($this->chnameIndex);
        $this->searchChnameIndex($value);

        $this->showTable = false;

    }    


    public function reloadPlantInfoCounty($thisCounty)
    {
        $this->thisCounty = in_array($thisCounty, $this->countyList, true) ? $thisCounty : '';
        $this->refreshFilterOptions();
        $this->applyComparisonFilters();
    }

    public function reloadPlantInfoHab($thisHabType)
    {
        $this->thisHabType = array_key_exists((string) $thisHabType, $this->habList)
            ? (string) $thisHabType
            : '';
        $this->refreshFilterOptions();
        $this->applyComparisonFilters();
    }

    private function applyComparisonFilters(): void
    {
        $this->filteredComparisonTable = collect($this->comparisonTable)
            ->when($this->thisCounty !== '', fn ($rows) => $rows->where('county', $this->thisCounty))
            ->when($this->thisHabType !== '', fn ($rows) => $rows->where('hab_code', $this->thisHabType))
            ->values()
            ->all();
    }

    private function refreshFilterOptions(): void
    {
        $this->countyList = collect($this->comparisonTable)
            ->when($this->thisHabType !== '', fn ($rows) => $rows->where('hab_code', $this->thisHabType))
            ->pluck('county')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($this->thisCounty !== '' && ! in_array($this->thisCounty, $this->countyList, true)) {
            $this->thisCounty = '';
        }

        $this->habList = collect($this->comparisonTable)
            ->when($this->thisCounty !== '', fn ($rows) => $rows->where('county', $this->thisCounty))
            ->filter(fn ($row) => filled($row['hab_code'] ?? null))
            ->unique('hab_code')
            ->sortBy('hab_code')
            ->mapWithKeys(fn ($row) => [(string) $row['hab_code'] => $row['habitat']])
            ->all();

        if ($this->thisHabType !== '' && ! array_key_exists($this->thisHabType, $this->habList)) {
            $this->thisHabType = '';
        }
    }

    public function searchChnameIndex($value)
    {
// chnameIndex
// dd($value);
        $this->loadChnameIndexRows((string) $value);

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
        abort_unless($user, 403);
        $creatorCode = explode('@', $user->email)[0]; // 取出 email 前綴
        $spcode = trim((string) $this->plantCode);
        abort_if($spcode === '', 404);

        $plantExists = TaiwanChecklist::where('spcode', $spcode)
            ->where('spcode_status', 'active')
            ->exists();
        abort_unless($plantExists, 404);

        $rows = collect($this->chnameIndex)
            ->map(function ($row) use ($spcode) {
                if (! is_array($row)) {
                    throw ValidationException::withMessages([
                        'chnameIndex' => '中文別名資料格式不正確，請重新載入後再試。',
                    ]);
                }

                return [
                    'id' => ($row['id'] ?? '') === '' ? null : $row['id'],
                    'spcode' => $spcode,
                    'chname_index' => trim((string) ($row['chname_index'] ?? '')),
                    'note' => trim((string) ($row['note'] ?? '')),
                ];
            })
            ->filter(fn ($row) => $row['id'] !== null || $row['chname_index'] !== '')
            ->values()
            ->all();

        Validator::make(['rows' => $rows], [
            'rows' => ['array', 'max:100'],
            'rows.*.id' => ['nullable', 'integer', 'distinct'],
            'rows.*.spcode' => ['required', 'string', 'in:'.$spcode],
            'rows.*.chname_index' => ['required', 'string', 'max:100', 'distinct:strict'],
            'rows.*.note' => ['nullable', 'string', 'max:500'],
        ], [
            'rows.max' => '單一植物最多可維護 100 筆中文別名。',
            'rows.*.id.integer' => '中文別名資料列編號不正確，請重新載入後再試。',
            'rows.*.id.distinct' => '中文別名資料列重複，請重新載入後再試。',
            'rows.*.chname_index.required' => '中文別名不可空白。',
            'rows.*.chname_index.max' => '中文別名不可超過 100 個字元。',
            'rows.*.chname_index.distinct' => '同一植物不可輸入重複的中文別名。',
            'rows.*.note.max' => '備註不可超過 500 個字元。',
        ])->validate();

        $changed = DB::connection('invasiflora')->transaction(function () use ($rows, $spcode, $creatorCode) {
            $current = SpcodeIndex::where('spcode', $spcode)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $state = RecordStateGuard::snapshot($current, ['spcode', 'chname_index', 'note']);
            RecordStateGuard::assertToken(
                $this->chnameIndexStateToken,
                $this->chnameIndexScope($spcode),
                $state,
                'chnameIndex',
                '中文別名已由其他分頁更新，請重新載入植物後再儲存。'
            );

            $currentById = $current->keyBy(fn ($record) => (string) $record->getKey());
            $submittedIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (string) $id);
            if ($submittedIds->diff($currentById->keys())->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'chnameIndex' => '送出的中文別名資料列不屬於目前植物，請重新載入後再試。',
                ]);
            }

            $changed = false;
            foreach ($current as $record) {
                if (! $submittedIds->contains((string) $record->getKey())) {
                    $this->logChnameIndexChange($record, [
                        '_deleted' => [
                            'spcode' => $record->spcode,
                            'chname_index' => $record->chname_index,
                            'note' => $record->note,
                        ],
                    ], $creatorCode);
                    $record->delete();
                    $changed = true;
                }
            }

            foreach ($rows as $row) {
                if ($row['id'] === null) {
                    $record = SpcodeIndex::create([
                        'spcode' => $spcode,
                        'chname_index' => $row['chname_index'],
                        'note' => $row['note'],
                        'created_by' => $creatorCode,
                    ]);
                    $this->logChnameIndexChange($record, [
                        '_created' => [
                            'spcode' => $spcode,
                            'chname_index' => $row['chname_index'],
                            'note' => $row['note'],
                        ],
                    ], $creatorCode);
                    $changed = true;
                    continue;
                }

                $record = $currentById->get((string) $row['id']);
                $newData = [
                    'chname_index' => $row['chname_index'],
                    'note' => $row['note'],
                ];
                $changes = collect($newData)
                    ->filter(fn ($value, $field) => (string) $record->{$field} !== (string) $value)
                    ->map(fn ($value, $field) => ['old' => $record->{$field}, 'new' => $value])
                    ->all();
                if ($changes !== []) {
                    $this->logChnameIndexChange($record, $changes, $creatorCode);
                    $record->update($newData + ['updated_by' => $creatorCode]);
                    $changed = true;
                }
            }

            return $changed;
        });

        $this->loadChnameIndexRows($spcode);

        if ($changed) {
            session()->flash('chIndexMessage', '中文別名已更新！');
        
            $this->dispatch('sync-complete', data: [
                'data' => $this->chnameIndex,
                'spcode' => $this->plantCode,
            ]); 
        }
        
 
    }

    private function loadChnameIndexRows(string $spcode): void
    {
        $records = SpcodeIndex::where('spcode', $spcode)->orderBy('id')->get();
        $this->chnameIndexStateToken = RecordStateGuard::token(
            $this->chnameIndexScope($spcode),
            RecordStateGuard::snapshot($records, ['spcode', 'chname_index', 'note'])
        );
        $rows = $records->map(fn ($row) => [
            'spcode' => $row->spcode,
            'chname_index' => $row->chname_index,
            'note' => $row->note ?? '',
            'id' => $row->id,
        ])->all();
        $this->chnameIndex = array_merge($rows, array_fill(0, 2, [
            'spcode' => $spcode,
            'chname_index' => '',
            'note' => '',
            'id' => '',
        ]));
    }

    private function chnameIndexScope(string $spcode): string
    {
        return 'query-plant:chname-index:'.$spcode;
    }

    private function logChnameIndexChange(SpcodeIndex $record, array $changes, string $creatorCode): void
    {
        FixLog::create([
            'table_name' => $record->getTable(),
            'record_id' => $record->getKey(),
            'changes' => $changes,
            'modified_by' => $creatorCode,
            'modified_at' => now(),
        ]);
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
