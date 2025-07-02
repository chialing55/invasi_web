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
            <li>是否完成小樣方照片上傳，為判斷小樣方調查是否完成的依據。</li>
        </ul>
    </div>
<div class="flex justify-start gap-8 items-start">
    <div>
    <div class="flex flex-wrap justify-start gap-3">
        @foreach ($showContyInfo as $row)
            @php

                $plotPercent =
                    $row['total_plots'] > 0 ? round(($row['completed_plots'] / $row['total_plots']) * 100) : 0;
                $subplotPercent =
                    $row['total_subplots'] > 0 ? round(($row['completed_subplots'] / $row['total_subplots']) * 100) : 0;

                $plotColor = $plotPercent > 0 ? '#3B7A57' : '#CBD5E0'; // 森林綠 or 淺灰
                $subplotColor = $subplotPercent > 0 ? '#3C8DAD' : '#CBD5E0'; // 湖藍 or 淺灰

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

                <div>
                    <p class="text-sm mb-1">小樣方完成數：{{ $row['completed_subplots'] }} / {{ $row['total_subplots'] }}</p>
                    <div class="w-full h-4 bg-[#CBD5E0] rounded overflow-hidden">
                        <div class="h-full rounded"
                            style="width: {{ $subplotPercent }}%; background-color: {{ $subplotColor }}"></div>
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
            @if (!empty($subPlotSummary))
                <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
                    <label class="block font-semibold md:mr-2">選擇生育地類型：</label>
                    <select id='habType' wire:model="thisHabType" class="border rounded p-2 w-40"
                        wire:change="reloadPlotInfo($event.target.value)">
                        <option value="">-- All --</option>
                        @foreach ($subPlotHabList as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
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

<table class="table-auto w-full border text-sm">
    <thead class="bg-gray-100">
        <tr>
            <th class="border px-2 py-1">plot</th>
            <th class="border px-2 py-1">hab</th>
            <th class="border px-2 py-1">2010數量</th>
            <th class="border px-2 py-1">2025數量</th>
            <th class="border px-2 py-1">file</th>
        </tr>
    </thead>
    <tbody>
        @foreach($showAllPlotInfo as $row)
            <tr>
                {{-- 合併 plot --}}
                @if (!in_array($row['plot'], $printedPlots))
                    @php $rowspan = $plotRowCounts[$row['plot']]; @endphp
                    <td class="border px-2 py-1 text-center align-top" rowspan="{{ $rowspan }}">
                        {{ $row['plot'] }}
                    </td>
                @endif

                <td class="border px-2 py-1">{{ $row['hab_name'] }}</td>
                <td class="border px-2 py-1 text-center">{{ $row['subplot_count_2010'] }}</td>
                <td class="border px-2 py-1 text-center">{{ $row['subplot_count_2025'] }}</td>

                {{-- 合併 file --}}
                @if (!in_array($row['plot'], $printedPlots))
                    <td class="border px-2 py-1 text-center align-top" rowspan="{{ $rowspan }}">
                        @if ($row['plotFile'])
                            <a href="{{ $row['plotFile'] }}" target="_blank" class="text-blue-500 underline">
                                {{ basename($row['plotFile']) }}
                            </a>
                        @else
                            <span class="text-gray-400 text-xs">無</span>
                        @endif
                    </td>
                    @php $printedPlots[] = $row['plot']; @endphp
                @endif
            </tr>
        @endforeach
    </tbody>
</table>

    @endif
    @if ($thisPlot)
        @if (!empty($filteredSubPlotSummary))

            <div class="gray-card w-fit">
                <h2>調查結果</h2>

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
                        @foreach ($filteredSubPlotSummary as $row)
                            <tr class="hover:bg-amber-800/10">
                                <td class="border-b px-4 py-2">{{ $row['plot_full_id'] }}</td>
                                <td class="border-b px-4 py-2 text-center">{{ $row['habitat_code'] }} - {{ $row['habitat'] }}</td>
                                <td class="border-b px-4 py-2 text-center">{{ $row['subplot_id'] }}</td>
                                <td class="border-b px-4 py-2 text-center">{{ $row['date'] }}</td>
                                <td class="border-b px-4 py-2 text-center">{{ $row['plant_count'] }}</td>
                                <td class="border-b px-4 py-2 text-center">{{ $row['unidentified_count'] }}</td>
                                <td class="border-b px-4 py-2 text-center">{{ $row['data_error_count'] }}</td>
                                <td class="border-b px-4 py-2 text-center">
                                    {{ $row['uploaded_at'] ? '✅' : '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            </div>
        @else
            <div>
                <p>該樣區尚無調查資料</p>
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
</script>
