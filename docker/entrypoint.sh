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

php artisan storage:link >/dev/null 2>&1 || true

exec "$@"
