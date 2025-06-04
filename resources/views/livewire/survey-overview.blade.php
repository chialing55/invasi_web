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
        <div class="md:flex md:flex-row md:items-center gap-2 mb-4">
            <label class="block font-semibold">選擇樣區：</label>
            <select wire:model="thisPlot" class="border rounded p-2 w-40" wire:change="loadPlotInfo($event.target.value)">
                <option value="">-- 請選擇 --</option>
                @foreach ($plotList as $plot)
                    <option value="{{ $plot }}">{{ $plot }}</option>
                @endforeach
            </select>
        </div>
        @endif        

    <div class='green-card'>
        暫定功能:
        <ul class="list-disc ml-6 space-y-2">
            <li>選擇/輸入樣區編號(或團隊名稱)後開始檢視</li>
            <li>各樣區完成之小樣區數量、生育地類型是否足夠</li>
            <li>小樣區列表各項完成指標: 環境輸入、植物調查輸入、資料上傳....</li>
        </ul>
    </div>
</div>
