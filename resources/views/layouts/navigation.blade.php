<nav x-data="{ open: false }" class=" text-white">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center">

            {{-- 漢堡選單按鈕（手機版顯示） --}}
            <div class="md:hidden">
                <button @click="open = !open" class="text-white focus:outline-none">
                    ☰
                </button>
            </div>

            {{-- 電腦版選單 --}}
            <div class="hidden md:flex space-x-1 m-auto">
                <div class="relative">
                    <div class="btn-navigation-div">
                        <button class="btn-navigation @navActive('index')"><a href="{{ route('index') }}">相關文件</a></button>
                    </div>
                </div>

                <x-nav.dropdown
                    label="資料查詢"
                    active="query.*"
                    :routes="[
                        ['label' => '依植物查詢', 'route' => 'query.plant'],
                        ['label' => '依樣區查詢', 'route' => 'query.plot']
                    ]"
                />

                <x-nav.dropdown
                    label="資料輸入"
                    active="entry.*"
                    :routes="[
                        ['label' => '資料輸入注意事項', 'route' => 'entry.notes'],
                        ['label' => '資料輸入', 'route' => 'entry.entry'],
                        ['label' => '小樣方未調查原因', 'route' => 'entry.missingnote']
                    ]"
                />
                <div class="relative">
                    <div class="btn-navigation-div">
                        <button class="btn-navigation @navActive('survey.overview')"><a href="{{ route('survey.overview') }}">調查進度</a></button>
                    </div>
                </div>

                <x-nav.dropdown
                    label="調查成果"
                    active="results.*"
                    :routes="[
                        ['label' => '物種數', 'route' => 'results.species'],
                        ['label' => '成果圖表', 'route' => 'results.charts']
                    ]"
                />

                <div class="relative">
                    <div class="btn-navigation-div">
                        <button class="btn-navigation @navActive('data.export')"><a href="{{ route('data.export') }}">資料匯出</a></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

        {{-- 手機版選單 --}}
    <div x-show="open" class="hanb md:hidden px-4 py-4 space-y-1">
        <a href="{{ route('index') }}">相關文件</a>
        <a href="{{ route('query.plant') }}">依植物查詢</a>
        <a href="{{ route('query.plot') }}">依樣區查詢</a>
        <a href="{{ route('entry.notes') }}">資料輸入注意事項</a>
        <a href="{{ route('entry.entry') }}">資料輸入</a>
        <a href="{{ route('survey.overview') }}">調查進度</a>
        <a href="{{ route('results.species') }}">物種數</a>
        <a href="{{ route('results.charts') }}">成果圖表</a>
        <a href="{{ route('data.export') }}">資料匯出</a>
    </div>
</nav>
