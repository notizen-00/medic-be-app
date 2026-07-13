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

Contoh ketersediaan mitra untuk service:

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

Harga yang dipakai saat booking service:

```text
services.base_price -> markup aplikasi -> promo -> total_amount
```

Catatan:
- Untuk layanan non-konsultasi seperti homecare, perawat datang, bidan datang, caregiver, procedure, dan visit, harga pasien memakai `services.base_price` yang ditentukan admin.
- `partner_services.price` dan `partner_services.custom_price` tidak dipakai sebagai harga pasien dan tidak menjadi pembeda ranking untuk service booking non-konsultasi.
- Untuk konsultasi dokter, flow konsultasi tetap memakai harga custom dokter dari `partner_profiles.consultation_fee`.

## Admin Master Service

Admin mengelola kategori layanan dan master service dari endpoint berikut. Semua endpoint memakai:

```http
Authorization: Bearer {admin_api_token}
Accept: application/json
Content-Type: application/json
```

### Service Category CRUD

```http
GET /api/admin/service-categories
POST /api/admin/service-categories
GET /api/admin/service-categories/{serviceCategory}
PATCH /api/admin/service-categories/{serviceCategory}
DELETE /api/admin/service-categories/{serviceCategory}
```

Query list:

| Query | Required | Type | Catatan |
| --- | --- | --- | --- |
| `search` | Tidak | string | cari `name` atau `slug` |
| `is_active` | Tidak | boolean | filter aktif/nonaktif |
| `per_page` | Tidak | integer | 1-100, default 20 |

Body create/update:

```json
{
  "name": "Nurse",
  "slug": "nurse",
  "icon": "heart-pulse",
  "sort_order": 20,
  "is_active": true
}
```

Field:

| Field | Required | Type | Catatan |
| --- | --- | --- | --- |
| `name` | Ya saat create | string | nama kategori yang tampil di admin/mobile |
| `slug` | Tidak | string | unique; jika kosong saat create dibuat dari `name` |
| `icon` | Tidak | string | nama icon untuk UI, contoh `heart-pulse` |
| `sort_order` | Tidak | integer | urutan tampilan kategori |
| `is_active` | Tidak | boolean | status kategori |

Kategori tidak bisa dihapus jika masih punya service.

### Service CRUD

```http
GET /api/admin/services
POST /api/admin/services
GET /api/admin/services/{service}
PATCH /api/admin/services/{service}
DELETE /api/admin/services/{service}
```

Query list:

| Query | Required | Type | Catatan |
| --- | --- | --- | --- |
| `service_category_id` / `category_id` | Tidak | integer | filter kategori |
| `service_type` | Tidak | enum | `consultation`, `procedure`, `caregiver`, `homecare`, atau value legacy |
| `service_mode` | Tidak | enum | `chat`, `voice`, `video`, `visit` |
| `is_active` | Tidak | boolean | filter aktif/nonaktif |
| `requires_address` | Tidak | boolean | filter layanan yang butuh alamat |
| `requires_schedule` | Tidak | boolean | filter layanan yang butuh jadwal |
| `requires_matchmaking` | Tidak | boolean | filter layanan yang butuh matchmaking |
| `search` | Tidak | string | cari nama, kode, slug, kategori teks |
| `per_page` | Tidak | integer | 1-100, default 20 |

Body create:

```json
{
  "service_category_id": 2,
  "service_code": "SRV-NRS-JBR-001",
  "name": "Pasang Infus",
  "slug": "pasang-infus",
  "service_type": "procedure",
  "service_mode": "visit",
  "category": "Nurse",
  "description": "Layanan pemasangan infus di rumah oleh perawat terverifikasi.",
  "base_price": 185000,
  "duration_minutes": 90,
  "requires_address": true,
  "requires_schedule": true,
  "requires_matchmaking": true,
  "sort_order": 30,
  "is_active": true,
  "is_homecare": true
}
```

Field:

