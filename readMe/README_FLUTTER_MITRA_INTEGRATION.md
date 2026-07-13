# Flutter Mitra App Integration

Dokumen ini untuk aplikasi Flutter mitra yang memakai API dan WebSocket backend Medic App. App mitra mencakup tenaga kesehatan (`dokter`, `perawat`, `bidan`), mitra apotik, dan sebagian endpoint pengiriman/kurir sesuai role/data akun.

## Base URL

Production:

```text
https://backend.perawatku.tech
```

Local Docker:

```text
http://localhost:8081
```

Local Laragon:

```text
http://medic-app.test
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

Header `Authorization` dipakai setelah login/register berhasil.

## Auth Mitra

Semua akun mitra memakai role backend `mitra`. Perbedaan dokter/perawat/bidan/apotik ditentukan oleh relasi profil:

- tenaga kesehatan: `partner_profile.profession`
- apotik: relasi `pharmacy`
- kurir: relasi `courier_profile`

### Register Mitra Umum

```http
POST /api/mitra/register
```

Gunakan `multipart/form-data` jika mengirim `str_photo` atau `ktp_photo`.

Body:

```json
{
  "name": "dr. Andi",
  "email": "andi@example.com",
  "phone": "081234567890",
  "password": "password123",
  "password_confirmation": "password123",
  "profession": "dokter",
  "specialization": "Dokter Umum",
  "license_number": "STR-12345",
  "work_location": "Jember",
  "latitude": -8.172357,
  "longitude": 113.700302,
  "years_of_experience": 5,
  "consultation_fee": 125000,
  "bio": "Dokter umum berpengalaman"
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
| `profession` | Ya | enum | `dokter`, `bidan`, `perawat` |
| `specialization` | Ya | string | max 255 |
| `license_number` | Ya | string | max 255, unique |
| `work_location` | Tidak | string | max 255 |
| `latitude` | Tidak | numeric | -90 sampai 90 |
| `longitude` | Tidak | numeric | -180 sampai 180 |
| `years_of_experience` | Tidak | integer | min 0 |
| `consultation_fee` | Tidak | numeric | min 0 |
| `bio` | Tidak | string | deskripsi singkat |
| `str_photo` | Tidak | file | jpg, jpeg, png, pdf; max 5MB |
| `ktp_photo` | Tidak | file | jpg, jpeg, png, pdf; max 5MB |

Response penting:

```json
{
  "message": "Pendaftaran mitra layanan kesehatan berhasil. Akun menunggu verifikasi admin.",
  "data": {
    "id": 12,
    "name": "dr. Andi",
    "email": "andi@example.com",
    "role": "mitra",
    "partner_profile": {
      "profession": "dokter",
      "verification_status": "pending"
    }
  },
  "user_api_token": "1|plain-token"
}
```

### Register Dokter

```http
POST /api/mitra/doctor/register
```

Field sama seperti register mitra umum. `profession` boleh tidak dikirim; backend akan memakai `dokter`.

### Register Perawat

```http
POST /api/mitra/nurse/register
```

Field sama seperti register mitra umum, tanpa `profession`; backend akan memakai `perawat`.

### Login Mitra Umum

```http
POST /api/mitra/login
```

Body:

```json
{
  "email": "andi@example.com",
  "password": "password123"
}
```

Login ini hanya menerima user dengan `role = mitra`.

### Login Dokter

```http
POST /api/mitra/doctor/login
```

Login berhasil hanya jika akun `role = mitra` dan `partner_profile.profession = dokter`.

### Login Perawat

```http
POST /api/mitra/nurse/login
```

Login berhasil hanya jika akun `role = mitra` dan `partner_profile.profession = perawat`.

### Login Apotik

```http
POST /api/mitra/apotik/login
```

Login berhasil hanya jika akun `role = mitra` dan sudah punya relasi `pharmacy`.

### Me dan Logout

```http
GET /api/shared/me
POST /api/shared/logout
```

Simpan `user_api_token` di secure storage Flutter. Login/register akan menerbitkan token baru dan mereset token lama user tersebut, jadi selalu overwrite token lama di secure storage setelah login ulang.

Semua endpoint protected memakai:

```http
Authorization: Bearer 1|plain-token
```

## Profil Mitra

Endpoint ini untuk tenaga kesehatan mitra yang punya `partner_profile`.

```http
GET /api/mitra/profile
PATCH /api/mitra/profile
```

Body `PATCH /api/mitra/profile`:

```json
{
  "specialization": "Dokter Umum",
  "license_number": "STR-12345",
  "work_location": "Klinik Jember",
  "latitude": -8.172357,
  "longitude": 113.700302,
  "years_of_experience": 7,
  "consultation_fee": 150000,
  "bio": "Praktik umum dan homecare",
  "is_available": true
}
```

Field update profile:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `specialization` | Tidak | string | max 255 |
| `license_number` | Tidak | string | max 255, unique selain profil sendiri |
| `work_location` | Tidak | string | max 255 |
| `latitude` | Tidak | numeric | -90 sampai 90 |
| `longitude` | Tidak | numeric | -180 sampai 180 |
| `years_of_experience` | Tidak | integer | min 0 |
| `consultation_fee` | Tidak | numeric | min 0 |
| `bio` | Tidak | string | deskripsi |
| `is_available` | Tidak | boolean | status online/tersedia untuk layanan |
| `str_photo` | Tidak | file | jpg, jpeg, png, pdf; max 5MB |
| `ktp_photo` | Tidak | file | jpg, jpeg, png, pdf; max 5MB |

Catatan penting:

```text
Mitra baru bisa masuk matchmaking jika:
- partner_profile.verification_status = verified
- partner_profile.is_available = true
- partner service aktif dan terverifikasi
```

## Pengajuan Layanan Mitra

Tenaga kesehatan mengajukan layanan yang bisa dikerjakan.

```http
GET /api/mitra/service-applications
POST /api/mitra/service-applications
PATCH /api/mitra/service-applications/{partnerService}
```

Query `GET /api/mitra/service-applications`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `per_page` | Tidak | integer | 1-100 |

Body `POST /api/mitra/service-applications`:

```json
{
  "service_id": 1,
  "coverage_radius_km": 15,
  "notes": "Siap untuk homecare area Jember Kota"
}
```

Field:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `service_id` | Ya | integer | harus ada di `services` |
| `price` | Tidak | numeric | hanya dipakai untuk service konsultasi/chat jika tersedia; service booking non-konsultasi dikunci ke `service.base_price` |
| `coverage_radius_km` | Tidak | integer | min 1 |
| `notes` | Tidak | string | catatan pengajuan |

Body `PATCH /api/mitra/service-applications/{partnerService}`:

```json
{
  "coverage_radius_km": 20,
  "is_available": true,
  "is_active": true,
  "notes": "Update radius layanan"
}
```

Rule profesi layanan:

```text
dokter  -> dokter_homecare, konsultasi_tindakan
perawat -> perawat_homecare, konsultasi_tindakan
bidan   -> bidan_homecare, konsultasi_tindakan
```

`is_verified` hanya bisa diubah admin. Setelah submit, status awal `is_verified = false`.

## Booking Layanan Mitra

Endpoint ini untuk menerima dan memproses booking layanan yang ditugaskan ke mitra. Route `/api/mitra/*` dilindungi middleware role mitra, sehingga token pasien tidak dapat memakai endpoint mitra. Saat pasien membuat booking, backend langsung memilih mitra yang cocok dan mengirim event realtime ke mitra tersebut.

```http
GET /api/mitra/service-bookings
GET /api/mitra/service-bookings/{serviceBooking}
PATCH /api/mitra/service-bookings/{serviceBooking}/accept
PATCH /api/mitra/service-bookings/{serviceBooking}/start-journey
PATCH /api/mitra/service-bookings/{serviceBooking}/location
POST /api/mitra/service-bookings/{serviceBooking}/histories
PATCH /api/mitra/service-bookings/{serviceBooking}/complete
PATCH /api/mitra/service-bookings/{serviceBooking}/status
```

Query `GET /api/mitra/service-bookings`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `service_id` | Tidak | integer | filter layanan |
| `status` | Tidak | enum | `pending`, `confirmed`, `scheduled`, `on_the_way`, `completed`, `cancelled` |
| `per_page` | Tidak | integer | 1-100 |

List otomatis dibatasi ke:

```text
assigned_partner_user_id = user login
```

### Accept Booking

```http
PATCH /api/mitra/service-bookings/{serviceBooking}/accept
```

Body:

```json
{
  "notes": "Pesanan diterima, saya segera bersiap."
}
```

Syarat:

- akun login adalah mitra tenaga kesehatan;
- booking ditugaskan ke mitra tersebut atau belum punya assigned partner;
- layanan sesuai profesi mitra;
- partner service aktif dan terverifikasi;
- status booking masih `pending` atau `scheduled`.

Accept bisa dilakukan sebelum pembayaran lunas. Setelah accept, pasien melanjutkan pembayaran. Aksi berikutnya seperti `start-journey`, `histories`, dan `complete` membutuhkan `payment.status = paid`.

Payout saldo mitra juga dapat terjadi saat pasien mengonfirmasi layanan selesai lewat:

```http
PATCH /api/patient/service-bookings/{serviceBooking}/confirm-completion
```

Jika booking sudah lunas dan belum pernah dibayarkan ke mitra, backend mengkredit wallet mitra sebesar `partner_payout_amount` dan mengisi `partner_paid_at` serta `partner_balance_transaction_id`. Endpoint ini idempotent, jadi konfirmasi ulang pasien tidak menggandakan saldo.

### Start Journey

```http
PATCH /api/mitra/service-bookings/{serviceBooking}/start-journey
```

Body:

```json
{
  "notes": "Mitra mulai menuju lokasi pasien."
}
```

Status booking berubah menjadi:

```text
on_the_way
```

Setelah status `on_the_way`, app mitra dapat mulai mengirim lokasi realtime berkala.

### Update Lokasi Realtime

```http
PATCH /api/mitra/service-bookings/{serviceBooking}/location
```

Body:

```json
{
  "latitude": -8.172357,
  "longitude": 113.700302,
  "accuracy_meters": 12.5,
  "heading": 90,
  "speed_mps": 4.2,
  "recorded_at": "2026-07-08 10:00:00"
}
```

Field:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `latitude` | Ya | numeric | -90 sampai 90 |
| `longitude` | Ya | numeric | -180 sampai 180 |
| `accuracy_meters` | Tidak | numeric | akurasi GPS meter, min 0 |
| `heading` | Tidak | numeric | arah derajat 0-360 |
| `speed_mps` | Tidak | numeric | meter/detik |
| `recorded_at` | Tidak | datetime | waktu dari device; default backend `now()` |

Syarat:

- akun login adalah mitra yang ditugaskan ke booking;
- status booking sudah `on_the_way`.

Saat berhasil, backend menyimpan lokasi terakhir di booking dan broadcast event `service-booking.location.updated` ke channel pasien:

```text
private-service-booking.{serviceBookingId}.tracking
```

Rekomendasi Flutter mitra: mulai background/location stream setelah `start-journey`, kirim lokasi tiap 5-10 detik atau saat perpindahan signifikan, hentikan saat status `completed` atau `cancelled`. Tetap minta izin lokasi foreground/background sesuai kebutuhan platform.

### Tambah Catatan Penanganan

```http
POST /api/mitra/service-bookings/{serviceBooking}/histories
```

Body:

```json
{
  "title": "Pemeriksaan awal",
  "description": "Tekanan darah pasien 120/80, suhu 37.8C.",
  "handled_at": "2026-07-08 09:30:00",
  "meta": {
    "temperature": 37.8,
    "blood_pressure": "120/80"
  }
}
```

Field:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `title` | Ya | string | max 255 |
| `description` | Tidak | string | catatan tindakan |
| `handled_at` | Tidak | datetime | default `now()` |
| `meta` | Tidak | object | metadata bebas |

### Complete Booking

```http
PATCH /api/mitra/service-bookings/{serviceBooking}/complete
```

Body:

```json
{
  "notes": "Layanan selesai.",
  "summary": "Pasien sudah mendapat tindakan dan edukasi perawatan."
}
```

Saat berhasil:

- status menjadi `completed`;
- `completed_at` terisi;
- saldo mitra dikreditkan jika `partner_payout_amount > 0` dan belum pernah dibayarkan ke mitra.
- Dashboard mitra harus menampilkan `partner_payout_amount` sebagai uang diterima. `total_amount` adalah total bayar pasien dan dapat berisi markup aplikasi, transport, atau biaya lain.

### Update Status Manual

```http
PATCH /api/mitra/service-bookings/{serviceBooking}/status
```

Body:

```json
{
  "status": "cancelled",
  "notes": "Mitra membatalkan karena alasan darurat."
}
```

Status tersedia:

```text
pending, confirmed, scheduled, on_the_way, completed, cancelled
```

## Saldo Mitra

Endpoint saldo mitra dipakai oleh dashboard mitra dan bisa dipakai Flutter untuk halaman wallet. Route `/api/mitra/*` dilindungi middleware role mitra, sehingga token pasien tidak dapat membaca saldo mitra.

```http
GET /api/mitra/balance
GET /api/mitra/balance/history
```

Response `GET /api/mitra/balance`:

```json
{
  "success": true,
  "data": {
    "balance": {
      "id": 1,
      "user_id": 12,
      "balance": "250000.00",
      "reserved_balance": "50000.00",
      "status": "active"
    },
    "summary": {
      "current_balance": 250000,
      "reserved_balance": 50000,
      "available_balance": 200000,
      "total_topup": 250000,
      "total_refund": 0,
      "total_deduction": 0,
      "status": "active"
    }
  }
}
```

Query `GET /api/mitra/balance/history`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `per_page` | Tidak | integer | default 20, max 100 |
| `type` | Tidak | string | contoh `topup`, `refund`, `deduction`, `adjustment`, `transfer`, `payment` |
| `status` | Tidak | string | contoh `pending`, `completed`, `failed`, `cancelled` |

Payout dari service booking masuk ke history saldo setelah pasien mengonfirmasi layanan selesai atau saat endpoint mitra complete berhasil mengkredit saldo. Gunakan `available_balance` untuk nominal yang bisa ditampilkan sebagai saldo tersedia.

## Konsultasi Mitra

```http
GET /api/mitra/consultations
GET /api/mitra/consultations/{consultation}
PATCH /api/mitra/consultations/{consultation}/status
POST /api/mitra/consultations/{consultation}/messages
```

Query `GET /api/mitra/consultations`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `status` | Tidak | enum | `pending`, `confirmed`, `ongoing`, `completed`, `cancelled` |
| `service_type` | Tidak | string | contoh `chat`, `voice_call`, `video_call`, `visit` |
| `search` | Tidak | string | max 100; cari kode, complaint, diagnosis, patient/partner |
| `per_page` | Tidak | integer | 1-100 |

List konsultasi otomatis dibatasi ke:

```text
partner_user_id = user login
```

### Update Status Konsultasi

```http
PATCH /api/mitra/consultations/{consultation}/status
```

Body:

```json
{
  "status": "ongoing",
  "diagnosis": "Observasi demam",
  "notes": "Pasien diminta istirahat dan minum cukup."
}
```

Field:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `status` | Ya | enum | `pending`, `confirmed`, `ongoing`, `completed`, `cancelled` |
| `diagnosis` | Tidak | string | diagnosis dokter/mitra |
| `notes` | Tidak | string | catatan konsultasi |

Jika status `ongoing`, `started_at` akan terisi jika masih null. Jika `completed` atau `cancelled`, `ended_at` akan terisi jika masih null.

### Kirim Pesan Konsultasi

```http
POST /api/mitra/consultations/{consultation}/messages
```

Body:

```json
{
  "message_type": "text",
  "message": "Baik, saya cek keluhannya dulu.",
  "attachment_path": null
}
```

Field:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `message_type` | Ya | enum | `text`, `image`, `file`, `system` |
| `message` | Tidak | string | isi pesan |
| `attachment_path` | Tidak | string | max 255 |

Pesan akan memicu event realtime `chat.message.created` ke channel consultation terkait.

## Apotik Mitra

### Register Data Apotik

Endpoint ini dipakai akun `mitra` yang belum punya data `pharmacy`.

```http
POST /api/mitra/apotik/register
```

Body:

```json
{
  "pharmacy_name": "Apotik Sehat Jember",
  "license_number": "SIA-12345",
  "work_location": "Jl. Jawa No. 10, Jember",
  "latitude": -8.172357,
  "longitude": 113.700302,
  "opening_time": "08:00",
  "closing_time": "21:00",
  "bio": "Apotik layanan obat dan alat kesehatan"
}
```

Field:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `pharmacy_name` | Ya | string | max 255 |
| `license_number` | Tidak | string | max 255, unique di `pharmacy_profiles` |
| `work_location` | Tidak | string | max 500 |
| `latitude` | Tidak | numeric | -90 sampai 90 |
| `longitude` | Tidak | numeric | -180 sampai 180 |
| `opening_time` | Tidak | time | format `HH:mm` |
| `closing_time` | Tidak | time | format `HH:mm`, harus setelah opening_time |
| `bio` | Tidak | string | deskripsi |

Data apotik awal `is_active = false` dan menunggu verifikasi/admin.

### Produk Apotik

```http
GET /api/mitra/apotik/products
POST /api/mitra/apotik/products
GET /api/mitra/apotik/products/{product}
PATCH /api/mitra/apotik/products/{product}
PATCH /api/mitra/apotik/products/{product}/stock
DELETE /api/mitra/apotik/products/{product}
```

Query `GET /api/mitra/apotik/products`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `type` | Tidak | enum | `obat`, `produk_kesehatan`, `layanan`, `sewa_alat_kesehatan` |
| `requires_prescription` | Tidak | boolean | filter butuh resep |
| `search` | Tidak | string | max 100 |
| `is_active` | Tidak | boolean | filter aktif/nonaktif |
| `per_page` | Tidak | integer | 1-100 |

Body `POST /api/mitra/apotik/products`:

```json
{
  "sku": "OBT-PCT-500",
  "name": "Paracetamol 500mg",
  "type": "obat",
  "category": "Obat Demam",
  "description": "Paracetamol tablet 500mg",
  "price": 12000,
  "stock": 100,
  "minimum_stock_alert": 10,
  "track_stock": true,
  "requires_prescription": false,
  "is_active": true,
  "image": "products/paracetamol.jpg"
}
```

Field create product:

| Field | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `sku` | Ya | string | max 100, unique per apotik |
| `name` | Ya | string | max 255 |
| `type` | Ya | enum | `obat`, `produk_kesehatan`, `layanan`, `sewa_alat_kesehatan` |
| `category` | Tidak | string | max 255 |
| `description` | Tidak | string | deskripsi |
| `price` | Ya | numeric | min 0 |
| `stock` | Tidak | integer | min 0, default 0 |
| `minimum_stock_alert` | Tidak | integer | min 0, default 5 |
| `track_stock` | Tidak | boolean | default true |
| `requires_prescription` | Tidak | boolean | default false |
| `is_active` | Tidak | boolean | default true |
| `image` | Tidak | string | max 255 |

Body update stok:

```json
{
  "stock": 80,
  "minimum_stock_alert": 10,
  "track_stock": true,
  "is_active": true
}
```

## Shipment / Kurir

Endpoint shipment tersedia di prefix mitra. Aplikasi kurir/mitra pengiriman dapat memakai endpoint ini sesuai kebutuhan.

```http
GET /api/mitra/shipments
GET /api/mitra/shipments/{shipment}
PATCH /api/mitra/shipments/{shipment}/assign-courier
PATCH /api/mitra/shipments/{shipment}/status
```

Query `GET /api/mitra/shipments`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `courier_user_id` | Tidak | integer | filter kurir |
| `status` | Tidak | enum | `waiting_courier`, `picked_up`, `on_delivery`, `delivered`, `failed`, `cancelled` |
| `per_page` | Tidak | integer | 1-100 |

Assign courier:

```http
PATCH /api/mitra/shipments/{shipment}/assign-courier
```

```json
{
  "courier_user_id": 30
}
```

Update status shipment:

```http
PATCH /api/mitra/shipments/{shipment}/status
```

```json
{
  "status": "on_delivery",
  "title": "Pesanan dalam pengiriman",
  "description": "Kurir sedang menuju alamat pasien."
}
```

Status shipment:

```text
waiting_courier, picked_up, on_delivery, delivered, failed, cancelled
```

Jika status `delivered`, order terkait ikut berubah menjadi `delivered`. Jika `picked_up` atau `on_delivery`, order berubah menjadi `shipped`.

## Notifikasi

Semua role mitra bisa memakai endpoint notifikasi shared.

```http
GET /api/shared/notifications
GET /api/shared/notifications/unread-count
PATCH /api/shared/notifications/{notification}/read
PATCH /api/shared/notifications/read-all
DELETE /api/shared/notifications/{notification}
POST /api/shared/notifications
```

Query `GET /api/shared/notifications`:

| Query | Required | Type | Rule/Catatan |
| --- | --- | --- | --- |
| `status` | Tidak | enum | `read`, `unread` |
| `type` | Tidak | string | max 100 |
| `per_page` | Tidak | integer | 1-100 |

Tipe notifikasi yang relevan untuk app mitra:

```text
service_booking.matched
service_booking.paid
service_booking.status_updated
consultation.created
consultation.status_updated
consultation.message_created
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

Local Laragon:

```text
key: medic-app-key
host: 127.0.0.1
port: 8080
scheme: ws
auth endpoint: http://medic-app.test/api/broadcasting/auth
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
  "channel_name": "private-partner.12.service-bookings"
}
```

### Booking Match Realtime

Laravel channel:

```text
partner.{partnerId}.service-bookings
```

Pusher channel name:

```text
private-partner.{partnerId}.service-bookings
```

Event:

```text
service-booking.matched
```

Payload:

```json
{
  "booking": {
    "id": 25,
    "booking_code": "SVB-20260708101010-123",
    "status": "pending",
    "scheduled_at": "2026-07-08T03:00:00.000000Z",
    "total_amount": "150000.00",
    "notes": "Pasien demam",
    "service": {
      "id": 1,
      "name": "Homecare Perawat",
      "service_type": "perawat_homecare"
    },
    "patient": {
      "id": 7,
      "name": "Budi",
      "phone": "08123456789"
    },
    "address": {
      "id": 10,
      "label": "Rumah",
      "address": "Jl. Jawa No. 10",
      "latitude": "-8.1723570",
      "longitude": "113.7003020"
    }
  },
  "matchmaking": {
    "partner_service_id": 4,
    "partner_user_id": 12,
    "distance_km": 2.35,
    "match_score": 82.4,
    "quality_score": 90
  },
  "created_at": "2026-07-08T03:00:00.000000Z"
}
```

Setelah menerima event ini, app mitra sebaiknya:

1. tampilkan notifikasi/order baru;
2. panggil `GET /api/mitra/service-bookings/{id}` untuk detail penuh;
3. tampilkan tombol accept jika status booking `pending` atau `scheduled`;
4. tampilkan tombol berangkat/selesai hanya jika `payment.status = paid`.

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

Flutter mitra login dengan user ID `12` harus subscribe:

```text
private-user.12.notifications
```

Payload:

```json
{
  "id": 101,
  "user_id": 12,
  "type": "service_booking.matched",
  "title": "Pesanan layanan baru",
  "body": "Ada pesanan layanan baru yang cocok untuk Anda.",
  "action_url": "/mitra/service-bookings/25",
  "reference_type": "service_booking",
  "reference_id": 25,
  "data": {
    "service_booking_id": 25,
    "booking_code": "SVB-20260708101010-123",
    "status": "pending"
  },
  "read_at": null,
  "created_at": "2026-07-08T03:00:00.000000Z"
}
```

### Chat Consultation

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
    "role": "pasien"
  },
  "message_type": "text",
  "message": "Halo dokter",
  "attachment_path": null,
  "read_at": null,
  "created_at": "2026-07-08T03:00:00.000000Z"
}
```

