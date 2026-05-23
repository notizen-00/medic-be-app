# Docker (Laravel)

## Start

```bash
cp .env.docker.example .env
docker compose up -d --build
```

App URL: `http://localhost:8081`

Vite dev server (optional): `http://localhost:5173`

## First-time setup

Saat container `app` pertama kali jalan, entrypoint akan otomatis:
- `composer install`
- `php artisan key:generate`
- `php artisan storage:link`
- `php artisan migrate:fresh --seed`
- clear cache (`config/cache/view`)

Kalau kamu tidak mau auto-reset database, set `DOCKER_RUN_MIGRATIONS=false` di service `app` dan `reverb` pada `docker-compose.yml`.

## Reverb (WebSocket)

```bash
docker compose up -d reverb
```

Expose: `ws://localhost:8080`

## Useful commands

```bash
docker compose exec app php artisan tinker
docker compose exec app php artisan queue:work
docker compose logs -f nginx app reverb
```
