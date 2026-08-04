<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
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

        if ($user->role === 'admin' && $validated['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            $this->addError('role', '系統至少需要保留一位資料管理員。');
            return;
        }

        $emailChanged = $user->email !== $validated['email'];
        $user->fill($validated);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        $this->cancelEdit();
        session()->flash('userMessage', '使用者資料已更新。');
    }

    public function deactivateUser(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->is(Auth::user())) {
            session()->flash('userError', '不能停用自己的帳號。');
            return;
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            session()->flash('userError', '不能停用系統最後一位資料管理員。');
            return;
        }

        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->remember_token = null;
            $user->deleted_by = Auth::id();
            $user->save();
            $user->delete();
        });

        $this->cancelEdit();
        session()->flash('userMessage', "已停用 {$user->name} 的帳號。所有既有登入已撤銷。");
    }

    public function restoreUser(int $userId): void
    {
        $user = User::onlyTrashed()->findOrFail($userId);
        $user->deleted_by = null;
        $user->restore();

        session()->flash('userMessage', "已還原 {$user->name} 的帳號。");
    }

    public function sendPasswordReset(int $userId): void
    {
        $user = User::findOrFail($userId);
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('userMessage', "已寄送密碼重設信給 {$user->email}。");
            return;
        }

        session()->flash('userError', '密碼重設信寄送失敗，請稍後再試。');
    }

    public function resendVerification(int $userId): void
    {
        $user = User::findOrFail($userId);
        if ($user->hasVerifiedEmail()) {
            session()->flash('userError', '此帳號已完成 Email 驗證。');
            return;
        }

        $user->sendEmailVerificationNotification();
        session()->flash('userMessage', "已重新寄送驗證信給 {$user->email}。");
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
}
