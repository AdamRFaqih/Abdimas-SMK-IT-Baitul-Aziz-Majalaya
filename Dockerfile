# ==============================================================================
# Stage 1: Build Frontend Assets
# ==============================================================================
FROM node:20-alpine AS frontend

WORKDIR /app

# Copy package descriptors first to take advantage of Docker layer caching
COPY package.json package-lock.json ./
RUN npm ci || npm install

# Copy source assets required for building
COPY resources ./resources
COPY vite.config.js ./

# Build production assets using Vite
RUN npm run build

# ==============================================================================
# Stage 2: Production PHP-FPM + Nginx + Supervisor Image
# ==============================================================================
FROM php:8.2-fpm-bookworm

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1

# Install system dependencies, Nginx, Supervisor, and tools
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions for Laravel, DomPDF, and Excel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# Get Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy configuration files
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy application source code
COPY . /var/www/html

# Copy compiled frontend assets from frontend stage
COPY --from=frontend /app/public/build /var/www/html/public/build

# Install production PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Prepare permissions and make scripts executable
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod +x /var/www/html/docker/entrypoint.sh

# Expose HTTP port 80
EXPOSE 80

# Define entrypoint and default command
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
