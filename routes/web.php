<?php

use App\Http\Controllers\DataDownloadController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GoogleBindingController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// 首頁（登入畫面 or dashboard）
Route::redirect('/', '/login');

// google 驗證
Route::middleware(['auth'])->group(function () {
    Route::get('/google/bind', [GoogleBindingController::class, 'redirectToGoogle'])->name('google.bind');
    Route::get('/google/callback', [GoogleBindingController::class, 'handleGoogleCallback'])->name('google.callback');
});

Route::get('dashboard', function () {
    return redirect()->route('index'); // ✅ 正確：導向 index 頁面
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/auth.php';

Route::get('/email-verified', function () {
    Auth::logout();
    session()->flush();

    return view('auth.email-verified');
})->name('email.verified');

// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::get('/docs', [HomeController::class, 'docs'])->name('index');
// });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/downloads/data', [DataDownloadController::class, 'raw'])->name('downloads.data');
    Route::post('/downloads/stats', [DataDownloadController::class, 'stats'])->name('downloads.stats');

    Route::view('/profile', 'page.profile')->name('profile.edit');
    Route::view('/docs', 'page.docs')->name('index');
    Route::view('/query/plant', 'page.query-plant')->name('query.plant');
    Route::view('/query/plot', 'page.query-plot')->name('query.plot');
    Route::view('/entry/notes', 'page.entry-notes')->name('entry.notes');
    Route::view('/entry/entry', 'page.entry-entry')->name('entry.entry');
    Route::view('/entry/missingnote', 'page.entry-missingnote')->name('entry.missingnote');
    Route::view('/entry/habitat-completion-exception', 'page.entry-habitat-completion-exception')
        ->name('entry.habitat-completion-exception');
    Route::view('/survey/overview', 'page.survey-overview')->name('survey.overview');
    Route::redirect('/survey/stats', '/results/species')->name('survey.stats');
    Route::redirect('/results/species', '/results/charts')->name('results.species');
    Route::view('/results/charts', 'page.results-charts')->name('results.charts');
    Route::view('/results/guide', 'page.results-guide')->name('results.guide');
    Route::redirect('/data/export', '/results/charts')->name('data.export');
});

Route::middleware(['auth', 'verified', 'can:manage-users'])->group(function () {
    Route::view('/admin/users', 'page.user-management')->name('admin.users');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/files/plot/{plot}', [FileController::class, 'plot'])->name('file.plot');
    Route::get('/files/subplot-photo/{plotFullId}', [FileController::class, 'subplotPhoto'])->name('file.subplot-photo');
    Route::get('/files/document/{document}', [FileController::class, 'document'])->name('file.document');
    Route::get('/files/export/{token}', [FileController::class, 'export'])->name('file.export');

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
});
