#!/bin/sh
set -e


php artisan key:generate || true


chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

# 4. 等待資料庫啟動
echo "⏳ 等待 MySQL 資料庫啟動..."
until php artisan migrate:status; do
  echo "⌛ MySQL 尚未就緒，稍等 3 秒..."
  sleep 3
done

# 5. 執行 migrate

php artisan migrate --force

# 6. 清除 Laravel 快取
php artisan config:clear
php artisan cache:clear
php artisan view:clear

echo "✅ Laravel 專案初始化完成！"