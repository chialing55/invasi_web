<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordConfirmController;
use App\Http\Controllers\GoogleBindingController;
// use App\Http\Controllers\HomeController;
use App\Http\Controllers\FileController;

use App\Http\Livewire\QueryPlot;
use Illuminate\Support\Facades\Auth;


// 首頁（登入畫面 or dashboard）
Route::redirect('/', '/login');


// 註冊
Route::get('/register', [RegisteredUserController::class, 'create'])
    ->middleware('guest')
    ->name('register');
Route::post('/register', [RegisteredUserController::class, 'store']);

// 登入
Route::get('/login', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

// 登出
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// 忘記密碼（輸入 Email）
Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
    ->middleware('guest')
    ->name('password.request');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('guest')
    ->name('password.email');

// 重設密碼（含 token）
Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
    ->middleware('guest')
    ->name('password.reset');
Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest')
    ->name('password.reset.store');

// 確認密碼（敏感操作）
Route::get('/confirm-password', [ConfirmablePasswordController::class, 'show'])
    ->middleware('auth')
    ->name('password.confirm');
Route::post('/confirm-password', [ConfirmablePasswordController::class, 'store']);

// Email 驗證提示頁
Route::get('/verify-email', [EmailVerificationPromptController::class, '__invoke'])
    ->middleware('auth')
    ->name('verification.notice');

// 實際驗證 Email 連結
Route::get('/verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/email-verified', function () {
    Auth::logout(); // ✅ 驗證完登出
    session()->flush(); // ✅ 清除 session（選擇性）

    return view('auth.email-verified');
})->name('email.verified');

// 重新寄送驗證信
Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

// google 驗證
Route::middleware(['auth'])->group(function () {
    Route::get('/google/bind', [GoogleBindingController::class, 'redirectToGoogle'])->name('google.bind');
    Route::get('/google/callback', [GoogleBindingController::class, 'handleGoogleCallback'])->name('google.callback');
});

Route::get('dashboard', function () {
    return redirect()->route('index'); // ✅ 正確：導向 index 頁面
})->middleware(['auth', 'verified'])->name('dashboard');



// web.php
// Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
// Route::get('/auth/google/callback', [GoogleController::class, 'callback']);
Route::get('/auth/google', )->name('google.redirect');
Route::get('/auth/google/callback', );
require __DIR__.'/auth.php';



// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::get('/docs', [HomeController::class, 'docs'])->name('index');
// });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/docs', 'page.docs')->name('index');
    Route::view('/query/plant', 'page.query-plant')->name('query.plant');
    Route::view('/query/plot', 'page.query-plot')->name('query.plot');
    Route::view('/entry/notes', 'page.entry-notes')->name('entry.notes');
    Route::view('/entry/entry', 'page.entry-entry')->name('entry.entry');
    Route::view('/entry/missingnote', 'page.entry-missingnote')->name('entry.missingnote');
    Route::view('/survey/overview', 'page.survey-overview')->name('survey.overview');
    Route::view('/survey/stats', 'page.survey-stats')->name('survey.stats');
    Route::view('/data/export', 'page.data-export')->name('data.export');
});

//  // 下載檔案
Route::get('/download/{path}', [FileController::class, 'download'])
    ->where('path', '.*')
    ->middleware('auth')
    ->name('file.download');

Route::get('/view/{path}', [FileController::class, 'view'])
    ->where('path', '.*')
    ->middleware('auth')
    ->name('file.view');

Route::get('/test', function () {
    return view('test');
});

Route::get('/redirect-to-query-plot', function () {
    session([
        'query.county' => request('county'),
        'query.plot' => request('plot'),
        'query.subPlot' => request('subPlot'),
        'query.habitat' => request('habitat'),
        'query.spcode' => request('spcode'),

    ]);
    return redirect('/query/plot');
})->name('overview.to.query.plot');

Route::get('/redirect-to-entry-entry', function () {
    session([
        'query.county' => request('county'),
        'query.plot' => request('plot'),
        'query.subPlot' => request('subPlot'),
        'query.habitat' => request('habitat'),
    ]);
    return redirect('/entry/entry');
})->name('overview.to.entry.entry');
