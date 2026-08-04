<div class="space-y-5">
    <h2 class="text-xl font-bold">使用者管理</h2>

    @if (session('userMessage'))
        <div class="rounded border border-green-300 bg-green-50 px-4 py-3 text-green-800">{{ session('userMessage') }}</div>
    @endif
    @if (session('userError'))
        <div class="rounded border border-red-300 bg-red-50 px-4 py-3 text-red-800">{{ session('userError') }}</div>
    @endif

    <div class="gray-card">
        <div class="flex flex-wrap items-center gap-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="搜尋姓名、Email 或單位"
                class="w-full rounded border border-gray-300 px-3 py-2 md:w-80">
            <div class="flex rounded border border-gray-300 bg-white p-1">
                <button type="button" wire:click="$set('status', 'active')"
                    class="rounded px-4 py-1 {{ $status === 'active' ? 'bg-forest text-white' : 'text-gray-600' }}">有效帳號</button>
                <button type="button" wire:click="$set('status', 'trashed')"
                    class="rounded px-4 py-1 {{ $status === 'trashed' ? 'bg-forest text-white' : 'text-gray-600' }}">已停用帳號</button>
            </div>
        </div>
    </div>

    @if ($editingUserId)
        <div class="gray-card">
            <h3 class="mb-4">編輯使用者</h3>
            <form wire:submit="saveUser" class="grid gap-4 md:grid-cols-2">
                <div><label class="block font-semibold">姓名</label><input wire:model="name" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">@error('name')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="block font-semibold">Email</label><input type="email" wire:model="email" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">@error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="block font-semibold">單位</label><select wire:model="organization" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><option value="">請選擇</option>@foreach($organizations as $code => $label)<option value="{{ $code }}">{{ $code }} {{ $label }}</option>@endforeach</select>@error('organization')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="block font-semibold">職稱</label><select wire:model="title" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><option value="">請選擇</option>@foreach($titles as $titleOption)<option value="{{ $titleOption }}">{{ $titleOption }}</option>@endforeach</select>@error('title')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="block font-semibold">身分</label><select wire:model="role" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"><option value="member">一般使用者</option><option value="admin">資料管理員</option></select>@error('role')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div class="flex items-end gap-2"><button type="submit" class="btn-submit">儲存</button><button type="button" wire:click="cancelEdit" class="rounded border border-gray-300 bg-gray-100 px-4 py-1 font-semibold text-gray-600 hover:bg-gray-200">取消</button></div>
            </form>
        </div>
    @endif

    <div class="overflow-x-auto rounded border border-gray-300 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-forest-mist text-left"><tr><th class="px-3 py-2">姓名</th><th class="px-3 py-2">Email</th><th class="px-3 py-2">單位</th><th class="px-3 py-2">職稱</th><th class="px-3 py-2">身分</th><th class="px-3 py-2">驗證</th><th class="px-3 py-2">操作</th></tr></thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="border-t border-gray-200 align-top">
                        <td class="px-3 py-2 font-semibold">{{ $user->name }}</td>
                        <td class="px-3 py-2">{{ $user->email }}</td>
                        <td class="px-3 py-2">{{ $user->organization ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $user->title ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $user->role === 'admin' ? '資料管理員' : '一般使用者' }}</td>
                        <td class="px-3 py-2">{{ $user->email_verified_at ? '已驗證' : '未驗證' }}</td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap gap-2">
                                @if ($status === 'active')
                                    <button type="button" wire:click="editUser({{ $user->id }})" class="underline text-forest">編輯</button>
                                    <button type="button" wire:click="sendPasswordReset({{ $user->id }})" class="underline text-forest">寄送密碼重設信</button>
                                    @if (!$user->email_verified_at)<button type="button" wire:click="resendVerification({{ $user->id }})" class="underline text-forest">重寄驗證信</button>@endif
                                    @if ($user->id !== auth()->id())
                                        <button type="button" wire:click="deactivateUser({{ $user->id }})"
                                            wire:confirm="確定要停用 {{ $user->name }} 的帳號嗎？該使用者將立即無法登入。"
                                            class="underline text-gray-500">停用帳號</button>
                                    @endif
                                @else
                                    <button type="button" wire:click="restoreUser({{ $user->id }})"
                                        wire:confirm="確定要還原 {{ $user->name }} 的帳號嗎？"
                                        class="underline text-forest">還原帳號</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">沒有符合條件的使用者。</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
