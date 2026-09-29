<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\PlotList2025;
use App\Models\SubPlotEnv2025;
use App\Helpers\PlantListHelper;
use App\Helpers\PlotHelper;
use App\Helpers\HabHelper;
use Illuminate\Validation\ValidationException;

class QueryPlot extends Component
{

    public $countyList = [];
    public $thisCounty;
    public $plotList = [];
    public $thisPlot;


    public $subPlotList = [];
    public $thisSubPlot;
    public $allPlotInfo = [];
    public $showAllPlot = true;
    public $thisListType = '';
    public $thisSpcode = '';

    public function mount()
    {
        $this->countyList = PlotList2025::select('county')->distinct()->pluck('county')->toArray();

        if (session()->has('query.county')) {
            $county  = session()->pull('query.county');
            $plot    = session()->pull('query.plot');
            $subPlot = session()->pull('query.subPlot');
            $habitat = session()->pull('query.habitat');
            $this->thisSpcode = session()->pull('query.spcode', '');
            $this->fromOverview($county, $plot, $subPlot, $habitat);
        }

    }

    public function loadPlots($county)
    {
        $county = trim((string) $county);
        if ($county !== '' && ! PlotList2025::where('county', $county)->exists()) {
            throw ValidationException::withMessages([
                'thisCounty' => '縣市選項不存在，請重新選擇。',
            ]);
        }

        $this->thisCounty = $county;
        $this->plotList = [];
        $this->plotList = PlotList2025::where('county', $county)
            ->select('plot')->distinct()->pluck('plot')->toArray();
        $this->thisHabType = '';
        $this->thisPlot = '';
        $this->thisListType = '';
        $this->habTypeOptions = [];
        $this->plotplantList = [];
        $this->subPlotEnvForm = [];
        $this->dispatch('thisPlotUpdated');
        $this->dispatch('thisHabTypeUpdated');
        $this->dispatch('thisSubPlotUpdated');
    }
    public $plotplantList = [];
    public $plotplantListAll = [];
    public $habTypeOptions;


    // 樣區內所有生育地類型與全部植物名錄
    public function loadPlotInfo($plot)
    {
        $plot = trim((string) $plot);
        if ($plot === '') {
            $this->resetPlotSelection();

            return;
        }

        $plotExists = PlotList2025::where('county', (string) $this->thisCounty)
            ->where('plot', $plot)
            ->exists();
        if (! $plotExists) {
            throw ValidationException::withMessages([
                'thisPlot' => '樣區不屬於目前選取的縣市，請重新選擇。',
            ]);
        }

        $this->thisPlot = $plot;
        $this->thisHabType = '';
        $this->thisListType = $plot;
        $this->habTypeOptions = [];
        // $this->thisSubPlot = ''; // 清空樣區ID
        // $this->subPlotList = []; // 清空樣區ID
        // $this->thisSubPlotInfo = []; // 清空樣區ID資料
        // $this->plotInfo2025 = []; // 清空樣區資料

        //取得生育地類型列表、小樣區清單
        $data = PlotHelper::getSubPlotInfo($plot);
        $this->habTypeOptions = $data['habTypeOptions'];
        $this->subPlotList = $data['subPlotList'];
        $this->subPlotEnvForm =[];
        // dd($plotPlant2025);

        // $this->dispatch('plotIDUpdated', plotID: '');

        $this->plotplantList = PlantListHelper::getMergedPlotPlantList($this->thisPlot);
        // dd( $this->plotplantList);
        $this->plotplantListAll =  $this->plotplantList;

        $this->dispatch('thisHabTypeUpdated');
        $this->dispatch('thisSubPlotUpdated');
        $this->dispatch('plantListLoaded');
    }

    public $thisSubPlotPlant2010 = [];
    public $thisSubPlotPlant2025 = [];
    public $thisHabType;

    //單一個生育地資料
    public function loadPlotHab($habType)
    {
        $this->assertCurrentPlot();
        $habType = trim((string) $habType);
        $options = PlotHelper::getSubPlotInfo((string) $this->thisPlot)['habTypeOptions'];

        if ($habType === '') {
            // 顯示全部樣區
            $this->thisHabType = '';
            $this->subPlotEnvForm = [];
            $this->plotplantList = $this->plotplantListAll;
            $this->thisListType = $this->thisPlot;
            return;
        }

        if (! array_key_exists($habType, $options)) {
            throw ValidationException::withMessages([
                'thisHabType' => '生育地類型不屬於目前樣區，請重新選擇。',
            ]);
        }
        $this->thisHabType = $habType; // 讓下拉選單同步更新
        $this->habTypeOptions = $options;

        // $this->dispatch('plotIDUpdated', plotID: '');
        // dd($plotPlant2025);
        $this->plotplantList = PlantListHelper::getMergedPlotPlantList($this->thisPlot, [
            'hab_type' => $habType
        ]);

        // $this->plotplantListAll =  $this->plotplantList;
        // $this->dispatch('SubPlotIDUpdated', subPlotID: $subPlot);
        // dd($habType);
        $this->thisListType = $this->thisPlot . ' ' . $options[$habType];
        $this->subPlotEnvForm = [];

        $this->dispatch('thisSubPlotUpdated');
        $this->dispatch('plantListLoaded');
    }

