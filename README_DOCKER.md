# Docker (Laravel)

## Start

```bash
cp .env.docker.example .env
docker compose up -d --build
```

App URL: `http://localhost:8081`

Vite dev server (optional): `http://localhost:5173`

phpMyAdmin: `http://localhost:8082` (host: `db`, user: `medic`, pass: `medic`, atau root/root)

Service `node` otomatis jalanin `npm ci|npm install` dan `npm run build` (sekali, kalau `public/build/manifest.json` belum ada) sebelum `npm run dev`.

Kalau browser kamu kebuka ke `http://0.0.0.0:5173/...` itu akan error (`ERR_ADDRESS_INVALID`). Pakai `http://localhost:5173` (sudah diforce via `vite.config.js` + `VITE_DEV_SERVER_URL`).

## First-time setup

Saat container `app` pertama kali jalan, entrypoint akan otomatis:
- `composer install`
- `php artisan key:generate`
- `php artisan storage:link`
- `php artisan migrate:fresh --seed`
- clear cache (`config/cache/view`)

Kalau kamu tidak mau auto-reset database, set `DOCKER_RUN_MIGRATIONS=false` di service `app` dan `reverb` pada `docker-compose.yml`.

Service `app`, `reverb`, dan `phpmyadmin` menunggu healthcheck `db`/`redis` sebelum start. Ini mencegah error awal seperti `SQLSTATE[HY000] [2002] Connection refused` saat Reverb membaca cache atau Laravel bootstrap terlalu cepat.

## Reverb (WebSocket)

```bash
docker compose up -d reverb
```

Expose: `ws://localhost:8080`

Image PHP menginstall ekstensi `pcntl` karena Laravel Reverb membutuhkan signal constants seperti `SIGINT` dan `SIGTERM` di Linux container.

Di Docker, service `app` dan `reverb` dioverride agar broadcast benar-benar memakai Reverb:

```env
BROADCAST_CONNECTION=reverb
CACHE_STORE=redis
REVERB_APP_ID=medic-app
REVERB_APP_KEY=medic-app-key
REVERB_APP_SECRET=medic-app-secret
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
```

Halaman test mitra tersedia di:

`http://localhost:8081/mitra/login`

Gunakan halaman itu untuk login mitra, subscribe ke private channel booking, menerima notifikasi matchmaking, dan test tombol accept booking.

## Useful commands

```bash
docker compose exec app php artisan tinker
docker compose exec app php artisan queue:work
docker compose logs -f nginx app reverb
docker compose exec app php artisan optimize:clear
```
