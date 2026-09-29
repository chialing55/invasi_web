<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('invasi-files:prune-exports', function () {
    $disk = Storage::disk('invasi_files');
    $cutoff = now()->subDay()->getTimestamp();
    $deleted = 0;

    foreach ($disk->files('exports') as $path) {
        $filename = basename($path);
        if (
            preg_match('/^[a-f0-9]{32}-/', $filename) === 1
            && $disk->lastModified($path) < $cutoff
            && $disk->delete($path)
        ) {
            $deleted++;
        }
    }

    $this->info("已清除 {$deleted} 個過期統計匯出檔。");
})->purpose('清除超過 24 小時且未下載的統計 PDF');

Schedule::command('invasi-files:prune-exports')->dailyAt('03:30');