Subscribe hanya berhasil jika user login adalah pasien atau mitra yang terkait dengan consultation tersebut.

### Presence Online Users

Laravel channel:

```text
online-users
```

Pusher channel name:

```text
presence-online-users
```

Gunakan channel ini jika app mitra perlu menampilkan status user online.

## Referensi Field Model

### User

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID user |
| `name` | string | nama user |
| `email` | string | email login |
| `phone` | string/null | nomor telepon |
| `role` | enum | normalnya `mitra` untuk app mitra |
| `partner_profile` | object/null | profil tenaga kesehatan |
| `pharmacy` | object/null | data apotik |
| `courier_profile` | object/null | data kurir |

### Partner Profile

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID profile |
| `user_id` | integer | ID user mitra |
| `profession` | enum | `dokter`, `perawat`, `bidan` |
| `specialization` | string/null | spesialisasi |
| `license_number` | string/null | nomor STR/SIP |
| `work_location` | string/null | lokasi praktik |
| `latitude` | decimal/null | latitude |
| `longitude` | decimal/null | longitude |
| `years_of_experience` | integer | pengalaman |
| `consultation_fee` | decimal string | biaya konsultasi |
| `is_available` | boolean | status tersedia |
| `bio` | string/null | deskripsi |
| `verification_status` | enum/string | `pending`, `verified`, atau status lain sesuai data |
| `verified_at` | datetime/null | waktu verifikasi |
| `str_photo_path` | string/null | path dokumen STR |
| `ktp_photo_path` | string/null | path dokumen KTP |

