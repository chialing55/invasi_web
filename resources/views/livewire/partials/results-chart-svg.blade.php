@php
    $rows = array_values(array_filter($section['rows'] ?? [], fn($row) => !empty((array) $row)));
    $canvasId = 'results-chart-' . $section['displayKey'];
@endphp

@if (empty($rows))
    <div class="text-gray-600">無資料</div>
@else
    <div class="w-full max-w-5xl" style="height: 520px;">
        <canvas id="{{ $canvasId }}"></canvas>
    </div>
@endif