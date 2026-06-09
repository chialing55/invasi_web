{{-- livewire/query-plant.blade.php --}}
<div>
    <div wire:loading.class="flex" wire:loading.remove.class="hidden" wire:target="plantCode"
        class="hidden fixed top-0 left-0 w-full h-full z-50 bg-white/50 items-center justify-center">
        <div class="w-10 h-10 border-4 border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
    </div>
    <!-- wire:target="plantCode,submitData,tableData" -->
    <h2>植物查詢</h2>
    <!-- 查詢輸入框 -->
    <div>
        <div class="mb-4">
            <label for="plant-select" class="block font-bold mb-2">輸入植物名稱：</label>

            <input list="plants" id="plant-select" class="border rounded px-2 py-1 w-full max-w-[600px]"
                placeholder="輸入或選擇植物中名/學名/科名" />
            <input id='plant-code' type='hidden' />

            <datalist id="plants">
                @foreach ($suggestions as $item)
                    <option value="{{ $item['label'] }} / {{ $item['family'] }}" data-label="{{ $item['label'] }}"
                        data-spcode="{{ $item['spcode'] }}"></option>
                @endforeach
            </datalist>
        </div>
    </div>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6" role="alert">
        <ul class="list-['🌱'] pl-5 space-y-1 mt-2 text-sm">
            <li>
                當未出現中名選單時，表示該植物名稱尚未收錄於資料庫中。建議可改用學名再次查詢。
            </li>
            <li>
                若仍未能查詢到植物，請至<a href='https://tai2.ntu.edu.tw/search/1/'
                    target="_blank">「臺灣植物資訊整合查詢系統」</a>查詢，以確認資料庫所使用之中文名與學名。
            </li>
            <li>若資料庫名錄中確實無該植物，且<b>確定需新增</b>，請填寫<a
                    href='https://docs.google.com/spreadsheets/d/13GUOo_I5fhUBh2IeGb1TJpQeIPN0GqSQKfsMwulSTHE/edit?usp=sharing'
                    target="_blank">「外來植物調查計畫-需新增的植物」</a>資料表。</li>
        </ul>
    </div>
    @if ($spnameInfo != [])
        <!--有查詢結果  -->
        <!-- 植物名稱 -->
        @php
            $inatLink1 = 'https://taiwan.inaturalist.org/search?q=' . urlencode($spnameInfo['chname'] ?? '');
            $inatLink2 = 'https://tai2.ntu.edu.tw/search/1/' . urlencode($spnameInfo['chname'] ?? '');
            $inatLink3 = 'https://taicol.tw/catalogue?keyword=' . urlencode($spnameInfo['chname'] ?? '');
        @endphp
        <div class="mt-8">
            <h2><strong>{{ $spnameInfo['chname'] }}</strong>
                <strong>{!! $spnameInfo['scientific_name_html'] ?? '' !!}</strong>
            </h2>
            <p><strong>{{ $spnameInfo['chfamily'] }} {{ $spnameInfo['family'] }} </strong></p>
            <p>
                @foreach ($spnameInfo['status_labels'] ?? [] as $statusLabel)
                    <span class="font-bold mr-2">{{ $statusLabel }}</span>
                @endforeach
            </p>
            <p>{{ $spnameInfo['growth_form'] }}</p>

            @if ($chnameIndex != [] && $chnameIndex[0]['chname_index'] != '')
                <p>中文別名:
                    @foreach ($chnameIndex as $item)
                        <span class="inline-block mr-2">{{ $item['chname_index'] }}</span>
                    @endforeach
                </p>
            @endif
            <div>
                <div class="flex flex-wrap gap-x-4 gap-y-4 mt-4 mb-4">
                    <a href="{{ $inatLink1 }}" target="_blank">
                        <img src="{{ asset('images/inaturelist.png') }}" alt="Link 1" class="h-12">
                    </a>
                    <a href="{{ $inatLink2 }}" target="_blank">
                        <img src="{{ asset('images/plants of taiwan.jpg') }}" alt="Link 2" class="h-12">
                    </a>
                    <a href="{{ $inatLink3 }}" target="_blank">
                        <img src="{{ asset('images/taicol.png') }}" alt="Link 3" class="h-12">
                    </a>
                </div>
            </div>
            <!-- 新增chnameIndex -->
            <button wire:click="toggle" class="btn-add">
                {{ $showTable ? '關閉新增' : '新增/修改中文別名' }}
            </button>
            <div class="mt-4">
                @if (session()->has('chIndexMessage'))
                    <div class="mb-2 text-red-800">{{ session('chIndexMessage') }}</div>
                @endif

                <div id="chname-table-wrapper" class="{{ $showTable ? 'md:flex md:gap-4 md:items-end' : 'hidden' }} ">
                    <div id="tabulator-table" wire:ignore class="w-full md:w-[441.6px]"></div>
                    <div class='mt-4 md:ml-8 md:mt-0'><button id="submit-btn" class="btn-submit"
                            type="button">新增/修改</button></div>
                </div>
            </div>



            <!--前次調查結果  -->
            <div class="mt-8">
                @if ($filteredComparisonTable != [])
                    <div class="gray-card w-fit">
                        <h2>調查結果</h2>

                        <div class="bg-forest-mist rounded-md p-4 text-sm mb-4 leading-relaxed">
                            <ul class="list-disc list-inside space-y-1">
                                <li>預設依「縣市」排序，可點選各欄位標題重新排序。</li>
                                <li>覆蓋度欄位的格式為：<span class="font-semibold">平均值 ± 標準差</span>。</li>
                               </li>點選樣區數旁的向下箭頭，可展開查看樣區清單。</li>
                            </ul>
                        </div>
                        <div class="md:flex md:flex-row gap-4 mb-8">
                            <!-- 選擇縣市 -->
                            <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
                                <label class="block font-semibold md:mr-2">選擇縣市：</label>
                                <select id="county" wire:model="thisCounty" class="border rounded p-2 w-40"
                                    wire:change="reloadPlantInfoCounty($event.target.value)">
                                    <option value="">-- All --</option>
                                    @foreach ($countyList as $county)
                                        <option value="{{ $county }}">{{ $county }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <!-- 生育地類型 -->
                            <div class="md:flex md:flex-row md:items-center gap-2 mb-4 md:mb-0">
                                <label class="block font-semibold md:mr-2">或 選擇生育地類型：</label>
                                <select id='habType' wire:model="thisHabType" class="border rounded p-2 w-40"
                                    wire:change="reloadPlantInfoHab($event.target.value)">
                                    <option value="">-- All --</option>
                                    @foreach ($habList as $label)
                                        <option value="{{ $label }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>


                        </div>
                        <div>

                            <table class="text-sm border border-gray-300 w-full">
                                <!-- 桌機版表頭 -->
                                <thead class=" hidden sm:table-header-group sm:sticky sm:top-0 sm:z-10"
                                    style="background-color: #F9E7AC;">
                                    <tr class="border-b border-gray-300 ">
                                        <x-th-sort field="county" :sort-field="$sortField" :sort-direction="$sortDirection" rowspan="2">
                                            縣市
                                        </x-th-sort>
                                        <x-th-sort field="habitat" :sort-field="$sortField" :sort-direction="$sortDirection" rowspan="2">
                                            生育地類型
                                        </x-th-sort>
                                        <th colspan="5" class="px-4 py-2 text-center bg-lime-200/50">2010</th>
                                        <th colspan="5" class="px-4 py-2 text-center bg-orange-200">2025</th>
                                    </tr>
                                    <tr class="border-b border-gray-300">
                                        <x-th-sort colspan="2" field="plot_2010" :sort-field="$sortField" :sort-direction="$sortDirection"
                                            class='bg-lime-200/50'>
                                            樣區數
                                        </x-th-sort>

                                        <x-th-sort field="sub_2010" :sort-field="$sortField" :sort-direction="$sortDirection"
                                            class='bg-lime-200/50'>
                                            小樣方數
                                        </x-th-sort>
                                        <x-th-sort field="cov_2010" :sort-field="$sortField" :sort-direction="$sortDirection" colspan="2"
                                            class='bg-lime-200/50'>
                                            覆蓋度
                                        </x-th-sort>

                                        <x-th-sort colspan="2" field="plot_2025" :sort-field="$sortField"
                                            :sort-direction="$sortDirection" class='bg-orange-200'>
                                            樣區數
                                        </x-th-sort>
                                        <x-th-sort field="sub_2025" :sort-field="$sortField" :sort-direction="$sortDirection"
                                            class='bg-orange-200'>
                                            小樣方數
                                        </x-th-sort>
                                        <x-th-sort field="cov_2025" :sort-field="$sortField" :sort-direction="$sortDirection"
                                            colspan="2" class='bg-orange-200'>
                                            覆蓋度
                                        </x-th-sort>
                                    </tr>
                                </thead>

                                <!-- 手機版表頭 -->
                                <thead class=" sm:hidden sticky top-0 z-10" style="background-color: #F9E7AC;">
                                    <tr class="border-b border-gray-300">
                                        <x-th-sort field="county" :sort-field="$sortField" :sort-direction="$sortDirection">
                                            縣市
                                        </x-th-sort>
                                        <x-th-sort field="habitat" :sort-field="$sortField" :sort-direction="$sortDirection">
                                            生育地類型
                                        </x-th-sort>
                                        <x-th-sort field="cov_2010" :sort-field="$sortField" :sort-direction="$sortDirection"
                                            colspan="2" class='bg-lime-200/50'>
                                            2010 覆蓋度
                                        </x-th-sort>
                                        <x-th-sort field="cov_2025" :sort-field="$sortField" :sort-direction="$sortDirection"
                                            colspan="2" class='bg-orange-200'>
                                            2025 覆蓋度
                                        </x-th-sort>
                                    </tr>
                                </thead>

                                <tbody>
                                    @php $prevCounty = null; @endphp
                                    @foreach ($filteredComparisonTable as $row)
                                        @php
                                            $borderClass =
                                                $prevCounty && $prevCounty !== $row['county']
                                                    ? 'border-t border-gray-300'
                                                    : '';
                                            $prevCounty = $row['county'];
                                        @endphp
                                        <tr class=" {{ $borderClass }} group">
                                            <td class="px-4 py-2 group-hover:bg-amber-800/10">{{ $row['county'] }}
                                            </td>
                                            <td class="px-4 py-2 group-hover:bg-amber-800/10">{{ $row['habitat'] }}
                                            </td>

                                            @include('components.query-table-plots-cell', [
                                                'tdClass' => 'bg-lime-100/50 group-hover:bg-amber-800/10',
                                                'title' => '2010 樣區清單',
                                                'width' => 'w-36',
                                                'count' => $row['plot_2010'],
                                                'plots' => $row['plots_2010'] ?? [],
                                                'county' => $row['county'],
                                                'habitat' => $row['hab_code'] ?? '',
                                                'spcode' => $spnameInfo['spcode'] ?? '',
                                                'openInNew' => true,
                                            ])

                                            <td
                                                class="px-4 py-2 text-center group-hover:bg-amber-800/10 bg-lime-100/50 hidden sm:table-cell">
                                                {{ $row['sub_2010'] }}</td>
                                            <td
                                                class="pl-4 py-2 text-right group-hover:bg-amber-800/10 bg-lime-100/50">
                                                {{ $row['cov_2010'] }}</td>
                                            <td class="pr-4 py-2 text-left group-hover:bg-amber-800/10 bg-lime-100/50">
                                                {{ $row['sd_2010'] }}</td>

                                            @include('components.query-table-plots-cell', [
                                                'tdClass' =>
                                                    'leading-none group-hover:bg-amber-800/10 bg-orange-100',
                                                'title' => '2025 樣區清單',
                                                'width' => 'w-36',
                                                'count' => $row['plot_2025'],
                                                'plots' => $row['plots_2025'] ?? [],
                                                'county' => $row['county'],
                                                'habitat' => $row['hab_code'] ?? '',
                                                'spcode' => $spnameInfo['spcode'] ?? '',
                                                'openInNew' => true,
                                            ])

                                            <td
                                                class="px-4 py-2 text-center group-hover:bg-amber-800/10 bg-orange-100 hidden sm:table-cell">
                                                {{ $row['sub_2025'] }}</td>
                                            <td class="pl-4 py-2 text-right group-hover:bg-amber-800/10 bg-orange-100">
                                                {{ $row['cov_2025'] }}</td>
                                            <td class="pr-4 py-2 text-left group-hover:bg-amber-800/10 bg-orange-100">
                                                {{ $row['sd_2025'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>

                            </table>
                        </div>
                    </div>
                @else
                    <div class="mt-4">
                        <h2>調查結果</h2>
                        <p>尚無調查資料</p>
                    </div>
                @endif

            </div>
    @endif


</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        //監聽的名稱, select的id
        listenAndResetSelect('updateHabType', 'habType');
        listenAndResetSelect('updateCounty', 'county');
    });



    window.chnameIndexTable = null; // 全域變數，存放 Tabulator 實例
    // 監聽輸入框的變化

    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('plant-select');
        const input2 = document.getElementById('plant-code');
        input.addEventListener('input', function() {
            const componentId = input.closest('[wire\\:id]')?.getAttribute('wire:id');
            const componentId2 = input2.closest('[wire\\:id]')?.getAttribute('wire:id');

            if (componentId && window.Livewire && typeof Livewire.find === 'function') {
                const rawValue = input.value;
                const matchedOption = document.querySelector(`#plants option[value="${rawValue}"]`);
                if (matchedOption) {
                    const spcode = matchedOption.getAttribute('data-spcode');
                    Livewire.find(componentId2)?.set('plantCode', spcode);
                    Livewire.find(componentId2)?.call('plantInfo', spcode);

                } else {
                    const spcode = '';
                }

                const parts = rawValue.split(' / ');
                const plantName = parts[0].trim();

                // 設定 Livewire 的變數
                Livewire.find(componentId)?.set('plantName', plantName);

            }
        });
    });

    // 監聽 Livewire 的事件

    window.addEventListener('plant-name-selected', (event) => {
        const input = document.getElementById('plant-select');
        input.value = '';
        if (window.chnameIndexTable != null) {

            resetAndInitTabulator();
        } else {
        }

    });

    window.addEventListener('chname_index_table', (event) => {
        const data = event.detail.data;
        if (window.chnameIndexTable != null) {

            requestAnimationFrame(() => {
                if (window.chnameIndexTable instanceof Tabulator) {
                    window.chnameIndexTable.redraw(true);
                }
            });

        } else {
            initTabulatorStart(data.data, data.spcode);
        }

    });

    window.addEventListener('sync-complete', (event) => {
        const data = event.detail.data;
        window.chnameIndexTable.replaceData(data.data);
    });

    // 摧毀表格
    function resetAndInitTabulator(containerId = 'tabulator-table') {
        const tabulatorDiv = document.getElementById(containerId);

        if (!tabulatorDiv) {
            console.warn(`❌ 找不到 #${containerId}`);
            return;
        }

        // 1. 銷毀舊表格
        if (window.chnameIndexTable instanceof Tabulator) {
            window.chnameIndexTable.destroy();
            window.chnameIndexTable = null;
        }

        // 2. 清除 DOM 殘留
        tabulatorDiv.innerHTML = '';
        tabulatorDiv.classList.remove('tabulator', 'tabulator-initialized');

    }


    function initTabulatorStart(tableData, thisSpcode) {
        const columns = [{
                title: "#",
                formatter: "rownum",
                width: 40,
                hozAlign: "center",
                headerSort: false
            },
            {
                title: "中文別名",
                field: "chname_index",
                editor: "input",
                width: 200
            },
            {
                title: "備註",
                field: "note",
                editor: "input",
                width: 200
            },
            {
                title: "spcode",
                field: "spcode",
                visible: false
            },
            {
                title: "id",
                field: "id",
                visible: false
            },
        ];

        initTabulator({
            tableData: tableData,
            elementId: 'tabulator-table',
            columns: columns,
            livewireField: 'chnameIndex',
            presetKey: 'spcode',
            presetValue: thisSpcode,
            globalName: 'chnameIndexTable',
        });
    }


    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'submit-btn') {
            const data = window.chnameIndexTable.getData();
            const componentId = document.querySelector('[wire\\:id]')?.getAttribute('wire:id');
            if (componentId) {
                Livewire.find(componentId).set('chnameIndex', data);
                Livewire.find(componentId).call('saveChnameIndex');
            }
        }
    });
</script>
