<?php
// app/Http/Controllers/GoogleBindingController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;

class GoogleBindingController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $user = Auth::user();

        // 檢查這個 Google ID 是否已被綁定
        $existing = \App\Models\User::where('google_id', $googleUser->id)->first();
        if ($existing && $existing->id !== $user->id) {
            return redirect()->route('index')->with('error', '此 Google 帳號已被其他使用者綁定');
        }

        // 將目前登入使用者綁定 Google 資料
        $user->update([
            'google_id' => $googleUser->id,
            'google_email' => $googleUser->getEmail(),
            'google_avatar' => $googleUser->getAvatar(),
        ]);

        return redirect()->route('index')->with('success', 'Google 帳號綁定成功');
    }
}
