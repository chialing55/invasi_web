@php
    // 傳入：
    // $subPlotTeam: [['team'=>'NTU','herb_plots'=>..., 'woody_plots'=>..., /* 可選: 'plot_count'=>8 */], ...]
    // $subPlantTeam: [['team'=>'NTU','herb_plants'=>..., 'woody_plants'=>..., 'total_plants'=>...], ...]
    $plotMap = collect($subPlotTeam ?? [])->keyBy('team');
    $plantMap = collect($subPlantTeam ?? [])->keyBy('team');

    $teamNames = $plotMap
        ->keys()
        ->merge($plantMap->keys())
        ->unique()
        ->values()
        ->sortByDesc(function ($t) use ($plotMap, $plantMap) {
            $hp = (int) ($plotMap[$t]['herb_plots'] ?? 0);
            $wp = (int) ($plotMap[$t]['woody_plots'] ?? 0);
            $tp = (int) ($plantMap[$t]['total_plants'] ?? 0);
            return [$hp + $wp, $tp];
        })
        ->values();

    // 定義表格列
    $rows = [
        ['label' => '一平方公里樣區數', 'get' => fn($t) => $plotMap[$t]['total_plots'] ?? ''], // 若沒有就留空
        ['label' => '草本小樣方數', 'get' => fn($t) => (int) ($plotMap[$t]['herb_plots'] ?? 0)],
        ['label' => '木本小樣方數', 'get' => fn($t) => (int) ($plotMap[$t]['woody_plots'] ?? 0)],
        ['label' => '草本樣方植物資料筆數', 'get' => fn($t) => (int) ($plantMap[$t]['herb_plants'] ?? 0)],
        ['label' => '木本樣方植物資料筆數', 'get' => fn($t) => (int) ($plantMap[$t]['woody_plants'] ?? 0)],
    ];
@endphp



<div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
    <table class="min-w-[880px] w-full text-sm">
        <thead class="bg-forest-mist/50 text-gray-700">
            <tr>
                <th class="px-3 py-2 text-left w-48">資料項目</th>
                @foreach ($teamNames as $team)
                    <th class="px-2 py-2 text-right">{{ $team }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach ($rows as $row)
                <tr class="hover:bg-forest-mist/30">
                    <td class="px-3 py-2 font-medium text-gray-800">{{ $row['label'] }}</td>
                    @foreach ($teamNames as $team)
                        @php $v = $row['get']($team); @endphp
                        <td class="px-2 py-2 text-right tabular-nums">{{ $v === '' ? '' : number_format($v) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<p class="mt-2 text-xs text-gray-500">
    註：草本樣方 = habitat_code 01–20（排除 08、09）；木本樣方 = 08、09。
</p>
