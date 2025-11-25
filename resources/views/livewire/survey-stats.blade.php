{{-- livewire/survey-stats.blade.php --}}
<div>
    <div wire:loading.class="flex" wire:loading.remove.class="hidden"
        class="hidden fixed top-0 left-0 w-full h-full z-50 bg-white/50 items-center justify-center">
        <div class="w-10 h-10 border-4 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
    </div>
    <h2 class="text-xl font-bold mb-4">樣區成果初步統計</h2>
    <div class="md:flex md:flex-row md:items-center gap-4 mb-4 md:mb-0">

        <div class="md:flex md:flex-row md:items-center">
            <label class="block font-semibold md:mr-2">選擇計畫年度：</label>
            <select id="year" wire:model="thisCensusYear" class="border rounded p-2 w-[100px]">
                <option value="">- 請選擇 -</option>
                <option value="All">All</option>
                @foreach ($yearList as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <!-- 選擇樣區 -->

        <div class="md:flex md:flex-row md:items-center">
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
        <div class="md:flex md:flex-row md:items-center mb-4 md:mb-0">
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
        <div class="md:flex md:flex-row md:items-center mb-4 md:mb-0">
            <label class="block font-semibold md:mr-2">選擇生育地類型：</label>
            <select id='habType' wire:model="thisHabType" class="border rounded p-2 w-40"
                wire:change="habPlantInfo($event.target.value)">
                <option value="">-- 請選擇 --</option>
                <option value="All">All</option>
                @foreach ($habTypeOptions as $code => $label)
                    <option value="{{ $code }}">{{ $code }} {{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    @if ($stats != [])
        <div class="gray-card w-fit mt-6">
            <h3>{{ $thisTeam }} {{ $thisCounty }} {{ $habTypeName }} 生育地類型物種數</h3>
            <div class="mb-6">
                <p>{{ $habTypeName }}生育地類型，共記錄到 {{ $stats['total_species'] }} 種植物，分屬 {{ $stats['total_families'] }}
                    科、{{ $stats['total_genera'] }} 屬。</p>
                <p>其中歸化植物共 {{ $stats['naturalized_species'] }} 種，占全部物種之 {{ $stats['naturalized_ratio'] }}%；原生植物
                    {{ $stats['native_species'] }} 種，包含特有種 {{ $stats['endemic_species'] }} 種。</p>

                <p>植物生長習性以{{ $stats['growth_form'][0]['growth_form'] }}為最多數，占全部物種之
                    {{ round(($stats['growth_form'][0]['growth_form_count'] / $stats['total_species']) * 100, 1) }}%；其次為{{ $stats['growth_form'][1]['growth_form'] }}，約佔
                    {{ round(($stats['growth_form'][1]['growth_form_count'] / $stats['total_species']) * 100, 1) }}%
                    @if (count($stats['growth_form']) > 2)
                        ；以{{ $stats['growth_form'][count($stats['growth_form']) - 1]['growth_form'] }}為最少，約占
                        {{ round(($stats['growth_form'][count($stats['growth_form']) - 1]['growth_form_count'] / $stats['total_species']) * 100, 1) }}%
                    @endif
                    。
                </p>
                @if ($stats['naturalized_species'] > 0)
                    <p>歸化植物共{{ $stats['naturalized_species'] }}種，分屬{{ $stats['naturalized_families'] }}科，{{ $stats['naturalized_genera'] }}個屬。以{{ $stats['naturalized_growth_form'][0]['growth_form'] }}最常見，佔全部歸化植物之
                        {{ round(($stats['naturalized_growth_form'][0]['growth_form_count'] / $stats['naturalized_species']) * 100, 1) }}%。
                    </p>
                @endif
            </div>

            {{-- table --}}

            <table class="text-sm border border-gray-300">
                <thead class="sm:table-header-group sm:sticky sm:top-0 sm:z-10" style="background-color: #F9E7AC;">
                    <tr class="border-b border-gray-300 ">
                        <x-th-sort field="family" :sort-field="$sortField" :sort-direction="$sortDirection">科名</x-th-sort>
                        <x-th-sort field="chfamily" :sort-field="$sortField" :sort-direction="$sortDirection">中文科名</x-th-sort>
                        <x-th-sort field="latinname" :sort-field="$sortField" :sort-direction="$sortDirection">學名</x-th-sort>
                        <x-th-sort field="chname" :sort-field="$sortField" :sort-direction="$sortDirection">中文名</x-th-sort>
                        <x-th-sort field="growth_form" :sort-field="$sortField" :sort-direction="$sortDirection">生長型</x-th-sort>
                        <x-th-sort field="native" :sort-field="$sortField" :sort-direction="$sortDirection">原生</x-th-sort>
                        <x-th-sort field="naturalized" :sort-field="$sortField" :sort-direction="$sortDirection">歸化</x-th-sort>
                        <x-th-sort field="endemic" :sort-field="$sortField" :sort-direction="$sortDirection">特有</x-th-sort>
                        <x-th-sort field="cultivated" :sort-field="$sortField" :sort-direction="$sortDirection">栽培</x-th-sort>
                        <x-th-sort field="IUCN" :sort-field="$sortField" :sort-direction="$sortDirection">IUCN</x-th-sort>
                    </tr>
                </thead>
                <tbody class="list">
                    @foreach ($habPlantList as $plant)
                        <tr
                            class="text-left {{ $loop->even ? 'bg-gray-50' : 'bg-white' }} hover:bg-amber-800/10 cursor-pointer">
                            <td class="border px-4 py-2">{{ $plant['family'] ?? '--' }}</td>
                            <td class="border px-4 py-2">{{ $plant['chfamily'] ?? '--' }}</td>
                            <td class="border px-4 py-2 italic">{!! $plant['latinname_html'] ?? '--' !!}</td>
                            <td class="border px-4 py-2">{{ $plant['chname'] ?? '--' }}</td>
                            <td class="border px-4 py-2">{{ $plant['growth_form'] ?? '--' }}</td>

                            <td class="border px-4 py-2">
                                {!! $plant['native'] ?? 0 ? '✔' : '' !!}
                            </td>
                            <td class="border px-4 py-2">
                                {!! $plant['naturalized'] ?? 0 ? '✔' : '' !!}
                            </td>
                            <td class="border px-4 py-2">
                                {!! $plant['endemic'] ?? 0 ? '✔' : '' !!}
                            </td>
                            <td class="border px-4 py-2">
                                {!! $plant['cultivated'] ?? 0 ? '✔' : '' !!}
                            </td>
                            <td class="border px-4 py-2">
                                {{ $plant['IUCN'] ?? '' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>
    @endif
    @if ($message)
        <div class="font-semibold mt-6">
            {{ $message }}
        </div>
    @endif
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        //監聽的名稱, select的id
        listenAndResetSelect('thisCountyUpdated', 'county');
        listenAndResetSelect('thisHabTypeUpdated', 'habType');
        // listenAndResetSelect('thisSubPlotUpdated', 'subPlot');
    });
</script>
