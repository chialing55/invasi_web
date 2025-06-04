{{-- 不使用 Breeze 的 <x-guest-layout> --}}
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入 - 外來植物調查資料管理系統</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="flex items-center justify-center lg:min-h-screen ">
        <div class="flex flex-col lg:flex-row items-center justify-center white-card m-5 max-w-4xl">
            <div><img src="{{ asset('images/login.png') }}" alt="Login" class=" mx-auto w-40 h-40 lg:w-[400px] lg:h-[400px]"></div>
            <div class="m-8">
                <h1>全國外來植物調查資料管理系統</h1>
            @if ($errors->any())
                <div class="mb-4 text-red-600 text-sm">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
            @if (session('logout_success'))
                <div class="mb-4 text-green-600 font-medium">
                    {{ session('logout_success') }}
                </div>
            @endif
            @if (session('status'))
                <div class="text-green-600">
                    {{ session('status') }}
                </div>
            @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                            class="w-full mt-1 px-3 py-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-forest">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium">密碼</label>
                        <input type="password" name="password" required
                            class="w-full mt-1 px-3 py-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-forest">
                    </div>

                    <div class="flex justify-between items-center mb-4">
                        <label class="text-sm">
                            <input type="checkbox" name="remember" class="mr-2"> 記住我
                        </label>
                        <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:underline">忘記密碼？</a>
                    </div>

                    <button type="submit" class="w-full bg-forest text-white py-2 px-4 rounded hover:bg-forest-dark transition">登入</button>
                </form>
                <!-- <div class="my-4 text-center relative">

                <div class="text-gray-400 text-sm my-2">或</div>

                    <a href="{{ route('google.redirect') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded hover:bg-gray-100 transition text-sm">
                        <img src="{{ asset('images/google_logo.svg') }}" alt="Google" class="w-5 h-5">
                        使用 Google 帳號登入
                    </a>
                </div> -->
                <div class="text-center mt-4">
                    <a href="{{ route('register') }}" class="text-sm text-gray-600 hover:underline">還沒有帳號？點此註冊</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