### Partner Service

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID pengajuan layanan |
| `service_id` | integer | ID layanan |
| `partner_user_id` | integer | ID mitra |
| `price` | decimal string/null | harga yang terlihat di mitra; untuk service booking non-konsultasi mengikuti `service.base_price` |
| `coverage_radius_km` | integer/null | radius layanan |
| `is_active` | boolean | aktif/nonaktif |
| `is_verified` | boolean | sudah diverifikasi admin |
| `notes` | string/null | catatan |
| `service` | object/null | detail layanan |

### Service Booking

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID booking |
| `booking_code` | string | kode booking |
| `service_id` | integer | ID layanan |
| `patient_user_id` | integer | ID pasien |
| `patient_member_id` | integer/null | profil pasien keluarga |
| `assigned_partner_user_id` | integer/null | ID mitra |
| `patient_address_id` | integer/null | alamat layanan |
| `status` | enum | `pending`, `confirmed`, `scheduled`, `on_the_way`, `completed`, `cancelled` |
| `booking_type` | enum | `scheduled`, `daily` |
| `visit_plan` | enum | `once`, `recurring` |
| `recurrence` | enum/null | `weekly`, `monthly`; null untuk sekali visit |
| `visit_count` | integer | jumlah kunjungan dalam paket |
| `care_mode` | enum | `visit`, `live_in` |
| `location_type` | enum | `home`, `hospital` |
| `distance_km` | decimal string/null | snapshot jarak saat matchmaking |
| `scheduled_at` | datetime/null | jadwal |
| `schedule_start_at` | datetime/null | mulai layanan harian |
| `schedule_end_at` | datetime/null | selesai layanan harian |
| `duration_days` | integer | durasi hari |
| `accepted_at` | datetime/null | waktu diterima |
| `started_at` | datetime/null | waktu mulai/perjalanan |
| `completed_at` | datetime/null | waktu selesai |
| `partner_current_latitude` | decimal string/null | latitude lokasi realtime terakhir |
| `partner_current_longitude` | decimal string/null | longitude lokasi realtime terakhir |
| `partner_location_accuracy_meters` | decimal string/null | akurasi GPS meter |
| `partner_location_heading` | decimal string/null | arah derajat 0-360 |
| `partner_location_speed_mps` | decimal string/null | kecepatan meter/detik |
| `partner_location_updated_at` | datetime/null | waktu lokasi terakhir diterima backend |
| `total_amount` | decimal string | total bayar pasien, bisa termasuk markup dan biaya tambahan |
| `partner_payout_amount` | decimal/number | uang yang diterima mitra; gunakan ini untuk dashboard pendapatan |
| `transport_fee` | decimal string | total transport seluruh kunjungan; nol untuk live-in |
| `meal_fee` | decimal string | total uang makan jika lokasi rumah sakit |
| `fee_policy_snapshot` | object/null | snapshot aturan biaya ketika pasien booking |
| `notes` | string/null | catatan |
| `service` | object/null | layanan |
| `patient` | object/null | user pasien |
| `patient_member` | object/null | profil pasien |
| `assigned_partner` | object/null | user mitra |
| `address` | object/null | alamat pasien |
| `histories` | array | riwayat status/treatment |
| `payment` | object/null | pembayaran |
| `partner_balance_transaction` | object/null | transaksi saldo mitra |

