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
  "password_confirmation": "password123"
}
```

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

### Layanan

```http
GET /api/patient/services
GET /api/patient/services/{service}
```

Alternatif catalog pasien:

```http
GET /api/patient/service-bookings/services
GET /api/patient/service-bookings/services/{service}
```

### Booking Layanan / Matchmaking

Buat booking:

```http
POST /api/patient/service-bookings
```

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

Response penting:

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

List booking pasien:

```http
GET /api/patient/service-bookings
```

Detail booking:

```http
GET /api/patient/service-bookings/{serviceBooking}
```

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

### Promo Code

```http
POST /api/patient/service-bookings/check-promo-code
GET /api/patient/promo-codes/available
```

### Konsultasi

List konsultasi:

```http
GET /api/patient/consultations
```

Buat konsultasi:

```http
POST /api/patient/consultations
```

Detail konsultasi:

```http
GET /api/patient/consultations/{consultation}
```

Bayar konsultasi:

```http
PATCH /api/patient/consultations/{consultation}/pay
```

Update status:

```http
PATCH /api/patient/consultations/{consultation}/status
```

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

### Produk dan Order Apotik

Produk:

```http
GET /api/patient/products/global
GET /api/patient/products
GET /api/patient/products/{product}
```

Order:

```http
GET /api/patient/orders
POST /api/patient/orders
GET /api/patient/orders/{order}
PATCH /api/patient/orders/{order}/status
```

### Balance

```http
GET /api/patient/balance
GET /api/patient/balance/history
POST /api/patient/balance/topup
PATCH /api/patient/balance/topup/confirm
```

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
