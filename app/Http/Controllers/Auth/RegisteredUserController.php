<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $role = match ($request->title) {
            '計畫主持人' => 'admin',
            '研究助理' => 'member',
            default => 'member', // 預設
        };

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'organization' => $request->organization,
            'title' => $request->title,
            'role' => $role,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));
//自動登入
        // Auth::login($user);
//導入信箱驗證頁面
        return redirect()->intended(route('verification.notice'));
    }
}
