{{-- livewire/survey-overview.blade.php --}}
<div>
    <div wire:loading.class="flex" wire:loading.remove.class="hidden"
        class="hidden fixed top-0 left-0 w-full h-full z-50 bg-white/50 items-center justify-center">
        <div class="w-10 h-10 border-4 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
    </div>
    <h2 class="text-xl font-bold mb-4">樣區完成狀況總覽</h2>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6" role="alert">
        <ul class="list-['🌼'] pl-5 space-y-1 mt-2 text-sm">
            <li>
                工作流程：野外調查→資料輸入→物種鑑定→修改紙本資料、掃描、上傳。
            </li>
            <li>是否完成紙本資料上傳，為判斷樣區調查是否完成的依據。</li>
            <li>點選下方縣市卡片以檢視各縣市的調查狀況與進度。</li>
            {{-- <li>是否完成小樣方照片上傳，為判斷小樣方調查是否完成的依據。</li> --}}
        </ul>
    </div>
@if ($thisCounty=='')
   
<div class="gray-card mb-6 space-y-3">
    <h3>各團隊調查進度 <span class='text-sm text-gray-500 ml-8'> 2025年目標: 完成 20-25 個樣區</span></h3>
    @foreach ($showTeamInfo as $row)
@php
    $target = 20;
    $plotDone = $row['completed_plots'];
    $plotTotal = 25;

    $plotPercent = $plotTotal > 0 ? round($plotDone / $plotTotal * 100) : 0;

    // 顏色根據是否達標
    $plotColor =
        $plotDone >= $plotTotal ? '#2E7D32' :      // 深綠（滿分）
        ($plotDone >= $target ? '#3B7A57' : '#F87171'); // 綠 or 紅

    $reached = $plotDone >= $target;
@endphp

<div class="flex items-center gap-2 md:gap-4">
    {{-- Team 名稱 --}}
    <div class="w-[60px] font-semibold text-sm">{{ $row['team'] }}</div>

    {{-- 完成數 + 達標狀態（手機隱藏 emoji） --}}
    <div class="w-[120px] md:w-[160px] text-sm">
        <span class="{{ $reached ? 'text-green-700 font-semibold' : 'text-red-600' }}">
            {{ $plotDone }} / {{ $plotTotal }} ({{ $plotPercent }}%)
        </span>
        <span class="hidden md:inline">
            @if ($plotDone >= $plotTotal)
                🎊 全部完成！
            @elseif ($plotDone >= $target)
                🎉 完成了！
            @elseif ($plotDone >= 15)
                🌟 就快完成了！
            @else
                💪 加油加油
            @endif
        </span>
    </div>

    {{-- 進度條（手機版寬度變小） --}}
    <div class="w-[180px] sm:w-[240px] md:w-[500px] h-4 bg-[#CBD5E0] rounded overflow-hidden">
        <div class="h-4 rounded" style="width: {{ $plotPercent }}%; background-color: {{ $plotColor }}"></div>
    </div>
</div>

    @endforeach
</div>
@endif
<div class="flex justify-start gap-8 items-start">
  
    <div>
    <div class="flex flex-wrap justify-start gap-3">
        @foreach ($showContyInfo as $row)
            @php

                $plotPercent =
                    $row['total_plots'] > 0 ? round(($row['completed_plots'] / $row['total_plots']) * 100) : 0;

                $plotColor = $plotPercent > 0 ? '#3B7A57' : '#CBD5E0'; // 森林綠 or 淺灰

            @endphp

            <div wire:click="surveryedPlotInfo('{{ $row['county'] }}')" wire:key="card-{{ $row['county'] }}" 
                class="cursor-pointer w-[200px] sm:w-[220px] md:w-[240px] rounded p-4 shadow transition bg-white hover:bg-gray-50 hover:shadow-xl">
                <div class="mb-2 flex justify-between">
                <h3 class="text-lg font-bold text-forest mb-2">{{ $row['county'] }} </h3>
                <p>{{ $row['teams'] }}</p>
                </div>

                <div class="mb-3">
                    <p class="text-sm mb-1">樣區完成數：{{ $row['completed_plots'] }} / {{ $row['total_plots'] }}</p>
                    <div class="w-full h-4 bg-[#CBD5E0] rounded overflow-hidden">
                        <div class="h-full rounded"
                            style="width: {{ $plotPercent }}%; background-color: {{ $plotColor }}"></div>
                    </div>
                </div>
            </div>
        @endforeach
    
    </div>
@if ($plotList)
    <div class="mt-4 md:mb-0">
        <label class="block font-semibold">選擇其他縣市：</label>
        <select wire:model="thisCounty" class="border rounded p-2 w-40" wire:change="surveryedPlotInfo($event.target.value)">
            <option value="">-- 全部縣市 --</option>
            @foreach ($countyList as $county)
                <option value="{{ $county }}">{{ $county }}</option>
            @endforeach
        </select>
    </div>