| Field | Required | Type | Catatan |
| --- | --- | --- | --- |
| `service_category_id` | Tidak | integer | relasi ke `service_categories`; alias request `category_id` juga diterima |
| `service_code` | Tidak | string | unique; jika kosong dibuat otomatis |
| `name` | Ya saat create | string | nama service yang tampil ke pasien |
| `slug` | Tidak | string | jika kosong saat create dibuat dari `name` |
| `service_type` | Ya saat create | enum | `consultation`, `procedure`, `caregiver`, `homecare`, plus legacy |
| `service_mode` | Tidak | enum | `chat`, `voice`, `video`, `visit`; default `visit` |
| `category` | Tidak | string | label teks kompatibilitas lama |
| `description` | Tidak | string | deskripsi layanan |
| `base_price` | Ya saat create | numeric | harga dasar sebelum markup/promo |
| `duration_minutes` | Tidak | integer | default 60 |
| `requires_address` | Tidak | boolean | wajib alamat saat booking |
| `requires_schedule` | Tidak | boolean | wajib jadwal saat booking |
| `requires_matchmaking` | Tidak | boolean | jika false, tidak mencari mitra otomatis |
| `sort_order` | Tidak | integer | urutan tampilan service |
| `is_active` | Tidak | boolean | status service |
| `is_homecare` | Tidak | boolean | flag kompatibilitas homecare |

Service tidak bisa dihapus jika sudah dipakai oleh `partner_services` atau `service_bookings`.

Contoh response create service:

```json
{
  "message": "Master layanan berhasil dibuat.",
  "data": {
    "id": 1,
    "service_category_id": 2,
    "service_code": "SRV-NRS-JBR-001",
    "name": "Pasang Infus",
    "slug": "pasang-infus",
    "service_type": "procedure",
    "service_mode": "visit",
    "base_price": "185000.00",
    "requires_address": true,
    "requires_schedule": true,
    "requires_matchmaking": true,
    "is_active": true,
    "service_category": {
      "id": 2,
      "name": "Nurse",
      "slug": "nurse"
    }
  }
}
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
- `price_score`: untuk layanan konsultasi/chat dapat memakai harga custom partner sebagai pembeda ringan. Untuk service booking non-konsultasi, semua mitra memakai harga admin dari `services.base_price`, sehingga harga tidak menjadi pembeda ranking.

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
- harga efektif lebih rendah, hanya relevan untuk layanan yang masih memakai harga custom partner seperti konsultasi.

## Catalog Service

Catalog layanan memakai service yang sama untuk menghitung mitra tersedia.

Field response penting:
- `available_partner_count`: jumlah mitra eligible;
- `starting_price`: harga admin dari `services.base_price` untuk service booking non-konsultasi; untuk konsultasi dapat mengikuti harga custom partner;
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

## Booking Sekali Visit dan Terjadwal

Kontrak booking baru menggunakan field berikut:

| Field | Nilai | Keterangan |
| --- | --- | --- |
| `visit_plan` | `once`, `recurring` | pola kunjungan |
| `recurrence` | `weekly`, `monthly`, null | wajib untuk recurring |
| `visit_count` | 1-52 | recurring minimal 2 |
| `care_mode` | `visit`, `live_in` | live-in hanya untuk recurring |
| `location_type` | `home`, `hospital` | rumah sakit mendapat uang makan |

Booking recurring menyimpan kunjungan pertama pada `schedule_start_at`, kunjungan terakhir pada `schedule_end_at`, dan pola pada `recurrence`. Versi ini belum membuat lifecycle/status terpisah untuk setiap occurrence.

Transport dihitung per visit jika jarak mitra lebih dari ambang admin dan `care_mode` bukan live-in. Uang makan dihitung per visit untuk rumah sakit. Booking menyimpan `distance_km`, `transport_fee`, `meal_fee`, serta `fee_policy_snapshot`; payment memakai `total_amount` hasil snapshot tersebut.

Pengaturan admin:

```http
GET /api/admin/service-booking-fees
PUT /api/admin/service-booking-fees
```

Dokumentasi aplikasi klien yang lebih lengkap:

- `readMe/README_FLUTTER_PATIENT_INTEGRATION.md`
- `readMe/README_FLUTTER_MITRA_INTEGRATION.md`
- `readMe/README_ADMIN_DASHBOARD_API.md`
- `readMe/PRD-service-booking-terjadwal-dan-biaya.md`
