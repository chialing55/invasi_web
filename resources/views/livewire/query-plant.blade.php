{{-- livewire/query-plant.blade.php --}}
<div>
    <div
        wire:loading.class="flex"
        wire:loading.remove.class="hidden"
        wire:target="plantCode"
        class="hidden fixed top-0 left-0 w-full h-full z-50 bg-white/50 items-center justify-center"
    >
        <div class="w-10 h-10 border-4 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
    </div>
<!-- wire:target="plantCode,submitData,tableData" -->
    <h1>植物查詢</h1>
<!-- 查詢輸入框 -->
    <div>
        <div class="mb-4">
        <label for="plant-select" class="block font-bold mb-2">輸入植物名稱：</label>
    
        <input
            list="plants"            
            id="plant-select"
            class="border rounded px-2 py-1 w-full max-w-[600px]"
            placeholder="輸入或選擇植物中名/學名/科名"
        />
        <input id='plant-code' type='hidden'/>
    
        <datalist id="plants">
            @foreach ($suggestions as $item)
                <option 
                    value="{{ $item['label'] }} / {{ $item['family'] }}"
                    data-label="{{ $item['label'] }}"
                    data-spcode="{{ $item['spcode'] }}"
                   
                ></option>
            @endforeach
        </datalist>
        </div>
    </div>
@if($spnameInfo!=[])
<!--有查詢結果  -->
<!-- 植物名稱 -->
 @php
    $inatLink = "https://taiwan.inaturalist.org/search?q=" . urlencode($spnameInfo['chname'] ?? '');
@endphp
    <div class="mt-8">
        <h2><a href="{{ $inatLink }}" target="_blank"
                class=" hover:underline">
                    <strong>{{ $spnameInfo['chname'] }}</strong>
                    <strong><i>{{ $spnameInfo['simname'] }}</i></strong>
                </a></h2>

        <p><strong>{{$spnameInfo['chfamily']}}  {{ $spnameInfo['family'] }} </strong></p>
        @if($chnameIndex!=[] && $chnameIndex[0]['chname_index']!='')
        <p>中文別名: 
        @foreach ($chnameIndex as $item)
            
            <span class="inline-block mr-2">{{ $item['chname_index'] }}</span>
        @endforeach
        </p>
        @endif
    </div>
<!-- 新增chnameIndex -->
    <button wire:click="toggle" class="btn-add">
        {{ $showTable ? '關閉新增' : '新增/修改中文別名' }}
    </button>
    <div class="mt-4">
    @if (session()->has('chIndexMessage'))
        <div class="mb-2 text-red-800">{{ session('chIndexMessage') }}</div>
    @endif

        <div id="chname-table-wrapper" class="{{ $showTable ? 'md:flex md:gap-4 md:items-end' : 'hidden' }} ">
            <div id="tabulator-table" wire:ignore class="w-full md:w-[441.6px]"></div>
            <div class='mt-4 md:ml-8 md:mt-0'><button id="submit-btn" class="btn-submit" type="button">新增/修改</button></div > 
        </div>
    </div>
   

    
<!--前次調查結果  -->
    <div class="mt-8">
        @if($filteredComparisonTable!=[])
    <div class="white-card w-fit">
        <h2 class="text-lg font-semibold text-green-800 mb-4">調查結果</h2>

    <div class="bg-forest-mist rounded-md p-4 text-sm mb-4 leading-relaxed">
        <ul class="list-disc list-inside space-y-1">
            <li>預設依「縣市」排序，可點選各欄位標題重新排序。</li>
            <li>覆蓋度欄位的格式為：<span class="font-semibold">平均值 ± 標準差</span>。</li>
        </ul>
    </div>
        <!-- 選擇縣市 -->
        <div class="md:flex md:flex-row md:items-center gap-2 mb-4">
            <label class="block font-semibold md:mr-2">選擇縣市：</label>
            <select wire:model="thisCounty" class="border rounded p-2 w-40" wire:change="reloadPlantInfo($event.target.value)">
                <option value="">-- All --</option>
                @foreach ($countyList as $county)
                    <option value="{{ $county }}">{{ $county }}</option>
                @endforeach
            </select>
        </div>
