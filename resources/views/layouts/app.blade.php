<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>全國外來植物調查資料建置系統</title>
    <!-- <script src="//unpkg.com/alpinejs" defer></script> -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')

    <style>
        .tabulator-edit-list-item.focused {
            background-color: #cce5ff !important;
            /* 淺藍底 */
            color: #003366 !important;
            /* 深藍字 */
        }

        .tabulator-edit-list-item:hover {
            background-color: #cce5ff !important;
            /* 淺藍底 */
            color: #003366 !important;
            /* 深藍字 */
            outline: 1px solid #1d68cd;
        }

        .tabulator {
            font-size: 16px !important;
            max-width: 100%;

        }

        .tabulator-table-plant {
            max-width: 100%;

        }

        button.sort::after {
            content: '';
            margin-left: 0.5em;
        }

        button.sort[data-order="asc"]::after {
            content: "▲";
        }

        button.sort[data-order="desc"]::after {
            content: "▼";
        }

        .autocomplete-dropdown {
            border-radius: 4px;
            overflow: hidden;
        }

        .autocomplete-option:hover,
        .autocomplete-option.selected {
            background: #97c498;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-900 min-h-screen flex flex-col">

    {{-- Header --}}
    <header class="bg-forest text-white px-4 shadow-md flex justify-between items-center">
        <h1 class="text-xl font-bold text-white">全國外來植物調查資料建置系統</h1>
        <div class="flex items-center">
            <div class="text-white font-semibold mr-4 ">
                👋 Hi, {{ Auth::user()->name }}
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-secondary">登出</button>
            </form>
        </div>
    </header>

    {{-- Navigation --}}
    <nav class="relative bg-forest-moss">
        <!-- 棋盤裝飾 -->
        <div class="absolute right-0 top-0 z-0 h-[56px] overflow-hidden">
            <div class="hidden lg:grid "
                style="grid-template-columns: repeat(12, 20px); grid-template-rows: repeat(1, 60px)">
                @php
                    $colors = ['bg-forest', 'bg-forest-mist', 'bg-forest-canopy', 'bg-forest-soil', 'bg-white'];
                @endphp

                @for ($i = 0; $i < 12; $i++)
                    <div class="w-[40px] h-[60px] {{ $colors[array_rand($colors)] }} opacity-70"></div>
                @endfor
            </div>
        </div>

        <!-- 導覽列內容 -->
        <div class="relative z-10">
            @include('layouts.navigation')
        </div>
    </nav>



    {{-- Main Content --}}
    <main class="p-6 flex-1">
        <div class='m-auto max-w-7xl w-full'>
            @yield('content')
        </div>
    </main>

    {{-- Footer --}}
    <footer class="bg-forest text-white text-sm text-center p-6">
        如有任何意見與問題，請至群組 @chialing lu
    </footer>
    @livewireScripts
    @stack('scripts')
</body>

</html>
