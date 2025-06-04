<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊 - 外來植物調查資料管理系統</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="flex items-center justify-center lg:min-h-screen">
        <div class="flex flex-col lg:flex-row items-center justify-center white-card m-5 max-w-4xl">
            <!-- 圖示區塊 -->
            <div>
                <img src="{{ asset('images/login.png') }}" alt="Register" class="mx-auto w-40 h-40 lg:w-[400px] lg:h-[400px]">
            </div>

            <!-- 註冊表單 -->
            <div class="m-8">
            <h1>全國外來植物調查資料管理系統</h1>
                <h2 class='text-center'>建立新帳號</h2>

                @if ($errors->any())
                    <div class="mb-4 text-red-600 text-sm">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('register') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium">姓名</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                            class="w-full mt-1 px-3 py-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-forest">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                            class="w-full mt-1 px-3 py-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-forest">
                    </div>
                    <!-- 單位 -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium">單位</label>
                        <select name="organization" required
                            class="w-full mt-1 px-3 py-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-forest">
                            <option value="">請選擇單位</option>
                            <option value="NIU">宜蘭大學</option>
                            <option value="NTU">台灣大學</option>
                            <option value="NCHU">中興大學</option>
                            <option value="NCYU">嘉義大學</option>
                            <option value="NSYSU">中山大學</option>
                            <option value="NPUST">屏東科技大學</option>
                            <!-- ...你可以之後繼續加更多單位 -->
                        </select>
                    </div>

                    <!-- 職稱 -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium">職稱</label>
                        <select name="title" required
                            class="w-full mt-1 px-3 py-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-forest">
                            <option value="">請選擇職稱</option>
                            <option value="計畫主持人">計畫主持人</option>
                            <option value="研究助理">研究助理</option>
                            <!-- ...你可以之後繼續加更多職稱 -->
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium">密碼</label>
                        <input type="password" name="password" required autocomplete="new-password"
                            class="w-full mt-1 px-3 py-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-forest">
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium">確認密碼</label>
                        <input type="password" name="password_confirmation" required
                            class="w-full mt-1 px-3 py-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-forest">
                    </div>

                    <button type="submit"
                        class="w-full bg-forest text-white py-2 px-4 rounded hover:bg-forest-bark transition">
                        註冊
                    </button>
                </form>

                <div class="text-center mt-4 text-sm">
                    已有帳號？
                    <a href="{{ route('login') }}" class="text-forest hover:underline">登入</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
