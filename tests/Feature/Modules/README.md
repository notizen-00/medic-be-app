# Feature Tests Per Module

Folder ini dipakai untuk mengelompokkan feature test berdasarkan area aplikasi.

Contoh struktur:

```text
tests/Feature/Modules/Auth
tests/Feature/Modules/Patient
tests/Feature/Modules/Mitra
tests/Feature/Modules/Admin
tests/Feature/Modules/Shared
tests/Feature/Modules/ServiceBooking
tests/Feature/Modules/Consultation
tests/Feature/Modules/Pharmacy
tests/Feature/Modules/Order
```

Jalankan semua feature test:

```bash
composer test:feature
```

Jalankan semua test module:

```bash
composer test:module
```

Jalankan satu module:

```bash
composer test:module:auth
composer test:module:patient
composer test:module:service-booking
```

Setiap feature test otomatis memakai `RefreshDatabase` dari `tests/Pest.php`, jadi data antar test di-reset menggunakan konfigurasi testing.

Dokumentasi lengkap ada di `README_TESTING_FEATURE.md`.
