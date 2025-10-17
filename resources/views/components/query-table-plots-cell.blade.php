@php
  // 預設與保護：即使沒有傳也能安全渲染
  $tdClass    = $tdClass   ?? 'px-4 py-2 text-center hidden sm:table-cell relative overflow-visible';
  $title      = $title     ?? '樣區清單';
  $width      = $width     ?? 'w-36';
  $openInNew  = $openInNew ?? true;
  $plots      = $plots     ?? [];
  $count      = $count     ?? (is_countable($plots) ? count($plots) : 0);
@endphp
<td class="pl-4 pr-1 py-2 text-right {{ $tdClass }} hidden sm:table-cell relative overflow-visible">
  <span class="">{{ $count }}</span>
</td>
<td class="pl-2 py-2 text-right {{ $tdClass }} hidden sm:table-cell relative overflow-visible">
  <div x-data="{ open:false, hideTimer:null }"
       @mouseenter="clearTimeout(hideTimer); open = true"
       @mouseleave="hideTimer = setTimeout(() => open = false, 150)"
       @focusin="clearTimeout(hideTimer); open = true"
       @focusout="hideTimer = setTimeout(() => open = false, 150)"
       class="inline-flex items-center gap-1 cursor-default relative">

  {{-- 固定的箭頭佔位（0 也保留寬度，不顯示） --}}
  @if(($count ?? 0) > 0)
    <button type="button"
            class="inline-flex items-center justify-center w-4 h-5"
            x-on:click="open = !open"  {{-- 或 hover 觸發 --}}
            aria-label="展開樣區清單">
      <svg class="w-3 h-3 opacity-60" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 011.08 1.04l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
      </svg>
    </button>
  @endif
    @if (!empty($plots))
      {{-- 浮出清單 --}}
      <div x-show="open" x-cloak
           x-transition.opacity.duration.120ms
           @mouseenter="clearTimeout(hideTimer); open = true"
           @mouseleave="hideTimer = setTimeout(() => open = false, 150)"
           class="absolute left-1/2 -translate-x-1/2 top-full mt-1 {{ $width }}
                  max-h-64 overflow-auto flex flex-col rounded-xl border border-amber-200
                  bg-white/95 backdrop-blur shadow-lg z-30 text-left pointer-events-auto">

        <div class="px-3 py-2 text-xs text-gray-600 sticky top-0 bg-white/90">
          {{ $title }}（{{ is_countable($plots) ? count($plots) : 0 }}）
        </div>
        <ul class="divide-y divide-gray-100">
          @foreach ($plots as $plot)
            <li class="flex items-center px-3 py-2 text-sm">
              <a href="{{ route('overview.to.query.plot', ['county' => $county, 'plot' => $plot, 'habitat' => $habitat, 'spcode' => $spcode]) }}"
                   target="_blank" rel="noopener"
                   title="查詢此樣區">{{ $plot }}</a>
            </li>
          @endforeach
        </ul>
      </div>
    @endif
  </div>
</td>
