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
            <li>樣區調查完成條件：
                <ol class="list-decimal list-inside">
                    <li>無資料錯誤（覆蓋度為 0 或資料重複）。</li>
                    <li>各生育地類型皆已完成 5 筆小樣方資料輸入（以選取的生育地類型為準）。</li>
                    <li>所有小樣方皆已上傳照片。</li>
                    <li>樣區資料已完成上傳。</li>
                </ol>
            </li>
            <li>點選下方縣市卡片以檢視各縣市的調查狀況與進度。</li>
            {{-- <li>是否完成小樣方照片上傳，為判斷小樣方調查是否完成的依據。</li> --}}
        </ul>
    </div>
    @if ($thisCounty == '')
        <h3>各團隊調查進度 <span class='text-sm text-gray-500 ml-8'> 2025年目標：完成 20-25 個 1 ×1 km<sup>2</sup>樣區</span><span
                class='text-sm text-gray-500 ml-8'>年度主題：平地</span></h3>
        <div class="gray-card mb-6 space-y-3">
            {{-- 圖說 --}}
            <div class="flex flex-wrap gap-3 text-xs text-gray-700 mt-1 justify-end">
                <div class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded-sm inline-block" style="background-color: #2E7D32"></span>
                    <span class="w-3 h-3 rounded-sm inline-block" style="background-color: #F87171"></span>
                    <span>已完成</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded-sm inline-block" style="background-color: #adaeaeff"></span>
                    <span>已調查</span>
                </div>
            </div>
            @foreach ($showTeamInfo as $row)
                @php
                    $target = 20;
                    $plotDone = $row['completed_plots'];
                    $plotEntry = $row['has_data_plots'] > 0 ? $row['has_data_plots'] - $row['completed_plots'] : 0;
                    $plotTotal = 25;

                    $plotDonePercent = $plotTotal > 0 ? round(($plotDone / $plotTotal) * 100) : 0;
                    $plotEntryPercent = $plotTotal > 0 ? round(($plotEntry / $plotTotal) * 100) : 0;
                    // 顏色根據是否達標
                    $plotDoneColor =
                        $plotDone >= $plotTotal
                            ? '#2E7D32' // 深綠（滿分）
                            : ($plotDone >= $target
                                ? '#3B7A57'
                                : '#F87171'); // 綠 or 紅
                    $plotEntryColor = $plotEntry > 0 ? '#adaeaeff' : '#CBD5E0';
                    $reached = $plotDone >= $target;
                @endphp

                <div class="flex items-center gap-2 md:gap-4">
                    {{-- Team 名稱 --}}
                    <div class="w-[60px] font-semibold text-sm">{{ $row['team'] }}</div>

                    {{-- 完成數 + 達標狀態（手機隱藏 emoji） --}}
                    <div class="w-[110px] md:w-[240px] text-sm">
                        <span
                            class="{{ $reached ? 'text-green-700 font-semibold' : 'text-red-600' }} w-[110px] inline-block">
                            {{ $plotDone }}<span class='text-gray-500'>+{{ $plotEntry }}</span> /
                            {{ $plotTotal }} ({{ $plotDonePercent }}%)
                        </span>
                        <span class="hidden md:inline">
                            @if ($plotDone >= $plotTotal)
                                🎊 全部完成！
                            @elseif ($plotDone >= $target)
                                🎉 完成目標！
                            @elseif ($plotDone >= 15)
                                🌟 就快完成了！
                            @elseif ($plotDone >= 8)
                                🌟 期中進度達標！
                            @else
                                💪 加油加油
                            @endif
                        </span>
                    </div>

                    {{-- 進度條（手機版寬度變小） --}}
                    @php
                        if ($plotEntryPercent == 0) {
                            $rounded = 'rounded';
                        } else {
                            $rounded = 'rounded-l';
                        }
                    @endphp
                    <div class="relative w-[180px] sm:w-[240px] md:w-[500px] h-4 bg-[#CBD5E0] rounded overflow-hidden">
                        @php
                            $done = max(0, min(100, (float) $plotDonePercent));
                            $entry = max(0, min(100 - $done, (float) $plotEntryPercent));
                            $firstClass = $entry > 0 ? 'rounded-l' : 'rounded';
                        @endphp

                        <div class="absolute left-0 h-4 {{ $firstClass }}"
                            style="width: {{ $done }}%; background-color: {{ $plotDoneColor }}"></div>

                        @if ($entry > 0)
                            <div class="absolute h-4 rounded-r"
                                style="left: {{ $done }}%; width: {{ $entry }}%; background-color: {{ $plotEntryColor }}">
                            </div>
                        @endif
                    </div>

                </div>
            @endforeach
        </div>
        <div>
            <button wire:click="showTeamProgressToggle()"
                class="px-4 py-2  bg-gray-200 rounded hover:bg-gray-300 mb-4">團隊詳細調查進度</button>
        </div>

        <div class="flex justify-start gap-8 items-start flex-wrap {{ $showTeamProgress ? '' : 'hidden' }}">
            <div class="gray-card space-y-3">
                <canvas id="teamBarChart2" class="w-[400px] h-[260px]"></canvas>
            </div>
            <div class="gray-card space-y-3">
                <canvas id="teamBarChart1" class="w-[400px] h-[260px]"></canvas>
            </div>
            <div class="gray-card mb-6 space-y-3">@include('components.team-stats-table', [
                'subPlotTeam' => $this->subPlotTeam,
                'subPlantTeam' => $this->subPlantTeam,

            ])
            </div>

        </div>

    @endif


    <h3>各縣市調查進度</h3>
    @if ($thisCounty != '')
        <button wire:click="surveryedPlotInfo('')" class="px-4 py-2  bg-gray-200 rounded hover:bg-gray-300 mb-4">
            顯示全部縣市
        </button>
    @endif

    <div class="flex justify-start gap-8 items-start">

        <div>
            <div class="flex flex-wrap justify-start gap-3">
                @foreach ($showContyInfo as $row)
                    @php
                        $completed = (int) ($row['completed_plots'] ?? 0); // 已完成數
                        $hasData = (int) ($row['has_data_plots'] ?? 0); // 有資料數
                        $total = max(0, (int) ($row['total_plots'] ?? 0)); // 總樣區數（分母）

                        $entryCnt = max(0, $hasData - $completed); // 尚未完成但已有資料的數

                        // 先用「比例」避免各自 round 的誤差
                        $doneRatio = $total > 0 ? $completed / $total : 0; // 0~1
                        $entryRatio = $total > 0 ? $entryCnt / $total : 0; // 0~1

                        // 第二段（entry）限制在剩餘空間（避免超過 100%）
                        $entryRatio = min(max(0, $entryRatio), max(0, 1 - $doneRatio));

                        // 最後才轉百分比並四捨五入
                        $donePct = (int) round($doneRatio * 100);
                        $entryPct = (int) round($entryRatio * 100);

                        // 供進度條用的實際寬度
                        $done = $donePct;

                        // $entry = $entryPct;
                        $entry = 20;

                        $firstClass = $entry > 0 ? 'rounded-l' : 'rounded';

                        $plotColor = $completed > 0 ? '#3B7A57' : '#CBD5E0'; // 森林綠 or 淺灰
                        $plotEntryColor = $entryCnt > 0 ? '#adaeaeff' : '#CBD5E0';
                    @endphp

                    <div wire:click="surveryedPlotInfo('{{ $row['county'] }}')" wire:key="card-{{ $row['county'] }}"
                        class="cursor-pointer w-[200px] sm:w-[220px] md:w-[240px] rounded p-4 shadow transition bg-white hover:bg-gray-50 hover:shadow-xl">
                        <div class="mb-2 flex justify-between">
                            <h3>{{ $row['county'] }} </h3>
                            <p>{{ $row['teams'] }}</p>
                        </div>

                        <div class="mb-3">
                            <p class="text-sm mb-1">樣區完成數：{{ $row['completed_plots'] }} + {{ $entryCnt }} /
                                {{ $row['total_plots'] }}
                            </p>

                            <div class="relative w-full h-4 bg-[#CBD5E0] rounded overflow-hidden">

                                <div class="absolute left-0 h-4 {{ $firstClass }}"
                                    style="width: {{ $done }}%; background-color: {{ $plotColor }}"></div>

                                @if ($entry > 0)
                                    <div class="absolute h-4 rounded-r"
                                        style="left: {{ $done }}%; width: {{ $entry }}%; background-color: {{ $plotEntryColor }}">
                                    </div>
                                @endif
                            </div>

                        </div>
                    </div>
                @endforeach

            </div>
            @if ($plotList)
                <div class="mt-4 md:mb-0">
                    <label class="block font-semibold">選擇其他縣市：</label>
                    <select wire:model="thisCounty" class="border rounded p-2 w-40"
                        wire:change="surveryedPlotInfo($event.target.value)">
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
                <div class="md:flex md:flex-row gap-4 mb-4">
                    <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0"
                        wire:key="refresh-{{ $refreshKey }}">
                        <label class="block font-semibold">選擇資料所屬年度：</label>
                        <select id="census_year" wire:model="thisCensusYear" class="border rounded p-2 w-40"
                            wire:change="loadThisCensusYearData($event.target.value)">
                            <option value="all">-- All --</option>
                            @foreach ($censusYearList as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="md:flex md:flex-row gap-4 mb-8">
                    <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0"
                        wire:key="refresh-{{ $refreshKey }}">
                        <label class="block font-semibold">選擇樣區：</label>
                        <select id="plot" wire:model="thisPlot" class="border rounded p-2 w-40"
                            wire:change="loadPlotInfo($event.target.value)">
                            <option value="">-- All --</option>
                            @foreach ($plotList as $plot)
                                <option value="{{ $plot }}">{{ $plot }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (!empty($subPlotSummary))
                        <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0 ">
                            <label class="block font-semibold md:mr-2">選擇生育地類型：</label>
                            <select id='habType' wire:model="thisHabType" class="border rounded p-2 w-40"
                                wire:change="reloadPlotInfo($event.target.value)">
                                <option value="">-- All --</option>
                                @foreach ($subPlotHabList as $code => $label)
                                    <option value="{{ $code }}">{{ $code }} {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0 ml-0 md:ml-4">
                            <button wire:click="loadPlotInfo('')"
                                class="px-4 py-2  bg-gray-200 rounded hover:bg-gray-300">
                                回樣區列表
                            </button>
                        </div>
                    @endif
                </div>

            @endif

            @if ($showAllPlotInfo)
                @php
                    // 先預處理資料：把每個 plot 出現的次數記錄下來
                    $plotRowCounts = collect($showAllPlotInfo)
                        ->groupBy('plot')
                        ->map(fn($rows) => count($rows))
                        ->toArray();

                    $printedPlots = []; // 記錄已經輸出過的 plot
                @endphp
                <div class="gray-card w-fit mb-6">
                    <h3>樣區列表</h3>
                    <table class="text-sm border border-gray-300 w-full">
                        <thead style="background-color: #F9E7AC;">
                            <tr>
                                <th class="border-b px-4 py-2">樣區編號</th>
                                <th class="border-b px-4 py-2 text-left">生育地類型</th>
                                <th class="border-b px-4 py-2">小樣方數量</th>
                                <th class="border-b px-4 py-2">未鑑定植物</th>
                                <th class="border-b px-4 py-2">資料錯誤</th>
                                <th class="border-b px-4 py-2">樣區資料檔案</th>
                                <th class="border-b px-4 py-2">樣區完成</th>
                            </tr>
                        </thead>

                        @php
                            $grouped = collect($showAllPlotInfo)->groupBy('plot');
                        @endphp

                        @foreach ($grouped as $plot => $rows)
                            <tbody class="group hover:bg-amber-800/10 cursor-pointer bg-white"
                                wire:click="loadPlotInfo('{{ $plot }}')"
                                wire:key="plot-{{ $plot }}">
                                @foreach ($rows as $index => $row)
                                    <tr>
                                        @if ($index === 0)
                                            <td class="border-b px-4 py-2 text-center align-top"
                                                rowspan="{{ count($rows) }}">
                                                {{ $row['plot'] }}
                                            </td>
                                        @endif

                                        <td class="border-b px-4 py-2">{!! $row['hab_code'] !!} {!! $row['hab_name'] !!}
                                        </td>
                                        <td class="border-b px-4 py-2 text-center">{{ $row['subplot_count_2025'] }}
                                        </td>
                                        <td class="border-b px-4 py-2 text-center">{{ $row['unidentified_count'] }}
                                        </td>
                                        <td class="border-b px-4 py-2 text-center">{{ $row['data_error_count'] }}</td>

                                        @if ($index === 0)
                                            <td class="border-b px-4 py-2 text-center align-top"
                                                rowspan="{{ count($rows) }}">
                                                @if ($row['plotFile'])
                                                    <a href="{{ $row['plotFile'] }}" target="_blank"
                                                        onclick="event.stopPropagation()"
                                                        class="text-blue-500 underline">
                                                        {{ basename($row['plotFile']) }}
                                                    </a>
                                                @else
                                                    <span class="text-gray-400 text-xs">-</span>
                                                @endif
                                            </td>
                                            <td class="border-b px-4 py-2 text-center align-top"
                                                rowspan="{{ count($rows) }}">
                                                @if ($row['completed'])
                                                    <span>✔️</span>
                                                @else
                                                    <span class="text-gray-400 text-xs">-</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>

                </div>
            @endif
            @if ($thisPlot)
                @if (!empty($filteredSubPlotSummary))

                    <div class="gray-card w-fit">
                        <h3>{{ $thisPlot }} {{ $thisSelectedHabitat }} 調查結果</h3>
                        @php

                            $labels = [
                                'dataCorrect' => '資料正確',
                                'subPlotImage' => '小樣方照片',
                                'subPlotData' => '小樣方資料',
                                'plotFile' => '樣區檔案',
                                'plotHabData' => '生育地類型',
                                'plotCompleted' => '是否完成',
                            ];
                        @endphp

                        <p>
                            @foreach ($labels as $key => $label)
                                {{ $label }}：{{ ($status[$key] ?? '0') === '1' ? '✔' : '✘' }}
                                @if (!$loop->last)
                                    ｜
                                @endif
                            @endforeach
                        </p>

                        @if ($thisPlotFile)
                            <div class="mb-4">
                                樣區調查資料：<a href='{{ $thisPlotFile }}' target="_blank"><img
                                        src="/images/PDF_file_icon.svg" alt="PDF" class="inline w-5 h-5 mr-1">
                                    {{ $thisPlot }}.pdf</a>
                            </div>
                        @endif
                        <table class="text-sm border border-gray-300 w-full">
                            <thead class="bg-yellow-500/30">
                                <tr>
                                    <th class="border-b px-4 py-2">小樣方編號</th>
                                    <th class="border-b px-4 py-2 text-left">生育地類型</th>
                                    <th class="border-b px-4 py-2">流水號</th>
                                    <th class="border-b px-4 py-2">調查日期</th>
                                    <th class="border-b px-4 py-2">植物筆數</th>
                                    <th class="border-b px-4 py-2">未鑑定</th>
                                    <th class="border-b px-4 py-2">資料錯誤</th>
                                    <th class="border-b px-4 py-2">原編號</th>
                                    <th class="border-b px-4 py-2">小樣方照片</th>
                                    <th class="border-b px-4 py-2">查看資料</th>

                                </tr>
                            </thead>
                            <tbody>
                                @php $lastHabitatCode = null; @endphp
                                @foreach ($filteredSubPlotSummary as $row)
                                    @php
                                        $habitatChanged = $row['habitat_code'] !== $lastHabitatCode;
                                        $lastHabitatCode = $row['habitat_code'];
                                    @endphp
                                    <tr
                                        class="hover:bg-amber-800/10 bg-white {{ $habitatChanged ? 'border-t border-gray-300' : '' }}">
                                        <td class="px-4 py-2">{{ $row['plot_full_id'] }}</td>
                                        <td class="px-4 py-2">{{ $row['habitat_code'] }} {{ $row['habitat'] }}</td>
                                        <td class="px-4 py-2 text-center">{{ $row['subplot_id'] }}</td>
                                        <td class="px-4 py-2 text-center">{{ $row['date'] }}</td>
                                        <td class="px-4 py-2 text-center">{{ $row['plant_count'] }}</td>
                                        <td class="px-4 py-2 text-center">{{ $row['unidentified_count'] }}</td>
                                        <td class="px-4 py-2 text-center">{{ $row['data_error_count'] }}</td>
                                        <td class="px-4 py-2 text-center">{{ $row['original_plot_id'] }}</td>
                                        <td class="px-4 py-2 text-center">
                                            {!! !empty($row['uploaded_at'])
                                                ? "<a href='{$row['photo_path']}' target='_blank' class='hover:no-underline no-underline'>✅</a>"
                                                : '' !!}
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            @php
                                                if ($userOrg == $row['team'] || $userRole == 'admin') {
                                                    $entryLink = route('overview.to.entry.entry', [
                                                        'county' => $thisCounty,
                                                        'plot' => $thisPlot,
                                                        'subPlot' => $row['plot_full_id'],
                                                    ]);
                                                } else {
                                                    $entryLink = '#';
                                                }

                                            @endphp
                                            <a href="{{ route('overview.to.query.plot', ['county' => $thisCounty, 'plot' => $thisPlot, 'subPlot' => $row['plot_full_id']]) }}"
                                                target="_blank" class="hover:no-underline no-underline">🔍</a>
                                            @if ($entryLink != '#')
                                                <a href="{{ $entryLink }}" target="_blank"
                                                    class="hover:no-underline no-underline">✏️</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>
                @else
                    <div>
                        <p>{{ $subPlotinfomessage }}</p>
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


    window.listenAndResetSelect = function(eventName, selectId) {
        window.addEventListener(eventName, () => {
            const select = document.getElementById(selectId);
            if (!select) return;

            const componentEl = select.closest('[wire\\:id]');
            const componentId = componentEl?.getAttribute('wire:id');

            console.log(`🟡 ${eventName} 事件收到，重設 #${selectId}`);
            select.selectedIndex = 0;
        });
    };

    window.listenAndResetSelect('thisPlotUpdated2', 'plot');

    window.addEventListener('thisTeamProgress', (event) => {
        const totalPlantsByTeam = event.detail.data.totalPlantsByTeam;
        const totalSubPlotsByTeam = event.detail.data.totalSubPlotsByTeam;

        const Plantlabels = totalPlantsByTeam.map(item => item.team);
        const Plantdata = totalPlantsByTeam.map(item => item.total_plants);

        const SubPlotlabels = totalSubPlotsByTeam.map(item => item.team);
        const SubPlotdata = totalSubPlotsByTeam.map(item => item.total_plots);

        const ctx1 = document.getElementById('teamBarChart1')?.getContext('2d');
        const ctx2 = document.getElementById('teamBarChart2')?.getContext('2d');

        if (!ctx1 || !ctx2) {
            console.warn('❌ 找不到 canvas 元素，請確認 teamBarChart1 / 2 是否已存在於 DOM 中');
            return;
        }

        // 如果圖已存在，先銷毀
        if (window.teamChart1) window.teamChart1.destroy();
        if (window.teamChart2) window.teamChart2.destroy();

        // 植物筆數圖表
        window.teamChart1 = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: Plantlabels,
                datasets: [{
                    label: '植物筆數',
                    data: Plantdata,
                    backgroundColor: 'rgba(104, 151, 115, 0.6)',
                    borderColor: 'rgba(104, 151, 115, 1)',
                    borderWidth: 1,
                    borderRadius: 6,

                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: '各團隊植物調查資料筆數',
                        font: {
                            size: 16
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });

        // 樣區筆數圖表
        window.teamChart2 = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: SubPlotlabels,
                datasets: [{
                    label: '樣區筆數',
                    data: SubPlotdata,
                    backgroundColor: 'rgba(255, 159, 64, 0.6)', // 不同顏色
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1,
                    borderRadius: 6,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: '各團隊調查小樣方數量',
                        font: {
                            size: 16
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
</script>
