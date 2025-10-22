{{-- livewire/data-export.blade.php --}}
<div>
    <div wire:loading.class="flex" wire:loading.remove.class="hidden"
        class="hidden fixed top-0 left-0 w-full h-full z-50 bg-white/50 items-center justify-center">
        <div class="w-10 h-10 border-4 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
    </div>
    <h2 class="text-xl font-bold mb-4">資料匯出</h2>
    <div class="space-y-4">
        <div class="md:flex md:flex-row gap-4 mb-4">
            <!-- 選擇年分 -->

            <div class="md:flex md:flex-row md:items-center gap-2">
                <label class="block font-semibold md:mr-2">選擇年分：</label>
                <select id="year" wire:model="thisCensusYear" class="border rounded p-2 w-[100px]">
                    <option value="">- 請選擇 -</option>
                    @foreach ($yearList as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <!-- 選擇樣區 -->

            <div class="md:flex md:flex-row md:items-center gap-2">
                <label class="block font-semibold md:mr-2">選擇團隊：</label>
                <select id="team" wire:model="thisTeam" class="border rounded p-2 w-[130px]"
                    wire:change="loadCountyList($event.target.value)">
                    <option value="">-- 請選擇 --</option>
                    <option value="All">All</option>
                    @foreach ($teamList as $team)
                        <option value="{{ $team }}">{{ $team }}</option>
                    @endforeach
                </select>
            </div>
            <!-- 選擇縣市 -->
            <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
                <label class="block font-semibold md:mr-2">選擇縣市：</label>
                <select id="county" wire:model="thisCounty" class="border rounded p-2 w-40"
                    wire:change="surveryedPlotInfo($event.target.value)">
                    <option value="">-- 請選擇 --</option>
                    <option value="All">All</option>
                    @foreach ($countyList as $county)
                        <option value="{{ $county }}">{{ $county }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if ($thisCensusYear && $thisTeam && $thisCounty)

            @if ($allPlotInfo)
                <div class="gray-card w-fit mb-6">
                    <h3>樣區列表</h3>
                    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6" role="alert">
                        <p class="font-bold">⚠️ 重要提醒</p>
                        <ul class="list-disc pl-5 space-y-1 mt-2 text-sm">
                            <li>列出選擇縣市中，已有輸入資料的所有樣區（以樣區為單位）。</li>
                            <li>樣區是否完成調查，以是否上傳紙本資料為依據。</li>
                            <li>預設勾選所有樣區，可自行取消不需下載者。</li>
                            <li>選擇下載資料內容與格式：預設為 Excel（.xlsx），包含環境資料、植物資料與植物名錄。亦可選擇文字檔（.txt，Tab 分隔），三種資料內容需分別下載。</li>
                            <li><a href='https://hospitable-nickel-b27.notion.site/234ed0b14d7e802b84b0c61fbe3a3e2a'
                                    target='_blank'>資料欄位說明</a></li>
                        </ul>
                    </div>
                    <table class="text-sm border border-gray-300">
                        <thead style="background-color: #F9E7AC;">
                            <tr>
                                <th class="border-b px-4 py-2">下載</th>
                                <th class="border-b px-4 py-2">縣市</th>
                                <th class="border-b px-4 py-2">樣區編號</th>
                                <th class="border-b px-4 py-2">樣區調查完成</th>
                            </tr>
                        </thead>

                        <tbody class="group hover:bg-amber-800/10 cursor-pointer bg-white">
                            @foreach ($allPlotInfo as $index => $row)
                                <tr>
                                    <td class="border-b px-4 py-2 text-center align-middle">
                                        <input type="checkbox" wire:model="selectedPlots" value="{{ $row['plot'] }}">
                                    </td>
                                    <td class="border-b px-4 py-2 text-center align-top">
                                        {{ $row['county'] }}
                                    </td>
                                    <td class="border-b px-4 py-2 text-center align-top">
                                        {{ $row['plot'] }}
                                    </td>
                                    <td class="border-b px-4 py-2 text-center align-middle">
                                        @if ($row['completed'])
                                            ✔️
                                        @else
                                            <span class="text-gray-400 text-xs">—</span>
                                        @endif
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="font-semibold mt-6">選擇下載資料內容與格式：</p>
                    <div class="flex items-center mt-4">
                        <label class="inline-flex items-center mr-4 cursor-pointer">
                            <input type="radio" name="downloadFormat" wire:model="downloadFormat" value="xlsx"
                                class="form-radio text-forest focus:ring-forest">
                            <span class="ml-1">所有資料.xlsx</span>
                        </label>
                        <label class="inline-flex items-center mr-4 cursor-pointer">
                            <input type="radio" name="downloadFormat" wire:model="downloadFormat" value="xlsx.2"
                                class="form-radio text-forest focus:ring-forest">
                            <span class="ml-1">統計表格.xlsx</span>
                        </label>
                        <label class="inline-flex items-center mr-4 cursor-pointer">
                            <input type="radio" name="downloadFormat" wire:model="downloadFormat" value="xlsx.3"
                                class="form-radio text-forest focus:ring-forest">
                            <span class="ml-1">小樣方未調查原因.xlsx</span>
                        </label>
                    </div>
                    <div class="flex items-center mt-2">
                        <label class="inline-flex items-center mr-4 cursor-pointer">
                            <input type="radio" name="downloadFormat" wire:model="downloadFormat" value="txt.1"
                                class="form-radio text-forest focus:ring-forest">
                            <span class="ml-1">環境資料.txt</span>
                        </label>
                        <label class="inline-flex items-center mr-4 cursor-pointer">
                            <input type="radio" name="downloadFormat" wire:model="downloadFormat" value="txt.2"
                                class="form-radio text-forest focus:ring-forest">
                            <span class="ml-1">植物資料.txt</span>
                        </label>
                        <label class="inline-flex items-center mr-4 cursor-pointer">
                            <input type="radio" name="downloadFormat" wire:model="downloadFormat" value="txt.3"
                                class="form-radio text-forest focus:ring-forest">
                            <span class="ml-1">植物名錄.txt</span>
                        </label>

                    </div>
                    <div class='mb-4 mt-2'>
                        <label class="inline-flex items-center mr-4 cursor-pointer">
                            <input type="radio" name="downloadFormat" wire:model="downloadFormat" value="xlsx.1"
                                class="form-radio text-forest focus:ring-forest">
                            <span class="ml-1">全部植物名錄.xlsx</span>
                        </label>
                    </div>


                    <div class="mt-4 text-right">
                        <button wire:click="downloadSelected" class="btn-submit">下載選取資料</button>
                    </div>

                </div>
            @else
                <div>
                    <p>尚未有調查資料。</p>
                </div>
            @endif
        @endif

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        //監聽的名稱, select的id
        listenAndResetSelect('thisCountyUpdated', 'county');
        // listenAndResetSelect('thisHabTypeUpdated', 'habType');
        // listenAndResetSelect('thisSubPlotUpdated', 'subPlot');
    });
</script>
