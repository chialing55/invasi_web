{{-- livewire/query-plot.blade.php --}}

<div>
<div
    wire:loading.class="flex"
    wire:loading.remove.class="hidden"
    class="hidden fixed top-0 left-0 w-full h-full z-50 bg-white/50 items-center justify-center"
>
    <div class="w-10 h-10 border-4 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
</div>
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
                        <option value="{{ $code }}">{{ $code }} {{ $label }}</option>
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
            <li>覆蓋度欄位的格式為：<span class="font-semibold">平均值 ± 標準差</span>。</li>
            <li>點擊物種列可開啟 iNaturalist 網頁以查看更多資訊。</li>
        </ul>
    </div>
    <table class="text-sm border border-gray-300">
            <!-- 桌機版表頭 -->
            <thead class=" hidden sm:table-header-group sm:sticky sm:top-0 sm:z-10" style="background-color: #F9E7AC;">
                <tr class="border-b border-gray-300 ">
                    <th rowspan="2">
                       <button class="sort px-4 py-2" data-sort="chfamily">科名</button>
                    </th>
                    <th rowspan="2">
                        <button class="sort px-4 py-2" data-sort="chname">中文名</button>
                    </th>
                    <th rowspan="2"><button class="sort px-4 py-2" data-sort="nat">外來/栽培</button></th>
                    <th colspan="4" class="px-4 py-2 text-center bg-lime-200/50">2010</th>
                    <th colspan="4" class="px-4 py-2 text-center bg-orange-200">2025</th>
                </tr>
                <tr class="border-b border-gray-300">
                    <th><button class="sort px-4 py-2  bg-lime-200/50" >樣區數</button></th>
                    <th><button class="sort px-4 py-2  bg-lime-200/50" data-sort="sub2010">小樣區數</button></th>
                    <th colspan="2"><button class="sort px-4 py-2 w-full bg-lime-200/50" data-sort="cov2010">覆蓋度</button></th>
                    <th><button class="sort px-4 py-2  bg-orange-200" >樣區數</button></th>
                    <th><button class="sort px-4 py-2  bg-orange-200" data-sort="sub2025">小樣區數</button></th>
                    <th colspan="2"><button class="sort px-4 py-2 w-full  bg-orange-200" data-sort="cov2025">覆蓋度</button></th>
                </tr>
            </thead>

            <!-- 手機版表頭 -->
            <thead class=" sm:hidden sticky top-0 z-10" style="background-color: #F9E7AC;">
                <tr class="border-b border-gray-300">
                    <th><button class="sort px-4 py-2" data-sort="chfamily">科名</button></th>
                    <th><button class="sort px-4 py-2" data-sort="chname">中文名</button></th>
                    <th><button class="sort px-4 py-2" data-sort="nat">外來/栽培</button></th>
                    <th colspan="2"><button class="sort px-4 py-2 w-full  bg-lime-200/50" data-sort="cov2010">2010 覆蓋度</button></th>
                    <th colspan="2"><button class="sort px-4 py-2 w-full  bg-orange-200" data-sort="cov2025">覆蓋度</button></th>
                </tr>
            </thead>
        <tbody class="list">
            @foreach ($plotplantList as $item)
@php
    $inatLink = "https://taiwan.inaturalist.org/search?q=" . urlencode($item['chname'] ?? '');
@endphp

            <tr
                class="group hover:bg-amber-800/10 {{ $item['chfamily'] === '--' ? 'bg-red-100 text-red-800' : ($loop->even ? 'bg-gray-50' : 'bg-white') }}"
                style="cursor: pointer;"
                onclick="window.open('{{ $inatLink }}', '_blank')"

            >
                <td class="group-hover:bg-amber-800/10 chfamily px-4 py-2 border-b ">{{ $item['chfamily'] }}</td>
                <td class="group-hover:bg-amber-800/10 chname px-4 py-2 border-b ">{{ $item['chname'] }}</td>
                <td class="group-hover:bg-amber-800/10 nat border-b px-4 py-2 text-center">{{ $item['nat_type'] }}</td>
                <td class="group-hover:bg-amber-800/10 border-b px-4 py-2  bg-lime-200/50 text-center">{{ $item['plot2010'] }}</td>
                <td class="group-hover:bg-amber-800/10 sub2010 border-b px-4 py-2  bg-lime-200/50 text-center" data-sort="{{ $item['sub2010'] ?? 0 }}">{{ $item['sub2010'] }}</td>
                <td class="group-hover:bg-amber-800/10 cov2010 border-b pl-4 py-2  bg-lime-200/50 text-right" data-sort="{{ $item['cov2010_sort'] ?? 0 }}">{{ $item['cov2010'] }}</td>
                <td class="group-hover:bg-amber-800/10 border-b pr-4 py-2   bg-lime-200/50 text-left">{{ $item['sd2010'] }}</td>
                
                <td class="group-hover:bg-amber-800/10 border-b px-4 py-2  bg-orange-200 text-center">{{ $item['plot2025'] }}</td>
                <td class="group-hover:bg-amber-800/10 sub2025 border-b px-4 py-2  bg-orange-200 text-center" data-sort="{{ $item['sub2025'] ?? 0 }}">{{ $item['sub2025'] }}</td>
                <td class="group-hover:bg-amber-800/10 cov2025 border-b pl-4 py-2 bg-orange-200 text-right" data-sort="{{ $item['cov2025_sort'] ?? 0 }}">{{ $item['cov2025'] }}</td>
                <td class="group-hover:bg-amber-800/10 border-b pr-4 py-2 bg-orange-200 text-left">{{ $item['sd2025'] }}</td>

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
    console.log("🟡 plantListLoaded received");

    // 等 DOM 插入完成
    setTimeout(() => {
        if (window.plantListSorter) {
            // ⚠ 不 destroy，避免干擾 DOM
            window.plantListSorter.reIndex(); // ✅ 讓 sorter 讀到最新 DOM
            console.log("🔁 plantListSorter reIndex 完成");
        } else {
            // 第一次初始化
            window.plantListSorter = new List("plant-list-wrapper", {
                valueNames: [
                    'chfamily', 'chname', 'nat',
                    { name: 'cov2010', attr: 'data-sort' },
                    { name: 'cov2025', attr: 'data-sort' },
                    { name: 'sub2010', attr: 'data-sort' },
                    { name: 'sub2025', attr: 'data-sort' }
                ]
            });
            console.log("✅ plantListSorter 初始化成功");
        }
    }, 200); // 讓 DOM 有足夠時間插入
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
        valueNames: ['chfamily', 'chname', 'nat', { name: 'cov2010', attr: 'data-sort' }, { name: 'cov2025', attr: 'data-sort' }, { name: 'sub2010', attr: 'data-sort' }, { name: 'sub2025', attr: 'data-sort' }],
    });


    // ✅ 排序切換
document.querySelector("#plant-list-wrapper").addEventListener("click", (event) => {
    const btn = event.target.closest(".sort");
    if (!btn || !window.plantListSorter) return;

    const sortField = btn.dataset.sort;
    currentSortOrder = (currentSortField === sortField && currentSortOrder === 'asc') ? 'desc' : 'asc';
    currentSortField = sortField;

    btn.setAttribute("data-order", currentSortOrder);
    document.querySelectorAll(".sort").forEach(b => {
        if (b !== btn) b.removeAttribute('data-order');
    });

    // ✅ 確保排序前有更新 list items
    plantListSorter.reIndex();

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

