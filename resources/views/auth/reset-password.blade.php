<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>設定新密碼 - 外來植物調查資料管理系統</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="white-card w-full max-w-md">
            <h1 class="text-xl font-bold mb-6 text-center">設定新密碼</h1>

            @if ($errors->any())
                <div class="mb-4 text-red-600 text-sm">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('password.reset.store') }}">
                @csrf
                <!-- @method('PUT') ✅ 這一行是關鍵 -->
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div class="mb-4">
                    <label class="block text-sm font-medium">Email</label>
                    <input type="email" name="email" value="{{ old('email', $request->email) }}" required
                        class="w-full mt-1 px-3 py-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-forest">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium">新密碼</label>
                    <input type="password" name="password" required autocomplete="new-password"
                            class="w-full mt-1 px-3 py-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-forest" placeholder="至少8個字元，包含英文字母、數字和符號">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium">再次輸入密碼</label>
                    <input type="password" name="password_confirmation" required
                        class="w-full mt-1 px-3 py-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-forest">
                </div>
 
                <button type="submit"
                    class="w-full bg-forest text-white py-2 px-4 rounded hover:bg-forest-bark transition">
                    送出
                </button>
            </form>
        </div>
    </div>
</body>
</html>
