<div>
    <div wire:loading.class="flex" wire:loading.remove.class="hidden"
        class="hidden fixed top-0 left-0 w-full h-full z-50 bg-white/50 items-center justify-center">
        <div class="w-10 h-10 border-4 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
    </div>

    <div class="md:flex md:flex-row md:items-center gap-4 mb-6">
        <div class="md:flex md:flex-row md:items-center">
            <label class="block font-semibold md:mr-2">選擇計畫年度：</label>
            <select id="year" wire:model="thisCensusYear" wire:change="loadYear($event.target.value)" class="border rounded p-2 w-[100px]">
                <option value="">All</option>
                @foreach ($yearList as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:flex md:flex-row md:items-center">
            <label class="block font-semibold md:mr-2">選擇團隊：</label>
            <select id="team" wire:model="thisTeam" class="border rounded p-2 w-[130px]"
                wire:change="loadCountyList($event.target.value)">
                <option value="">All</option>
                @foreach ($teamList as $team)
                    <option value="{{ $team }}">{{ $team }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:flex md:flex-row md:items-center">
            <label class="block font-semibold md:mr-2">選擇縣市：</label>
            <select id="county" wire:model="thisCounty" class="border rounded p-2 w-40"
                wire:change="loadResultsForCounty($event.target.value)">
                <option value="">-- 請選擇 --</option>
                <option value="All">All</option>
                @foreach ($countyList as $county)
                    <option value="{{ $county }}">{{ $county }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($message)
        <div class="font-semibold mt-6">{{ $message }}</div>
    @endif

    @if (!empty($sections))
        <div class="space-y-3">
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
    @endif
</div>