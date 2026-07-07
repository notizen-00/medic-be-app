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

Simpan `user_api_token` di secure storage Flutter. Semua endpoint protected memakai:

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

## Endpoint Pasien

Semua endpoint di bawah ini memakai `Authorization: Bearer {token}`.

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

```http
GET /api/patient/services
GET /api/patient/services/{service}
```

Query `GET /api/patient/services`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `patient_address_id` | Tidak | integer | dipakai menghitung jarak/matchmaking |
| `service_type` | Tidak | enum | `dokter_homecare`, `perawat_homecare`, `bidan_homecare`, `konsultasi_tindakan` |
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

Query alternatif catalog:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `category` | Tidak | string | filter kategori layanan |
| `search` | Tidak | string | cari nama layanan |
| `per_page` | Tidak | integer | default 20 |

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
| `patient_member_id` | Tidak | integer | harus milik akun pasien login; profil pasien keluarga yang menerima layanan |
| `patient_address_id` | Tidak | integer | harus ada di `patient_addresses`; wajib secara bisnis untuk layanan homecare |
| `booking_type` | Tidak | enum | `scheduled` untuk sekali jalan, `daily` untuk layanan harian; default `scheduled` |
| `scheduled_at` | Tidak | datetime | format `YYYY-MM-DD HH:mm:ss`; wajib setelah waktu sekarang jika dikirim |
| `schedule_start_at` | Tidak | datetime | wajib untuk `booking_type=daily` jika `scheduled_at` tidak dikirim |
| `schedule_end_at` | Tidak | datetime | tanggal selesai `daily`; harus >= `schedule_start_at` |
| `duration_days` | Tidak | integer | 1-30; dipakai untuk `daily` jika `schedule_end_at` tidak dikirim |
| `notes` | Tidak | string | catatan pasien; max 1000 di endpoint alternatif |
| `promo_code` | Tidak | string | dipakai endpoint alternatif service booking |
| `patient_user_id` | Tidak | integer | hanya didukung endpoint umum; normalnya tidak perlu dikirim karena memakai token |

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

Response penting:

```json
{
  "message": "Booking layanan berhasil dibuat.",
  "data": {
    "id": 25,
    "booking_code": "SVB-20260705101010-123",
    "service_id": 1,
    "patient_user_id": 7,
    "patient_member_id": 2,
    "assigned_partner_user_id": 12,
    "patient_address_id": 10,
    "booking_type": "scheduled",
    "status": "pending",
    "duration_days": 1,
    "total_amount": "100000.00",
    "payment": {
      "id": 50,
      "payment_code": "PAY-SVC-20260707120000-123",
      "status": "pending",
      "amount": "100000.00"
    }
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

List booking pasien:

```http
GET /api/patient/service-bookings
```

Detail booking:

```http
GET /api/patient/service-bookings/{serviceBooking}
```

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

Mitra baru bisa menerima/memproses booking jika `payment.status` sudah `paid`.

Update status booking:

```http
PATCH /api/patient/service-bookings/{serviceBooking}/status
```

Body:

```json
{
  "status": "cancelled",
  "notes": "Pasien membatalkan pesanan"
}
```

Status yang tersedia:

```text
pending, confirmed, scheduled, on_the_way, completed, cancelled
```

Query `GET /api/patient/service-bookings`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `status` | Tidak | enum | `pending`, `confirmed`, `scheduled`, `on_the_way`, `completed`, `cancelled` |
| `service_id` | Tidak | integer | didukung endpoint umum |
| `assigned_partner_user_id` | Tidak | integer | didukung endpoint umum |
| `per_page` | Tidak | integer | 1-100 atau default 20 tergantung endpoint yang match |

Field `PATCH /api/patient/service-bookings/{serviceBooking}/status`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `status` | Ya | enum | `pending`, `confirmed`, `scheduled`, `on_the_way`, `completed`, `cancelled` |
| `scheduled_at` | Tidak | datetime | update jadwal |
| `notes` | Tidak | string | catatan status |

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
PATCH /api/patient/balance/topup/confirm
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

Field `PATCH /api/patient/balance/topup/confirm`:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `transaction_uuid` | Ya | string | UUID transaksi topup |
| `status` | Ya | enum | `success`, `completed` |

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

### Booking Matchmaking Mitra

Channel ini untuk aplikasi mitra, bukan aplikasi pasien:

```text
private-partner.{partnerId}.service-bookings
```

Event:

```text
service-booking.matched
```

Saat pasien membuat booking, backend mengirim event ini ke mitra yang dipilih matchmaking. Aplikasi pasien saat ini cukup memakai response HTTP dari `POST /api/patient/service-bookings` untuk mengetahui hasil matchmaking.

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
| `assigned_partner_user_id` | integer/null | ID mitra hasil matchmaking |
| `patient_address_id` | integer/null | alamat layanan |
| `status` | enum | `pending`, `confirmed`, `scheduled`, `on_the_way`, `completed`, `cancelled` |
| `booking_type` | enum | `scheduled`, `daily` |
| `scheduled_at` | datetime/null | jadwal |
| `schedule_start_at` | datetime/null | tanggal mulai layanan |
| `schedule_end_at` | datetime/null | tanggal selesai layanan |
| `duration_days` | integer | jumlah hari layanan; default 1 |
| `accepted_at` | datetime/null | waktu diterima mitra |
| `started_at` | datetime/null | waktu mulai/perjalanan |
| `completed_at` | datetime/null | waktu selesai |
| `total_amount` | decimal string | total akhir |
| `notes` | string/null | catatan |
| `promo_code` | string/null | kode promo |
| `discount_amount` | decimal string/null | nominal diskon |
| `discount_type` | string/null | tipe diskon |
| `subtotal` | decimal string/null | subtotal sebelum diskon |
| `markup_amount` | decimal string/null | markup layanan |
| `service` | object/null | data layanan |
| `patient` | object/null | user pasien |
| `patient_member` | object/null | profil pasien keluarga |
| `assigned_partner` | object/null | user mitra |
| `address` | object/null | alamat pasien |
| `histories` | array | histori tindakan/status jika dimuat |
| `payment` | object/null | tagihan booking layanan |

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
| `price` | decimal string | harga jual |
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
| `unit_price` | decimal string | harga satuan |
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
3. Ambil catalog layanan via `GET /api/patient/service-bookings/services`.
4. Buat booking via `POST /api/patient/service-bookings`.
5. Tampilkan status booking dari response.
6. Polling/list detail booking via `GET /api/patient/service-bookings/{id}` jika perlu.
7. Untuk chat konsultasi, subscribe ke `private-consultation.{consultationId}`.
8. Saat mengirim pesan, panggil `POST /api/patient/consultations/{consultation}/messages`; penerima akan dapat event `chat.message.created`.
9. Subscribe ke `private-user.{userId}.notifications` untuk menerima notifikasi realtime.
10. Panggil `GET /api/shared/notifications/unread-count` untuk badge jumlah notifikasi.

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
