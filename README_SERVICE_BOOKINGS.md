# Service Booking & Matchmaking

Dokumen ini menjelaskan alur pemesanan layanan pasien berbasis Healthcare Marketplace. Pasien memilih kebutuhan layanan, bukan memilih profesi tenaga kesehatan. Backend baru melakukan matchmaking mitra setelah pembayaran layanan berhasil.

## Arsitektur Layanan

Frontend pasien cukup menampilkan daftar kebutuhan:

```text
Konsultasi Dokter
Dokter Datang ke Rumah
Pasang Infus
Pasang Kateter
Perawatan Luka
Rawat Lansia
Pemeriksaan Kehamilan
Caregiver 24 Jam
```

Backend memakai alur:

```text
Service Category -> Service -> Partner Service -> Booking -> Payment -> Matchmaking
```

Field service yang didukung:

| Field | Catatan |
| --- | --- |
| `service_category_id` | relasi kategori layanan, opsional untuk kompatibilitas data lama |
| `service_type` | `consultation`, `procedure`, `caregiver`, `homecare`, plus value legacy |
| `service_mode` | `chat`, `voice`, `video`, `visit` |
| `requires_address` | jika true, pasien wajib mengirim alamat/profil pasien beralamat |
| `requires_schedule` | jika true, pasien wajib mengirim jadwal |
| `requires_matchmaking` | jika true, backend mencari partner setelah pembayaran paid |

## Contoh Seed Service

Seeder utama ada di:

```text
database/seeders/JemberMedicSeeder.php
```

Contoh kategori layanan:

```php
ServiceCategory::updateOrCreate(
    ['slug' => 'nurse'],
    [
        'name' => 'Nurse',
        'icon' => 'heart-pulse',
        'sort_order' => 20,
        'is_active' => true,
    ]
);
```

Contoh service yang tampil ke pasien:

```php
Service::updateOrCreate(
    ['service_code' => 'SRV-NRS-JBR-001'],
    [
        'service_category_id' => $nurseCategory->id,
        'name' => 'Pasang Infus',
        'slug' => 'pasang-infus',
        'service_type' => 'procedure',
        'service_mode' => 'visit',
        'category' => 'Nurse',
        'description' => 'Layanan pemasangan infus di rumah oleh perawat terverifikasi.',
        'base_price' => 185000,
        'duration_minutes' => 90,
        'requires_address' => true,
        'requires_schedule' => true,
        'requires_matchmaking' => true,
        'sort_order' => 30,
        'is_active' => true,
        'is_homecare' => true,
    ]
);
```

Contoh harga mitra untuk service:

```php
PartnerService::updateOrCreate(
    [
        'service_id' => $pasangInfus->id,
        'partner_user_id' => $nurse->id,
    ],
    [
        'price' => 185000,
        'custom_price' => 185000,
        'coverage_radius_km' => 15,
        'is_active' => true,
        'is_verified' => true,
        'is_available' => true,
        'notes' => 'Perawat aktif untuk layanan pasang infus area Jember.',
    ]
);
```

Contoh markup aplikasi:

```php
ServiceMarkupSetting::updateOrCreate(
    [
        'service_id' => $pasangInfus->id,
        'priority' => 1,
        'notes' => 'Markup setting default untuk service Pasang Infus',
    ],
    [
        'markup_type' => 'percentage',
        'markup_value' => 10,
        'min_final_price' => null,
        'is_active' => true,
    ]
);
```

Harga yang dipakai saat booking:

```text
base_price service -> markup aplikasi -> promo -> total_amount
partner_services.price dipakai untuk ranking partner dan harga efektif mitra.
```

## Pesanan Cepat Pasien

Endpoint:

`POST /api/patient/service-bookings`

Middleware:
- `auth:sanctum`

Body minimal:

```json
{
  "service_id": 1,
  "patient_address_id": 10,
  "notes": "Pasien demam sejak malam"
}
```

Body dengan jadwal:

```json
{
  "service_id": 1,
  "patient_address_id": 10,
  "scheduled_at": "2026-07-06 10:00:00",
  "notes": "Datang pagi jika memungkinkan"
}
```

Catatan:
- `patient_user_id` opsional untuk endpoint pasien. Jika tidak dikirim, sistem memakai user dari token login.
- `patient_address_id` wajib secara bisnis untuk layanan homecare.
- Booking dibuat dengan status awal `pending`.
- `assigned_partner_user_id` masih `null` saat booking baru dibuat.
- Pasien melakukan pembayaran terlebih dahulu.
- Setelah payment berubah menjadi `paid`, backend menjalankan matchmaking, mengisi `assigned_partner_user_id`, dan mengirim event websocket `ServiceBookingMatched` ke mitra terpilih.

Contoh response ringkas:

