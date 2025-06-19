{{-- livewire/entry-entry.blade.php --}}
<div>
    <h2 class="text-xl font-bold mb-4">資料輸入</h2>
    <div class="space-y-4">

        <div class='md:flex md:gap-4'>
            <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
                <label class="block font-semibold">選擇縣市：</label>
                <select wire:model="thisCounty" class="border rounded p-2 w-40" wire:change="loadPlots($event.target.value)">
                    <option value="">-- 請選擇 --</option>
                    @foreach ($countyList as $county)
                        <option value="{{ $county }}">{{ $county }}</option>
                    @endforeach
                </select>
            </div>

            @if ($plotList)
            <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
                <label class="block font-semibold">選擇樣區：</label>
                <select id="plot" wire:model="thisPlot" class="border rounded p-2 w-40" wire:change="loadPlotInfo($event.target.value)">
                    <option value="">-- 請選擇 --</option>
                    @foreach ($plotList as $plot)
                        <option value="{{ $plot }}">{{ $plot }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>
<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6" role="alert">
    <p class="font-bold">⚠️ 重要提醒</p>
    <ul class="list-disc pl-5 space-y-1 mt-2 text-sm">
        <li>
            請先詳讀
            <a href="{{ url('/entry/notes') }}" target="_blank" class="underline hover:text-blue-900">
                資料輸入說明
            </a>
            以確保操作順利。
        </li>
        <li><b>請務必按下儲存鈕</b>，資料才會儲存，否則將會遺失所填寫內容。</li>
        <li>如有 <b>新增或刪除資料</b>，請儲存後才能正確套用變更。</li>
        <li>若 <b>尚未儲存即切換樣區或離開頁面</b>，變更內容將不會保留。</li>
    </ul>
</div>

<!-- 有選擇樣區之後 -->
    @if ($thisPlot!='')

        <div class="flex flex-col gap-4 mt-8 gray-card">
            <h3>{{$thisPlot}} 生育地類型 <span class='ml-4 text-sm font-normal text-gray-700 align-middle'>* 淺綠色表示上次調查曾包含的生育地類型</span></h3>
            
@if (session()->has('habSaveMessage'))
    <div class="mb-1">
        <p class="font-semibold">{{ session('habSaveMessage') }}</p>
    </div>
@endif

<pre>{{ json_encode($refHabitatCodes) }}</pre>
<div class="md:flex flex-wrap gap-2 items-center" wire:key="habitat-checkboxes-{{ $this->thisPlot }}">

    @foreach($habTypeOptions as $code => $label)
        <label for="hab_{{ $code }}" class="flex items-center gap-1 px-2 py-1 rounded border cursor-pointer
            {{ in_array($code, $refHabitatCodes) ? 'bg-lime-600/20 border-lime-800/50' : 'border-gray-300' }}">
        <input id="hab_{{ $code }}" type="checkbox"
            wire:model="selectedHabitatCodes"
            value="{{ $code }}"
            class=" habitat-checkbox">

            <span class="text-sm">{{ $label }}</span>
        </label>
    @endforeach
        <button type="button" wire:click="saveHabitatSelection"
            class="btn-submit">
        儲存生育地類型
    </button>
</div>
       

        </div>

        <div class="flex flex-col gap-4 mt-8">
            <div class="flex flex-row flex-wrap items-center gap-4">
                @if ($subPlotList)
                    <div class="flex items-center gap-2 mr-8">
                        <label class="font-semibold">檢視小樣方</label>
                        <select id="thisSubPlot" wire:model.lazy="thisSubPlot" class="border rounded p-2 w-60" wire:key="subplot-select-{{ $this->thisPlot }}-{{ $this->thisSubPlot }}">
                            <option value="">-- 請選擇 --</option>
                            @foreach ($subPlotList as $subPlot)
                                <option value="{{ $subPlot }}">{{ $subPlot }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <p class='mr-8'>此樣區尚無資料</p>
                    </div>
                @endif

                <button wire:click="loadEmptyEnvForm" type="button" class="btn-add">
                    新增小樣方
                </button>
            </div>
        </div>


    @endif
    </div>
    @if (session('form') === 'env' && session('saveMsg'))
        <div class="mb-4 mt-4 rounded-md px-4 py-3">
            <p class="font-semibold">{{ session('saveMsg') }}</p>
        </div>
    @endif


    @if($showPlotEntryTable)
    <div class='mt-8 gray-card' wire:key="plot-entry-table-{{ $thisPlot }}-{{ now()->timestamp }}">
        <h3>{{$thisSubPlot}}小樣方環境資料</h3>

        @if ($errors->any() && session('form') === 'env')
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <strong class="font-semibold">請修正以下錯誤：</strong>
            <ul class="mt-2 list-disc list-inside text-sm">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
            </ul>
        </div>
        @endif
        <form wire:submit.prevent="envInfoSave" class='mt-4'>
            @include('components.plot-info-form')
        <div class="mt-4 text-right">
            <button type="submit" class="btn-submit">儲存環境資料</button>
        </div>

        </form>
    </div>
    
    @endif

    @if($showPlantEntryTable)
    
        <div id="plant-table-wrapper" class="mt-8 gray-card md:flex md:flex-col pb-16">
            <h3>{{$thisSubPlot}}小樣方植物調查資料</h3>
    @if (session()->has('plantSaveMessage'))
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3000)"
            x-show="show"
            x-transition
            id="plant-msg"
            class="mb-2 text-red-800"
        >
            {{ session('plantSaveMessage') }}
        </div>
    @endif
            
            <div id="tabulator-table-plant"  wire:ignore class='mt-4'></div>
            <div class="mt-4 flex justify-end">
                <button id="submit-btn-plant" class="btn-submit" type="button">儲存植物調查資料</button>
            </div>
        </div>

    @endif
</div>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        //監聽的名稱, select的id
        listenAndResetSelect('thisPlotUpdated', 'plot');
    });

