<div class="space-y-4">
    <div wire:loading.class="flex" wire:loading.remove.class="hidden"
        class="fixed left-0 top-0 z-50 hidden h-full w-full items-center justify-center bg-white/50">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-gray-200 border-t-blue-500"></div>
    </div>

    <h2 class="text-xl font-bold">生育地完成例外</h2>

    <div class="flex flex-wrap gap-4">
        <div class="flex items-center gap-2">
            <label class="font-semibold" for="exception-year">選擇計畫年度：</label>
            <select id="exception-year" class="w-40 rounded border p-2"
                wire:model="thisCensusYear" wire:change="loadThisCensusYearData($event.target.value)">
                <option value="">-- All --</option>
                @foreach ($censusYearList as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <label class="font-semibold" for="exception-county">選擇縣市：</label>
            <select id="exception-county" class="w-40 rounded border p-2"
                wire:model="thisCounty" wire:change="loadPlots($event.target.value)">
                <option value="">-- 請選擇 --</option>
                @foreach ($countyList as $county)
                    <option value="{{ $county }}">{{ $county }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <label class="font-semibold" for="exception-plot">選擇樣區：</label>
            <select id="exception-plot" class="w-40 rounded border p-2"
                wire:model="thisPlot" wire:change="loadPlotInfo($event.target.value)">
                <option value="">-- 請選擇 --</option>
                @foreach ($plotList as $plot)
                    <option value="{{ $plot }}">{{ $plot }}</option>
                @endforeach
            </select>
            @error('thisPlot') <span class="text-sm text-red-700">{{ $message }}</span> @enderror
        </div>
    </div>

    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6" role="alert">
        <p class="font-bold">⚠️ 重要提醒</p>
        <ul class="list-disc pl-5 space-y-1 mt-2 text-sm">
            <li>生育地列表來自「資料輸入」頁已選擇並儲存的生育地類型。</li>
            <li>原則上每個生育地類型須調查 5 個小樣區。</li>
            <li>若現地環境無法完成 5 個，請勾選該生育地並填寫可調查數量。</li>
            <li>可調查數量只會調整完成門檻；資料、照片及樣區檔案等其他完成條件仍須符合。</li>
            <li>天然林、人工林與野化果樹林的木本及對應地被會連動設定。</li>
            <li>填寫或修改後，<b>請務必按下儲存鈕</b>，否則切換樣區或離開頁面時，所填寫內容將會遺失。</li>
        </ul>
    </div>

    @if ($thisPlot !== '' && empty($rows))
        <div class="p-4 font-semibold">此樣區尚未勾選要調查的生育地類型。</div>
    @endif

    @if (!empty($rows))
        <form wire:submit="save" class="gray-card space-y-4">
            @if (session()->has('habitatExceptionMessage'))
                <div class="text-green-800">{{ session('habitatExceptionMessage') }}</div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full border border-gray-300 bg-white text-sm">
                    <thead class="bg-[#F9E7AC]">
                        <tr>
                            <th class="border p-2">例外</th>
                            <th class="border p-2 text-left">生育地類型</th>
                            <th class="border p-2">目前已輸入數量</th>
                            <th class="border p-2">可調查數量</th>
                            <th class="border p-2">完成門檻</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            <tr wire:key="habitat-exception-{{ $thisPlot }}-{{ $index }}">
                                <td class="border p-2 text-center">
                                    <input type="checkbox" wire:model.live="rows.{{ $index }}.enabled">
                                </td>
                                <td class="border p-2">{{ $row['label'] }}</td>
                                <td class="border p-2 text-center">
                                    {{ implode('／', array_values($row['current_counts'])) }}
                                </td>
                                <td class="border p-2 text-center">
                                    @if ($row['enabled'])
                                        <input type="number" min="1" max="4"
                                            class="h-8 w-20 rounded border border-gray-300 px-2 py-1 text-center"
                                            wire:model="rows.{{ $index }}.actual_subplot_count">
                                        @error("rows.{$index}.actual_subplot_count")
                                            <div class="mt-1 text-xs text-red-700">{{ $message }}</div>
                                        @enderror
                                    @endif
                                </td>
                                <td class="border p-2 text-center font-semibold">
                                    {{ $row['enabled'] && $row['actual_subplot_count'] !== '' ? $row['actual_subplot_count'] : 5 }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <button class="btn-submit" type="submit">儲存</button>
            </div>
        </form>
    @endif
</div>
