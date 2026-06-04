<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleBindingController;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Auth;


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
    Route::view('/docs', 'page.docs')->name('index');
    Route::view('/query/plant', 'page.query-plant')->name('query.plant');
    Route::view('/query/plot', 'page.query-plot')->name('query.plot');
    Route::view('/entry/notes', 'page.entry-notes')->name('entry.notes');
    Route::view('/entry/entry', 'page.entry-entry')->name('entry.entry');
    Route::view('/entry/missingnote', 'page.entry-missingnote')->name('entry.missingnote');
    Route::view('/survey/overview', 'page.survey-overview')->name('survey.overview');
    Route::redirect('/survey/stats', '/results/species')->name('survey.stats');
    Route::view('/results/species', 'page.survey-stats')->name('results.species');
    Route::view('/results/charts', 'page.results-charts')->name('results.charts');
    Route::view('/data/export', 'page.data-export')->name('data.export');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // 下載檔案
    Route::get('/download/{path}', [FileController::class, 'download'])
        ->where('path', '.*')
        ->name('file.download');

    Route::get('/view/{path}', [FileController::class, 'view'])
        ->where('path', '.*')
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
});
