@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <h2 class="text-xl font-bold">個人基本資料</h2>

        <div class="flex flex-col gap-4 md:flex-row md:items-stretch">
            <div class="gray-card w-full md:w-[420px]">
                <h3 class="mb-4">帳號資料</h3>
                <dl class="grid grid-cols-[8rem_1fr] gap-x-4 gap-y-3 text-sm">
                    <dt class="font-semibold text-gray-600">姓名</dt><dd>{{ auth()->user()->name }}</dd>
                    <dt class="font-semibold text-gray-600">Email</dt><dd>{{ auth()->user()->email }}</dd>
                    <dt class="font-semibold text-gray-600">單位</dt><dd>{{ auth()->user()->organization ?: '—' }}</dd>
                    <dt class="font-semibold text-gray-600">職稱</dt><dd>{{ auth()->user()->title ?: '—' }}</dd>
                    <dt class="font-semibold text-gray-600">身分</dt><dd>{{ auth()->user()->role === 'admin' ? '資料管理員' : '一般使用者' }}</dd>
                </dl>
                <p class="mt-4 text-sm text-gray-500">帳號資料如需調整，請洽資料管理員。</p>
            </div>

            <div class="gray-card w-full md:w-[420px]">
                <h3 class="mb-4">修改密碼</h3>
                <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="block font-semibold">目前密碼</label>
                    <div class="relative mt-1">
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                            class="w-full rounded border border-gray-300 px-3 py-2 pr-11" required>
                        <x-password-visibility-button target="current_password" />
                    </div>
                    @error('current_password', 'updatePassword')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="block font-semibold">新密碼</label>
                    <div class="relative mt-1">
                        <input id="password" name="password" type="password" autocomplete="new-password"
                            class="w-full rounded border border-gray-300 px-3 py-2 pr-11" required>
                        <x-password-visibility-button target="password" />
                    </div>
                    @error('password', 'updatePassword')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block font-semibold">確認新密碼</label>
                    <div class="relative mt-1">
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                            class="w-full rounded border border-gray-300 px-3 py-2 pr-11" required>
                        <x-password-visibility-button target="password_confirmation" />
                    </div>
                </div>

                <button type="submit" class="btn-submit">更新密碼</button>
                @if (session('status') === 'password-updated')
                    <span class="ml-3 text-sm font-semibold text-green-700">密碼已更新。</span>
                @endif
                </form>
            </div>
        </div>
    </div>
@endsection
