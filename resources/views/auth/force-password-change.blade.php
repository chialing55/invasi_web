<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>設定新密碼 - 外來植物調查資料管理系統</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800">
    <main class="flex min-h-screen items-center justify-center p-5">
        <div class="white-card w-full max-w-lg p-8">
            <h1>請先設定新密碼</h1>
            <p class="mb-6 text-sm text-gray-600">你目前使用的是臨時密碼。完成修改前無法使用其他功能。</p>

            @if ($errors->updatePassword->any())
                <div class="mb-4 text-sm text-red-600">
                    @foreach ($errors->updatePassword->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="password" class="block font-semibold">新密碼</label>
                    <div class="relative mt-1">
                        <input id="password" name="password" type="password" autocomplete="new-password" required
                            class="w-full rounded border border-gray-300 px-3 py-2 pr-11">
                        <x-password-visibility-button target="password" />
                    </div>
                </div>
                <div>
                    <label for="password_confirmation" class="block font-semibold">確認新密碼</label>
                    <div class="relative mt-1">
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                            class="w-full rounded border border-gray-300 px-3 py-2 pr-11">
                        <x-password-visibility-button target="password_confirmation" />
                    </div>
                </div>
                <p class="text-sm text-gray-500">至少 8 個字元，並包含英文字母、數字及符號。</p>
                <button type="submit" class="btn-submit w-full">更新密碼</button>
            </form>
            <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
                @csrf
                <button type="submit" class="text-sm text-gray-600 underline">登出</button>
            </form>
        </div>
    </main>
</body>
</html>