@endif  
    </div>
    <div>
    @if ($plotList)
        <div class="md:flex md:flex-row gap-4 mb-8">
            <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0" wire:key="refresh-{{ $refreshKey }}">
                <label class="block font-semibold">選擇樣區：</label>
                <select id="plot" wire:model="thisPlot" class="border rounded p-2 w-40"
                    wire:change="loadPlotInfo($event.target.value)">
                    <option value="">-- All --</option>
                    @foreach ($plotList as $plot)
                        <option value="{{ $plot }}">{{ $plot }}</option>
                    @endforeach
                </select>
            </div>
            @if (!empty($subPlotSummary))
                <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
                    <label class="block font-semibold md:mr-2">選擇生育地類型：</label>
                    <select id='habType' wire:model="thisHabType" class="border rounded p-2 w-40"
                        wire:change="reloadPlotInfo($event.target.value)">
                        <option value="">-- All --</option>
                        @foreach ($subPlotHabList as $code => $label)
                            <option value="{{ $code }}">{{ $code }} {{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        @if($thisPlotFile)
        <div class="mb-4">
            <a href='{{$thisPlotFile}}' target="_blank">樣區調查資料 <img src="/images/PDF_file_icon.svg" alt="PDF" class="inline w-5 h-5 mr-1"> {{$thisPlot}}.pdf</a>
        </div>
        @endif

    @endif

    @if($showAllPlotInfo)
@php
    // 先預處理資料：把每個 plot 出現的次數記錄下來
    $plotRowCounts = collect($showAllPlotInfo)
        ->groupBy('plot')
        ->map(fn($rows) => count($rows))
        ->toArray();

    $printedPlots = []; // 記錄已經輸出過的 plot
@endphp
    <div class="gray-card w-fit mb-6">
        <h2>樣區列表</h2>
<table class="text-sm border border-gray-300 w-full">
    <thead  style="background-color: #F9E7AC;">
        <tr>
            <th class="border-b px-4 py-2">樣區編號</th>
            <th class="border-b px-4 py-2">生育地類型</th>
            <th class="border-b px-4 py-2">小樣區數量</th>
            <th class="border-b px-4 py-2">樣區資料檔案</th>
        </tr>
    </thead>

    @php
        $grouped = collect($showAllPlotInfo)->groupBy('plot');
    @endphp

    @foreach ($grouped as $plot => $rows)
        <tbody class="group hover:bg-amber-800/10 cursor-pointer bg-white" wire:click="loadPlotInfo('{{  $plot }}')" wire:key="plot-{{ $plot }}">
            @foreach ($rows as $index => $row)
                <tr >
                    @if ($index === 0)
                        <td class="border-b px-4 py-2 text-center align-top" rowspan="{{ count($rows) }}">
                            {{ $row['plot'] }}
                        </td>
                    @endif

                    <td class="border-b px-4 py-2">{!! $row['hab_code'] !!} {!! $row['hab_name'] !!}</td>
                    <td class="border-b px-4 py-2 text-center">{{ $row['subplot_count_2025'] }}</td>

                    @if ($index === 0)
                        <td class="border-b px-4 py-2 text-center align-top" rowspan="{{ count($rows) }}">
                            @if ($row['plotFile'])
                                <a href="{{ $row['plotFile'] }}" target="_blank"
                                   onclick="event.stopPropagation()" class="text-blue-500 underline">
                                    {{ basename($row['plotFile']) }}
                                </a>
                            @else
                                <span class="text-gray-400 text-xs">-</span>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    @endforeach
</table>

    </div>
    @endif
    @if ($thisPlot)
        @if (!empty($filteredSubPlotSummary))

            <div class="gray-card w-fit">
                <h2>{{$thisPlot}} 調查結果</h2>

                <table class="text-sm border border-gray-300 w-full">
                    <thead class="bg-yellow-500/30">
                        <tr>
                            <th class="border-b px-4 py-2">小樣方編號</th>
                            <th class="border-b px-4 py-2">生育地</th>
                            <th class="border-b px-4 py-2">流水號</th>
                            <th class="border-b px-4 py-2">調查日期</th>
                            <th class="border-b px-4 py-2">植物筆數</th>
                            <th class="border-b px-4 py-2">未鑑定</th>
                            <th class="border-b px-4 py-2">資料錯誤</th>
                            <th class="border-b px-4 py-2">照片上傳</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $lastHabitatCode = null; @endphp
                        @foreach ($filteredSubPlotSummary as $row)
                            @php
                                $habitatChanged = $row['habitat_code'] !== $lastHabitatCode;
                                $lastHabitatCode = $row['habitat_code'];
                            @endphp
                            <tr class="hover:bg-amber-800/10 bg-white {{ $habitatChanged ? 'border-t border-gray-300' : '' }}">
                                <td class="px-4 py-2">{{ $row['plot_full_id'] }}</td>
                                <td class="px-4 py-2">{{ $row['habitat_code'] }} {{ $row['habitat'] }}</td>
                                <td class="px-4 py-2 text-center">{{ $row['subplot_id'] }}</td>
                                <td class="px-4 py-2 text-center">{{ $row['date'] }}</td>
                                <td class="px-4 py-2 text-center">{{ $row['plant_count'] }}</td>
                                <td class="px-4 py-2 text-center">{{ $row['unidentified_count'] }}</td>
                                <td class="px-4 py-2 text-center">{{ $row['data_error_count'] }}</td>
                                <td class="px-4 py-2 text-center">
                                    {!! !empty($row['uploaded_at']) ? "<a href='{$row['photo_path']}' target='_blank' class='hover:no-underline no-underline'>✅</a>" : '' !!}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            </div>
        @else
            <div>
                <p>{{$subPlotinfomessage}}</p>
            </div>
        @endif
    @endif
        </div>
</div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        //監聽的名稱, select的id
        listenAndResetSelect('thisPlotUpdated', 'plot');

    });

window.listenAndResetSelect = function ('thisPlotUpdated2', 'plot') {
    window.addEventListener(eventName, () => {
        const select = document.getElementById(selectId);
        if (!select) return;

        const componentEl = select.closest('[wire\\:id]');
        const componentId = componentEl?.getAttribute('wire:id');

        console.log(`🟡 ${eventName} 事件收到，重設 #${selectId}`);
        select.selectedIndex = 0;
    });
};

</script>

