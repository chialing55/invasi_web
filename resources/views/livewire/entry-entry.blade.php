{{-- livewire/entry-entry.blade.php --}}
<div class="space-y-4">
    <div wire:loading.class="flex" wire:loading.remove.class="hidden"
        class="hidden fixed top-0 left-0 w-full h-full z-50 bg-white/50 items-center justify-center">
        <div class="w-10 h-10 border-4 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
    </div>

    <h2 class="text-xl font-bold mb-4">資料輸入</h2>
    <div class="space-y-4">

        <div class='md:flex md:gap-4'>
            <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
                <label class="block font-semibold">選擇縣市：</label>
                <select wire:model="thisCounty" class="border rounded p-2 w-40"
                    wire:change="loadPlots($event.target.value)">
                    <option value="">-- 請選擇 --</option>
                    @foreach ($countyList as $county)
                        <option value="{{ $county }}">{{ $county }}</option>
                    @endforeach
                </select>
            </div>

            @if ($plotList)
                <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
                    <label class="block font-semibold">選擇樣區：</label>
                    <select id="plot" wire:model="thisPlot" class="border rounded p-2 w-40"
                        wire:change="loadPlotInfo($event.target.value)">
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
                    <a href="{{ url('/entry/notes') }}" target="_blank" class="underline hover:text-green-900">
                        資料輸入說明
                    </a>
                    以確保操作順利。
                </li>
                <li>新增或刪除資料後，<b>請務必按下儲存鈕</b>，否則切換樣區或離開頁面時，所填寫內容將會遺失。</li>
            </ul>
        </div>

        <!-- 有選擇樣區之後 -->
        @if ($thisPlot != '')

            <div class="flex flex-col gap-4 mt-8 gray-card">
                <h3>{{ $thisPlot }} 生育地類型 <span class='ml-4 text-sm font-normal text-gray-700 align-middle'>*
                        淺綠色表示上次調查曾包含的生育地類型</span></h3>

                @if (session()->has('habSaveMessage'))
                    <div class="mb-1">
                        <p class="font-semibold">{{ session('habSaveMessage') }}</p>
                    </div>
                @endif

                <div class="md:flex flex-wrap gap-2 items-center"
                    wire:key="habitat-checkboxes-{{ $this->thisPlot }}-{{ implode('-', $selectedHabitatCodes) }}">

                    @foreach ($habTypeOptions as $code => $label)
                        <label for="hab_{{ $code }}"
                            class="flex items-center gap-1 px-2 py-1 rounded border cursor-pointer
            {{ in_array($code, $refHabitatCodes) ? 'bg-lime-600/20 border-lime-800/50' : 'border-gray-300' }}">
                            <input id="hab_{{ $code }}" type="checkbox" wire:model="selectedHabitatCodes"
                                value="{{ $code }}" class=" habitat-checkbox">

                            <span class="text-sm">{{ $label }}</span>
                        </label>
                    @endforeach
                    <button type="button" wire:click="saveHabitatSelection" class="btn-submit">
                        儲存生育地類型
                    </button>
                </div>


            </div>
            <div class="mt-8 gray-card md:flex md:flex-col mb-4">
                <h3>{{ $thisPlot }} 樣區調查資料上傳</h3>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6" role="alert">
                    <ul class="list-disc pl-5 space-y-1 mt-2 text-sm">
                        <li>調查資料輸入並確認無誤後，請將該樣區的所有紙本資料掃描為電子檔，合併成單一檔案（pdf 檔），並透過此處上傳至主機。</li>
                        <li>系統將自動以 <b>樣區編號</b> 作為檔案名稱。</li>
                        <li>若需更新檔案，請直接重新上傳，新檔案將自動覆蓋舊檔。</li>
                        <li>如之後有再更動紙本資料，務必重新掃描(或修圖更改)，並上傳最新版本。</li>
                    </ul>
                </div>

                @if (session()->has('fileUploadSuccess'))
                    <p class="font-semibold">{{ session('fileUploadSuccess') }}</p>
                @endif
                @if ($thisPlotFile)
                    <div class="mb-4">
                        <a href='{{ $thisPlotFile }}' target="_blank"><img src="/images/PDF_file_icon.svg"
                                alt="PDF" class="inline w-5 h-5 mr-1"> {{ $thisPlot }}.pdf</a>
                    </div>
                @endif
                <div>

                    <input type="file" wire:model.defer="plotFile" accept=".pdf">

                    <button id="submit-btn-file" type="button" class="btn-submit" wire:click="clickUploadFile">
                        {{ $thisPlotFile ? '更新樣區調查資料' : '上傳樣區調查資料' }}
                    </button>

                    @error('plotFile')
                        <div class="text-red-500 text-sm">{{ $message }}</div>
                    @enderror

                </div>
            </div>
            <div class="flex flex-col gap-4 !mt-8">
                <div class="flex flex-row flex-wrap items-center gap-4">
                    @if ($subPlotList)
                        <div class="flex items-center gap-2 mr-8">
                            <label class="font-semibold">檢視小樣方</label>
                            <select id="thisSubPlot" wire:model.lazy="thisSubPlot" class="border rounded p-2 w-60"
                                wire:key="subplot-select-{{ $this->thisPlot }}-{{ $this->thisSubPlot }}">
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
            <p class="font-semibold">{{ session('saveMsg') }}{{ session('saveMsg2') }}</p>
        </div>
    @endif


    @if ($showPlotEntryTable)

        <!-- @php
            $plotId = substr($thisSubPlot, 0, 6);
            $habType = substr($thisSubPlot, 6, 2);
            $subplotNo = substr($thisSubPlot, 8, 2);
        @endphp -->
        <div class='mt-8 gray-card' wire:key="plot-entry-table-{{ $thisPlot }}-{{ now()->timestamp }}">
            <h3> {{ $thisSubPlot }} 小樣方環境資料</h3>

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

    @if ($showPlantEntryTable)

        <div id="plant-table-wrapper" class="mt-8 gray-card md:flex md:flex-col mb-4">
            <h3>{{ $thisSubPlot }} 小樣方植物調查資料</h3>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6" role="alert">
                <p class="font-bold">⚠️ 重要提醒</p>
                <ul class="list-disc pl-5 space-y-1 mt-2 text-sm">
                    <li>新增: 可在中名欄位以植物學名查詢中名，但仍以中名為輸入值。</li>
                    <li>若植物名單內無目標物種，請至<a href='https://tai2.ntu.edu.tw/search/1/' class="underline hover:text-green-900"
                            target="_blank">「臺灣植物資訊整合查詢系統」</a>查詢，以確認資料庫所使用之中名與學名。</li>
                    <li>若資料庫名錄中確實無該植物，且<b>確定需新增</b>，請填寫<a
                            href='https://docs.google.com/spreadsheets/d/13GUOo_I5fhUBh2IeGb1TJpQeIPN0GqSQKfsMwulSTHE/edit?usp=sharing'
                            target="_blank" class="underline hover:text-green-900">「外來植物調查計畫-需新增的植物」</a>資料表。</li>
                </ul>
            </div>
            @if (session()->has('plantSaveMessage'))
                <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" x-transition
                    wire:key="plant-msg-{{ now()->format('YmdHisv') }}" id="plant-msg" class="mb-2 text-red-800">
                    {{ session('plantSaveMessage') }}
                </div>
            @endif
            <div class="flex justify-end">
                <button id="submit-btn-plant" class="btn-submit" type="button">儲存植物調查資料</button>
            </div>

            <div id="tabulator-table-plant" wire:ignore class='mt-4'></div>

            <div class="mt-4 flex justify-end">
                <button id="submit-btn-plant" class="btn-submit" type="button">儲存植物調查資料</button>
            </div>
        </div>
        @if (substr($thisSubPlot, 6, 2) !== '99' && substr($thisSubPlot, 6, 2) !== '88')
            <div class="mt-8 gray-card md:flex md:flex-col mb-4">
                <h3>{{ $thisSubPlot }} 小樣方照片上傳</h3>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6" role="alert">
                    <p class="font-bold">⚠️ 重要提醒</p>
                    <ul class="list-disc pl-5 space-y-1 mt-2 text-sm">
                        <li>系統會自動將檔名更名為小樣方編號，並以 .jpg 儲存，檔案大小不得超過 20MB。</li>
                        <li>每個小樣方僅可上傳一張照片。若需更換照片，請直接重新上傳，系統會自動覆蓋原有檔案。</li>
                    </ul>
                </div>
                @if (session()->has('photoUploadSuccess'))
                    <p class="font-semibold">{{ session('photoUploadSuccess') }}</p>
                @endif
                @if ($thisPhoto)
                    <div class="mb-4">
                        <img src="{{ $thisPhoto }}?v={{ now()->timestamp }}" alt="小樣方照片"
                            class="w-[500px] h-auto rounded shadow">
                    </div>
                @endif
                <div>

                    <input type="file" wire:model="photo" accept="image/*">

                    <button id="submit-btn-photo" type="button" class="btn-submit" wire:click="clickUploadPhoto">
                        {{ $thisPhoto ? '更新小樣方照片' : '上傳小樣方照片' }}
                    </button>

                    @error('photo')
                        <div class="text-red-500 text-sm">{{ $message }}</div>
                    @enderror

                </div>
            </div>

        @endif

