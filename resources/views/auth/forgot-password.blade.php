<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重設密碼連結 - 外來植物調查資料管理系統</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="white-card w-full max-w-md">
            <h1 class="text-xl font-bold mb-6 text-center">重設密碼連結</h1>

            @if (session('status'))
                <div class="mb-4 text-green-600 text-sm text-center">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 text-red-600 text-sm">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
            @if (!session('status'))
            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="mb-4">
                    <label class="block text-sm font-medium">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="w-full mt-1 px-3 py-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-forest">
                </div>

                <button type="submit"
                    class="w-full bg-forest text-white py-2 px-4 rounded hover:bg-forest-bark transition">
                    發送重設密碼連結
                </button>
            </form>
            @endif
            <div class="text-center mt-4 text-sm">
                <a href="{{ route('login') }}" class="text-forest hover:underline">回登入頁</a>
            </div>
        </div>
    </div>
</body>
</html>
