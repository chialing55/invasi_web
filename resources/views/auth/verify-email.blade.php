<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>驗證 Email - 外來植物調查資料管理系統</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="white-card w-full max-w-md text-center">
            <h1 class="text-xl font-bold mb-4">請驗證你的 Email</h1>

            <p class="text-sm text-gray-700 mb-4">
                我們已經寄送一封驗證信到你的信箱。<br>若未收到，請點擊下方按鈕重新寄送。
            </p>

            @if (session('status') === 'verification-link-sent')
                <div class="mb-4 text-green-600 text-sm">
                    新的驗證連結已寄出！
                </div>
            @endif

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit"
                    class="w-full bg-forest text-white py-2 px-4 rounded hover:bg-forest-bark transition mb-2">
                    重新寄送驗證信
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="w-full text-sm text-gray-600 hover:underline mt-2">
                    登出
                </button>
            </form>
        </div>
    </div>
</body>
</html>
