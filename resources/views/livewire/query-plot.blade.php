{{-- livewire/query-plot.blade.php --}}

<div>
    <h2 class="text-xl font-bold mb-4">樣區查詢</h2>

<div class="space-y-4">
    <div class="md:flex md:flex-row gap-4 mb-4">
        <!-- 選擇縣市 -->
        <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
            <label class="block font-semibold md:mr-2">選擇縣市：</label>
            <select wire:model="thisCounty" class="border rounded p-2 w-40" wire:change="loadPlots($event.target.value)">
                <option value="">-- 請選擇 --</option>
                @foreach ($countyList as $county)
                    <option value="{{ $county }}">{{ $county }}</option>
                @endforeach
            </select>
        </div>

        <!-- 選擇樣區 -->
        @if ($plotList)
        <div class="md:flex md:flex-row md:items-center gap-2">
            <label class="block font-semibold md:mr-2">選擇樣區：</label>
            <select id="plot" wire:model="thisPlot" class="border rounded p-2 w-40" wire:change="loadPlotInfo($event.target.value)">
                <option value="">-- 請選擇 --</option>
                @foreach ($plotList as $plot)
                    <option value="{{ $plot }}">{{ $plot }}</option>
                @endforeach
            </select>
        </div>
        @endif
    </div>


    @if ($habTypeOptions)
    <div class="pt-4">
     
        <div class="md:flex md:flex-row gap-4 mb-8">
            <!-- 生育地類型 -->
            <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
                <label class="block font-semibold md:mr-2">選擇生育地類型：</label>
                <select id='habType' wire:model="thisHabType" class="border rounded p-2 w-40" wire:change="loadPlotHab($event.target.value)">
                    <option value="">-- 請選擇 --</option>
                    @foreach ($habTypeOptions as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- 小樣方 -->
            <div class="md:flex md:flex-row md:items-center gap-2">
                <label class="block font-semibold md:mr-2">或 選擇小樣方：</label>
                <select id='subPlot' wire:model="thisSubPlot" class="border rounded p-2 w-40" wire:change="loadSubPlot($event.target.value)">
                    <option value="">-- 請選擇 --</option>
                    @foreach ($subPlotList as $code => $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
       
@if (!empty($plotplantList))
<div id="plant-list-wrapper" class="gray-card w-fit" wire:key="plant-list-{{ $this->thisPlot }}">
    <h2>{{$this->thisListType}} 物種名錄 <span class="ml-2 text-gray-600 text-base font-normal">共記錄到 {{count($plotplantList)}} 種植物</span></h2>

    <div class="bg-forest-mist rounded-md p-4 text-sm mb-4 leading-relaxed">
        <ul class="list-disc list-inside space-y-1">
            <li>預設依「覆蓋度 2025 / 覆蓋度 2010」排序，可點選各欄位標題重新排序。</li>
            <li>覆蓋度欄位的格式為：<span class="font-semibold">平均值 ± 標準差（出現小樣方數 / 總小樣方數）</span>。</li>
            <li>點擊物種列可開啟 iNaturalist 網頁以查看更多資訊。</li>
        </ul>
    </div>
    <table class="text-sm border border-gray-300">
        <thead class="bg-yellow-500/30 sticky top-0 z-10">
            <tr>
                <th><button class="sort px-4 py-2" data-sort="chfamily">科名</button></th>
                <th><button class="sort px-4 py-2" data-sort="chname">中文名</button></th>
                <th><button class="sort px-4 py-2" data-sort="nat">外來/栽培</button></th>
                <th><button class="sort px-4 py-2" data-sort="cov2010">覆蓋度 2010</button></th>
                <th><button class="sort px-4 py-2" data-sort="cov2025">覆蓋度 2025</button></th>
            </tr>
        </thead>
        <tbody class="list">
            @foreach ($plotplantList as $item)
@php
    $inatLink = "https://taiwan.inaturalist.org/search?q=" . urlencode($item['chname'] ?? '');
@endphp

            <tr
                class="hover:bg-amber-800/10 {{ $item['chfamily'] === '--' ? 'bg-red-100 text-red-800' : ($loop->even ? 'bg-gray-50' : 'bg-white') }}"
                style="cursor: pointer;"
                onclick="window.open('{{ $inatLink }}', '_blank')"
            >
                <td class="chfamily px-4 py-2 border-b ">{{ $item['chfamily'] }}</td>
                <td class="chname px-4 py-2 border-b ">{{ $item['chname'] }}</td>
                <td class="nat border-b px-2 py-1 text-center">{{ $item['nat_type'] }}</td>
                <td class="cov2010 border-b px-2 py-1  text-center" data-sort="{{ $item['cov2010_sort'] ?? 0 }}">{{ $item['cov2010'] }}</td>
                <td class="cov2025 border-b px-2 py-1 text-center" data-sort="{{ $item['cov2025_sort'] ?? 0 }}">{{ $item['cov2025'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

                </div>
            </div>
    @endif
</div>
    

<script>

    document.addEventListener('DOMContentLoaded', function () {
    //監聽的名稱, select的id
        listenAndResetSelect('thisPlotUpdated', 'plot');
        listenAndResetSelect('thisHabTypeUpdated', 'habType');
        listenAndResetSelect('thisSubPlotUpdated', 'subPlot');
    });

let plantListSorter = null;
let currentSortField = null;
let currentSortOrder = 'asc';

    window.addEventListener('plantListLoaded', () => {
        if (plantListSorter) {
            console.log("🟡 plantListLoaded 事件收到，重新初始化 plantListSorter");
            // plantListSorter.destroy();
            plantListSorter = null;
        }

        setTimeout(() => {
            requestAnimationFrame(() => {
                try {
                    console.log("⏳ 延遲後準備初始化");
                    initPlantListSorter();
                } catch (e) {
                    console.error("❌ 錯誤：", e);
                }
            });
        }, 200);


    });



function initPlantListSorter() {
    console.log("🟢 初始化 plantListSorter");
    const wrapper = document.getElementById("plant-list-wrapper");
    const listBody = wrapper?.querySelector(".list");

    if (!wrapper || !listBody || listBody.children.length === 0) {
        console.warn("⚠️ 找不到表格或資料還沒渲染");
        return;
    }

    if (plantListSorter) {
        plantListSorter = null;
    }

    // ✅ 初始化 List.js
    plantListSorter = new List("plant-list-wrapper", {
        valueNames: ['chfamily', 'chname', 'nat', { name: 'cov2010', attr: 'data-sort' }, { name: 'cov2025', attr: 'data-sort' }],
    });


    // ✅ 排序切換
    document.querySelector("#plant-list-wrapper").addEventListener("click", (event) => {
        const btn = event.target.closest(".sort");
        if (!btn) return; // 不是排序按鈕就忽略

        const sortField = btn.dataset.sort;

        if (currentSortField === sortField) {
            currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            currentSortField = sortField;
            currentSortOrder = 'asc';
        }

        // 更新箭頭
        document.querySelectorAll("#plant-list-wrapper .sort").forEach(b => b.removeAttribute('data-order'));
        btn.setAttribute("data-order", currentSortOrder);

        plantListSorter.sort(sortField, {
            order: currentSortOrder,
            sortFunction: function (a, b) {
                const aVal = a.values()[sortField]?.toString() ?? '';
                const bVal = b.values()[sortField]?.toString() ?? '';
                return aVal.localeCompare(bVal, 'zh-Hant', { sensitivity: 'base', numeric: true });
            }
        });
        resetRowColors();
        console.log(`🔃 排序 ${sortField} - ${currentSortOrder}`);
    });

function resetRowColors() {
    const rows = document.querySelectorAll("#plant-list-wrapper tbody.list tr");
    rows.forEach((row, index) => {
        row.classList.remove("bg-white", "bg-gray-50");
        row.classList.add(index % 2 === 0 ? "bg-white" : "bg-gray-50");
    });
}



}





</script>

