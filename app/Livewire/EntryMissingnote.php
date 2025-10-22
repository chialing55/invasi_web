<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\PlotList2025;
use App\Models\SubPlotEnv2025;
use App\Models\SubPlotMissing;
use App\Models\Reasons;
use App\Models\FixLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use App\Services\DataSyncService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class EntryMissingnote extends Component
{
    public $userOrg;
    public $user;
    public $creatorCode;
    public $countyList=[];
    public $thisCounty;
    public $plotList=[];
    public $subPlotList=[];
    public $noSubplotData = false;
    public $thisPlot;
    public $reasonForm = [];

    public $plotInfo = [];   
    public $thisCensusYear;
    public $censusYearList=[];
    public $reasonOptions=[];

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
        $currentYear = (int)date('Y');

        if ($user->role == 'member') {
            $this->countyList = PlotList2025::select('county')
                ->where('census_year', $currentYear)
                ->where('team', $this->userOrg)
                ->distinct()
                ->pluck('county')
                ->toArray(); 
        } else {
            $this->countyList = PlotList2025::select('county')
                ->where('census_year', $currentYear)
                ->distinct()
                ->pluck('county')
                ->toArray();
        }

        $this->censusYearList = PlotList2025::where('census_year', '>=', 2025)
            ->distinct()
            ->orderByDesc('census_year')
            ->pluck('census_year')
            ->toArray(); 

        $this->thisPlot = '';
        $this->thisCensusYear = date('Y');
        $this->reasonOptions = $this->reasonOptions();
        $this->noMissingSubplotData = false;
    }

    public function loadPlots($county)
    {

        $this->plotList = PlotList2025::where('county', $county)
            ->select('plot')->distinct()->pluck('plot')->toArray();
        $this->thisPlot = '';
        $this->dispatch('reset_missingSubPlot_table');
        $this->dispatch('thisPlotUpdated');
        $this->noMissingSubplotData = false;

       
    }
    public function loadPlotInfo($plot)
    {
        // $this->dispatch('reset_habitat');
        $this->thisPlot = $plot;
        $this->dispatch('reset_missingSubPlot_table');
        $this->noMissingSubplotData = false;

        // 取得樣區資料
        $plotInfo = SubPlotMissing::where('plot', $plot)->orderBy('plot_full_id_2010')->get();
        if ($plotInfo->isEmpty()) {
            $this->addPlotInfo($plot);
        } else {
            $this->plotInfo = $plotInfo->toArray();
            // dd($this->plotInfo);
            $this->dispatch('missingSubPlot_table', data: [
                'data' => $this->plotInfo,
                'thisPlot' =>$this->thisPlot,
            ]);
        }
// dd($this->selectedHabitatCodes);
    }

    public function reasonOptions(): array
    {
        $rows = Reasons::query()
            ->orderBy('level')
            ->orderBy('code')
            ->get();

        $byCode = $rows->keyBy('code');
        

        $buildLabel = function ($code) use ($byCode) {
            $chain = [];
            $cur = $byCode[$code] ?? null;
            while ($cur) {
                array_unshift($chain, $cur->title); // 往前塞，最後變成「父 / 子 / 孫」
                if (empty($cur->parent_code)) break;
                $cur = $byCode[$cur->parent_code] ?? null;
            }
            return implode(' / ', $chain);  // 例：「地被覆蓋度低 / 完全沒有地被」
        };

        // dd($buildLabel);

        $rows = Reasons::where('active', 1)
            ->orderBy('id')
            ->get();

        // 轉成 { code => label } 的 map
        $map = [];

        foreach ($rows as $r) {
            $label = '['.$r->code.'] '.$buildLabel($r->code);
            // dd($label);
            $map[] = ['value' => $r->code, 'label' => $label];
        }
        // dd($map);
        return $map;
    }

    public $noMissingSubplotData = false;

    public function addPlotInfo($plot)
    {
        $payload = $this->comparePlotInfo($plot);

        if (!empty($payload)) {
            DB::transaction(function () use ($payload) {
                SubPlotMissing::insert($payload);
            });
        }

        $plotInfo = SubPlotMissing::where('plot', $plot)->orderBy('plot_full_id_2010')->get();

        if($plotInfo->isEmpty()){
            
            $this->noMissingSubplotData = true;
        } else {
            $this->plotInfo = $plotInfo->toArray();
            $this->noMissingSubplotData = false;
            $this->dispatch('missingSubPlot_table', data: [
                'data' => $this->plotInfo,
                'thisPlot' =>$this->thisPlot,
            ]);
        }

             

    }


    public function reCheckPlotInfo($plot)
    {
        $payload = $this->comparePlotInfo($plot);
// dd($payload);
        // 2) 寫入（避免覆蓋既有資料）
    // 既有 & 新計算的鍵集合
        $existingKeys = SubPlotMissing::on('invasiflora')
            ->where('plot', $plot)
            ->pluck('plot_full_id_2010')
            ->all();

        $newKeys = array_column($payload, 'plot_full_id_2010');

        // 準備差集
        $keysToDelete = array_values(array_diff($existingKeys, $newKeys));
        $rowsToInsert = array_values(array_filter($payload, function ($r) use ($existingKeys) {
            return !in_array($r['plot_full_id_2010'], $existingKeys, true);
        }));

        // 交易：先刪後增
        DB::connection('invasiflora')->transaction(function () use ($plot, $keysToDelete, $rowsToInsert) {
            if (!empty($keysToDelete)) {
                SubPlotMissing::on('invasiflora')
                    ->where('plot', $plot)
                    ->whereIn('plot_full_id_2010', $keysToDelete)
                    ->delete();
            }
            if (!empty($rowsToInsert)) {
                SubPlotMissing::on('invasiflora')->insert($rowsToInsert);
            }
        });

        // 訊息
        $added   = count($rowsToInsert);
        $removed = count($keysToDelete);
        $msg = ($added === 0 && $removed === 0)
            ? '無需更新：清單一致。'
            : "更新完成：新增 {$added} 筆、刪除 {$removed} 筆。";

        // 重新載入 & 回傳前端

        $plotInfo = SubPlotMissing::where('plot', $plot)->orderBy('plot_full_id_2010')->get();

        if($plotInfo->isEmpty()){
            
            $this->noMissingSubplotData = true;
        } else {
            $this->plotInfo = $plotInfo->toArray();
            $this->noMissingSubplotData = false;
            $this->dispatch('missingSubPlot_table', data: [
                'data' => $this->plotInfo,
                'thisPlot' =>$this->thisPlot,
            ]);
        }

        session()->flash('missingnote_sync', $msg);  
    }
    
    public function comparePlotInfo($plot)
    {
        $kExpr = "CONCAT(t.PLOT_ID, t.HAB_TYPE, LPAD(t.SUB_ID, 2, '0'))";

        // 2025 的所有樣區編號（去重）
        $s2025 = DB::connection('invasiflora')->table('im_splotdata_2025 as s')
            ->selectRaw('DISTINCT s.plot_full_id AS k_2025');
            // ->whereNotIn('s.habitat_code', [88, 99]); // 若需排除 88/99，取消註解

        // 2010 該 plot 的組合 k，去重；過濾掉 2025 已存在的
        $rows = DB::connection('invasiflora')->table('im_splotdata_2010 as t')
            ->where('t.PLOT_ID', (int) $plot)
            ->selectRaw("DISTINCT t.PLOT_ID AS plot, t.HAB_TYPE, t.SUB_ID, {$kExpr} AS k")
            ->leftJoinSub($s2025, 's25', function ($join) use ($kExpr) {
                $join->on('s25.k_2025', '=', DB::raw($kExpr));
            })
            ->whereNull('s25.k_2025')
            ->orderBy('t.HAB_TYPE')
            ->orderBy('t.SUB_ID')
            ->get();

        // 只保留需要的四個欄位
        $plotInfo = $rows->map(fn($r) => [
            'county'            => $this->thisCounty,  
            'plot'              => (int) $r->plot,
            'plot_full_id_2010' => (string) $r->k,
            'not_done_reason_code'   => '',
            'description'       => '',
        ])->values()->all();        // 可以在這裡處理屬性更新後的邏輯

        $alterMap = $this->alterPlotID($plot);        // 例如：['8010010102' => '8010010201', ...]
        // 若 $alterMap 是 Collection，先轉陣列
        $alterMap = is_array($alterMap) ? $alterMap : ($alterMap?->all() ?? []);

        $payload = collect($plotInfo)->map(function ($r) use ($plot, $alterMap) {
            $k2010 = (string)($r['plot_full_id_2010'] ?? '');
            $hit   = isset($alterMap[$k2010]);

            // 預設用原本資料
            $desc   = (string)($r['description'] ?? '');
            $reason = (string)($r['not_done_reason_code'] ?? '');

            if ($hit) {
                $target = (string)$alterMap[$k2010];          // 目標 plot_full_id
                // 取第 7–8 位（1-based）；PHP substr 0-based → 從 6 開始取 2 碼
                $hab78  = (strlen($target) >= 8) ? substr($target, 6, 2) : null;
                // 88/89 → description = '1'，否則 = 目標 ID
                $reason  = in_array($hab78, ['88','99'], true) ? '1' : '5-2-1';
                $desc = $target;
            }

            return [
                'county'             => (string)($r['county'] ?? ''),
                'plot'               => (int)$plot,
                'plot_full_id_2010'  => $k2010,
                'not_done_reason_code'    => $reason,
                'description'        => $desc,
                'created_at'         => now(),
                'created_by'         => $this->creatorCode,
                'updated_at'         => now(),
                'updated_by'         => '',
            ];
        })->all();

        return $payload;
    }

    public function alterPlotID($plot)
    {
        $rows = SubPlotEnv2025::where('plot', $plot)
            ->whereNotNull('original_plot_id')
            ->where('original_plot_id', '!=', '')
            ->get(['original_plot_id','plot_full_id']);

        $map = $rows->isNotEmpty()
            ? $rows->mapWithKeys(fn($r) => [
                (string)$plot . (string)$r->original_plot_id => (string)$r->plot_full_id
            ])->all()
            : [];
        return $map;
    }   

    public function missingReasonSave(){
        $newData = $this->reasonForm;
        $originalData = $this->plotInfo;
        $columns = Schema::connection('invasiflora')->getColumnListing('sub_plot_missing');

        $columns = array_diff($columns, [
            'created_by', 'updated_by', 'created_at', 'updated_at'
        ]);

        $columns = array_values($columns); // 儲存欄位順序
        $changed = DataSyncService::syncById(
            modelClass: SubPlotMissing::class,
            originalData: $originalData,
            newData: $newData,
            fields: $columns,
            createExtra: ['created_by' => $this->creatorCode],
            updateExtra: ['updated_by' => $this->creatorCode],
            requiredFields: [],
            userCode: $this->creatorCode
        );        
        session()->flash('plotSaveMessage', $changed ? '資料已更新' : '無任何變更'); 

        $this->plotInfo = SubPlotMissing::where('plot', $this->thisPlot)->orderBy('plot_full_id_2010')->get()->toArray();

        $this->dispatch('missingSubPlot_table', data: [
            'data' => $this->plotInfo,
            'thisPlot' =>$this->thisPlot,
        ]);         
    }


    public function render()
    {
        return view('livewire.entry-missingnote');
    }
}
