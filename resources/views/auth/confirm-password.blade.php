<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>確認密碼 - 外來植物調查資料管理系統</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="white-card w-full max-w-md">
            <h1 class="text-xl font-bold mb-6 text-center">請輸入密碼以繼續操作</h1>

            @if ($errors->any())
                <div class="mb-4 text-red-600 text-sm">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf

                <div class="mb-6">
                    <label class="block text-sm font-medium">密碼</label>
                    <input type="password" name="password" required
                        class="w-full mt-1 px-3 py-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-forest">
                </div>

                <button type="submit"
                    class="w-full bg-forest text-white py-2 px-4 rounded hover:bg-forest-bark transition">
                    確認密碼
                </button>
            </form>
        </div>
    </div>
</body>
</html>
