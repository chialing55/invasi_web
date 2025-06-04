<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
    
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();
    
                event(new PasswordReset($user));
            }
        );
    
        // ✅ 完整中文訊息提示
        return match ($status) {
            Password::PASSWORD_RESET =>
                redirect()->route('login')->with('status', '密碼已成功重設，請重新登入。'),
    
            Password::INVALID_USER =>
                back()->withInput($request->only('email'))
                    ->withErrors(['email' => '查無此 Email，請確認後再試一次。']),
    
            Password::INVALID_TOKEN =>
                back()->withInput($request->only('email'))
                    ->withErrors(['email' => '密碼重設連結已失效，請重新申請。']),
    
            default =>
                back()->withInput($request->only('email'))
                    ->withErrors(['email' => '密碼重設失敗，請稍後再試一次。']),
        };
    }
    
}
