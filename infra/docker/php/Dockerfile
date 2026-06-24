# Backend API image — PHP 8.4 FPM. See docs/11-DEPLOYMENT.md.
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        bash git icu-dev libzip-dev postgresql-dev oniguruma-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo pdo_pgsql pgsql intl zip bcmath opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies first for better layer caching.
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress || true

COPY backend/ ./

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

# Opcache tuned for production.
COPY infra/docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 9000
CMD ["php-fpm"]
