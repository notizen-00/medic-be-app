# Feature Testing

Dokumen ini menjelaskan environment testing dan pola feature test per module untuk Medic BE App.

## Tujuan

Feature test dipakai untuk menguji alur API dan behavior aplikasi dari sisi HTTP, database, autentikasi, validasi, dan response JSON. Struktur per module membuat test lebih mudah dicari dan dijalankan sesuai area kerja.

## Environment Testing

Testing memakai konfigurasi khusus:

```env
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=":memory:"
CACHE_STORE=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
MAIL_MAILER=array
BROADCAST_CONNECTION=null
```

Konfigurasi utama ada di:

- `.env.testing`
- `.env.testing.example`
- `phpunit.xml`

`phpunit.xml` memakai `force="true"` untuk variabel penting agar test tidak terbawa config lokal atau config cache production.

## Database Reset

Semua test di `tests/Feature` otomatis memakai `RefreshDatabase` dari `tests/Pest.php`.

Efeknya:

- migration dijalankan untuk environment testing;
- data antar test tidak saling bocor;
- setiap test harus menyiapkan datanya sendiri lewat factory/model create.

## Struktur Per Module

Gunakan folder:

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

Contoh:

```text
tests/Feature/Modules/Auth/PatientRegistrationTest.php
```

## Command Testing

Jalankan semua test:

```bash
php artisan test
```

Jalankan semua feature test:

```bash
php artisan test tests/Feature
```

Jalankan semua module test:

```bash
php artisan test tests/Feature/Modules
```

Jalankan module tertentu:

```bash
php artisan test tests/Feature/Modules/Auth
php artisan test tests/Feature/Modules/Patient
php artisan test tests/Feature/Modules/ServiceBooking
```

Jalankan file tertentu:

```bash
php artisan test tests/Feature/PatientDoctorSpecializationTest.php
```

Filter nama test tertentu:

```bash
php artisan test --filter="filters patient doctor list by specialization"
```

Jika `php` belum ada di PATH terminal, pakai binary Laragon:

```bash
C:\laragon\bin\php\php-8.4.20-Win32-vs17-x64\php.exe artisan test
```

## Composer Scripts

Project juga menyediakan script Composer:

```bash
composer test
composer test:feature
composer test:module
composer test:module:auth
composer test:module:patient
composer test:module:service-booking
composer test:module:mitra
composer test:module:admin
composer test:module:consultation
composer test:module:pharmacy
composer test:module:order
composer test:module:shared
```

## Pola Penulisan Test

Gunakan Pest:

```php
it('registers a patient', function () {
    $response = $this->postJson('/api/patient/register', [
        'name' => 'Pasien Test',
        'email' => 'pasien@example.test',
        'phone' => '081234567890',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('users', [
        'email' => 'pasien@example.test',
        'role' => 'pasien',
    ]);
});
```

Untuk endpoint authenticated, gunakan Sanctum:

```php
use App\Models\User;
use Laravel\Sanctum\Sanctum;

$patient = User::factory()->create(['role' => 'pasien']);

Sanctum::actingAs($patient);
```

## Catatan Migration SQLite

Testing memakai SQLite in-memory. Migration yang memakai SQL spesifik MySQL perlu diberi guard atau ditulis memakai Schema API Laravel.

Contoh SQL yang perlu dihindari untuk SQLite:

- `SHOW INDEX`
- `ALTER TABLE ... MODIFY`
- `UPDATE ... INNER JOIN`
- fungsi MySQL seperti `NOW()` dan `CONCAT()`

Jika migration hanya melakukan backfill data lama, blok tersebut boleh dibatasi untuk non-SQLite agar fresh test database tetap bisa dibuat.

## Troubleshooting

Jika test memakai database lokal, jalankan:

```bash
php artisan optimize:clear
```

Jika muncul `could not find driver` untuk SQLite, aktifkan extension ini di PHP CLI:

```ini
extension=pdo_sqlite
extension=sqlite3
```

Jika test gagal karena data tidak ditemukan, cek apakah data fixture sudah memenuhi filter query aktual. Contoh: directory dokter hanya mengambil `partner_profiles.verification_status = verified`, jadi test harus membuat profile verified.
