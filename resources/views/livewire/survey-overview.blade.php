{{-- livewire/survey-overview.blade.php --}}
<div>
    <h2 class="text-xl font-bold mb-4">樣區完成狀況總覽</h2>
        
        <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
            <label class="block font-semibold">選擇縣市：</label>
            <select wire:model="thisCounty" class="border rounded p-2 w-40" wire:change="surveryedPlotInfo($event.target.value)">
                <option value="">-- 請選擇 --</option>
                @foreach ($countyList as $county)
                    <option value="{{ $county }}">{{ $county }}</option>
                @endforeach
            </select>
        </div>
        

@php
    if ($thisCounty) {
        $thisCountyTitle = $thisCounty;
    } else {
        $thisCountyTitle = '';
    }
@endphp

        <div class="mt-4 mb-4 gray-card text-sm text-gray-700">
            <p>                
                <b>{{ $thisCountyTitle }}</b> 共有 <span class="font-bold text-green-700">{{ $totalPlotCount }}</span> 個樣區，
                其中 <span class="font-bold text-green-700">{{ $surveyedPlotCount }}</span> 個樣區已進行調查，
                共完成 <span class="font-bold text-green-700">{{ $completedSubPlotCount }}</span> 個小樣區的調查。
            </p>
        </div>

        @if ($plotList)
        <div class="md:flex md:flex-row gap-4 mb-8">
            <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
                <label class="block font-semibold">選擇樣區：</label>
                <select id="plot" wire:model="thisPlot" class="border rounded p-2 w-40" wire:change="loadPlotInfo($event.target.value)">
                    <option value="">-- 請選擇 --</option>
                    @foreach ($plotList as $plot)
                        <option value="{{ $plot }}">{{ $plot }}</option>
                    @endforeach
                </select>
            </div>
        @if(!empty($subPlotSummary))
            <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
                <label class="block font-semibold md:mr-2">選擇生育地類型：</label>
                <select id='habType' wire:model="thisHabType" class="border rounded p-2 w-40" wire:change="reloadPlotInfo($event.target.value)">
                    <option value="">-- 請選擇 --</option>
                    @foreach ($subPlotHabList as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        </div>
        
        @endif




@if($thisPlot)
    @if (!empty($filteredSubPlotSummary))

        <div class="gray-card w-fit">
            <h2>調查結果</h2>

            <table class="text-sm border border-gray-300 w-full">
                <thead class="bg-yellow-500/30">
                    <tr>
                        <th class="border-b px-4 py-2">小樣區編號</th>
                        <th class="border-b px-4 py-2">生育地</th>
                        <th class="border-b px-4 py-2">流水號</th>                       
                        <th class="border-b px-4 py-2">植物筆數</th>
                        <th class="border-b px-4 py-2">未鑑定</th>
                        <th class="border-b px-4 py-2">覆蓋度錯誤</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filteredSubPlotSummary as $row)
                        <tr class="hover:bg-amber-800/10">
                            <td class="border-b px-4 py-2">{{ $row['plot_full_id'] }}</td>
                            <td class="border-b px-4 py-2">{{ $row['habitat'] }}</td>
                            <td class="border-b px-4 py-2 text-center">{{ $row['subplot_id'] }}</td>                  
                            <td class="border-b px-4 py-2 text-center">{{ $row['plant_count'] }}</td>
                            <td class="border-b px-4 py-2 text-center">{{ $row['unidentified_count'] }}</td>
                            <td class="border-b px-4 py-2 text-center">{{ $row['cov_error_count'] }}</td>
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
<script>

    document.addEventListener('DOMContentLoaded', function () {
        //監聽的名稱, select的id
        listenAndResetSelect('thisPlotUpdated', 'plot');
    });
</script>