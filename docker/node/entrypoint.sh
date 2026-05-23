#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ -f "package.json" ]; then
  if [ ! -d "node_modules" ]; then
    npm ci || npm install
  fi

  # Optional: ensure production build exists (Vite manifest)
  if [ "${DOCKER_NODE_BUILD:-true}" != "false" ] && [ ! -f "public/build/manifest.json" ]; then
    npm run build || true
  fi
fi

exec "$@"

