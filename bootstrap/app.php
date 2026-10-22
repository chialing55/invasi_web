<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 前端以此一次性代碼判斷下載回應已開始；內容不含敏感資料。
        $middleware->encryptCookies(except: ['download_complete']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