    public $subPlotEnvForm=[];

    public function loadSubPlot($thisSubPlot)
    {
        $this->assertCurrentPlot();
        $thisSubPlot = trim((string) $thisSubPlot);

        if ($thisSubPlot === '') {
            // 顯示全部樣區
            $this->thisSubPlot = '';
            $this->thisListType = $this->thisPlot;
            $this->subPlotEnvForm = [];
            $this->plotplantList = $this->plotplantListAll;
            return;
        }

        $availableSubPlots = PlotHelper::getSubPlotInfo((string) $this->thisPlot)['subPlotList'];
        if (! in_array($thisSubPlot, $availableSubPlots, true)) {
            throw ValidationException::withMessages([
                'thisSubPlot' => '小樣方不屬於目前樣區，請重新選擇。',
            ]);
        }
        $this->thisSubPlot = $thisSubPlot; // 讓下拉選單同步更新

        $this->thisHabType = ''; // 讓下拉選單同步更新
        //  dd($thisSubPlot);
        $habType = substr($thisSubPlot, 6, 2);   // 第 7,8 位（index 從 0 開始）
        $sub_id  = substr($thisSubPlot, -2);     // 最後兩位

        $data = SubPlotEnv2025::where('plot', (string) $this->thisPlot)
            ->where('plot_full_id', $thisSubPlot)
            ->first();

        if ($data) {
            $hab[] = $habType;
            $habTypeOptions = HabHelper::habitatOptions($hab);
            // dd($habTypeOptions);
            $data->hab_type = $habTypeOptions[$habType] ?? '未知';
            $subPlotAreaMap = config('item_list.sub_plot_area');
            $data->subplot_area_data = $subPlotAreaMap[$data->subplot_area] ?? $data->subplot_area;

            $this->subPlotEnvForm = $data->toArray(); // 有資料：預填入表單
        } else {
            $this->subPlotEnvForm = [];
        }

        $this->plotplantList = PlantListHelper::getMergedPlotPlantList($this->thisPlot, [
            'hab_type' => $habType,
            'sub_id' => $sub_id,
            'sub_plot' => $thisSubPlot, // 給 2025 用
        ]);
        // dd($this->plotplantList);
        // $this->plotplantListAll =  $this->plotplantList;
        // $this->dispatch('SubPlotIDUpdated', subPlotID: $subPlot);
        $this->thisListType = $thisSubPlot;

        $this->dispatch('thisHabTypeUpdated');
        $this->dispatch('plantListLoaded');
    }

    public function fromOverview($county, $plot, $subPlot, $habitat)
    {
        $this->thisCounty = $county;
        $this->loadPlots($county);
        $this->loadPlotInfo($plot);
        if (filled($subPlot)) {
            $this->loadSubPlot($subPlot);
        }

        if (filled($habitat)) {
            $this->loadPlotHab($habitat);
        }

    }

    public $sortField = 'cov_2025';
    public $sortDirection = 'asc';

    public function sortBy($field)
    {
        $field = (string) $field;
        $allowedFields = [
            'chfamily', 'chname', 'nat_type',
            'plot2010', 'sub2010', 'cov2010',
            'plot2025', 'sub2025', 'cov2025',
        ];
        if (! in_array($field, $allowedFields, true)) {
            throw ValidationException::withMessages([
                'sortField' => '不支援此排序欄位。',
            ]);
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $sortKey = match ($this->sortField) {
            'cov2010' => 'cov2010_sort',
            'cov2025' => 'cov2025_sort',
            default => $this->sortField,
        };
        $this->plotplantList = collect($this->plotplantList)
            ->sortBy($sortKey, SORT_REGULAR, $this->sortDirection === 'desc')
            ->values()
            ->toArray();
    }

    private function assertCurrentPlot(): void
    {
        $exists = filled($this->thisCounty)
            && filled($this->thisPlot)
            && PlotList2025::where('county', (string) $this->thisCounty)
                ->where('plot', (string) $this->thisPlot)
                ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'thisPlot' => '目前樣區選項已失效，請重新選擇縣市與樣區。',
            ]);
        }
    }

    private function resetPlotSelection(): void
    {
        $this->thisPlot = '';
        $this->thisSubPlot = '';
        $this->thisHabType = '';
        $this->thisListType = '';
        $this->habTypeOptions = [];
        $this->subPlotList = [];
        $this->plotplantList = [];
        $this->plotplantListAll = [];
        $this->subPlotEnvForm = [];
    }
    public $downloadFormat = 'csv'; // 預設為 .csv

    public function render()
    {
        return view('livewire.query-plot');
    }
}
