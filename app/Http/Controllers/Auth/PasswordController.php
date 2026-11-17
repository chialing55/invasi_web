<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $mustChangePassword = $request->user()->must_change_password;
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => [$mustChangePassword ? 'nullable' : 'required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers()->symbols(),
            ],
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'remember_token' => Str::random(60),
        ]);

        if ($mustChangePassword) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', '密碼已更新，請使用新密碼重新登入。');
        }

        return back()->with('status', 'password-updated');
    }
}
