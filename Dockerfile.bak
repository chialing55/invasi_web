# 第一步：Composer 安裝器
FROM composer:2 AS composer

# 第二步：主容器 PHP 8.2
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git curl unzip zip libpng-dev libjpeg-dev libfreetype6-dev libzip-dev ca-certificates \
    nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd

WORKDIR /app

# 安裝前端套件（可選，可保留以備未來改為 CI Build）
COPY package*.json ./
ENV PATH="./node_modules/.bin:$PATH"
RUN npm install -D vite@5.4.19 \
    laravel-vite-plugin@1.0.2 \
    tailwindcss@3.4.1 \
    postcss@8.4.31 \
    autoprefixer@10.4.16 \
    @tailwindcss/forms@latest \
    axios 

# 安裝 Composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

# 複製 Laravel 專案檔案
COPY . .

# 安裝 PHP 套件並設定權限
RUN composer install --no-interaction --prefer-dist \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache
RUN echo "upload_max_filesize=12M" >> /usr/local/etc/php/php.ini
RUN echo "post_max_size=16M" >> /usr/local/etc/php/php.ini

# 啟動
# 預設啟動指令（含 sleep 讓 db 準備好）
CMD ["sh", "-c", "sleep 5 && bash init.sh && npm run dev & exec php-fpm"]

