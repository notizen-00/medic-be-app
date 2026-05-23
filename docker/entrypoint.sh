#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ ! -f ".env" ] && [ -f ".env.example" ]; then
  cp .env.example .env
fi

if [ ! -f "vendor/autoload.php" ]; then
  composer install --no-interaction --prefer-dist
fi

php artisan key:generate --ansi --force >/dev/null 2>&1 || true

mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions || true
chmod -R 775 storage bootstrap/cache >/dev/null 2>&1 || true

php artisan storage:link >/dev/null 2>&1 || true
php artisan config:clear >/dev/null 2>&1 || true
php artisan cache:clear >/dev/null 2>&1 || true
php artisan view:clear >/dev/null 2>&1 || true

# One-time init for fresh environments (dangerous on existing DBs).
# Disable by setting DOCKER_RUN_MIGRATIONS=false
INIT_MARKER="storage/.docker-initialized"
if [ ! -f "$INIT_MARKER" ] && [ "${DOCKER_RUN_MIGRATIONS:-true}" != "false" ]; then
  # Wait a bit for MySQL if configured
  if [ "${DB_CONNECTION:-}" = "mysql" ] && [ -n "${DB_HOST:-}" ]; then
    HOST="${DB_HOST}"
    PORT="${DB_PORT:-3306}"
    echo "Waiting for DB at ${HOST}:${PORT}..."
    i=0
    while [ "$i" -lt 60 ]; do
      H="$HOST" P="$PORT" php -r "exit(@fsockopen(getenv('H') ?: 'db', (int)(getenv('P') ?: 3306)) ? 0 : 1);" && break || true
      i=$((i + 1))
      sleep 1
    done
  fi

  php artisan migrate:fresh --seed --force || true
  date > "$INIT_MARKER" || true
fi

exec "$@"
