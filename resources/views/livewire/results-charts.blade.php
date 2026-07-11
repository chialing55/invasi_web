<div>
    <div wire:loading.class="flex" wire:loading.remove.class="hidden"
        class="hidden fixed top-0 left-0 w-full h-full z-50 bg-white/50 items-center justify-center">
        <div class="w-10 h-10 border-4 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
    </div>

    <div class="md:flex md:flex-row md:items-center gap-4 mb-6">
        <div class="md:flex md:flex-row md:items-center">
            <label class="block font-semibold md:mr-2">選擇計畫年度：</label>
            <select id="year" wire:model.live="thisCensusYear" class="border rounded p-2 w-[100px]">
                <option value="" @selected($thisCensusYear === '')>All</option>
                @foreach ($yearList as $year)
                    <option value="{{ $year }}" @selected((string) $year === $thisCensusYear)>{{ $year }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:flex md:flex-row md:items-center">
            <label class="block font-semibold md:mr-2">選擇團隊：</label>
            <select id="team" wire:model.live="thisTeam" class="border rounded p-2 w-[130px]">
                <option value="" @selected($thisTeam === '')>All</option>
                @foreach ($teamList as $team)
                    <option value="{{ $team }}" @selected((string) $team === $thisTeam)>{{ $team }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:flex md:flex-row md:items-center">
            <label class="block font-semibold md:mr-2">選擇縣市：</label>
            <select id="county" wire:model.live="thisCounty" class="border rounded p-2 w-40">
                <option value="" @selected($thisCounty === '')>All</option>
                @foreach ($countyList as $county)
                    <option value="{{ $county }}" @selected((string) $county === $thisCounty)>{{ $county }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($message)
        <div class="font-semibold mt-6">{{ $message }}</div>
    @endif

    @if (!empty($availablePlots))
        <div class="gray-card mb-6" wire:key="plot-filter-{{ $thisCensusYear }}-{{ $thisTeam }}-{{ $thisCounty }}"
            x-data="{ mode: @entangle('plotSelectionMode').live }">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-5">
                    <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-forest">
                        <input type="radio" value="all" x-model="mode">
                        <span>全部樣區</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-forest">
                        <input type="radio" value="filter" x-model="mode">
                        <span>篩選樣區</span>
                    </label>
                </div>
                <span class="text-sm text-gray-700">
                    目前成果套用 {{ count($selectedPlots) }} / {{ count($availablePlots) }} 個樣區
                </span>
            </div>

            <div class="mt-4" x-show="mode === 'filter'" x-cloak>
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <button type="button" class="border rounded px-3 py-1 bg-white" wire:click="selectAllPlots(false)">全部取消</button>
                    <span class="text-sm text-gray-600">勾選不會立即重算，請按「套用樣區」。</span>
                </div>

                <div class="max-h-72 overflow-y-auto border border-gray-300 bg-white"
                    wire:key="plot-options-{{ $plotSelectionRevision }}">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-[#F9E7AC]">
                            <tr>
                                <th class="border-b px-4 py-2 text-center">選取</th>
                                <th class="border-b px-4 py-2 text-left">縣市</th>
                                <th class="border-b px-4 py-2 text-left">樣區編號</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($availablePlots as $row)
                                <tr class="hover:bg-amber-800/10" wire:key="result-plot-{{ $plotSelectionRevision }}-{{ $row['plot'] }}">
                                    <td class="border-b px-4 py-2 text-center">
                                        <input type="checkbox" value="{{ $row['plot'] }}" wire:model="draftSelectedPlots">
                                    </td>
                                    <td class="border-b px-4 py-2">{{ $row['county'] }}</td>
                                    <td class="border-b px-4 py-2">{{ $row['plot'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="button" class="btn-submit" wire:click="applyPlotSelection">套用樣區</button>
                </div>
            </div>
        </div>

    @endif

    @if (!empty($sections))
        <div x-data="{ tab: 'species' }">
            <div class="flex border-b border-gray-300 mb-4">
                <button type="button" @click="tab = 'species'"
                    :class="tab === 'species' ? 'bg-forest text-white' : 'bg-white text-forest'"
                    class="px-5 py-2 font-semibold border border-b-0 border-gray-300 rounded-t">
                    物種數
                </button>
                <button type="button" @click="tab = 'charts'"
                    :class="tab === 'charts' ? 'bg-forest text-white' : 'bg-white text-forest'"
                    class="px-5 py-2 font-semibold border border-b-0 border-gray-300 rounded-t">
                    成果圖表
                </button>
                <button type="button" @click="tab = 'download'"
                    :class="tab === 'download' ? 'bg-forest text-white' : 'bg-white text-forest'"
                    class="px-5 py-2 font-semibold border border-b-0 border-gray-300 rounded-t">
                    資料下載
                </button>
            </div>

            <div x-show="tab === 'charts'" class="space-y-3">
            <div class="gray-card mb-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">下載統計成果</h3>
                        <p class="text-sm text-gray-600">依目前已套用的 {{ count($selectedPlots) }} 個樣區產生統計表格與統計圖。</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-submit" wire:click="downloadStatsXlsx">統計表 xlsx</button>
                        <button type="button" class="btn-submit" wire:click="downloadStatsDocx">統計表 docx</button>
                        <button type="button" class="btn-submit" wire:click="downloadStatsPdf">統計圖 PDF</button>
                    </div>
                </div>
            </div>
            @foreach ($sections as $sectionMeta)
                @php $section = $this->displaySection($sectionMeta); @endphp
                <div class="border border-gray-300 bg-white">
                    <button type="button"
                        wire:click="toggleSection('{{ $sectionMeta['displayKey'] }}')"
                        class="w-full flex items-center justify-between gap-4 px-4 py-3 text-left font-semibold text-forest bg-forest-mist hover:bg-forest-mist/70">
                        <span>{{ $section['displayTitle'] }}</span>
                        <span>{{ $this->isOpen($sectionMeta['displayKey']) ? '收合' : '展開' }}</span>
                    </button>

                    @if ($this->isOpen($sectionMeta['displayKey']))
                        <div class="p-4 overflow-x-auto" wire:loading.class="opacity-50" wire:target="toggleSection('{{ $sectionMeta['displayKey'] }}')">
                            @if (!($section['isLoaded'] ?? false))
                                <div class="text-gray-600">載入中...</div>
                            @elseif (!empty($section['emptyMessage']))
                                <div class="text-gray-600">{{ $section['emptyMessage'] }}</div>
                            @elseif ($section['isFigure'])
                                @include('livewire.partials.results-chart-svg', ['section' => $section])
                            @else
                                @include('livewire.partials.results-table', ['section' => $section])
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
            </div>

            <div x-show="tab === 'species'" x-cloak class="gray-card">
                <p class="mb-4 text-sm text-gray-600">
                    物種數以目前已套用的 {{ count($selectedPlots) }} 個樣區為範圍；可再選擇生育地類型。
                </p>
                <livewire:survey-stats
                    :selected-plots="$selectedPlots"
                    :embedded="true"
                    :key="'species-' . md5(json_encode($selectedPlots))" />
            </div>

            <div x-show="tab === 'download'" x-cloak class="gray-card">
                <livewire:data-export
                    :selected-plots="$selectedPlots"
                    :embedded="true"
                    :this-team="$thisTeam"
                    :this-county="$thisCounty"
                    :this-census-year="$thisCensusYear"
                    :key="'downloads-' . md5(json_encode($selectedPlots))" />
            </div>
        </div>
    @endif
</div>