<table class="text-sm border border-gray-300 w-full">
    <!-- 桌機版表頭 -->
    <thead class="bg-green-50 hidden sm:table-header-group sm:sticky sm:top-0 sm:z-10">
        <tr class="border-b border-gray-300 ">
            <th rowspan="2" class="px-4 py-2 cursor-pointer" wire:click="sortBy('county')">
                縣市
                @if ($sortField === 'county')
                    {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                @endif
            </th>
            <th rowspan="2" class="px-4 py-2 cursor-pointer" wire:click="sortBy('habitat')">
                生育地類型
                @if ($sortField === 'habitat')
                    {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                @endif
            </th>
            <th colspan="3" class="px-4 py-2 text-center bg-green-200">2010</th>
            <th colspan="3" class="px-4 py-2 text-center bg-orange-200">2025</th>
        </tr>
        <tr class="border-b border-gray-300">
            <th class="px-4 py-2 text-center bg-green-200 cursor-pointer" wire:click="sortBy('plot_2010')">
                樣區數
                @if ($sortField === 'plot_2010')
                    {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                @endif
            </th>
            <th class="px-4 py-2 text-center bg-green-200 cursor-pointer" wire:click="sortBy('sub_2010')">
                小樣區數
                @if ($sortField === 'sub_2010')
                    {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                @endif
            </th>
            <th class="px-4 py-2 text-center bg-green-200 cursor-pointer" wire:click="sortBy('cov_2010')">
                覆蓋度
                @if ($sortField === 'cov_2010')
                    {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                @endif
            </th>
            <th class="px-4 py-2 text-center bg-orange-200 cursor-pointer" wire:click="sortBy('plot_2025')">
                樣區數
                @if ($sortField === 'plot_2025')
                    {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                @endif
            </th>
            <th class="px-4 py-2 text-center bg-orange-200 cursor-pointer" wire:click="sortBy('sub_2025')">
                小樣區數
                @if ($sortField === 'sub_2025')
                    {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                @endif
            </th>
            <th class="px-4 py-2 text-center bg-orange-200 cursor-pointer" wire:click="sortBy('cov_2025')">
                覆蓋度
                @if ($sortField === 'cov_2025')
                    {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                @endif
            </th>
        </tr>
    </thead>

    <!-- 手機版表頭 -->
    <thead class="bg-green-50 sm:hidden sticky top-0 z-10">
        <tr class="border-b border-gray-300">
            <th class="px-4 py-2">縣市</th>
            <th class="px-4 py-2">生育地</th>
            <th class="px-4 py-2 text-center bg-green-200">2010 覆蓋度</th>
            <th class="px-4 py-2 text-center bg-orange-200">2025 覆蓋度</th>
        </tr>
    </thead>

    <tbody>
    @php $prevCounty = null; @endphp
    @foreach ($filteredComparisonTable as $row)
        @php
            $borderClass = ($prevCounty && $prevCounty !== $row['county']) ? 'border-t border-gray-300' : '';
            $prevCounty = $row['county'];
        @endphp
        <tr class=" {{ $borderClass }}">
            <td class="px-4 py-2 ">{{ $row['county'] }}</td>
            <td class="px-4 py-2">{{ $row['habitat'] }}</td>
            <td class="px-4 py-2 text-center  bg-green-100 hidden sm:table-cell">{{ $row['plot_2010'] }}</td>
            <td class="px-4 py-2 text-center  bg-green-100 hidden sm:table-cell">{{ $row['sub_2010'] }}</td>
            <td class="px-4 py-2 text-center  bg-green-100">{{ $row['cov_sd_2010'] }}</td>
            <td class="px-4 py-2 text-center  bg-orange-100 hidden sm:table-cell">{{ $row['plot_2025'] }}</td>
            <td class="px-4 py-2 text-center  bg-orange-100 hidden sm:table-cell">{{ $row['sub_2025'] }}</td>
            <td class="px-4 py-2 text-center  bg-orange-100">{{ $row['cov_sd_2025'] }}</td>
        </tr>
    @endforeach
    </tbody>

</table>

    </div>

        @else
        <div class="mt-4">
            <h2>調查結果</h2>
            <p>尚無調查資料</p>
        </div>
        @endif
       
    </div>
@endif


</div>

<script>

    window.chnameIndexTable = null; // 全域變數，存放 Tabulator 實例
    // 監聽輸入框的變化

    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('plant-select');
        const input2 = document.getElementById('plant-code');
        input.addEventListener('input', function () {
            const componentId = input.closest('[wire\\:id]')?.getAttribute('wire:id');
            const componentId2 = input2.closest('[wire\\:id]')?.getAttribute('wire:id');

            if (componentId && window.Livewire && typeof Livewire.find === 'function') {
                const rawValue = input.value;
                const matchedOption = document.querySelector(`#plants option[value="${rawValue}"]`);
                // console.log('matchedOption', matchedOption.value);
                if (matchedOption) {
                    const spcode = matchedOption.getAttribute('data-spcode');
                    // console.log('spcode', spcode);
                    Livewire.find(componentId2)?.set('plantCode', spcode);
                    Livewire.find(componentId2)?.call('plantInfo', spcode);  

                } else {
                    const spcode = '';
                }
                
                const parts = rawValue.split(' / ');
                const plantName = parts[0].trim();

                // 設定 Livewire 的變數
                Livewire.find(componentId)?.set('plantName', plantName);  
                       
            }
        });
    });

    // 監聽 Livewire 的事件

    window.addEventListener('plant-name-selected', (event) => {
        const input = document.getElementById('plant-select');
        input.value = '';
        if (window.chnameIndexTable != null) {
            console.log("🔁 已有表格，清掉");
            
            resetAndInitTabulator();
        } else {
            console.log("🆕 沒有表格");
        }
        
    });

    window.addEventListener('chname_index_table', (event) => {
        const data = event.detail.data; 
        if (window.chnameIndexTable != null) {

            requestAnimationFrame(() => {
                if (window.chnameIndexTable instanceof Tabulator) {
                    window.chnameIndexTable.redraw(true);
                }
            });
            console.log("🔁 已有表格");

        } else {
            console.log("🆕 沒有表格，新建");
            initTabulatorStart(data.data, data.spcode);
        }      

    });

    window.addEventListener('sync-complete', (event) => {
        const data = event.detail.data; 
        console.log("data.data", data.data);
        console.log("🔁 已有表格，用 replaceData");
        window.chnameIndexTable.replaceData(data.data);
    });

    // 摧毀表格
    function resetAndInitTabulator(containerId = 'tabulator-table') {
        const tabulatorDiv = document.getElementById(containerId);

        if (!tabulatorDiv) {
            console.warn(`❌ 找不到 #${containerId}`);
            return;
        }

        // 1. 銷毀舊表格
        if (window.chnameIndexTable instanceof Tabulator) {
            console.log("🧹 銷毀舊 Tabulator");
            window.chnameIndexTable.destroy();
            window.chnameIndexTable = null;
        }

        // 2. 清除 DOM 殘留
        tabulatorDiv.innerHTML = '';
        tabulatorDiv.classList.remove('tabulator', 'tabulator-initialized');

    }


    function initTabulatorStart (tableData, thisSpcode) {
        const columns = [
            { title: "#", formatter: "rownum", width: 40, hozAlign: "center", headerSort: false },
            { title: "中文別名", field: "chname_index", editor: "input", width: 200},
            { title: "備註", field: "note", editor: "input", width:200},
            { title: "spcode", field: "spcode", visible: false},
            { title: "id", field: "id", visible: false },
        ];

        initTabulator({
            tableData: tableData,
            elementId: 'tabulator-table',
            columns: columns,
            livewireField: 'chnameIndex',
            presetKey: 'spcode',
            presetValue: thisSpcode,
            globalName: 'chnameIndexTable',
        });
    }


    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'submit-btn') {
            // console.log(window.chnameIndexTable);
            const data = window.chnameIndexTable.getData();
            const componentId = document.querySelector('[wire\\:id]')?.getAttribute('wire:id');
            if (componentId) {
                Livewire.find(componentId).set('chnameIndex', data);
                Livewire.find(componentId).call('saveChnameIndex');
            }
        }
    });



</script>