window.plantTable = null; // 全域變數，存放 Tabulator 實例


    
    window.addEventListener('reset_plant_table', (event) => {
        if (window.plantTable != null) {
            console.log("🔁 已有表格，清掉");
            resetAndInitTabulator();
        } else {
            console.log("🆕 沒有表格");
        }
        
    });

    window.addEventListener('plant_table', (event) => {
        const data = event.detail.data; 
        const rawPlantList = event.detail.data.plantList;
        console.log(data.data);
        const chnameList = rawPlantList.map(item => item.chname);
        if (window.plantTable != null) {
            // ✅ 更新 autocomplete name list（column editorParams）
            const column = window.plantTable.getColumn("chname_index");
            if (column) {
                column.getDefinition().editorParams.values = chnameList;
            }

            // ✅ 再更新表格資料
            window.plantTable.replaceData(data.data);
            console.log("🔁 已有表格，用 replaceData");
        } else {
            console.log("🆕 沒有表格，新建");
            initTabulatorStart(data.data, data.thisSubPlot, chnameList);
        }       
    });

    window.addEventListener('sync-complete-plant-name', (event) => {
        const data = event.detail.data; 
        console.log(data.data);
        console.log("🔁 已有表格，用 replaceData");
        window.plantTable.replaceData(data.data);

    });

    function resetAndInitTabulator(containerId = 'tabulator-table-plant') {
        const tabulatorDiv = document.getElementById(containerId);

        if (!tabulatorDiv) {
            console.warn(`❌ 找不到 #${containerId}`);
            return;
        }

        // 1. 銷毀舊表格
        if (window.plantTable instanceof Tabulator) {
            console.log("🧹 銷毀舊 Tabulator");
            window.plantTable.destroy();
            window.plantTable = null;
        }

        // 2. 清除 DOM 殘留
        tabulatorDiv.innerHTML = '';
        tabulatorDiv.classList.remove('tabulator', 'tabulator-initialized');

    }

    function initTabulatorStart(tableData, thisSubPlot, nameList = []) {
        const columns = [
            { title: "#", formatter: "rownum", width: 40, hozAlign: "center", headerSort: false },
            {
                title: "中文名",
                field: "chname_index",
                editor: "autocomplete",
                width: 200,
                editorParams: {
                    values: nameList,
                    freetext: true,
                    allowEmpty: true,
                    autocomplete: true, // ← 建議加上這行（有些版本需要）
                    searchFunc: function (term, values) {
                        term = term.toLowerCase();
                        return values.filter(v => v.toLowerCase().includes(term));
                    }
                },
                formatter: function (cell) {
                    const data = cell.getRow().getData();
                    const value = cell.getValue();

                    if (Number(data.unidentified) === 1) {
                        return `<span style="color: red;">${value}</span>`;
                    }

                    return value;
                }
            },
            { title: "覆蓋度", field: "coverage", editor: "input", width: 100,hozAlign: "center",    
                validator: ["numeric", "min:0", "max:100"],
                formatter: function (cell) {
                    const data = cell.getRow().getData();
                    const value = cell.getValue();

                    if (data.cov_error === 1) {
                        const el = cell.getElement();
                        el.style.color = 'red';           // 深紅文字
                    } else {
                        const el = cell.getElement();
                        el.style.color = '';
                    }

                    return value;
                } 
            },
            {
                title: "開花",
                field: "flowering",
                editor: false,
                formatter: tickToggleFormatter,hozAlign: "center",
                width: 80,
                cellClick: function (e, cell) {
                    const current = cell.getValue();
                    cell.setValue(current == 1 ? null : 1);
                    // syncSubPlotToLivewire(cell);
                }
            },
            {
                title: "結果",
                field: "fruiting",
                editor: false,
                formatter: tickToggleFormatter,hozAlign: "center",
                width: 80,
                cellClick: function (e, cell) {
                    const current = cell.getValue();
                    cell.setValue(current == 1 ? null : 1);
                    // syncSubPlotToLivewire(cell);
                }
            },
            { title: "標本", field: "specimen_id", editor: "input" },
            { title: "備註", field: "note", editor: "input" },
            { title: "id", field: "id", visible: false },
        ];

        initTabulator({
            tableData: tableData,
            elementId: 'tabulator-table-plant',
            columns: columns,
            livewireField: 'subPlotPlantForm',
            presetKey: 'plot_full_id',
            presetValue: thisSubPlot,
            globalName: 'plantTable',
        });
    }

    // ✅ 勾勾 formatter（只有 1 才顯示勾）
    function tickToggleFormatter(cell) {
        return cell.getValue() == 1 ? "✔️" : "";
    }


    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'submit-btn-plant') {
            // console.log(window.chnameIndexTable);
            const data = window.plantTable.getData();
            const componentId = document.querySelector('[wire\\:id]')?.getAttribute('wire:id');
            if (componentId) {
                Livewire.find(componentId).set('subPlotPlantForm', data);
                Livewire.find(componentId).call('plantDataSave');
            }
        }
    });

window.listenAndResetAllHabitatCheckboxes = function (eventName) {
    window.addEventListener(eventName, () => {
        console.log(`🟡 ${eventName} 事件收到，清除所有 habitat checkbox`);

        document.querySelectorAll('.habitat-checkbox').forEach(input => {
            input.checked = false;
        });
    });
};

// 初始化監聽
window.listenAndResetAllHabitatCheckboxes('reset_habitat');



</script>
