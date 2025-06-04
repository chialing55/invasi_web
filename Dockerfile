# # 第一步：取得 Composer
FROM composer:2 AS composer

# 第二步：主容器使用 PHP 8.2 FPM
FROM php:8.2-fpm

# 安裝常用套件與 Node.js + npm
RUN apt-get update && apt-get install -y \
    git curl unzip zip libpng-dev libjpeg-dev libfreetype6-dev libzip-dev ca-certificates \
    nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd

# 設定工作目錄
WORKDIR /app

# 複製 package.json 並安裝前端套件
COPY package*.json ./

ENV PATH="./node_modules/.bin:$PATH"

RUN npm install -D vite@5.4.19 \
    laravel-vite-plugin@1.0.2 \
    tailwindcss@3.4.1 \
    postcss@8.4.31 \
    autoprefixer@10.4.16 \
    @tailwindcss/forms@latest \
    axios 

# 複製 Composer（從第一階段 composer image 複製進來）
# COPY --from=composer /usr/bin/composer /usr/bin/composer
# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
# 複製 Laravel 專案檔案並安裝 PHP 套件
COPY . .
RUN composer install --no-interaction --prefer-dist \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# 預設啟動指令（含 sleep 讓 db 準備好）
CMD ["sh", "-c", "sleep 5 && bash init.sh && npm run dev & exec php-fpm"]