### Consultation

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID konsultasi |
| `consultation_code` | string | kode konsultasi |
| `patient_user_id` | integer | ID pasien |
| `partner_user_id` | integer | ID mitra |
| `service_type` | enum | `chat`, `voice_call`, `video_call`, `visit` |
| `status` | enum | `pending`, `confirmed`, `ongoing`, `completed`, `cancelled` |
| `scheduled_at` | datetime/null | jadwal |
| `started_at` | datetime/null | waktu mulai |
| `ended_at` | datetime/null | waktu selesai |
| `complaint` | string/null | keluhan pasien |
| `diagnosis` | string/null | diagnosis |
| `notes` | string/null | catatan |
| `consultation_fee` | decimal string | biaya |
| `patient` | object/null | user pasien |
| `partner` | object/null | user mitra |
| `messages` | array | pesan jika dimuat |
| `payment` | object/null | pembayaran |
| `prescription` | object/null | resep |

### Product

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID produk |
| `pharmacy_id` | integer | ID apotik |
| `sku` | string | SKU unik per apotik |
| `name` | string | nama produk |
| `type` | enum | `obat`, `produk_kesehatan`, `layanan`, `sewa_alat_kesehatan` |
| `category` | string/null | kategori |
| `description` | string/null | deskripsi |
| `price` | decimal string | harga |
| `stock` | integer | stok |
| `minimum_stock_alert` | integer/null | batas stok minimum |
| `track_stock` | boolean | stok dilacak |
| `requires_prescription` | boolean | butuh resep |
| `is_active` | boolean | aktif |
| `image` | string/null | path gambar |
| `pharmacy` | object/null | relasi apotik |

