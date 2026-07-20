# Flutter Patient App Integration

Dokumen ini untuk aplikasi Flutter pasien yang akan memakai API dan WebSocket dari backend Medic App.

## Base URL

Production:

```text
https://backend.perawatku.tech
```

Local Docker:

```text
http://localhost:8081
```

Semua endpoint API memakai prefix:

```text
/api
```

Header umum:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer {user_api_token}
```

Header `Authorization` hanya dipakai setelah login.

## Auth Pasien

### Register

```http
POST /api/patient/register
```

Body:

```json
{
  "name": "Budi",
  "email": "budi@example.com",
  "phone": "08123456789",
  "password": "password123",
  "password_confirmation": "password123",
  "date_of_birth": "1995-01-20",
  "gender": "laki-laki",
  "address": "Jl. Kalimantan No. 1, Jember",
  "blood_type": "O",
  "emergency_contact_name": "Siti",
  "emergency_contact_phone": "081298765432",
  "allergies": "Alergi penicillin",
  "medical_notes": "Riwayat hipertensi"
}
```

Field register:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `name` | Ya | string | max 255 |
| `email` | Ya | email | max 255, unique |
| `phone` | Ya | string | max 20, unique |
| `password` | Ya | string | min 8 |
| `password_confirmation` | Ya | string | harus sama dengan `password` |
| `date_of_birth` | Tidak | date | format aman: `YYYY-MM-DD` |
| `gender` | Tidak | enum | `laki-laki`, `perempuan` |
| `address` | Tidak | string | alamat profil pasien |
| `blood_type` | Tidak | string | max 5 |
| `emergency_contact_name` | Tidak | string | max 255 |
| `emergency_contact_phone` | Tidak | string | max 20 |
| `allergies` | Tidak | string | catatan alergi |
| `medical_notes` | Tidak | string | catatan medis tambahan |

### Login

```http
POST /api/patient/login
```

Body:

```json
{
  "email": "budi@example.com",
  "password": "password123"
}
```

Field login:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `email` | Ya | email | email akun pasien |
| `password` | Ya | string | password akun |

Response penting:

```json
{
  "message": "Login berhasil.",
  "data": {
    "id": 7,
    "name": "Budi",
    "email": "budi@example.com",
    "role": "patient"
  },
  "user_api_token": "1|plain-token"
}
```

Simpan `user_api_token` di secure storage Flutter. Login/register akan menerbitkan token baru dan mereset token lama user tersebut, jadi selalu overwrite token lama di secure storage setelah login ulang.

Semua endpoint protected memakai:

```http
Authorization: Bearer 1|plain-token
```

### Me

```http
GET /api/shared/me
```

### Logout

```http
POST /api/patient/logout
```

atau:

```http
POST /api/shared/logout
```

### Foto Profil

Endpoint foto profil bersifat shared, jadi bisa dipakai oleh pasien dan role login lain yang memakai Bearer token.

```http
POST /api/shared/profile-photo
DELETE /api/shared/profile-photo
```

Upload foto memakai `multipart/form-data`, bukan JSON. Jangan set header `Content-Type` manual di Flutter; biarkan `MultipartRequest`/Dio/FormData mengisi boundary otomatis.

Field upload:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `profile_photo` | Ya | file image | jpg, jpeg, png, webp; max 2MB |

Contoh response setelah upload:

```json
{
  "message": "Foto profil berhasil diperbarui.",
  "data": {
    "id": 7,
    "name": "Budi",
    "role": "pasien",
    "profile_photo_path": "users/7/profile/abc123.jpg",
    "profile_photo_url": "https://backend.perawatku.tech/storage/users/7/profile/abc123.jpg"
  }
}
```

Gunakan `profile_photo_url` untuk preview di aplikasi. `profile_photo_path` hanya path internal backend.

## Endpoint Pasien

Semua endpoint di bawah ini memakai `Authorization: Bearer {token}` dari akun pasien. Route `/api/patient/*` dilindungi middleware role pasien, sehingga token mitra tidak dapat memakai endpoint pasien.

### Dokter, Perawat, Apotik

```http
GET /api/patient/doctors
GET /api/patient/nurses
GET /api/patient/apotiks
```

Query `GET /api/patient/doctors`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `view` | Tidak | enum | `list`, `specializations`; jika `specializations`, response berisi daftar spesialisasi |
| `search` | Tidak | string | max 100 |
| `specialization` | Tidak | string | max 100 |
| `is_available` | Tidak | boolean | `true`/`false` atau `1`/`0` |
| `patient_address_id` | Tidak | integer | harus ada di `patient_addresses` |
| `latitude` | Tidak | numeric | -90 sampai 90, wajib bersama `longitude` |
| `longitude` | Tidak | numeric | -180 sampai 180, wajib bersama `latitude` |
| `max_distance_km` | Tidak | numeric | min 0 |
| `limit` | Tidak | integer | 1-100; dipakai sebagai `per_page` jika `per_page` tidak dikirim |
| `per_page` | Tidak | integer | 1-100 |

Query `GET /api/patient/nurses`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `search` | Tidak | string | max 100 |
| `specialization` | Tidak | string | max 100 |
| `is_available` | Tidak | boolean | `true`/`false` atau `1`/`0` |
| `patient_address_id` | Tidak | integer | harus ada di `patient_addresses` |
| `latitude` | Tidak | numeric | -90 sampai 90, wajib bersama `longitude` |
| `longitude` | Tidak | numeric | -180 sampai 180, wajib bersama `latitude` |
| `max_distance_km` | Tidak | numeric | min 0 |
| `limit` | Tidak | integer | 1-100 |
| `per_page` | Tidak | integer | 1-100 |

Query `GET /api/patient/apotiks`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `search` | Tidak | string | max 100 |
| `is_available` | Tidak | boolean | filter apotik aktif |
| `per_page` | Tidak | integer | 1-100 |

### Layanan

Untuk UI mobile, gunakan pola marketplace:

```text
Ambil catalog service -> group by service.service_category -> user pilih category -> tampilkan service di category itu -> booking -> payment -> matchmaking
```

Catatan penting:

```text
Tidak perlu memanggil endpoint admin /api/admin/service-categories dari app pasien.
App pasien cukup memakai catalog service pasien, lalu membuat kategori dari field service.service_category.
```

```http
GET /api/patient/services
GET /api/patient/services/{service}
```

Query `GET /api/patient/services`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `patient_address_id` | Tidak | integer | dipakai menghitung jarak/matchmaking |
| `service_type` | Tidak | enum | `consultation`, `procedure`, `caregiver`, `homecare`; value legacy seperti `dokter_homecare`, `perawat_homecare`, `bidan_homecare`, `konsultasi_tindakan` masih didukung |
| `service_mode` | Tidak | enum/string | `chat`, `voice`, `video`, `visit` |
| `search` | Tidak | string | max 100 |
| `per_page` | Tidak | integer | 1-100 |

Query `GET /api/patient/services/{service}`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `patient_address_id` | Tidak | integer | dipakai menghitung jarak/matchmaking |

Alternatif catalog pasien:

```http
GET /api/patient/service-bookings/services
GET /api/patient/service-bookings/services/{service}
```

Endpoint ini direkomendasikan untuk halaman pilih layanan di Flutter karena response langsung memuat `service_category`, harga dasar, status service, dan flag kebutuhan booking.

Query alternatif catalog:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `category` | Tidak | string | filter kategori layanan |
| `category_id` | Tidak | integer | filter `service_category_id`; dipakai saat user memilih category di UI |
| `service_mode` | Tidak | enum/string | `chat`, `voice`, `video`, `visit` |
| `search` | Tidak | string | cari nama layanan |
| `per_page` | Tidak | integer | default 20 |

Contoh ambil semua catalog lalu group category di Flutter:

```http
GET /api/patient/service-bookings/services?per_page=100
```

Contoh response item catalog:

```json
{
  "id": 1,
  "service_category_id": 2,
  "service_code": "SRV-NRS-JBR-001",
  "name": "Pasang Infus",
  "slug": "pasang-infus",
  "service_type": "procedure",
  "service_mode": "visit",
  "category": "Nurse",
  "description": "Layanan pemasangan infus di rumah oleh perawat terverifikasi.",
  "base_price": "185000.00",
  "duration_minutes": 90,
  "requires_address": true,
  "requires_schedule": true,
  "requires_matchmaking": true,
  "sort_order": 30,
  "is_active": true,
  "is_homecare": true,
  "service_category": {
    "id": 2,
    "name": "Nurse",
    "slug": "nurse",
    "icon": "heart-pulse",
    "sort_order": 20,
    "is_active": true
  }
}
```

Contoh UI data mapping:

| UI | Source response |
| --- | --- |
| Tab/chip category | `service.service_category.name` |
| Icon category | `service.service_category.icon` |
| Nama card service | `service.name` |
| Harga mulai | `service.base_price` atau detail `pricing.final_price` |
| Badge online/tersedia | service aktif dan jumlah mitra jika endpoint catalog advanced mengirim count |
| Butuh alamat | `service.requires_address` |
| Butuh jadwal | `service.requires_schedule` |

Catatan harga service booking:
- Untuk layanan non-konsultasi seperti homecare, perawat datang, bidan datang, caregiver, procedure, dan visit, harga dasar memakai `service.base_price` dari admin.
- Harga yang tampil ke pasien sebaiknya memakai `pricing.final_price` karena sudah memasukkan Service Markup Setting.
- `partner_services.price` hanya harga dasar yang terlihat di aplikasi mitra dan dikunci ke `service.base_price` untuk service booking non-konsultasi.
- Field `partner_services.custom_price` sudah tidak dipakai.
- Konsultasi dokter tetap memakai flow konsultasi dan harga custom dokter dari `partner_profile.consultation_fee`.

Contoh user pilih category Nurse:

```http
GET /api/patient/service-bookings/services?category_id=2&per_page=20
```

Contoh filter layanan visit:

```http
GET /api/patient/service-bookings/services?category_id=2&service_mode=visit
```

Contoh detail sebelum booking:

```http
GET /api/patient/service-bookings/services/1
```

Response detail berisi:

```json
{
  "success": true,
  "data": {
    "service": {
      "id": 1,
      "name": "Pasang Infus",
      "service_category": {
        "id": 2,
        "name": "Nurse",
        "slug": "nurse"
      },
      "requires_address": true,
      "requires_schedule": true
    },
    "pricing": {
      "base_price": 185000,
      "markup_amount": 18500,
      "final_price": 203500
    }
  }
}
```

Rekomendasi layar mobile:

```text
Home Layanan
- Search service
- Horizontal category dari service_category
- List service berdasarkan category aktif

Detail Service
- Nama service
- Deskripsi
- Harga mulai/final price
- Durasi
- Form alamat jika requires_address=true
- Form jadwal jika requires_schedule=true
- Tombol Booking

Booking Status
- Menunggu pembayaran
- Pembayaran diproses
- Mencari mitra
- Mitra ditemukan
- Dalam perjalanan
- Selesai
```

### Profil Pasien Keluarga

Satu akun pasien bisa memiliki beberapa profil pasien, misalnya diri sendiri, suami, istri, anak, kakek, atau nenek. Profil ini bisa dipakai saat membuat booking layanan dengan mengirim `patient_member_id`.

```http
GET /api/patient/members
POST /api/patient/members
GET /api/patient/members/{patientMember}
PATCH /api/patient/members/{patientMember}
PATCH /api/patient/members/{patientMember}/primary
DELETE /api/patient/members/{patientMember}
```

Query `GET /api/patient/members`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `relationship` | Tidak | string | contoh `self`, `suami`, `istri`, `anak`, `kakek`, `nenek` |
| `search` | Tidak | string | cari nama, hubungan, telepon, alamat |
| `per_page` | Tidak | integer | 1-100 |

Body `POST /api/patient/members`:

```json
{
  "name": "Siti Aminah",
  "relationship": "istri",
  "date_of_birth": "1996-02-14",
  "age": 30,
  "gender": "perempuan",
  "phone": "081234567890",
  "blood_type": "A",
  "emergency_contact_name": "Budi",
  "emergency_contact_phone": "081298765432",
  "allergies": "Alergi seafood",
  "medical_notes": "Riwayat asma",
  "address_label": "Rumah",
  "recipient_name": "Siti Aminah",
  "recipient_phone": "081234567890",
  "address": "Jl. Jawa No. 10, Jember",
  "province": "Jawa Timur",
  "city": "Jember",
  "district": "Sumbersari",
  "postal_code": "68121",
  "latitude": -8.172357,
  "longitude": 113.700302,
  "is_primary": false
}
```

Field `POST/PATCH /api/patient/members`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `name` | Ya saat POST | string | max 255 |
| `relationship` | Tidak | string | max 50; contoh `self`, `suami`, `istri`, `anak`, `kakek`, `nenek` |
| `date_of_birth` | Tidak | date | format `YYYY-MM-DD` |
| `age` | Tidak | integer | 0-150; bisa dipakai jika tanggal lahir tidak diketahui |
| `gender` | Tidak | enum | `laki-laki`, `perempuan` |
| `phone` | Tidak | string | max 20 |
| `blood_type` | Tidak | string | max 5 |
| `emergency_contact_name` | Tidak | string | max 255 |
| `emergency_contact_phone` | Tidak | string | max 20 |
| `allergies` | Tidak | string | alergi pasien |
| `medical_notes` | Tidak | string | catatan medis |
| `address_label` | Tidak | string | contoh `Rumah`, `Kos`, `Rumah Kakek` |
| `recipient_name` | Tidak | string | nama penerima/alamat |
| `recipient_phone` | Tidak | string | telepon penerima |
| `address` | Tidak | string | alamat lengkap profil pasien |
| `province` | Tidak | string | provinsi |
| `city` | Tidak | string | kota/kabupaten |
| `district` | Tidak | string | kecamatan |
| `postal_code` | Tidak | string | max 10 |
| `latitude` | Tidak | numeric | -90 sampai 90 |
| `longitude` | Tidak | numeric | -180 sampai 180 |
| `is_primary` | Tidak | boolean | jika `true`, profil utama lama otomatis dimatikan |

Endpoint `PATCH /api/patient/members/{patientMember}/primary` tidak butuh body.

### Booking Layanan / Matchmaking

Buat booking:

```http
POST /api/patient/service-bookings
```

Body minimal:

```json
{
  "service_id": 1,
  "patient_member_id": 2,
  "patient_address_id": 10,
  "booking_type": "scheduled",
  "notes": "Pasien demam sejak malam"
}
```

Field request `POST /api/patient/service-bookings`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `service_id` | Ya | integer | harus ada di `services` |
| `patient_member_id` | Ya | integer | harus milik akun pasien login; profil pasien keluarga yang menerima layanan |
| `patient_address_id` | Tidak | integer | harus ada di `patient_addresses`; jika tidak dikirim backend memakai alamat dari `patient_member_id` |
| `booking_type` | Tidak | enum | `scheduled` untuk sekali jalan, `daily` untuk layanan harian; default `scheduled` |
| `scheduled_at` | Tidak | datetime | format `YYYY-MM-DD HH:mm:ss`; wajib setelah waktu sekarang jika dikirim |
| `schedule_start_at` | Tidak | datetime | wajib untuk `booking_type=daily` jika `scheduled_at` tidak dikirim |
| `schedule_end_at` | Tidak | datetime | tanggal selesai `daily`; harus >= `schedule_start_at` |
| `duration_days` | Tidak | integer | 1-30; dipakai untuk `daily` jika `schedule_end_at` tidak dikirim |
| `visit_plan` | Tidak | enum | `once` atau `recurring`; default `once` untuk kompatibilitas |
| `recurrence` | Kondisional | enum | wajib saat `visit_plan=recurring`; `weekly` atau `monthly` |
| `visit_count` | Kondisional | integer | wajib saat recurring; minimal 2, maksimal 52 |
| `care_mode` | Tidak | enum | `visit` atau `live_in`; live-in hanya untuk recurring |
| `location_type` | Tidak | enum | `home` atau `hospital`; default `home` |
| `notes` | Tidak | string | catatan pasien; max 1000 |
| `promo_code` | Tidak | string | kode promo jika dipakai |

Body dengan jadwal:

```json
{
  "service_id": 1,
  "patient_member_id": 2,
  "patient_address_id": 10,
  "booking_type": "scheduled",
  "scheduled_at": "2026-07-06 10:00:00",
  "notes": "Datang pagi jika memungkinkan"
}
```

Jika `patient_address_id` tidak dikirim, backend akan memakai alamat dari `patient_member_id` jika profil tersebut punya alamat. Untuk layanan homecare, kirim salah satu:

```text
patient_address_id
atau
patient_member_id yang punya alamat
```

Body layanan harian:

```json
{
  "service_id": 1,
  "patient_address_id": 10,
  "booking_type": "daily",
  "schedule_start_at": "2026-07-08 09:00:00",
  "duration_days": 3,
  "notes": "Perawatan luka selama 3 hari"
}
```

Alternatif layanan harian dengan tanggal selesai:

```json
{
  "service_id": 1,
  "patient_address_id": 10,
  "booking_type": "daily",
  "schedule_start_at": "2026-07-08 09:00:00",
  "schedule_end_at": "2026-07-10 09:00:00"
}
```

Rule harga harian:

```text
subtotal harian = harga per hari x duration_days
markup harian = markup per hari x duration_days
promo percentage = diskon per hari x duration_days
promo fixed = dipotong satu kali
total_amount = subtotal - discount_amount
```

#### Booking sekali visit dan terjadwal

Untuk UI Flutter baru, gunakan `visit_plan` dan jangan menjadikan `booking_type` sebagai pilihan utama. `booking_type` tetap ada untuk kompatibilitas versi aplikasi lama.

Contoh sekali visit:

```json
{
  "service_id": 1,
  "patient_member_id": 2,
  "visit_plan": "once",
  "scheduled_at": "2026-07-20 09:00:00",
  "care_mode": "visit",
  "location_type": "home"
}
```

Contoh terjadwal mingguan empat kunjungan di rumah sakit:

```json
{
  "service_id": 1,
  "patient_member_id": 2,
  "visit_plan": "recurring",
  "recurrence": "weekly",
  "visit_count": 4,
  "scheduled_at": "2026-07-20 09:00:00",
  "care_mode": "visit",
  "location_type": "hospital"
}
```

Contoh terjadwal bulanan live-in:

```json
{
  "service_id": 1,
  "patient_member_id": 2,
  "visit_plan": "recurring",
  "recurrence": "monthly",
  "visit_count": 3,
  "scheduled_at": "2026-07-20 09:00:00",
  "care_mode": "live_in",
  "location_type": "hospital"
}
```

Aturan biaya:

```text
service_subtotal = (base_price + markup_per_visit) x visit_count
transport_fee = transport_fee_per_visit x visit_count
meal_fee = hospital_meal_fee_per_visit x visit_count
total_amount = service_subtotal - discount_amount + transport_fee + meal_fee
```

Transport dikenakan jika `care_mode=visit`, koordinat tersedia, dan jarak mitra lebih besar dari ambang admin. Sekali visit dikenakan satu kali; recurring dikenakan per visit. Live-in tidak dikenakan transport. Jarak tepat pada ambang tidak dikenai biaya. Lokasi rumah sakit selalu mendapat uang makan per visit. Nilai `distance_km` dan `fee_policy_snapshot` dari response harus dianggap snapshot/read-only.

Rekomendasi state form Flutter:

```dart
enum VisitPlan { once, recurring }
enum Recurrence { weekly, monthly }
enum CareMode { visit, liveIn }
enum BookingLocationType { home, hospital }

Map<String, dynamic> buildBookingPayload({
  required int serviceId,
  required int patientMemberId,
  required VisitPlan visitPlan,
  Recurrence? recurrence,
  int visitCount = 1,
  required DateTime scheduledAt,
  CareMode careMode = CareMode.visit,
  BookingLocationType locationType = BookingLocationType.home,
}) {
  return {
    'service_id': serviceId,
    'patient_member_id': patientMemberId,
    'visit_plan': visitPlan.name,
    if (visitPlan == VisitPlan.recurring) ...{
      'recurrence': recurrence!.name,
      'visit_count': visitCount,
    },
    'scheduled_at': scheduledAt.toIso8601String(),
    'care_mode': careMode == CareMode.liveIn ? 'live_in' : 'visit',
    'location_type': locationType.name,
  };
}
```

Di layar konfirmasi, tampilkan breakdown dari response backend (`subtotal`, `discount_amount`, `transport_fee`, `meal_fee`, `extra_fees`, `fee_messages`, `total_amount`). Jangan menghitung total final hanya di Flutter karena tarif admin dan jarak mitra ditentukan backend.

Jika `pricing.extra_fee_applied=true`, tampilkan banner biaya tambahan. Contoh field:

```json
{
  "extra_fee_total": 25000,
  "extra_fee_applied": true,
  "extra_fees": {
    "transport": {
      "applied": true,
      "amount": "25000.00",
      "distance_km": 14.45,
      "threshold_km": 10,
      "distance_over_threshold_km": 4.45,
      "message": "Lokasi berjarak 14.45 km, melewati batas 10.00 km. Biaya transport tambahan Rp25.000 dikenakan."
    },
    "meal": {
      "applied": false,
      "amount": "0.00",
      "message": null
    }
  },
  "fee_messages": [
    "Lokasi berjarak 14.45 km, melewati batas 10.00 km. Biaya transport tambahan Rp25.000 dikenakan."
  ]
}
```

Response penting:

```json
{
  "success": true,
  "message": "Service booking berhasil dibuat dan dikirim ke mitra. Lanjutkan pembayaran setelah mitra menerima pesanan.",
  "data": {
    "booking": {
      "id": 25,
      "booking_code": "SVC-ABCDEFGH",
      "service_id": 1,
      "patient_user_id": 7,
      "patient_member_id": 2,
      "assigned_partner_user_id": 12,
      "patient_address_id": null,
      "booking_type": "scheduled",
      "visit_plan": "recurring",
      "recurrence": "weekly",
      "visit_count": 4,
      "care_mode": "visit",
      "location_type": "hospital",
      "distance_km": "12.40",
      "status": "pending",
      "duration_days": 1,
      "total_amount": "560000.00",
      "payment": {
        "id": 50,
        "payment_code": "PAY-SVC-20260707120000-123",
        "status": "pending",
        "amount": "560000.00"
      }
    },
    "pricing": {
      "base_price": 100000,
      "markup_amount": 0,
      "subtotal": 400000,
      "discount_amount": 0,
      "transport_fee": "100000.00",
      "meal_fee": "60000.00",
      "total_amount": 560000,
      "visit_count": 4
    },
    "matchmaking": {
      "partner_service_id": 4,
      "partner_user_id": 12,
      "distance_km": 2.35,
      "match_score": 82.4,
      "quality_score": 90
    },
    "matchmaking_status": "waiting_partner_acceptance"
  }
}
```

Catatan alur terbaru:

```text
Pasien memilih service -> booking dibuat pending -> backend memilih mitra -> mitra menerima event realtime -> mitra accept -> pasien bayar -> mitra bisa berangkat/menyelesaikan layanan
```

`assigned_partner_user_id` sudah terisi saat booking berhasil dibuat jika backend menemukan mitra yang memenuhi syarat. Jika tidak ada mitra yang cocok, backend akan mengembalikan error validasi.

Jika mitra pertama menolak sebelum accept dan sebelum pembayaran lunas, booking pasien tidak otomatis batal. Backend akan mencatat penolakan, mengecualikan mitra yang sudah menolak booking tersebut, mencari mitra pengganti terdekat, lalu memperbarui `assigned_partner_user_id`. Karena mitra pengganti bisa punya jarak berbeda, `distance_km`, `transport_fee`, `meal_fee`, `total_amount`, dan `payment.amount` juga bisa berubah. Flutter pasien wajib refresh detail booking setelah menerima notifikasi `service_booking.rematched` atau ketika layar status dibuka kembali.

State UI yang disarankan:

| Kondisi | Tampilan pasien |
| --- | --- |
| `status=pending` dan `assigned_partner_user_id` terisi | Menunggu mitra menerima pesanan |
| `status=pending` dan `assigned_partner_user_id=null` | Sedang mencari mitra pengganti |
| Notifikasi `service_booking.rematched` | Mitra pengganti ditemukan, refresh detail dan tampilkan total terbaru |
| Notifikasi `service_booking.waiting_partner` | Mitra sebelumnya menolak, sistem masih mencari mitra |
| `status=confirmed` | Mitra menerima pesanan, pasien bisa lanjut bayar |

Jangan menampilkan nama/foto mitra sebagai final sebelum `accepted_at` terisi. Sebelum accept, mitra masih bisa berubah karena flow reject/rematch otomatis.

List booking pasien:

```http
GET /api/patient/service-bookings
```

Detail booking:

```http
GET /api/patient/service-bookings/{serviceBooking}
```

Batalkan booking sebelum dibayar dan sebelum diterima mitra:

```http
PATCH /api/patient/service-bookings/{serviceBooking}/cancel
```

Field `PATCH /api/patient/service-bookings/{serviceBooking}/cancel`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `notes` | Tidak | string | alasan pembatalan, maksimal 1000 karakter |

Catatan:
- Hanya bisa dipakai oleh pasien pemilik booking.
- Hanya bisa saat `status=pending`, `accepted_at=null`, dan payment belum `paid`.
- Jika payment masih `pending`, backend mengubah payment menjadi `expired`.
- Setelah sukses, booking menjadi `cancelled` dan tidak bisa dibayar lagi.

Bayar booking layanan:

```http
PATCH /api/patient/service-bookings/{serviceBooking}/pay
```

Field `PATCH /api/patient/service-bookings/{serviceBooking}/pay`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `notes` | Tidak | string | catatan pembayaran |

Response pembayaran service booking:

| Field | Type | Catatan |
| --- | --- | --- |
| `service_booking` | object | data booking layanan |
| `payment` | object | data tagihan |
| `midtrans` | object | Snap token dan redirect URL Midtrans |

Contoh response pembayaran:

```json
{
  "message": "Transaksi Midtrans berhasil dibuat. Lanjutkan pembayaran layanan.",
  "data": {
    "service_booking": {
      "id": 25,
      "booking_code": "SVC-ABCDEFGH",
      "payment": {
        "id": 50,
        "status": "pending",
        "amount": "300000.00"
      }
    },
    "payment": {
      "id": 50,
      "payment_code": "PAY-SVC-20260707120000-123",
      "status": "pending",
      "amount": "300000.00"
    },
    "midtrans": {
      "token": "snap-token",
      "redirect_url": "https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token",
      "order_id": "PAY-SVC-20260707120000-123",
      "gross_amount": 300000,
      "is_reused": false
    }
  }
}
```

Mitra dapat menerima booking saat status masih `pending` atau `scheduled`. Untuk aksi operasional berikutnya seperti berangkat, menambah catatan penanganan, atau menyelesaikan layanan, pembayaran harus sudah `paid`.

Mitra juga dapat menolak booking lewat endpoint mitra. Dari sisi pasien, perubahan ini muncul sebagai update detail booking dan notifikasi:

- `service_booking.rematched`: mitra pengganti sudah ditemukan;
- `service_booking.waiting_partner`: belum ada mitra pengganti yang tersedia.

Setelah notifikasi tersebut, panggil ulang:

```http
GET /api/patient/service-bookings/{serviceBooking}
```

Gunakan response terbaru untuk menampilkan mitra, jarak, biaya transport/makan, `total_amount`, dan `payment.amount`.

### Handling Mitra Reject & Rematch di Mobile

Flutter pasien tidak perlu memanggil endpoint khusus untuk mencari mitra baru. Pencarian mitra pengganti dilakukan otomatis oleh backend saat mitra menekan reject di aplikasi mitra.

Yang harus dilakukan mobile pasien:

1. Tetap simpan `service_booking_id` yang sama. Jangan membuat booking baru.
2. Subscribe notifikasi user di `private-user.{userId}.notifications`.
3. Saat menerima notifikasi `service_booking.rematched` atau `service_booking.waiting_partner`, panggil ulang:

```http
GET /api/patient/service-bookings/{serviceBooking}
```

4. Render ulang layar dari response terbaru.
5. Jangan tampilkan tombol bayar sebelum booking diterima mitra (`status=confirmed` atau `accepted_at != null`).

Jika setelah reject belum ada mitra pengganti (`assigned_partner_user_id=null`), mobile boleh menyediakan tombol **Cari mitra lagi**:

```http
POST /api/patient/service-bookings/{serviceBooking}/rematch
```

Body opsional:

```json
{
  "notes": "Cari mitra pengganti lagi."
}
```

Endpoint ini tidak menerima `service_id` dari mobile. Backend memakai `service_id`, alamat, jadwal, mode layanan, dan histori reject dari booking yang sama. Kandidat yang sudah pernah menolak booking tersebut tidak akan dipilih ulang, lalu backend memprioritaskan mitra aktif terdekat dari alamat pasien. Jika mitra pengganti ditemukan, response akan berisi `matchmaking_status=rematched_waiting_partner_acceptance`, `assigned_partner_user_id` baru, serta `payment.amount` terbaru. Jika payment sebelumnya sudah dihapus karena gagal matchmaking, backend akan membuat payment pending baru saat rematch berhasil. Jika belum ada, response tetap `matchmaking_status=waiting_partner_available`, `assigned_partner_user_id=null`, dan `payment=null`.

State machine yang disarankan:

```dart
enum ServiceBookingUiState {
  waitingPartnerAccept,
  findingReplacementPartner,
  readyToPay,
  waitingPayment,
  onTheWay,
  completed,
  cancelled,
}

ServiceBookingUiState resolveServiceBookingUiState(Map<String, dynamic> booking) {
  final status = booking['status'] as String?;
  final assignedPartnerId = booking['assigned_partner_user_id'];
  final acceptedAt = booking['accepted_at'];
  final paymentStatus = booking['payment']?['status'];

  if (status == 'cancelled') return ServiceBookingUiState.cancelled;
  if (status == 'completed') return ServiceBookingUiState.completed;
  if (status == 'on_the_way') return ServiceBookingUiState.onTheWay;

  if (status == 'pending' && assignedPartnerId == null) {
    return ServiceBookingUiState.findingReplacementPartner;
  }

  if (status == 'pending' && assignedPartnerId != null) {
    return ServiceBookingUiState.waitingPartnerAccept;
  }

  if ((status == 'confirmed' || acceptedAt != null) && paymentStatus != 'paid') {
    return ServiceBookingUiState.readyToPay;
  }

  return ServiceBookingUiState.waitingPayment;
}
```

Copy UI yang disarankan:

| State | Copy |
| --- | --- |
| `waitingPartnerAccept` | Menunggu mitra menerima pesanan |
| `findingReplacementPartner` | Mitra sebelumnya belum bisa mengambil pesanan. Kami sedang mencari mitra pengganti. |
| `readyToPay` | Mitra sudah menerima pesanan. Silakan lanjutkan pembayaran. |
| `waitingPayment` | Menunggu pembayaran selesai |
| `onTheWay` | Mitra sedang menuju lokasi |
| `completed` | Layanan selesai |
| `cancelled` | Booking dibatalkan |

Saat `findingReplacementPartner`, backend menghapus transaksi/payment pending agar pasien tidak membayar booking tanpa mitra. Flutter jangan memanggil endpoint pay sampai rematch berhasil dan `payment` kembali tersedia.

Saat rematch berhasil, backend dapat mengubah:

- `assigned_partner_user_id`
- `assigned_partner`
- `distance_km`
- `transport_fee`
- `meal_fee`
- `total_amount`
- `payment.amount`

Karena itu, setelah notifikasi rematch, mobile harus update ringkasan biaya dari detail booking terbaru. Jangan memakai cache harga lama.

Tombol yang disarankan:

| Kondisi | Tombol |
| --- | --- |
| `pending`, belum dibayar, `accepted_at=null` | Batalkan booking |
| `pending`, `assigned_partner_user_id=null` | Batalkan booking, refresh status, cari mitra lagi |
| `confirmed` dan payment belum paid | Bayar sekarang |
| `on_the_way` | Lihat tracking |
| `completed` dan belum konfirmasi pasien | Konfirmasi selesai |

Contoh handler notifikasi:

```dart
Future<void> handleNotification(Map<String, dynamic> notification) async {
  final type = notification['type'];
  final data = notification['data'] as Map<String, dynamic>?;
  final bookingId = data?['service_booking_id'];

  if (bookingId == null) return;

  if (type == 'service_booking.rematched' ||
      type == 'service_booking.waiting_partner' ||
      type == 'service_booking.accepted' ||
      type == 'service_booking.status_updated') {
    final latestBooking = await api.getServiceBooking(bookingId);
    bookingController.replaceBooking(latestBooking);
  }
}
```

Fallback jika notifikasi terlambat: selama layar detail booking terbuka dan status masih `pending`, lakukan polling ringan `GET /api/patient/service-bookings/{id}` setiap 10-15 detik sampai status menjadi `confirmed`, `cancelled`, atau `assigned_partner_user_id` berubah.

Konfirmasi layanan selesai:

```http
PATCH /api/patient/service-bookings/{serviceBooking}/confirm-completion
```

Body opsional:

```json
{
  "notes": "Layanan sudah selesai dan sesuai."
}
```

Endpoint ini hanya dapat dipakai pasien pemilik booking. Syaratnya booking sudah ditugaskan ke mitra, status booking `confirmed`, `scheduled`, `on_the_way`, atau `completed`, dan `payment.status = paid`. Saat berhasil, backend menandai booking `completed`, membuat histori konfirmasi pasien, dan mengirim saldo layanan ke wallet mitra. Endpoint aman dipanggil ulang karena payout tidak akan dibuat dua kali jika `partner_balance_transaction_id` sudah ada.

Tracking lokasi mitra:

```http
GET /api/patient/service-bookings/{serviceBooking}/tracking
```

Endpoint ini mengambil snapshot lokasi terakhir mitra untuk booking milik pasien login. Pakai endpoint ini saat membuka layar map, lalu subscribe ke channel WebSocket tracking untuk update berikutnya.

Contoh response tracking:

```json
{
  "success": true,
  "data": {
    "service_booking_id": 25,
    "booking_code": "SVC-ABCDEFGH",
    "status": "on_the_way",
    "assigned_partner_user_id": 12,
    "partner": {
      "id": 12,
      "name": "Nurse Andi",
      "phone": "081234567890"
    },
    "partner_location": {
      "latitude": "-8.1723570",
      "longitude": "113.7003020",
      "accuracy_meters": "12.50",
      "heading": "90.00",
      "speed_mps": "4.20",
      "updated_at": "2026-07-08T03:00:00.000000Z"
    },
    "destination": {
      "id": 10,
      "label": "Rumah",
      "address": "Jl. Jawa No. 10",
      "latitude": "-8.1700000",
      "longitude": "113.7000000"
    },
    "channel": "private-service-booking.25.tracking",
    "event": "service-booking.location.updated"
  }
}
```

Status booking yang muncul di response:

```text
pending, confirmed, scheduled, on_the_way, completed, cancelled
```

Endpoint update status booking tidak tersedia di aplikasi pasien. Perubahan status layanan dilakukan oleh endpoint mitra:

```http
PATCH /api/mitra/service-bookings/{serviceBooking}/status
```

Query `GET /api/patient/service-bookings`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `status` | Tidak | enum | `pending`, `confirmed`, `scheduled`, `on_the_way`, `completed`, `cancelled` |
| `per_page` | Tidak | integer | default 20 |

### Promo Code

```http
POST /api/patient/service-bookings/check-promo-code
GET /api/patient/promo-codes/available
```

Field `POST /api/patient/service-bookings/check-promo-code`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `code` | Ya | string | kode promo |
| `service_id` | Ya | integer | harus ada di `services` |

### Konsultasi

List konsultasi:

```http
GET /api/patient/consultations
```

Query `GET /api/patient/consultations`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `status` | Tidak | enum | `pending`, `confirmed`, `ongoing`, `completed`, `cancelled` |
| `partner_user_id` | Tidak | integer | filter dokter/mitra tertentu |
| `per_page` | Tidak | integer | 1-100 |

Buat konsultasi:

```http
POST /api/patient/consultations
```

Field request `POST /api/patient/consultations`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `partner_user_id` | Ya | integer | harus ada di `users`; partner harus punya `partnerProfile` profesi `dokter` |
| `service_type` | Ya | enum | `chat`, `voice_call`, `video_call`, `visit` |
| `scheduled_at` | Tidak | datetime | format aman: `YYYY-MM-DD HH:mm:ss` |
| `complaint` | Tidak | string | keluhan pasien |
| `notes` | Tidak | string | catatan tambahan |

Response `data` konsultasi berisi field utama:

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID konsultasi |
| `consultation_code` | string | kode unik konsultasi |
| `patient_user_id` | integer | user pasien |
| `partner_user_id` | integer | user mitra/dokter |
| `service_type` | enum | `chat`, `voice_call`, `video_call`, `visit` |
| `status` | enum | `pending`, `confirmed`, `ongoing`, `completed`, `cancelled` |
| `scheduled_at` | datetime/null | jadwal konsultasi |
| `started_at` | datetime/null | waktu mulai |
| `ended_at` | datetime/null | waktu selesai |
| `complaint` | string/null | keluhan |
| `diagnosis` | string/null | diagnosis dari dokter/mitra |
| `notes` | string/null | catatan |
| `consultation_fee` | decimal string | biaya konsultasi |
| `patient` | object/null | relasi user pasien |
| `partner` | object/null | relasi user mitra/dokter |
| `payment` | object/null | tagihan konsultasi |
| `messages` | array | muncul di detail |

Detail konsultasi:

```http
GET /api/patient/consultations/{consultation}
```

Bayar konsultasi:

```http
PATCH /api/patient/consultations/{consultation}/pay
```

Field `PATCH /api/patient/consultations/{consultation}/pay`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `notes` | Tidak | string | catatan pembayaran |

Response pembayaran berisi:

| Field | Type | Catatan |
| --- | --- | --- |
| `consultation` | object | data konsultasi |
| `payment` | object | data tagihan |
| `midtrans` | object | Snap token dan info transaksi Midtrans |

Update status:

```http
PATCH /api/patient/consultations/{consultation}/status
```

Field `PATCH /api/patient/consultations/{consultation}/status`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `status` | Ya | enum | `pending`, `confirmed`, `ongoing`, `completed`, `cancelled` |
| `diagnosis` | Tidak | string | biasanya diisi mitra, tapi endpoint pasien menerima field ini |
| `notes` | Tidak | string | catatan tambahan |

Kirim pesan:

```http
POST /api/patient/consultations/{consultation}/messages
```

Body pesan text:

```json
{
  "message": "Halo dokter",
  "message_type": "text"
}
```

Field `POST /api/patient/consultations/{consultation}/messages`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `message_type` | Ya | enum | `text`, `image`, `file`, `system` |
| `message` | Tidak | string | isi pesan; untuk text sebaiknya wajib di sisi app |
| `attachment_path` | Tidak | string | max 255; path/url lampiran jika ada |

Response message:

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID pesan |
| `consultation_id` | integer | ID konsultasi |
| `sender_user_id` | integer | pengirim |
| `message_type` | enum | `text`, `image`, `file`, `system` |
| `message` | string/null | isi pesan |
| `attachment_path` | string/null | lampiran |
| `read_at` | datetime/null | waktu dibaca |
| `sender` | object/null | relasi user pengirim |

### Produk dan Order Apotik

Produk:

```http
GET /api/patient/products/global
GET /api/patient/products
GET /api/patient/products/{product}
```

Query `GET /api/patient/products/global`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `type` | Tidak | enum | `obat`, `produk_kesehatan`, `layanan`, `sewa_alat_kesehatan` |
| `patient_address_id` | Tidak | integer | untuk grouping apotik terdekat |
| `requires_prescription` | Tidak | boolean | filter butuh resep |
| `search` | Tidak | string | max 100 |
| `per_page` | Tidak | integer | 1-100 |

Query `GET /api/patient/products`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `type` | Tidak | enum | `obat`, `produk_kesehatan`, `layanan`, `sewa_alat_kesehatan` |
| `patient_address_id` | Tidak | integer | untuk grouping apotik terdekat |
| `pharmacy_id` | Tidak | integer | filter apotik |
| `pharmacy_user_id` | Tidak | integer | filter owner apotik; backend akan resolve ke `pharmacy_id` |
| `requires_prescription` | Tidak | boolean | filter butuh resep |
| `search` | Tidak | string | max 100 |
| `per_page` | Tidak | integer | 1-100 |

Catatan harga produk:
- `GET /api/patient/products/global` menampilkan `catalog_price` sebagai harga jual seragam per SKU.
- `catalog_price` berasal dari `products.admin_price` yang dikelola admin. Jika data lama belum punya `admin_price`, backend fallback ke harga aktif terendah per SKU.
- `pharmacy_options.*.price` juga memakai harga katalog seragam. Harga custom apotik tetap tersedia sebagai `pharmacy_options.*.pharmacy_price` untuk audit/debug, bukan untuk checkout pasien.
- Saat order dibuat, `order_items.unit_price` memakai harga katalog/admin, bukan `products.price` milik apotik yang terpilih matchmaking.

Order:

```http
GET /api/patient/orders
POST /api/patient/orders
GET /api/patient/orders/{order}
PATCH /api/patient/orders/{order}/status
```

Query `GET /api/patient/orders`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `patient_user_id` | Tidak | integer | filter pasien; untuk app pasien biasanya isi dengan ID user sendiri jika diperlukan |
| `status` | Tidak | enum | `pending`, `confirmed`, `processed`, `shipped`, `delivered`, `cancelled` |
| `per_page` | Tidak | integer | 1-100 |

Field `POST /api/patient/orders`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `patient_user_id` | Ya | integer | harus ada di `users`; saat ini masih wajib dikirim |
| `patient_address_id` | Ya | integer | harus ada di `patient_addresses` |
| `prescription_id` | Tidak | integer | harus ada di `prescriptions` jika dikirim |
| `order_type` | Ya | enum | `resep`, `non_resep` |
| `notes` | Tidak | string | catatan order |
| `items` | Ya | array | min 1 item |
| `items.*.product_id` | Tidak | integer | harus ada di `products`; normalnya kirim ini |
| `items.*.sku` | Tidak | string | max 100; alternatif lookup item |
| `items.*.quantity` | Ya | integer | min 1 |

Contoh body order:

```json
{
  "patient_user_id": 7,
  "patient_address_id": 10,
  "prescription_id": null,
  "order_type": "non_resep",
  "notes": "Tolong antar sore",
  "items": [
    {
      "product_id": 15,
      "quantity": 2
    }
  ]
}
```

Field `PATCH /api/patient/orders/{order}/status`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `status` | Ya | enum | `pending`, `confirmed`, `processed`, `shipped`, `delivered`, `cancelled` |
| `notes` | Tidak | string | catatan status |

Response `data` order berisi field utama:

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID order |
| `order_code` | string | kode unik order |
| `patient_user_id` | integer | user pasien |
| `pharmacy_id` / `pharmacy_user_id` | integer | tergantung hasil migrasi/refactor, relasi apotik |
| `patient_address_id` | integer/null | alamat kirim |
| `prescription_id` | integer/null | resep terkait |
| `order_type` | enum | `resep`, `non_resep` |
| `status` | enum | `pending`, `confirmed`, `processed`, `shipped`, `delivered`, `cancelled` |
| `subtotal` | decimal string | subtotal item |
| `shipping_cost` | decimal string | ongkir |
| `total_amount` | decimal string | total bayar |
| `notes` | string/null | catatan |
| `ordered_at` | datetime/null | waktu order |
| `items` | array | item order |

### Balance

```http
GET /api/patient/balance
GET /api/patient/balance/history
POST /api/patient/balance/topup
```

Query `GET /api/patient/balance/history`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `per_page` | Tidak | integer | default 20 |
| `type` | Tidak | string | contoh: `topup`, `credit`, `debit` tergantung data transaksi |
| `status` | Tidak | string | contoh: `pending`, `completed`, `failed` |

Field `POST /api/patient/balance/topup`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `amount` | Ya | numeric | minimal 10000 |
| `payment_method` | Ya | enum | saat ini hanya `midtrans` |

Endpoint lama `PATCH /api/patient/balance/topup/confirm` dinonaktifkan dan selalu ditolak. Flutter tidak boleh mengubah saldo berdasarkan hasil UI pembayaran. Saldo hanya boleh bertambah setelah backend menerima dan memverifikasi callback payment gateway. Setelah pembayaran, refresh `GET /api/patient/balance` dan history untuk memperoleh status server.

## Notifikasi

Semua role mobile bisa memakai endpoint notifikasi shared berikut dengan Bearer token.

List notifikasi:

```http
GET /api/shared/notifications
```

Query opsional:

```text
status=unread|read
type=consultation.message_created
per_page=20
```

Contoh:

```http
GET /api/shared/notifications?status=unread&per_page=20
```

Unread count:

```http
GET /api/shared/notifications/unread-count
```

Tandai satu notifikasi sudah dibaca:

```http
PATCH /api/shared/notifications/{notification}/read
```

Tandai semua sudah dibaca:

```http
PATCH /api/shared/notifications/read-all
```

Hapus notifikasi:

```http
DELETE /api/shared/notifications/{notification}
```

Buat notifikasi test untuk user yang sedang login:

```http
POST /api/shared/notifications
```

Body:

```json
{
  "type": "test.mobile",
  "title": "Test notifikasi",
  "body": "Notifikasi dari mobile berhasil dibuat",
  "reference_type": "test",
  "reference_id": 1,
  "data": {
    "source": "flutter"
  }
}
```

Field `POST /api/shared/notifications`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `type` | Ya | string | max 100; contoh `test.mobile` |
| `title` | Ya | string | max 255 |
| `body` | Tidak | string | isi notifikasi |
| `action_url` | Tidak | string | max 255; path tujuan ketika notifikasi dibuka |
| `reference_type` | Tidak | string | max 100; contoh `consultation`, `service_booking` |
| `reference_id` | Tidak | integer | min 1 |
| `data` | Tidak | object | metadata bebas untuk mobile |

Response notifikasi:

```json
{
  "id": 101,
  "user_id": 7,
  "type": "consultation.message_created",
  "title": "Pesan konsultasi baru",
  "body": "dr. Andi: Baik, saya cek dulu.",
  "action_url": "/patient/consultations/1",
  "reference_type": "consultation",
  "reference_id": 1,
  "data": {
    "consultation_id": 1,
    "message_id": 99,
    "sender_user_id": 10
  },
  "read_at": null,
  "created_at": "2026-07-07T05:00:00.000000Z"
}
```

Tipe notifikasi yang sudah dibuat otomatis:

```text
consultation.created
consultation.status_updated
consultation.message_created
service_booking.matched
service_booking.paid
service_booking.status_updated
service_booking.accepted
service_booking.on_the_way
service_booking.completed
```

## WebSocket Reverb

Backend memakai Laravel Reverb dengan protokol Pusher.

Production:

```text
key: medic-app-key
host: backend.perawatku.tech
port: 443
scheme: wss
auth endpoint: https://backend.perawatku.tech/api/broadcasting/auth
```

Local Docker:

```text
key: medic-app-key
host: localhost
port: 8080
scheme: ws
auth endpoint: http://localhost:8081/api/broadcasting/auth
```

Untuk Flutter, gunakan package yang kompatibel dengan Pusher Channels. Konfigurasi penting:

```dart
const reverbKey = 'medic-app-key';
const wsHost = 'backend.perawatku.tech';
const wsPort = 443;
const useTLS = true;
const authEndpoint = 'https://backend.perawatku.tech/api/broadcasting/auth';
```

Saat authorizing private/presence channel, kirim Bearer token:

```http
Authorization: Bearer {user_api_token}
Accept: application/json
```

Body auth channel:

```json
{
  "socket_id": "{socket_id}",
  "channel_name": "private-consultation.1"
}
```

## Channel WebSocket

### Chat Konsultasi

Laravel channel:

```text
consultation.{consultationId}
```

Pusher channel name:

```text
private-consultation.{consultationId}
```

Event:

```text
chat.message.created
```

Payload:

```json
{
  "id": 99,
  "consultation_id": 1,
  "sender_user_id": 7,
  "sender": {
    "id": 7,
    "name": "Budi",
    "email": "budi@example.com",
    "role": "patient"
  },
  "message_type": "text",
  "message": "Halo dokter",
  "attachment_path": null,
  "read_at": null,
  "created_at": "2026-07-07T05:00:00.000000Z"
}
```

Subscribe hanya berhasil jika user adalah pasien atau mitra/dokter yang terkait dengan konsultasi tersebut.

### Presence Online Users

Laravel channel:

```text
online-users
```

Pusher channel name:

```text
presence-online-users
```

Data user:

```json
{
  "id": 7,
  "name": "Budi",
  "role": "patient"
}
```

Gunakan channel ini jika Flutter perlu menampilkan siapa saja yang sedang online.

### User Notifications

Laravel channel:

```text
user.{userId}.notifications
```

Pusher channel name:

```text
private-user.{userId}.notifications
```

Event:

```text
notification.created
```

Payload:

```json
{
  "id": 101,
  "user_id": 7,
  "type": "consultation.message_created",
  "title": "Pesan konsultasi baru",
  "body": "dr. Andi: Baik, saya cek dulu.",
  "action_url": "/patient/consultations/1",
  "reference_type": "consultation",
  "reference_id": 1,
  "data": {
    "consultation_id": 1,
    "message_id": 99,
    "sender_user_id": 10
  },
  "read_at": null,
  "created_at": "2026-07-07T05:00:00.000000Z"
}
```

Flutter pasien login dengan user ID `7` harus subscribe:

```text
private-user.7.notifications
```

Setelah menerima event realtime, aplikasi tetap bisa memanggil `GET /api/shared/notifications/unread-count` untuk sinkronisasi badge.

### Service Booking Tracking

Laravel channel:

```text
service-booking.{serviceBookingId}.tracking
```

Pusher channel name:

```text
private-service-booking.{serviceBookingId}.tracking
```

Event:

```text
service-booking.location.updated
```

Payload:

```json
{
  "service_booking_id": 25,
  "booking_code": "SVC-ABCDEFGH",
  "status": "on_the_way",
  "patient_user_id": 7,
  "assigned_partner_user_id": 12,
  "partner": {
    "id": 12,
    "name": "Nurse Andi",
    "phone": "081234567890"
  },
  "location": {
    "latitude": "-8.1723570",
    "longitude": "113.7003020",
    "accuracy_meters": "12.50",
    "heading": "90.00",
    "speed_mps": "4.20",
    "updated_at": "2026-07-08T03:00:00.000000Z"
  }
}
```

Pasien hanya bisa subscribe ke channel tracking booking miliknya. Gunakan payload ini untuk menggeser marker mitra di map.

### Booking Matchmaking Mitra

Channel ini untuk aplikasi mitra, bukan aplikasi pasien:

```text
private-partner.{partnerId}.service-bookings
```

Event:

```text
service-booking.matched
```

Saat pasien membuat booking, backend memilih mitra dan mengirim event ini ke aplikasi mitra yang terpilih. Pasien cukup memantau detail booking dan notifikasi user sendiri.

## Referensi Field Model

Bagian ini adalah kamus field yang umum muncul di response API. Field relasi seperti `patient`, `partner`, `service`, `items`, dan `messages` hanya muncul jika endpoint memuat relasi tersebut.

### User

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID user |
| `name` | string | nama user |
| `email` | string | email login |
| `phone` | string/null | nomor telepon |
| `role` | enum | `patient`, `mitra`, `admin`, atau role lain sesuai data |
| `profile_photo_path` | string/null | path internal foto profil |
| `profile_photo_url` | string/null | URL siap pakai untuk menampilkan foto profil |
| `patient_profile` | object/null | profil pasien |
| `partner_profile` | object/null | profil dokter/perawat/mitra |
| `pharmacy` | object/null | data apotik jika user owner apotik |

### Patient Profile

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID profile |
| `user_id` | integer | ID user pasien |
| `date_of_birth` | date/null | tanggal lahir |
| `gender` | enum/null | `laki-laki`, `perempuan` |
| `address` | string/null | alamat profil |
| `blood_type` | string/null | golongan darah |
| `emergency_contact_name` | string/null | nama kontak darurat |
| `emergency_contact_phone` | string/null | nomor kontak darurat |
| `allergies` | string/null | alergi |
| `medical_notes` | string/null | catatan medis |

### Patient Address

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID alamat |
| `patient_user_id` | integer | user pemilik alamat |
| `label` | string/null | contoh `Rumah`, `Kantor` |
| `recipient_name` | string/null | nama penerima |
| `recipient_phone` | string/null | nomor penerima |
| `address` | string | alamat lengkap |
| `province` | string/null | provinsi |
| `city` | string/null | kota/kabupaten |
| `district` | string/null | kecamatan |
| `postal_code` | string/null | kode pos |
| `latitude` | decimal/null | latitude alamat |
| `longitude` | decimal/null | longitude alamat |
| `is_primary` | boolean | alamat utama |

### Patient Member

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID profil pasien keluarga |
| `owner_user_id` | integer | akun pemilik profil |
| `name` | string | nama pasien |
| `relationship` | string/null | hubungan dengan pemilik akun |
| `date_of_birth` | date/null | tanggal lahir |
| `age` | integer/null | usia manual jika tanggal lahir tidak diketahui |
| `gender` | enum/null | `laki-laki`, `perempuan` |
| `phone` | string/null | nomor pasien |
| `blood_type` | string/null | golongan darah |
| `emergency_contact_name` | string/null | kontak darurat |
| `emergency_contact_phone` | string/null | nomor kontak darurat |
| `allergies` | string/null | alergi |
| `medical_notes` | string/null | catatan medis |
| `address_label` | string/null | label alamat |
| `recipient_name` | string/null | nama penerima |
| `recipient_phone` | string/null | telepon penerima |
| `address` | string/null | alamat lengkap |
| `province` | string/null | provinsi |
| `city` | string/null | kota/kabupaten |
| `district` | string/null | kecamatan |
| `postal_code` | string/null | kode pos |
| `latitude` | decimal/null | latitude alamat |
| `longitude` | decimal/null | longitude alamat |
| `is_primary` | boolean | profil utama |

### Partner Profile

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID profile mitra |
| `user_id` | integer | ID user mitra |
| `profession` | enum/string | contoh `dokter`, `perawat`, `bidan` |
| `specialization` | string/null | spesialisasi |
| `license_number` | string/null | nomor STR/SIP/lisensi |
| `work_location` | string/null | lokasi kerja |
| `latitude` | decimal/null | latitude lokasi mitra |
| `longitude` | decimal/null | longitude lokasi mitra |
| `years_of_experience` | integer/null | pengalaman |
| `consultation_fee` | decimal string/null | biaya konsultasi |
| `is_available` | boolean | status tersedia |
| `bio` | string/null | bio singkat |
| `verification_status` | enum/string | status verifikasi |

### Service

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID layanan |
| `service_code` | string | kode layanan |
| `name` | string | nama layanan |
| `service_type` | enum/string | contoh `dokter_homecare`, `perawat_homecare` |
| `category` | string/null | kategori layanan |
| `description` | string/null | deskripsi |
| `base_price` | decimal string | harga dasar |
| `duration_minutes` | integer/null | estimasi durasi |
| `is_active` | boolean | layanan aktif |
| `is_homecare` | boolean | layanan datang ke rumah |

### Service Booking

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID booking |
| `booking_code` | string | kode booking |
| `service_id` | integer | ID layanan |
| `patient_user_id` | integer | ID pasien |
| `patient_member_id` | integer/null | profil pasien keluarga yang dipakai |
| `assigned_partner_user_id` | integer/null | ID mitra hasil matchmaking; bisa berubah sebelum `accepted_at` jika mitra sebelumnya menolak |
| `patient_address_id` | integer/null | alamat layanan |
| `status` | enum | `pending`, `confirmed`, `scheduled`, `on_the_way`, `completed`, `cancelled` |
| `booking_type` | enum | `scheduled`, `daily` |
| `visit_plan` | enum | `once`, `recurring` |
| `recurrence` | enum/null | `weekly`, `monthly`; null untuk sekali visit |
| `visit_count` | integer | jumlah kunjungan |
| `care_mode` | enum | `visit`, `live_in` |
| `location_type` | enum | `home`, `hospital` |
| `distance_km` | decimal string/null | snapshot jarak mitra ke lokasi pasien; bisa berubah saat rematch sebelum accept |
| `scheduled_at` | datetime/null | jadwal |
| `schedule_start_at` | datetime/null | tanggal mulai layanan |
| `schedule_end_at` | datetime/null | tanggal selesai layanan |
| `duration_days` | integer | jumlah hari layanan; default 1 |
| `accepted_at` | datetime/null | waktu diterima mitra |
| `started_at` | datetime/null | waktu mulai/perjalanan |
| `completed_at` | datetime/null | waktu selesai |
| `partner_current_latitude` | decimal string/null | latitude lokasi realtime mitra terakhir |
| `partner_current_longitude` | decimal string/null | longitude lokasi realtime mitra terakhir |
| `partner_location_accuracy_meters` | decimal string/null | akurasi GPS dalam meter |
| `partner_location_heading` | decimal string/null | arah gerak derajat 0-360 |
| `partner_location_speed_mps` | decimal string/null | kecepatan meter/detik |
| `partner_location_updated_at` | datetime/null | waktu lokasi terakhir diterima backend |
| `total_amount` | decimal string | total akhir; refresh setelah rematch karena biaya jarak/makan bisa berubah |
| `notes` | string/null | catatan |
| `promo_code` | string/null | kode promo |
| `discount_amount` | decimal string/null | nominal diskon |
| `discount_type` | string/null | tipe diskon |
| `subtotal` | decimal string/null | subtotal sebelum diskon |
| `markup_amount` | decimal string/null | markup layanan |
| `transport_fee` | decimal string | total biaya transport booking; bisa berubah saat mitra pengganti punya jarak berbeda |
| `meal_fee` | decimal string | total uang makan booking |
| `fee_policy_snapshot` | object/null | tarif dan ambang admin saat booking dibuat |
| `service` | object/null | data layanan |
| `patient` | object/null | user pasien |
| `patient_member` | object/null | profil pasien keluarga |
| `assigned_partner` | object/null | user mitra |
| `address` | object/null | alamat pasien |
| `histories` | array | histori tindakan/status jika dimuat |
| `payment` | object/null | tagihan booking layanan; `amount` mengikuti `total_amount` terbaru selama payment masih pending |
| `detail_actions` | object/null | hanya muncul di detail booking; gunakan `chat.label = "Chat"` dan `call.label = "Call"` untuk tombol aksi |

Pada detail booking pasien, tombol komunikasi memakai field:

```json
{
  "detail_actions": {
    "chat": {
      "label": "Chat",
      "enabled": false,
      "notifier": "Silakan selesaikan pembayaran terlebih dahulu untuk memakai fitur ini."
    },
    "call": {
      "label": "Call",
      "enabled": false,
      "notifier": "Silakan selesaikan pembayaran terlebih dahulu untuk memakai fitur ini."
    }
  }
}
```

Jika `payment.status != paid`, disable tombol `Chat` dan `Call`, lalu tampilkan `notifier` ketika user menekan tombol. Setelah pembayaran lunas, `enabled=true` dan `notifier=null`.

### Consultation

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID konsultasi |
| `consultation_code` | string | kode konsultasi |
| `patient_user_id` | integer | ID pasien |
| `partner_user_id` | integer | ID dokter/mitra |
| `service_type` | enum | `chat`, `voice_call`, `video_call`, `visit` |
| `status` | enum | `pending`, `confirmed`, `ongoing`, `completed`, `cancelled` |
| `scheduled_at` | datetime/null | jadwal konsultasi |
| `started_at` | datetime/null | waktu mulai |
| `ended_at` | datetime/null | waktu selesai |
| `complaint` | string/null | keluhan pasien |
| `diagnosis` | string/null | diagnosis |
| `notes` | string/null | catatan |
| `consultation_fee` | decimal string | biaya |
| `patient` | object/null | user pasien |
| `partner` | object/null | user dokter/mitra |
| `messages` | array | pesan konsultasi jika dimuat |
| `prescription` | object/null | resep jika ada |
| `payment` | object/null | pembayaran jika ada |

### Consultation Message

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID pesan |
| `consultation_id` | integer | ID konsultasi |
| `sender_user_id` | integer | ID pengirim |
| `message_type` | enum | `text`, `image`, `file`, `system` |
| `message` | string/null | isi pesan |
| `attachment_path` | string/null | path/url lampiran |
| `read_at` | datetime/null | waktu pesan dibaca |
| `sender` | object/null | user pengirim |

### Product

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID produk |
| `pharmacy_id` | integer | ID apotik |
| `sku` | string/null | SKU produk |
| `name` | string | nama produk |
| `type` | enum/string | `obat`, `produk_kesehatan`, `layanan`, `sewa_alat_kesehatan` |
| `category` | string/null | kategori |
| `description` | string/null | deskripsi |
| `price` | decimal string | harga input apotik/legacy; checkout pasien tidak memakai field ini sebagai harga final |
| `admin_price` | decimal string/null | harga jual katalog dari admin untuk semua apotik dengan SKU yang sama |
| `cost_price` | decimal string/null | harga modal |
| `stock` | integer | stok |
| `minimum_stock_alert` | integer/null | batas stok minimum |
| `track_stock` | boolean | apakah stok dilacak |
| `requires_prescription` | boolean | butuh resep |
| `is_active` | boolean | produk aktif |
| `image` | string/null | path gambar |
| `pharmacy` | object/null | relasi apotik |

### Order

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID order |
| `order_code` | string | kode order |
| `patient_user_id` | integer | ID pasien |
| `pharmacy_id` | integer/null | ID apotik |
| `patient_address_id` | integer | alamat kirim |
| `prescription_id` | integer/null | resep |
| `order_type` | enum | `resep`, `non_resep` |
| `status` | enum | `pending`, `confirmed`, `processed`, `shipped`, `delivered`, `cancelled` |
| `subtotal` | decimal string | subtotal |
| `shipping_cost` | decimal string | ongkir |
| `total_amount` | decimal string | total |
| `notes` | string/null | catatan |
| `ordered_at` | datetime/null | waktu order |
| `patient` | object/null | user pasien |
| `pharmacy` | object/null | apotik |
| `address` | object/null | alamat kirim |
| `prescription` | object/null | resep |
| `items` | array | item order |
| `shipment` | object/null | pengiriman |

### Order Item

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID item |
| `order_id` | integer | ID order |
| `product_id` | integer/null | ID produk |
| `product_name` | string | snapshot nama produk |
| `unit_price` | decimal string | snapshot harga katalog/admin saat order dibuat |
| `unit_cost` | decimal string/null | modal satuan |
| `quantity` | integer | jumlah |
| `total_price` | decimal string | total harga item |
| `total_cost` | decimal string/null | total modal |
| `product` | object/null | relasi produk |

### App Notification

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID notifikasi |
| `user_id` | integer | penerima |
| `type` | string | tipe notifikasi |
| `title` | string | judul |
| `body` | string/null | isi |
| `action_url` | string/null | tujuan ketika dibuka |
| `reference_type` | string/null | tipe referensi |
| `reference_id` | integer/null | ID referensi |
| `data` | object/null | metadata |
| `read_at` | datetime/null | null berarti belum dibaca |
| `created_at` | datetime | waktu dibuat |

### Payment

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID pembayaran |
| `consultation_id` | integer/null | terisi untuk pembayaran konsultasi |
| `service_booking_id` | integer/null | terisi untuk pembayaran booking layanan |
| `patient_user_id` | integer | user pasien |
| `payment_code` | string | kode unik pembayaran, dipakai sebagai Midtrans `order_id` |
| `snap_token` | string/null | token Snap Midtrans |
| `snap_redirect_url` | string/null | URL pembayaran Midtrans |
| `snap_token_created_at` | datetime/null | waktu token dibuat |
| `payment_method` | enum | `wallet`, `bank_transfer`, `credit_card`, `cash` |
| `status` | enum | `pending`, `paid`, `failed`, `refunded`, `expired` |
| `amount` | decimal string | nominal pembayaran |
| `paid_at` | datetime/null | waktu lunas |
| `notes` | string/null | catatan |

## Contoh Flow Flutter Pasien

1. Login pasien via `POST /api/patient/login`.
2. Simpan `user_api_token`.
3. Ambil catalog layanan via `GET /api/patient/service-bookings/services?per_page=100`.
4. Bentuk tab/chip category dari `data.data.*.service_category`.
5. Saat user pilih category, filter lokal dari catalog yang sudah ada atau panggil ulang `GET /api/patient/service-bookings/services?category_id={id}`.
6. Saat user pilih service, ambil detail via `GET /api/patient/service-bookings/services/{serviceId}` untuk pricing terbaru.
7. Tampilkan form alamat jika `requires_address=true`.
8. Tampilkan form jadwal jika `requires_schedule=true`.
9. Buat booking via `POST /api/patient/service-bookings`.
10. Tampilkan status `Menunggu konfirmasi mitra`; response awal punya `assigned_partner_user_id` dan `matchmaking_status=waiting_partner_acceptance`.
11. Jika menerima notifikasi `service_booking.rematched` atau `service_booking.waiting_partner`, refresh detail booking. Tampilkan `Sedang mencari mitra pengganti` jika `assigned_partner_user_id=null`.
12. Jika pasien ingin batal sebelum mitra menerima dan sebelum bayar, panggil `PATCH /api/patient/service-bookings/{id}/cancel`.
13. Setelah status `confirmed` atau `accepted_at` terisi, bayar booking via `PATCH /api/patient/service-bookings/{id}/pay` memakai `payment.amount` terbaru.
14. Saat status `on_the_way`, buka map dengan snapshot `GET /api/patient/service-bookings/{id}/tracking`, lalu subscribe ke `private-service-booking.{id}.tracking`.
15. Setelah layanan selesai di lapangan, pasien konfirmasi via `PATCH /api/patient/service-bookings/{id}/confirm-completion`; wallet mitra otomatis dikreditkan jika belum pernah dibayarkan.
16. Setelah mitra menerima dan pembayaran berhasil, polling/detail booking atau tunggu notifikasi status untuk melihat perjalanan layanan.
17. Subscribe ke `private-user.{userId}.notifications` untuk menerima notifikasi realtime.
18. Untuk chat konsultasi, subscribe ke `private-consultation.{consultationId}`.
19. Saat mengirim pesan konsultasi, panggil `POST /api/patient/consultations/{consultation}/messages`; penerima akan dapat event `chat.message.created`.
20. Panggil `GET /api/shared/notifications/unread-count` untuk badge jumlah notifikasi.

## Debug WebSocket

Jika koneksi gagal:

1. Pastikan endpoint `/api/broadcasting/auth` mengembalikan `200`.
2. Jika auth mengembalikan `403`, user tidak diizinkan join channel tersebut.
3. Jika auth mengembalikan `401`, token salah atau belum dikirim.
4. Jika WebSocket tidak `101 Switching Protocols`, cek proxy HTTPS/Nginx Proxy Manager.
5. Untuk production dengan Nginx Proxy Manager, aktifkan `Websockets Support`.

Production WebSocket harus lewat domain HTTPS:

```text
wss://backend.perawatku.tech/app/medic-app-key
```

Jangan connect langsung ke port `8080` dari browser production. Port `8080` adalah port internal Reverb container.
