#!/bin/sh
set -e

echo "Starting Laravel Application..."

cd /var/www/html

# Create .env if not exists (Docker env vars will override)
if [ ! -f ".env" ]; then
    echo "Creating .env file..."
    if [ -f ".env.example" ]; then
        cp .env.example .env
    else
        touch .env
    fi
fi

# Generate app key if not set (from env var or .env file)
if [ -z "$APP_KEY" ] && ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    echo "Generating application key..."
    php artisan key:generate --force
    # Export the newly generated key so config:cache picks it up
    export APP_KEY=$(grep "^APP_KEY=" .env | cut -d '=' -f2-)
    echo "APP_KEY generated and exported: ${APP_KEY}"
elif [ -n "$APP_KEY" ]; then
    echo "APP_KEY found from environment variable"
    # Ensure .env file has the key from env var for config:cache
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env 2>/dev/null || true
else
    echo "APP_KEY found in .env file"
    # Export the key from .env so config:cache picks it up
    export APP_KEY=$(grep "^APP_KEY=" .env | cut -d '=' -f2-)
fi

# Wait for MySQL to be ready
# NOTE: Docker Compose healthcheck already handles this via depends_on condition
# Skipping manual wait as it causes restart loops when mysql is already healthy
echo "MySQL should be ready via Docker Compose depends_on healthcheck"

# until MYSQL_PWD="$DB_PASSWORD" mysqladmin ping -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --skip-ssl --silent > /dev/null 2>&1 || [ $TRIES -eq $MAX_TRIES ]; do
#     echo "  MySQL not ready yet (attempt $((TRIES+1))/$MAX_TRIES), retrying in 2s..."
#     sleep 2
#     TRIES=$((TRIES+1))
# done
# if [ $TRIES -eq $MAX_TRIES ]; then
#     echo "MySQL connection failed after $MAX_TRIES attempts. Exiting."
#     exit 1
# fi
# echo "MySQL connected!"

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Create log directory for supervisor
mkdir -p /var/log/supervisor

# Run migrations
echo "Running migrations..."
php artisan migrate --force 2>&1 || echo "Migration warning (may already be up to date)"

# Seed database
echo "Seeding database..."
php artisan db:seed --force 2>&1 || echo "Seeder warning (may already be seeded)"

# Discover packages (skipped during Docker build to avoid dev-only provider errors)
echo "Discovering packages..."
php artisan package:discover --ansi 2>&1 || echo "Package discover warning"

# Clear stale sessions & old cache from persistent volume
echo "Clearing stale cache/sessions..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
rm -rf storage/framework/sessions/* 2>/dev/null || true

# Re-export APP_KEY before config:cache (ensure it's in environment)
if [ -z "$APP_KEY" ]; then
    export APP_KEY=$(grep "^APP_KEY=" .env | cut -d '=' -f2-)
fi

# Cache config & routes (SKIP di development mode)
if [ "$SKIP_OPTIMIZE" = "true" ]; then
    echo "DEV MODE: Skipping config/route/view cache (live reload aktif)"
    echo "  -> Edit file PHP/Blade langsung terlihat tanpa rebuild!"
else
    echo "PRODUCTION: Optimizing..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Create storage link
php artisan storage:link 2>/dev/null || true

echo "Laravel ready! Listening on port 8080"
echo "Login: jurusan@unsoed.ac.id / password"

exec "$@"