### Shipment

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID shipment |
| `shipment_code` | string | kode shipment |
| `order_id` | integer | ID order |
| `courier_user_id` | integer/null | ID kurir |
| `delivery_type` | string/null | tipe pengiriman |
| `status` | enum | `waiting_courier`, `picked_up`, `on_delivery`, `delivered`, `failed`, `cancelled` |
| `assigned_at` | datetime/null | waktu assign |
| `picked_up_at` | datetime/null | waktu pick up |
| `delivered_at` | datetime/null | waktu delivered |
| `notes` | string/null | catatan |
| `order` | object/null | data order |
| `courier` | object/null | user kurir |
| `histories` | array | riwayat shipment |

### App Notification

| Field | Type | Catatan |
| --- | --- | --- |
| `id` | integer | ID notifikasi |
| `user_id` | integer | penerima |
| `type` | string | tipe |
| `title` | string | judul |
| `body` | string/null | isi |
| `action_url` | string/null | tujuan |
| `reference_type` | string/null | tipe referensi |
| `reference_id` | integer/null | ID referensi |
| `data` | object/null | metadata |
| `read_at` | datetime/null | null berarti unread |
| `created_at` | datetime | waktu dibuat |

## Contoh Flow Flutter Mitra

1. Login via `POST /api/mitra/login`, `POST /api/mitra/doctor/login`, `POST /api/mitra/nurse/login`, atau `POST /api/mitra/apotik/login`.
2. Simpan `user_api_token`.
3. Panggil `GET /api/shared/me` untuk menentukan mode UI dari `partner_profile`, `pharmacy`, atau `courier_profile`.
4. Tenaga kesehatan update availability via `PATCH /api/mitra/profile` dengan `is_available=true`.
5. Tenaga kesehatan ambil layanan via `GET /api/mitra/service-applications`.
6. Subscribe ke `private-partner.{userId}.service-bookings` untuk booking match realtime.
7. Subscribe ke `private-user.{userId}.notifications` untuk notifikasi realtime.
8. Saat event booking masuk, panggil detail booking dan tampilkan tombol aksi sesuai status/payment.
9. Saat mulai berangkat, panggil `PATCH /api/mitra/service-bookings/{id}/start-journey`, lalu kirim lokasi berkala ke `PATCH /api/mitra/service-bookings/{id}/location`.
10. Untuk chat konsultasi, ambil list via `GET /api/mitra/consultations`, subscribe ke `private-consultation.{consultationId}`, lalu kirim pesan via endpoint messages.
11. Untuk apotik, kelola produk dari endpoint `/api/mitra/apotik/products`.

## Debug WebSocket

Jika koneksi gagal:

1. Pastikan Reverb server berjalan.
2. Pastikan auth endpoint `/api/broadcasting/auth` mengembalikan `200`.
3. Jika auth `401`, token belum dikirim atau token salah.
4. Jika auth `403`, user tidak boleh subscribe channel tersebut.
5. Jika WebSocket tidak `101 Switching Protocols`, cek host/port/scheme.
6. Untuk production, gunakan `wss://backend.perawatku.tech/app/medic-app-key`, bukan port internal `8080`.

Local Laragon Reverb:

```bash
php artisan optimize:clear
php artisan reverb:start --host=0.0.0.0 --port=8080 --hostname=127.0.0.1 --debug
```

Production WebSocket:

```text
wss://backend.perawatku.tech/app/medic-app-key
```
