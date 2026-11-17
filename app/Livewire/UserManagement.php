<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class UserManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = 'active';
    public ?int $editingUserId = null;
    public string $name = '';
    public string $email = '';
    public string $organization = '';
    public string $title = '';
    public string $role = 'member';
    public ?string $temporaryPassword = null;
    public ?string $temporaryPasswordUser = null;
    public ?string $temporaryPasswordExpiresAt = null;

    public array $organizations = [
        'NIU' => '宜蘭大學',
        'NTU' => '臺灣大學',
        'NCHU' => '中興大學',
        'NCYU' => '嘉義大學',
        'NSYSU' => '中山大學',
        'NPUST' => '屏東科技大學',
    ];

    public array $titles = ['計畫主持人', '研究助理'];

    public function boot(): void
    {
        Gate::authorize('manage-users');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->cancelEdit();
        $this->resetPage();
    }

    public function editUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->organization = (string) $user->organization;
        $this->title = (string) $user->title;
        $this->role = $user->role === 'admin' ? 'admin' : 'member';
        $this->resetValidation();
    }

    public function saveUser(): void
    {
        $user = User::findOrFail($this->editingUserId);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'organization' => ['required', Rule::in(array_keys($this->organizations))],
            'title' => ['required', Rule::in($this->titles)],
            'role' => ['required', Rule::in(['admin', 'member'])],
        ]);

        if ($user->is(Auth::user()) && $validated['role'] !== 'admin') {
            $this->addError('role', '不能取消自己的資料管理員權限。');
            return;
        }

        if ($user->role === 'admin' && $validated['role'] !== 'admin'
            && User::where('role', 'admin')->where('status', 'active')->count() <= 1) {
            $this->addError('role', '系統至少需要保留一位資料管理員。');
            return;
        }

        $user->fill($validated);
        $user->save();

        $this->cancelEdit();
        session()->flash('userMessage', '使用者資料已更新。');
    }

    public function approveUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update([
            'status' => 'active',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'rejected_reason' => null,
        ]);

        session()->flash('userMessage', "已核准 {$user->name} 的帳號。");
    }

    public function rejectUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update(['status' => 'rejected']);
        $this->revokeSessions($user);

        session()->flash('userMessage', "已拒絕 {$user->name} 的帳號申請。");
    }

    public function suspendUser(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->is(Auth::user())) {
            session()->flash('userError', '不能停權自己的帳號。');
            return;
        }

        if ($this->isLastActiveAdmin($user)) {
            session()->flash('userError', '不能停權系統最後一位有效的資料管理員。');
            return;
        }

        $user->update(['status' => 'suspended']);
        $this->revokeSessions($user);

        $this->cancelEdit();
        session()->flash('userMessage', "已停權 {$user->name} 的帳號。所有既有登入已撤銷。");
    }

    public function reactivateUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update(['status' => 'active']);

        session()->flash('userMessage', "已恢復 {$user->name} 的帳號。");
    }

    public function deleteUser(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->is(Auth::user())) {
            session()->flash('userError', '不能刪除自己的帳號。');
            return;
        }

        if ($this->isLastActiveAdmin($user)) {
            session()->flash('userError', '不能刪除系統最後一位有效的資料管理員。');
            return;
        }

        DB::transaction(function () use ($user) {
            $this->revokeSessions($user);
            $user->deleted_by = Auth::id();
            $user->save();
            $user->delete();
        });

        $this->cancelEdit();
        session()->flash('userMessage', "已刪除 {$user->name} 的帳號；相關資料仍保留。");
    }

    public function restoreUser(int $userId): void
    {
        $user = User::onlyTrashed()->findOrFail($userId);
        $user->deleted_by = null;
        $user->restore();

        session()->flash('userMessage', "已還原 {$user->name} 的帳號。");
    }

    public function generateTemporaryPassword(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->is(Auth::user())) {
            session()->flash('userError', '不能從使用者管理重設自己的密碼，請使用個人資料頁的修改密碼功能。');
            return;
        }

        $password = Str::password(16);
        $expiresAt = now()->addDay();

        $user->password = $password;
        $user->must_change_password = true;
        $user->temporary_password_expires_at = $expiresAt;
        $user->save();
        $this->revokeSessions($user);

        $this->temporaryPassword = $password;
        $this->temporaryPasswordUser = $user->name;
        $this->temporaryPasswordExpiresAt = $expiresAt->format('Y-m-d H:i');
    }

    public function dismissTemporaryPassword(): void
    {
        $this->reset(['temporaryPassword', 'temporaryPasswordUser', 'temporaryPasswordExpiresAt']);
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingUserId', 'name', 'email', 'organization', 'title']);
        $this->role = 'member';
        $this->resetValidation();
    }

    public function render()
    {
        $query = User::query()
            ->when($this->status === 'trashed', fn ($query) => $query->onlyTrashed())
            ->when($this->status !== 'trashed', fn ($query) => $query->where('status', $this->status))
            ->when($this->search !== '', function ($query) {
                $term = '%' . trim($this->search) . '%';
                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('organization', 'like', $term);
                });
            })
            ->orderBy('organization')
            ->orderBy('name');

        return view('livewire.user-management', [
            'users' => $query->paginate(20),
        ]);
    }

    private function revokeSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->forceFill(['remember_token' => null])->save();
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return $user->role === 'admin'
            && $user->status === 'active'
            && User::where('role', 'admin')->where('status', 'active')->count() <= 1;
    }
}