</div>
@endif


<script>
    document.addEventListener('DOMContentLoaded', function() {
        //監聽的名稱, select的id
        listenAndResetSelect('thisPlotUpdated', 'plot');
    });

    window.plantTable = null; // 全域變數，存放 Tabulator 實例



    window.addEventListener('reset_plant_table', (event) => {
        if (window.plantTable != null) {
            resetAndInitTabulator();
        } else {
        }

    });

    window.addEventListener('plant_table', (event) => {
        const data = event.detail.data;
        // const rawPlantList = event.detail.data.plantList;
        // const chnameList = rawPlantList.map(item => item.chname);
        if (window.plantTable != null) {
            // ✅ 更新 autocomplete name list（column editorParams）
            // const column = window.plantTable.getColumn("chname_index");
            // if (column) {
            //     column.getDefinition().editorParams.values = chnameList;
            // }

            // ✅ 再更新表格資料
            window.plantTable.replaceData(data.data);
        } else {
            initTabulatorStart(data.data, data.thisSubPlot);
        }
    });

    window.addEventListener('sync-complete-plant-name', (event) => {
        const data = event.detail.data;
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
            window.plantTable.destroy();
            window.plantTable = null;
        }

        // 2. 清除 DOM 殘留
        tabulatorDiv.innerHTML = '';
        tabulatorDiv.classList.remove('tabulator', 'tabulator-initialized');

    }

    function initTabulatorStart(tableData, thisSubPlot) {
        const columns = [{
                title: "#",
                formatter: "rownum",
                width: 40,
                hozAlign: "center",
                headerSort: false
            },
            {

                title: "中名",
                field: "chname_index",
                width: 200,
                editor: remoteAutocompleteEditor('/api/plant-suggestions', {
                    fontSize: "1rem",
                    updateFields: (item) => ({
                        spcode: item.spcode,
                        hint: item.hint
                    })
                }),

                formatter: function(cell) {
                    const data = cell.getRow().getData();
                    const value = cell.getValue();

                    if (Number(data.unidentified) === 1) {
                        return `<span style="color: red;">${value}</span>`;
                    }

                    return value;
                }
            },
            {
                title: "覆蓋度",
                field: "coverage",
                editor: "input",
                width: 100,
                hozAlign: "center",
                validator: ["numeric", "min:0", "max:100"],
                formatter: function(cell) {
                    const data = cell.getRow().getData();
                    const value = cell.getValue();

                    if (data.data_error === 1 || data.coverage === 0) {
                        const el = cell.getElement();
                        el.style.color = 'red'; // 深紅文字
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
                formatter: tickToggleFormatter,
                hozAlign: "center",
                width: 80,
                cellClick: function(e, cell) {
                    const current = cell.getValue();
                    cell.setValue(current == 1 ? null : 1);
                    // syncSubPlotToLivewire(cell);
                }
            },
            {
                title: "結果",
                field: "fruiting",
                editor: false,
                formatter: tickToggleFormatter,
                hozAlign: "center",
                width: 80,
                cellClick: function(e, cell) {
                    const current = cell.getValue();
                    cell.setValue(current == 1 ? null : 1);
                    // syncSubPlotToLivewire(cell);
                }
            },
            {
                title: "標本",
                field: "specimen_id",
                editor: "input",
                width: 80
            },
            {
                title: "備註",
                field: "note",
                editor: "input",
                width: 80
            },
            {
                title: "spcode",
                field: "spcode",
                visible: false
            },
            {
                title: "中名 / 科名",
                field: "hint",
                width: 150
            },
            {
                title: "id",
                field: "id",
                visible: false
            },
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


    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'submit-btn-plant') {
            const data = window.plantTable.getData();
            const componentId = document.querySelector('[wire\\:id]')?.getAttribute('wire:id');
            if (componentId) {
                Livewire.find(componentId).set('subPlotPlantForm', data);
                Livewire.find(componentId).call('plantDataSave');
            }
        }
    });

    window.listenAndResetAllHabitatCheckboxes = function(eventName) {
        window.addEventListener(eventName, () => {

            document.querySelectorAll('.habitat-checkbox').forEach(input => {
                input.checked = false;
            });
        });
    };

    // 初始化監聽
    window.listenAndResetAllHabitatCheckboxes('reset_habitat');

    function remoteAutocompleteEditor(apiUrl, config = {}) {
        return function(cell, onRendered, success, cancel) {
            const row = cell.getRow();
            const column = cell.getColumn();
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
                    if (i === selectedIndex) {
                        el.scrollIntoView({
                            block: "nearest", // 不會整個跳動，僅捲動剛好看到它
                            behavior: "auto"
                        });
                    }
                });
            };

            const handleKey = (e) => {
                const cursorPos = input.selectionStart;
                const valueLength = input.value.length;
                if (e.key === "ArrowDown") {
                    if (results.length > 0 && dropdown.style.display !== "none") {
                        // 下拉選單開啟，選擇選項
                        selectedIndex = Math.min(results.length - 1, selectedIndex + 1);
                        highlightSelected();
                    } else {
                        // 向下移動到下方格子
                        e.preventDefault();
                        cleanup();
                        success(input.value);

                        const nextRow = row.getNextRow();
                        if (nextRow) {
                            const nextCell = nextRow.getCell(column.getField());
                            if (nextCell) nextCell.edit();
                        }
                    }
                    e.stopPropagation();
                } else if (e.key === "ArrowUp") {
                    const menuOpen = results.length > 0 && dropdown.style.display !== "none";

                    if (menuOpen && selectedIndex > 0) {
                        // 🔼 選單開啟且不是第一筆 → 選單向上選擇
                        selectedIndex = Math.max(0, selectedIndex - 1);
                        highlightSelected();
                    } else {
                        // ❗選單沒開 or 已經在第一筆 → 移動到上方格子
                        e.preventDefault();
                        cleanup();
                        success(input.value);

                        const prevRow = row.getPrevRow();
                        if (prevRow) {
                            const prevCell = prevRow.getCell(column.getField());
                            if (prevCell) prevCell.edit();
                        }
                    }

                    e.stopPropagation();
                } else if (e.key === "Enter") {

                    if (selectedIndex >= 0 && results[selectedIndex]) {
                        applySelection(results[selectedIndex]);
                    } else {
                        cleanup();
                        success(input.value);
                    }
                    e.stopPropagation();
                } else if (e.key === "Tab") {
                    if (selectedIndex >= 0 && results[selectedIndex]) {
                        e.preventDefault(); // 選了項目 → 自己處理選取與跳欄
                        applySelection(results[selectedIndex]);

                        // 👉 自動跳下一格編輯
                        // ✅ 從 cell 取得欄位資訊
                        const columns = cell.getTable().getColumns();
                        const currentIndex = columns.findIndex(col => col.getField() === column.getField());
                        for (let i = currentIndex + 1; i < columns.length; i++) {
                            const colDef = columns[i].getDefinition();
                            if (colDef.editor && colDef.editor !== false) {
                                const nextField = columns[i].getField();
                                const nextCell = row.getCell(nextField);
                                if (nextCell) {
                                    nextCell.edit(); // ✅ 右欄進入編輯
                                }
                                break;
                            }
                        }
                    } else {
                        // ❌ 沒有選擇建議項目，讓 Tabulator 自己處理跳欄
                        // 不要 preventDefault！
                        cleanup();
                        success(input.value);
                    }

                    e.stopPropagation();
                } else if (e.key === "ArrowRight") {
                    const cursorPos = input.selectionStart;
                    const valueLength = input.value.length;

                    if (cursorPos === valueLength) {
                        e.preventDefault();

                        // ✅ 先結束編輯（否則 Tabulator 不會進入下一格編輯模式）
                        cleanup();
                        success(input.value); // 非常重要！！

                        // ✅ 再跳到下一格並啟用編輯
                        // const currentIndex = columns.findIndex(col => col.getField() === column.getField());
                        // for (let i = currentIndex + 1; i < columns.length; i++) {
                        //     const colDef = columns[i].getDefinition();
                        //     if (colDef.editor && colDef.editor !== false) {
                        //         const nextField = columns[i].getField();
                        //         const nextCell = row.getCell(nextField);
                        //         if (nextCell) {
                        //             nextCell.edit();  // ✅ 啟動右邊欄位的編輯器
                        //         }
                        //         break;
                        //     }
                        // }
                    }
                } else {
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
