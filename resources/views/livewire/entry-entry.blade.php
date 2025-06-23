{{-- livewire/entry-entry.blade.php --}}
<div>
<div
    wire:loading.class="flex"
    wire:loading.remove.class="hidden"
    class="hidden fixed top-0 left-0 w-full h-full z-50 bg-white/50 items-center justify-center"
>
    <div class="w-10 h-10 border-4 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
</div>
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
        <div class="mt-4">
            <p class="font-semibold">{{ session('saveMsg') }}{{session('saveMsg2')}}</p>
        </div>
    @endif


    @if($showPlotEntryTable)
    <div class='mt-8 gray-card' wire:key="plot-entry-table-{{ $thisPlot }}-{{ now()->timestamp }}">
        <h3>{{$thisSubPlot}} 小樣方環境資料</h3>

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
    
        <div id="plant-table-wrapper" class="mt-8 gray-card md:flex md:flex-col mb-4">
            <h3>{{$thisSubPlot}} 小樣方植物調查資料</h3>

    @if (session()->has('plantSaveMessage'))
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3000)"
            x-show="show"
            x-transition
            wire:key="plant-msg-{{ now()->format('YmdHisv') }}"
            id="plant-msg"
            class="mb-2 text-red-800"
        >
            {{ session('plantSaveMessage') }}
        </div>
    @endif
            <div class="flex justify-end">
                <button id="submit-btn-plant" class="btn-submit" type="button">儲存植物調查資料</button>
            </div>

                <div id="tabulator-table-plant"  wire:ignore class='mt-4'></div>

            <div class="mt-4 flex justify-end">
                <button id="submit-btn-plant" class="btn-submit" type="button">儲存植物調查資料</button>
            </div>
        </div>

    <div class="mt-8 gray-card md:flex md:flex-col mb-4">
        <h3>{{$thisSubPlot}} 小樣方照片上傳</h3>
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
        // const rawPlantList = event.detail.data.plantList;
        console.log(data.data);
        // const chnameList = rawPlantList.map(item => item.chname);
        if (window.plantTable != null) {
            // ✅ 更新 autocomplete name list（column editorParams）
            // const column = window.plantTable.getColumn("chname_index");
            // if (column) {
            //     column.getDefinition().editorParams.values = chnameList;
            // }

            // ✅ 再更新表格資料
            window.plantTable.replaceData(data.data);
            console.log("🔁 已有表格，用 replaceData");
        } else {
            console.log("🆕 沒有表格，新建");
            initTabulatorStart(data.data, data.thisSubPlot);
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

    function initTabulatorStart(tableData, thisSubPlot) {
        const columns = [
            { title: "#", formatter: "rownum", width: 40, hozAlign: "center", headerSort: false },
            {

                title: "中文名",
                field: "chname_index",
                width: 200,
                editor: remoteAutocompleteEditor('/api/plant-suggestions', {
                    fontSize: "1rem",
                    updateFields: (item) => ({
                        spcode: item.spcode,
                        hint: item.hint
                    })
                }),           

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
            { title: "標本", field: "specimen_id", editor: "input", width: 80 },
            { title: "備註", field: "note", editor: "input", width: 80 },
            { title: "spcode", field: "spcode", visible: false },
            { title: "中名 / 科名", field: "hint", width: 150 },
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

function remoteAutocompleteEditor(apiUrl, config = {}) {
    return function (cell, onRendered, success, cancel) {
        const input = document.createElement("input");
        input.setAttribute("type", "text");
        input.classList.add("remote-autocomplete");
        input.style.fontSize = config.fontSize || "1.1rem";
        input.style.padding = "6px 8px";
        input.style.height = "2rem";
        input.style.width = "100%";
        input.autocomplete = "off";

        const currentValue = cell.getValue() || "";
        input.value = currentValue;

        const dropdown = document.createElement("div");
        dropdown.classList.add("autocomplete-dropdown");
        dropdown.style.position = "absolute";
        dropdown.style.zIndex = 9999;
        dropdown.style.background = "white";
        dropdown.style.border = "1px solid #ccc";
        dropdown.style.boxShadow = "0 2px 6px rgba(0,0,0,0.15)";
        dropdown.style.display = "none";
        dropdown.style.maxHeight = "200px";
        dropdown.style.overflowY = "auto";
        dropdown.style.fontSize = config.fontSize || "1.1rem";
        document.body.appendChild(dropdown);

        let results = [];
        let selectedIndex = -1;

        const showDropdown = () => {
            const rect = input.getBoundingClientRect();
            dropdown.style.left = `${rect.left + window.scrollX}px`;
            dropdown.style.top = `${rect.bottom + window.scrollY}px`;
            dropdown.style.width = `${rect.width}px`;
            dropdown.style.display = results.length > 0 ? "block" : "none";
        };

        const hideDropdown = () => {
            dropdown.style.display = "none";
        };

        const fetchSuggestions = (q) => {
            if (!q.trim()) {
                results = [];
                dropdown.innerHTML = "";
                hideDropdown();
                return;
            }

            fetch(`${apiUrl}?q=${encodeURIComponent(q)}`)
                .then((res) => res.json())
                .then((data) => {
                    results = data;
                    dropdown.innerHTML = "";
                    selectedIndex = -1;

                    data.forEach((item, i) => {
                        const option = document.createElement("div");
                        option.classList.add("autocomplete-option");
                        option.textContent = `${item.label} / ${item.family}`;
                        option.style.padding = "4px 8px";
                        option.style.cursor = "pointer";

                        option.addEventListener("mousedown", (e) => {
                            e.preventDefault(); // 避免 blur
                            applySelection(item);
                        });

                        dropdown.appendChild(option);
                    });

                    showDropdown();
                });
        };

        const applySelection = (item) => {
            input.value = item.value;
            const row = cell.getRow();

            // 若有指定欄位對應更新
            if (config.updateFields) {
                row.update(config.updateFields(item));
            }

            cleanup();
            success(item.value);
        };

        const highlightSelected = () => {
            [...dropdown.children].forEach((el, i) => {
                el.style.background = i === selectedIndex ? "#97c498" : "";
            });
        };

        const handleKey = (e) => {
            if (e.key === "ArrowDown") {
                selectedIndex = Math.min(results.length - 1, selectedIndex + 1);
                highlightSelected();
                e.stopPropagation();
            } else if (e.key === "ArrowUp") {
                selectedIndex = Math.max(0, selectedIndex - 1);
                highlightSelected();
                e.stopPropagation();
            } else if (e.key === "Enter") {
                if (selectedIndex >= 0 && results[selectedIndex]) {
                    applySelection(results[selectedIndex]);
                } else {
                    cleanup();
                    success(input.value);
                }
                e.stopPropagation();
            } else {
                // fetch 建議清單
                fetchSuggestions(input.value);
            }
        };

        const cleanup = () => {
            dropdown.remove();
        };

        input.addEventListener("keydown", handleKey);
        input.addEventListener("blur", () => {
            setTimeout(() => {
                cleanup();
                success(input.value);
            }, 10);
        });

        onRendered(() => {
            input.focus();
            fetchSuggestions(input.value);
        });

        return input;
    };
}


</script>