```json
{
  "success": true,
  "message": "Service booking berhasil dibuat. Lanjutkan pembayaran agar sistem mencarikan mitra.",
  "data": {
    "booking": {
      "id": 25,
      "booking_code": "SVC-ABCDEFGH",
      "service_id": 1,
      "patient_user_id": 7,
      "assigned_partner_user_id": null,
      "patient_address_id": 10,
      "status": "pending",
      "total_amount": "100000.00"
    },
    "pricing": {
      "base_price": 100000,
      "markup_amount": 0,
      "subtotal": 100000,
      "discount_amount": 0,
      "total_amount": 100000,
      "duration_days": 1
    },
    "matchmaking": null,
    "matchmaking_status": "waiting_payment"
  }
}
```

Setelah callback pembayaran sukses, detail booking akan berisi `assigned_partner_user_id`.

## Kriteria Kandidat Mitra

Kandidat diambil dari relasi `services -> partner_services -> users -> partner_profiles`.

Mitra hanya dianggap eligible jika:
- `partner_services.is_active = true`;
- `partner_services.is_verified = true`;
- `partner_services.is_available = true`;
- `partner_profiles.verification_status = verified`;
- `partner_profiles.is_available = true`;
- jika jarak bisa dihitung dan `coverage_radius_km` terisi, jarak pasien ke mitra tidak boleh melebihi radius coverage.

Jika tidak ada kandidat eligible, API mengembalikan validation error:

```json
{
  "message": "Belum ada mitra aktif yang tersedia untuk layanan ini.",
  "errors": {
    "service_id": [
      "Belum ada mitra aktif yang tersedia untuk layanan ini."
    ]
  }
}
```

## Rumus Matchmaking

Implementasi utama ada di:

`app/Services/ServicePartnerSelectionService.php`

Method utama:
- `resolveBestPartnerForQuickBooking(Service $service, ?PatientAddress $address)`
- `resolveNearestPartnerForBooking(...)` tetap tersedia sebagai alias kompatibilitas.

Skor akhir:

```text
match_score = (distance_score * 0.50) + (quality_score * 0.40) + (price_score * 0.10)
```

Komponen:
- `distance_score`: semakin dekat semakin tinggi. Jika koordinat pasien atau mitra belum lengkap, sistem memberi skor default `60`.
- `quality_score`: gabungan pengalaman mitra, completion rate, jumlah booking selesai, dan penalti booking cancelled.
- `price_score`: harga lebih rendah mendapat skor lebih tinggi, dipakai sebagai pembeda ringan.

Quality score:

```text
quality_score =
  (experience_score * 0.45)
  + (completion_rate * 100 * 0.35)
  + (completion_volume_score * 0.20)
  - cancellation_penalty
```

Detail:
- `experience_score = min(100, years_of_experience * 10)`
- `completion_rate = completed_bookings / total_bookings`
- mitra tanpa histori diberi default completion rate `0.75`
- `completion_volume_score = min(100, completed_bookings * 10)`
- `cancellation_penalty = min(30, cancelled_bookings * 5)`

Urutan tie-breaker setelah `match_score`:
- jarak terdekat;
- `quality_score` lebih tinggi;
- harga efektif lebih rendah.

## Catalog Service

Catalog layanan memakai service yang sama untuk menghitung mitra tersedia.

Field response penting:
- `available_partner_count`: jumlah mitra eligible;
- `starting_price`: harga efektif terendah;
- `best_partner`: kandidat dengan `match_score` terbaik;
- `nearest_partner`: kandidat terdekat secara jarak untuk kompatibilitas UI lama.

## Testing

Test coverage tambahan:

`tests/Feature/ServiceBookingMatchmakingTest.php`

Jalankan:

```bash
php artisan test tests/Feature/ServiceBookingMatchmakingTest.php
```

Catatan environment lokal:
- jika `bootstrap/cache/config.php` aktif, test bisa tetap memakai config lama. Jalankan `php artisan config:clear` sebelum test jika diperlukan.
- test default memakai sqlite memory sesuai `phpunit.xml`; pastikan extension `pdo_sqlite` aktif.

## Testing Realtime Mitra

Halaman test web untuk mitra tersedia di:

`GET /mitra/login`

Alur manual:

1. Jalankan app dan Reverb.
2. Buka `/mitra/login`.
3. Login menggunakan akun mitra.
4. Pastikan status Reverb menjadi `subscribed`.
5. Panel `Online` akan menampilkan user yang join presence channel `online-users`.
6. Buat booking pasien melalui `POST /api/patient/service-bookings`.
7. Bayar booking sampai payment menjadi `paid` atau simulasikan callback Midtrans.
8. Jika sistem memilih mitra tersebut setelah pembayaran, halaman akan menerima event `.service-booking.matched`.
8. Klik `Accept` untuk menguji endpoint `PATCH /api/mitra/service-bookings/{serviceBooking}/accept`.

Channel dan event:

```text
Channel: private-partner.{partnerId}.service-bookings
Laravel channel: partner.{partnerId}.service-bookings
Event: .service-booking.matched
Auth endpoint: POST /api/broadcasting/auth
Presence channel: presence-online-users
```
