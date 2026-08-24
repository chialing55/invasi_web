#!/bin/sh
set -e

# 1. 建立 .env 檔案（若不存在）
if [ ! -f .env ]; then
  echo "📄 建立 .env 檔案..."
  cp .env.example .env
fi

# 2. 僅在尚未設定時產生 APP Key
if ! grep -Eq '^APP_KEY=.+$' .env; then
  echo "🔑 產生 APP Key..."
  php artisan key:generate
else
  echo "🔑 APP Key 已存在，保留目前設定。"
fi

# echo "🎨 安裝 Breeze"
# composer require laravel/breeze --dev
# php artisan breeze:install blade

# 3. 設定目錄權限（storage、bootstrap/cache）
echo "🛠️ 設定 storage 和 cache 權限..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

# 4. 等待資料庫啟動
echo "⏳ 等待 MySQL 資料庫啟動..."
until php artisan migrate --pretend > /dev/null 2>&1; do
  echo "⌛ MySQL 尚未就緒，稍等 3 秒..."
  sleep 3
done

# 5. 執行 migrate
echo "🗂️ 開始正式 migrate..."
php artisan migrate

# 6. 清除 Laravel 快取
php artisan config:clear
php artisan cache:clear
php artisan view:clear

echo "✅ Laravel 專案初始化完成！"
