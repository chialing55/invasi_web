#!/bin/sh
set -e


if ! grep -Eq '^APP_KEY=.+$' .env; then
  php artisan key:generate
else
  echo "🔑 APP Key 已存在，保留目前設定。"
fi


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
