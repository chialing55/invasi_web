<div class="space-y-4">
    <div wire:loading.class="flex" wire:loading.remove.class="hidden"
        class="hidden fixed top-0 left-0 w-full h-full z-50 bg-white/50 items-center justify-center">
        <div class="w-10 h-10 border-4 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
    </div>
    <h2 class="text-xl font-bold mb-4">小樣方未調查原因</h2>
    <div class="space-y-4">
        <div class='md:flex md:gap-4'>
            <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
                <label class="block font-semibold">選擇調查年度：</label>
                <select id="census_year" wire:model="thisCensusYear" class="border rounded p-2 w-40"
                    wire:change="loadThisCensusYearData($event.target.value)">
                    <option value="all">-- All --</option>
                    @foreach ($censusYearList as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
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
        </div>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6" role="alert">
            <p class="font-bold">⚠️ 重要提醒</p>
            <ul class="list-disc pl-5 space-y-1 mt-2 text-sm">
                <li>
                    在該樣區皆已輸入完成後，請至此填寫前次小樣方未調查之原因。
                </li>
                <li>
                    未調查原因填寫說明：
                    <ul class="list-disc pl-6 mt-1 space-y-1">
                        <li>未調查原因請從下拉表單中選擇。</li>
                        <li>如需補充，請於「其他說明」欄填寫。</li>
                        <li>若選擇「其他」，請於「其他說明」欄說明原因。</li>                        
                        <li>若更改小樣方編號，請在「其他說明」欄填寫新的小樣方編號。</li>
                    </ul>
                </li>
                <li>新增或刪除資料後，<b>請務必按下儲存鈕</b>，否則切換樣區或離開頁面時，所填寫內容將會遺失。</li>
            </ul>
        </div>
    </div>
    @if ($thisPlot != '' && $plotInfo)
        <div class="flex gap-4 items-center">
            <button class="btn-add" type="button" wire:click="reCheckPlotInfo({{ $thisPlot }})">重新比對樣區</button>
            @if (session()->has('missingnote_sync'))
                <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" x-transition
                    wire:key="sync-msg-{{ now()->format('YmdHisv') }}" id="sync-msg" class="text-red-800">
                    {{ session('missingnote_sync') }}
                </div>
            @endif
        </div>
        <div class="mt-8 gray-card md:flex md:flex-col mb-4">
            @if (session()->has('plotSaveMessage'))
                <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" x-transition
                    wire:key="plot-msg-{{ now()->format('YmdHisv') }}" id="plot-msg" class="mb-2 text-red-800">
                    {{ session('plotSaveMessage') }}
                </div>
            @endif
            <div class="flex justify-end">
                <button id="submit-btn-subPlot" class="btn-submit" type="button">儲存</button>
            </div>

            <div id="tabulator-table-missingSubPlot" wire:ignore class='mt-4'></div>

            <div class="mt-4 flex justify-end">
                <button id="submit-btn-subPlot" class="btn-submit" type="button">儲存</button>
            </div>
        </div>
    @endif
    @if ($noMissingSubplotData)
        <div class="p-4 mb-6" role="alert">
            <p class="font-bold">❌ 無小樣方未調查資料</p>
        </div>
    @endif

</div>
<script>
    const REASON_LIST = @json($reasonOptions, JSON_UNESCAPED_UNICODE);
    const REASON_MAP = Object.fromEntries(REASON_LIST.map(o => [o.value, o.label]));
    document.addEventListener('DOMContentLoaded', function() {
        //監聽的名稱, select的id
        listenAndResetSelect('thisPlotUpdated', 'plot');
    });

    window.missingSubPlotTable = null; // 全域變數，存放 Tabulator 實例



    window.addEventListener('reset_missingSubPlot_table', (event) => {
        if (window.missingSubPlotTable != null) {
            resetAndInitTabulator();
        } else {
        }

    });

    window.addEventListener('missingSubPlot_table', (event) => {
        const data = event.detail.data;

        if (window.missingSubPlotTable != null) {
            window.missingSubPlotTable.replaceData(data.data);
        } else {
            initTabulatorStart(data.data, data.thisPlot);
        }
    });


    function resetAndInitTabulator(containerId = 'tabulator-table-missingSubPlot') {
        const tabulatorDiv = document.getElementById(containerId);

        if (!tabulatorDiv) {
            console.warn(`❌ 找不到 #${containerId}`);
            return;
        }

        // 1. 銷毀舊表格
        if (window.missingSubPlotTable instanceof Tabulator) {
            window.missingSubPlotTable.destroy();
            window.missingSubPlotTable = null;
        }

        // 2. 清除 DOM 殘留
        tabulatorDiv.innerHTML = '';
        tabulatorDiv.classList.remove('tabulator', 'tabulator-initialized');

    }

    function initTabulatorStart(tableData, thisPlot) {
        const columns = [{
                title: "#",
                formatter: "rownum",
                width: 40,
                hozAlign: "center",
                headerSort: false
            },
            {

                title: "小樣方編號",
                field: "plot_full_id_2010",
                width: 120,

            },
            {
                title: "未調查原因",
                field: "not_done_reason_code", // 存 code
                width: 260,
                editor: "list",
                editorParams: {
                    values: REASON_LIST, // ← 有順序的陣列
                    valueField: "value",
                    labelField: "label",
                    clearable: true,
                    autocomplete: true,
                    listOnEmpty: true, // 聚焦就展開
                },
                formatter: (cell) => REASON_MAP[cell.getValue()] || "",
            },

            {
                title: "其他說明",
                field: "description",
                editor: "input",
                width: 200
            },
            {
                title: "id",
                field: "id",
                visible: false
            },
        ];

        initTabulator({
            tableData: tableData,
            elementId: 'tabulator-table-missingSubPlot',
            columns: columns,
            livewireField: 'plotInfo',
            presetKey: 'plot_full_id',
            presetValue: thisPlot,
            globalName: 'missingSubPlotTable',
            enableRowContextMenu: false,
        });
    }


    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'submit-btn-subPlot') {
            const data = window.missingSubPlotTable.getData();
            const componentId = document.querySelector('[wire\\:id]')?.getAttribute('wire:id');
            if (componentId) {
                Livewire.find(componentId).set('reasonForm', data);
                Livewire.find(componentId).call('missingReasonSave');
            }
        }
    });
</script>
