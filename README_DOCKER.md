# Docker (Laravel)

## Start

```bash
cp .env.docker.example .env
docker compose up -d --build
```

App URL: `http://localhost:8081`

Vite dev server (optional): `http://localhost:5173`

## First-time setup

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

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
