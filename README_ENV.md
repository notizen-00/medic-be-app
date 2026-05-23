# Environment files

Best practice:
- Jangan commit file `.env*` yang berisi secret.
- Simpan nilai secret di CI/CD atau secret manager.

File yang disediakan:
- `.env.development` untuk local dev
- `.env.production` untuk production
- `.env.staging` untuk staging
- `.env.testing` untuk test runner

## Force HTTPS (production)

Di `app/Providers/AppServiceProvider.php`, kalau `APP_ENV=production` dan `FORCE_HTTPS=true`, Laravel akan `forceScheme('https')`.

Catatan: kalau deploy di belakang reverse proxy / load balancer, pastikan `X-Forwarded-Proto` diteruskan dan middleware proxy kamu sudah benar, supaya HTTPS terdeteksi dengan tepat.

