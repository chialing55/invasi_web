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
                <select wire:model="thisPlot" class="border rounded p-2 w-40" wire:change="loadPlotInfo($event.target.value)">
                    <option value="">-- 請選擇 --</option>
                    @foreach ($plotList as $plot)
                        <option value="{{ $plot }}">{{ $plot }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>
<!-- 有選擇樣區之後 -->
    @if ($thisPlot!='')

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
        <form wire:submit.prevent="envInfoSave">
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
            
            <div id="tabulator-table"  wire:ignore></div>
            <div class="mt-4 flex justify-end">
                <button id="submit-btn" class="btn-submit" type="button">儲存植物調查資料</button>
            </div>
        </div>

    @endif

    <div class='green-card mt-8'>
        暫定功能:
        <ul class="list-disc ml-6 space-y-2">
            <li>選擇/輸入樣區編號後開始輸入</li>
            <li>輸入/修改/檢視不分開</li>
            <li>每一團隊只能輸入屬於該團隊的樣區資料</li>
            <li>上方為環境資料，下方為植物資料</li>
            <li>每個輸入值有各自的驗證方式</li>
            <li>若有漏值或其他錯誤以及物種未鑑定，會填寫在後方驗證欄位提醒，而此小區將不被視為完成</li>            
            <li>物種若未在名單內，會被判定為物種未鑑定，以顯眼顏色標示</li>
            <li>若為資料表內未有的種類，進資料表新增種類之後，仍要來此更新物種連結。</li>
            <li>若尚未輸入即為空白頁面，輸入後即為資料頁面</li>
            <li>須按存檔才能儲存</li>
            <li>輸入之後的每一筆修改皆會留下紀錄</li>
        </ul>
</div>
</div>


<script>
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

    function resetAndInitTabulator(containerId = 'tabulator-table') {
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
            elementId: 'tabulator-table',
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
        if (e.target && e.target.id === 'submit-btn') {
            // console.log(window.chnameIndexTable);
            const data = window.plantTable.getData();
            const componentId = document.querySelector('[wire\\:id]')?.getAttribute('wire:id');
            if (componentId) {
                Livewire.find(componentId).set('subPlotPlantForm', data);
                Livewire.find(componentId).call('plantDataSave');
            }
        }
    });

</script>
