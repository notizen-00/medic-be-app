# Service Booking & Matchmaking

Dokumen ini menjelaskan alur pemesanan layanan pasien dan pemilihan mitra otomatis untuk pesanan cepat.

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
- Sistem langsung mengisi `assigned_partner_user_id` dari hasil matchmaking.
- Setelah booking dibuat, event websocket `ServiceBookingMatched` dikirim ke channel private mitra terpilih.

Contoh response ringkas:

```json
{
  "message": "Booking layanan berhasil dibuat.",
  "data": {
    "id": 25,
    "booking_code": "SVB-20260705101010-123",
    "service_id": 1,
    "patient_user_id": 7,
    "assigned_partner_user_id": 12,
    "patient_address_id": 10,
    "status": "pending",
    "total_amount": "100000.00"
  },
  "matchmaking": {
    "partner_service_id": 4,
    "partner_user_id": 12,
    "distance_km": 2.35,
    "match_score": 82.4,
    "quality_score": 90
  }
}
```

## Kriteria Kandidat Mitra

Kandidat diambil dari relasi `services -> partner_services -> users -> partner_profiles`.

Mitra hanya dianggap eligible jika:
- `partner_services.is_active = true`;
- `partner_services.is_verified = true`;
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
4. Buat booking pasien melalui `POST /api/patient/service-bookings`.
5. Jika sistem memilih mitra tersebut, halaman akan menerima event `.service-booking.matched`.
6. Klik `Accept` untuk menguji endpoint `PATCH /api/mitra/service-bookings/{serviceBooking}/accept`.

Channel dan event:

```text
Channel: private-partner.{partnerId}.service-bookings
Laravel channel: partner.{partnerId}.service-bookings
Event: .service-booking.matched
Auth endpoint: POST /api/broadcasting/auth
```
