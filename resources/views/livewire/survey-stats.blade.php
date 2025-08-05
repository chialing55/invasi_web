{{-- livewire/survey-stats.blade.php --}}
<div>
    <h2 class="text-xl font-bold mb-4">樣區成果初步統計</h2>
    <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
        <label class="block font-semibold md:mr-2">選擇生育地類型：</label>
        <select id='habType' wire:model="thisHabType" class="border rounded p-2 w-40"
            wire:change="habPlantInfo($event.target.value)">
            <option value="">-- 請選擇 --</option>
            @foreach ($habTypeOptions as $code => $label)
                <option value="{{ $code }}">{{ $code }} {{ $label }}</option>
            @endforeach
        </select>
    </div>
    @if ($stats != [])
        <div class="gray-card w-fit mt-6">
            <h3>{{ $habTypeName }}生育地類型物種數</h3>
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
                <thead>
                    <tr class="bg-yellow-100 text-left" style="background-color: #F9E7AC;">
                        <th class="border px-4 py-2">科名</th>
                        <th class="border px-4 py-2">中文科名</th>
                        <th class="border px-4 py-2">學名</th>
                        <th class="border px-4 py-2">中文名</th>
                        <th class="border px-4 py-2">原生</th>
                        <th class="border px-4 py-2">歸化</th>
                        <th class="border px-4 py-2">特有</th>
                        <th class="border px-4 py-2">栽培</th>
                        <th class="border px-4 py-2">IUCN</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @foreach ($habPlantList as $plant)
                        <tr
                            class="text-left {{ $loop->even ? 'bg-gray-50' : 'bg-white' }} hover:bg-amber-800/10 cursor-pointer">
                            <td class="border px-4 py-2">{{ $plant['family'] ?? '--' }}</td>
                            <td class="border px-4 py-2">{{ $plant['chfamily'] ?? '--' }}</td>
                            <td class="border px-4 py-2 italic">{{ $plant['latinname'] ?? '--' }}</td>
                            <td class="border px-4 py-2">{{ $plant['chname'] ?? '--' }}</td>

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
