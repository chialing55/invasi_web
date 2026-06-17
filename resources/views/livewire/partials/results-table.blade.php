@php
    $headings = $section['displayHeadings'] ?? [];
    $rows = $section['rows'] ?? [];
    $isGroupedTaxonTable = in_array('分組', $headings, true) && in_array('隸屬特性', $headings, true);
    $isIviComparisonTable = in_array('本次調查_相對覆蓋度(%)', $headings, true) && in_array('前次調查_相對覆蓋度(%)', $headings, true);
    $iviColumns = [
        '前次調查(2010)' => [
            'class' => 'bg-lime-200/50',
            'columns' => [
                '前次調查_相對覆蓋度(%)' => '相對覆蓋度(%)',
                '前次調查_相對頻度(%)' => '相對頻度(%)',
                '前次調查_IVI重要值(%)' => 'IVI重要值(%)',
                '前次調查_名次' => '名次',
            ],
        ],
        '本次調查(2025)' => [
            'class' => 'bg-orange-200',
            'columns' => [
                '本次調查_相對覆蓋度(%)' => '相對覆蓋度(%)',
                '本次調查_相對頻度(%)' => '相對頻度(%)',
                '本次調查_IVI重要值(%)' => 'IVI重要值(%)',
                '本次調查_名次' => '名次',
            ],
        ],
    ];

    $formatCell = function ($value) {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $value;
    };
@endphp

@if (empty($rows))
    <div class="text-gray-600">無資料</div>
@else
    <table class="text-sm border border-gray-300 bg-white">
        <thead style="background-color: #F9E7AC;">
            @if ($isIviComparisonTable)
                <tr>
                    <th class="border px-3 py-2 whitespace-nowrap text-center align-middle" rowspan="2">中文名</th>
                    <th class="border px-3 py-2 whitespace-nowrap text-center align-middle" rowspan="2">學名</th>
                    @foreach ($iviColumns as $group => $groupSpec)
                        <th class="border px-3 py-2 whitespace-nowrap text-center {{ $groupSpec['class'] }}" colspan="{{ count($groupSpec['columns']) }}">{{ $group }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($iviColumns as $groupSpec)
                        @foreach ($groupSpec['columns'] as $label)
                            <th class="border px-3 py-2 whitespace-nowrap text-center {{ $groupSpec['class'] }}">{{ $label }}</th>
                        @endforeach
                    @endforeach
                </tr>
            @else
                <tr>
                    @foreach ($headings as $heading)
                        <th class="border px-3 py-2 whitespace-nowrap text-center">{{ $heading }}</th>
                    @endforeach
                </tr>
            @endif
        </thead>
        <tbody>
            @if ($isGroupedTaxonTable)
                @php
                    $groupCounts = [];
                    foreach ($rows as $rowForCount) {
                        $rowForCount = (array) $rowForCount;
                        $group = (string) ($rowForCount['分組'] ?? '');
                        $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
                    }
                    $renderedGroups = [];
                @endphp
                @foreach ($rows as $row)
                    @php
                        $row = (array) $row;
                        $group = (string) ($row['分組'] ?? '');
                        $isFirstGroupRow = !isset($renderedGroups[$group]);
                        $renderedGroups[$group] = true;
                    @endphp
                    <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                        @foreach ($headings as $heading)
                            @continue($heading === '分組' && !$isFirstGroupRow)
                            @php $value = $formatCell($row[$heading] ?? ''); @endphp
                            <td class="border px-3 py-2 whitespace-nowrap {{ in_array($heading, ['分組', '隸屬特性'], true) ? 'text-center align-middle' : '' }}"
                                @if ($heading === '分組') rowspan="{{ $groupCounts[$group] ?? 1 }}" @endif>
                                {{ $value }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @elseif ($isIviComparisonTable)
                @foreach ($rows as $row)
                    @php
                        $row = (array) $row;
                        $scientificNameHtml = (string) ($row['學名_html'] ?? '');
                    @endphp
                    <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                        <td class="border px-3 py-2 whitespace-nowrap">{{ $formatCell($row['中文名'] ?? '') }}</td>
                        <td class="border px-3 py-2 whitespace-nowrap">
                            @if ($scientificNameHtml !== '')
                                {!! $scientificNameHtml !!}
                            @else
                                {{ $formatCell($row['學名'] ?? '') }}
                            @endif
                        </td>
                        @foreach ($iviColumns as $groupSpec)
                            @foreach ($groupSpec['columns'] as $key => $label)
                                <td class="border px-3 py-2 whitespace-nowrap text-right {{ $groupSpec['class'] }}">{{ $formatCell($row[$key] ?? '') }}</td>
                            @endforeach
                        @endforeach
                    </tr>
                @endforeach
            @else
                @foreach ($rows as $row)
                    @php $row = (array) $row; @endphp
                    <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                        @foreach ($headings as $heading)
                            @php $value = $formatCell($row[$heading] ?? ''); @endphp
                            <td class="border px-3 py-2 whitespace-nowrap">{{ $value }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
@endif