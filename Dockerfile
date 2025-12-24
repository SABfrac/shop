# Используем официальный образ PHP 8.2 с FPM
FROM php:8.3-fpm

# Устанавливаем системные зависимости, необходимые для расширений PHP

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
            redis-tools\
            libzip-dev \
        && docker-php-ext-install pdo pdo_pgsql pgsql zip sockets \
#        && pecl install xdebug \
#        && docker-php-ext-enable xdebug \
        && apt-get clean \
        && rm -rf /var/lib/apt/lists/*



 RUN docker-php-ext-configure gd \
           --with-freetype \
           --with-jpeg \
           --with-webp
RUN docker-php-ext-install gd
# Установка PHP расширений

# Устанавливаем Supervisor
RUN apt-get update && apt-get install -y supervisor
# Устанавливаем расширение для RabbitMQ через PECL
#RUN pecl install amqp && docker-php-ext-enable amqp

# Устанавливаем расширение для Redis
RUN pecl install -o -f redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

# Устанавливаем Composer
#COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Устанавливаем рабочую директорию
WORKDIR /var/www/html

# Копируем composer файлы и устанавливаем зависимости (для кэширования слоев Docker)

#RUN composer install --no-interaction  --optimize-autoloader --no-plugins

# Копируем весь остальной код приложения
COPY . .

# Устанавливаем права на папки, в которые Yii2 будет писать
RUN chown -R www-data:www-data /var/www/html/runtime /var/www/html/web/assets