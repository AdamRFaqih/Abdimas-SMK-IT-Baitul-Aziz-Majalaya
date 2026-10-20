#!/bin/bash
set -e

echo "==> [Entrypoint] Starting application setup..."

# Fix directory permissions for Laravel writable directories
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Wait for MySQL database if DB_HOST is set
if [ -n "$DB_HOST" ] && [ "$DB_CONNECTION" = "mysql" ]; then
    echo "==> [Entrypoint] Waiting for database at $DB_HOST:${DB_PORT:-3306}..."
    max_tries=30
    count=0
    until php -r "
        try {
            \$pdo = new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306) . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_TIMEOUT => 3]);
            exit(0);
        } catch (\Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; do
        count=$((count+1))
        if [ $count -ge $max_tries ]; then
            echo "==> [Entrypoint] Warning: Database connection timeout after $max_tries attempts. Continuing..."
            break
        fi
        sleep 2
    done
    echo "==> [Entrypoint] Database is ready!"
fi

# Ensure application key exists
if [ -z "$APP_KEY" ]; then
    echo "==> [Entrypoint] Generating application key..."
    php artisan key:generate --force
fi

# Create storage symlink
echo "==> [Entrypoint] Creating storage symlink..."
php artisan storage:link --force 2>/dev/null || true

# Run database migrations if AUTO_MIGRATE is true
if [ "$AUTO_MIGRATE" = "true" ]; then
    echo "==> [Entrypoint] Running database migrations..."
    php artisan migrate --force
fi

# Cache configuration, routes, and views in production
if [ "$APP_ENV" = "production" ]; then
    echo "==> [Entrypoint] Caching configuration, routes, and views for production..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "==> [Entrypoint] Initialization completed successfully."

# Execute CMD
exec "$@"
