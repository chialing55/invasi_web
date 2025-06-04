import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        host: '0.0.0.0',           // 允許網路訪問
        port: 5173,           // 固定 port，避免每次跳來跳去
        strictPort: true,     // 若 5173 被占用就直接錯誤，不自動換 port
        watch: {
            usePolling: true,   // 解決 Docker 中 Blade 改變不刷新問題
        },
        origin: 'http://localhost:5173', // ✅ 告訴 Laravel 要輸出哪個網址
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: [
                'resources/views/**/*.blade.php',       // 即時刷新 Blade 模板
                'resources/js/**/*.js',                 // 即時刷新 JS
                'app/Http/**/*.php',                    // 控制器變更時也能刷新
            ],
        }),
    ],
});
