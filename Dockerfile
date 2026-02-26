# Используем официальный образ PHP 8.2 с FPM
FROM dunglas/frankenphp:1-php8.3

# ========== СИСТЕМНЫЕ ЗАВИСИМОСТИ ==========
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libfreetype6-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libonig-dev \
    unzip \
    zip \
    redis-tools \
    libzip-dev \
    supervisor \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ========== PHP РАСШИРЕНИЯ ==========
RUN docker-php-ext-install pdo pdo_pgsql pgsql zip sockets \
    && pecl install -o -f redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

# ========== НАСТРОЙКА GD ==========
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    && docker-php-ext-install gd

# ========== OPCACHE (ВАЖНО ДЛЯ FRANKENPHP) ==========
RUN docker-php-ext-install opcache

# ========== COMPOSER ==========
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ========== РАБОЧАЯ ДИРЕКТОРИЯ ==========
WORKDIR /app/public

COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-dev

# ========== КОПИРОВАНИЕ ФАЙЛОВ ==========
# Копируем composer файлы сначала (для кэширования слоев)


# Копируем весь код приложения
COPY . .
COPY Caddyfile /etc/caddy/Caddyfile
COPY php.ini /usr/local/etc/php/conf.d/99-custom.ini

# ========== ПРАВА ДОСТУПА ==========
RUN chown -R www-data:www-data /app/public/runtime /app/public/web/assets \
    && chmod -R 755 /app/public/runtime /app/public/web/assets

# ========== НАСТРОЙКИ FRANKENPHP ==========
# Переменные окружения по умолчанию
ENV SERVER_NAME=:80
ENV FRANKENPHP_CONFIG="worker ./index.php"
ENV FRANKENPHP_MAX_REQUESTS=1000
ENV FRANKENPHP_MEMORY_LIMIT=512M

EXPOSE 80 443

# ========== ЗАПУСК ==========
CMD ["frankenphp", "run"]