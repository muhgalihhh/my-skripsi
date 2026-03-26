# ============================================
# Dockerfile - Laravel Application
# ============================================
# Multi-stage build (optimized for production):
#   1. Composer (install PHP deps)
#   2. Node (build frontend assets)
#   3. php:8.3-fpm (Debian Bookworm) + Nginx + Supervisor
#
# Menggunakan Debian (bukan Alpine) karena:
#   - Alpine APK mirror sering corrupt package (perl, icu, dll)
#   - pdo_mysql sudah tersedia via apt tanpa compile manual
#   - Lebih stabil untuk production MySQL

# --- Stage 1: Composer dependencies ---
FROM composer:latest AS composer-builder

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

COPY . .
# --no-scripts: mencegah package:discover gagal karena dev packages tidak ada
RUN composer dump-autoload --optimize --no-dev --no-scripts

# --- Stage 2: Build frontend assets ---
FROM node:20-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --prefer-offline 2>/dev/null || npm install --prefer-offline

COPY vite.config.js ./
COPY resources/ ./resources/

# vendor diperlukan untuk Tailwind @source scanning
COPY --from=composer-builder /app/vendor/ ./vendor/

RUN npm run build

# --- Stage 3: PHP Application (Debian-based, lebih stabil) ---
FROM php:8.3-fpm

LABEL maintainer="Galih <galih@unsoed.ac.id>"
LABEL description="Laravel Web App - Topic Modeling Skripsi UNSOED"

# Install system deps + PHP extensions dalam 1 layer
# pdo_mysql: koneksi MySQL
# mbstring, zip, intl, bcmath, pcntl: required Laravel
# opcache: 2-3x faster PHP execution
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        default-mysql-client \
        curl \
        libzip-dev \
        libonig-dev \
        libicu-dev \
        libpng-dev \
        libxml2-dev \
        unzip \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        zip \
        intl \
        bcmath \
        pcntl \
        opcache \
    && rm -rf /var/lib/apt/lists/*

# Configure OPcache untuk production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

# Configure PHP-FPM untuk production
RUN { \
    echo '[www]'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 20'; \
    echo 'pm.start_servers = 4'; \
    echo 'pm.min_spare_servers = 2'; \
    echo 'pm.max_spare_servers = 6'; \
    echo 'pm.max_requests = 500'; \
} > /usr/local/etc/php-fpm.d/zz-production.conf

WORKDIR /var/www/html

# Copy application code
COPY . .

# Copy PHP dependencies dari composer stage
COPY --from=composer-builder /app/vendor/ ./vendor/

# Copy built frontend assets dari node stage
COPY --from=node-builder /app/public/build ./public/build

# Permissions: hanya directory yang perlu writable oleh www-data
# (bukan chown -R seluruh /var/www/html - itu sangat lambat)
RUN mkdir -p storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Nginx: Debian pakai /etc/nginx/conf.d/ (bukan http.d seperti Alpine)
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
# Hapus default site Debian nginx
RUN rm -f /etc/nginx/sites-enabled/default

# Supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:8080 || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
