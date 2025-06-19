<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email 驗證成功 - 外來植物調查資料管理系統</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="white-card w-full max-w-md text-center">
            <h1 class="text-xl font-bold mb-4">✅ Email 驗證成功</h1>

            <p class="text-sm text-gray-700 mb-6">
                您的 Email 已成功驗證。<br>請重新登入以使用本系統。
            </p>

            @php
                $loginUrl = route('login');
            @endphp

            <button type="button"
                onclick="window.location.href='{{ $loginUrl }}'"
                class="w-full bg-forest text-white py-2 px-4 rounded hover:bg-forest-bark transition">
                前往登入
            </button>


        </div>
    </div>
</body>
</html>
