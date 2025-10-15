# 第一步：Composer 安裝器
FROM composer:2 AS composer

# 第二步：主容器 PHP 8.2
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git curl unzip zip \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libxml2-dev \
    ca-certificates nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd xml

WORKDIR /app

# 安裝前端套件
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

# 複製 Laravel 專案
COPY . .

# 安裝 PHP 套件並設定權限
RUN composer install --no-interaction --prefer-dist \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY ./docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# 預設啟動指令（含 sleep 讓 db 準備好）
CMD ["sh", "-c", "sleep 5 && bash init.sh && npm run dev & exec php-fpm"]
