# UX Flow Service Booking Terbaru

Dokumen ini merangkum UX flow service booking terbaru untuk aplikasi pasien, aplikasi mitra, dan dashboard admin. Flow ini mengikuti kontrak backend saat ini: pasien memilih kebutuhan layanan, sistem memilih mitra lewat matchmaking, mitra menerima pesanan, pasien membayar, lalu layanan diproses sampai selesai.

## Prinsip UX

- Pasien tidak memilih profesi atau mitra di awal. Pasien memilih kebutuhan layanan seperti Pasang Infus, Perawatan Luka, Dokter Datang ke Rumah, atau Caregiver.
- Tampilkan harga sebagai estimasi/final dari backend. Jangan hitung total final hanya di frontend.
- Jadikan profil pasien keluarga sebagai bagian utama flow, karena booking wajib mengirim `patient_member_id`.
- Untuk layanan rumah/homecare, alamat profil pasien harus jelas dan punya koordinat jika ingin matchmaking, jarak, dan biaya lebih akurat.
- Pisahkan status bisnis dan status pembayaran secara visual. Contoh: pesanan diterima mitra, tetapi belum bisa berangkat karena pembayaran belum lunas.
- Setelah mitra berangkat, layar utama pasien berubah menjadi tracking map dan timeline status.

## Flow Utama Pasien

```text
Home
  -> Layanan
  -> Pilih kategori
  -> Pilih service
  -> Detail service
  -> Pilih pasien penerima layanan
  -> Lengkapi alamat dan catatan medis
  -> Pilih pola kunjungan
  -> Pilih jadwal
  -> Review biaya
  -> Buat booking
  -> Menunggu mitra menerima
  -> Bisa batal jika belum diterima mitra dan belum dibayar
  -> Bayar
  -> Menunggu jadwal / mitra berangkat
  -> Tracking mitra
  -> Layanan berlangsung
  -> Konfirmasi selesai
  -> Ringkasan layanan
```

## Screen Pasien

### 1. Home Layanan

Tujuan: membantu pasien menemukan kebutuhan layanan secepat mungkin.

Komponen:
- Search layanan.
- Shortcut kategori dari `service_category`: Dokter, Perawat, Bidan, Caregiver, dan kategori aktif lain.
- Card layanan populer dengan `image_url`, nama layanan, durasi, harga mulai, dan badge tersedia.
- Entry point "Pesanan aktif" jika ada booking yang belum selesai.

Endpoint:

```http
GET /api/patient/service-bookings/services?per_page=100
```

UX state:
- Loading skeleton kategori dan card layanan.
- Empty state: "Belum ada layanan aktif."
- Error state dengan tombol coba lagi.

### 2. List Kategori / Hasil Pencarian

Tujuan: menampilkan service yang relevan setelah pasien memilih kategori atau mencari.

Komponen:
- Chip kategori horizontal.
- Filter mode layanan: Visit, Chat, Voice, Video jika dibutuhkan.
- Card service berisi nama, deskripsi pendek, durasi, harga, dan kebutuhan jadwal/alamat.

Endpoint:

```http
GET /api/patient/service-bookings/services?category_id={id}&per_page=20
GET /api/patient/service-bookings/services?search={keyword}&per_page=20
```

### 3. Detail Service

Tujuan: memberi kepastian sebelum pasien masuk form booking.

Komponen:
- Gambar/icon layanan.
- Nama, kategori, deskripsi, durasi.
- Harga dari response detail `pricing.final_price`.
- Info "Perlu alamat", "Perlu jadwal", "Mitra akan dipilih otomatis".
- CTA utama: "Pesan layanan".

Endpoint:

```http
GET /api/patient/service-bookings/services/{serviceId}
```

### 4. Pilih Pasien Penerima

Tujuan: menentukan siapa yang menerima layanan.

Komponen:
- List profil keluarga dari patient member.
- Badge profil utama.
- Tombol tambah/edit profil.
- Validasi alamat jika service `requires_address=true`.

Endpoint:

```http
GET /api/patient/members
POST /api/patient/members
PATCH /api/patient/members/{patientMember}
```

UX rule:
- Jika profil belum punya alamat untuk layanan homecare, arahkan ke edit profil sebelum lanjut.
- Jika koordinat kosong, tampilkan warning halus: jarak dan biaya tambahan bisa kurang akurat.

### 5. Pola Kunjungan

Tujuan: memilih sekali visit atau terjadwal tanpa menampilkan istilah teknis backend.

Pilihan UI:
- "Sekali kunjungan" -> `visit_plan=once`, `visit_count=1`.
- "Berulang" -> `visit_plan=recurring`, wajib pilih `recurrence` dan `visit_count`.

Jika berulang:
- Frekuensi: Mingguan atau Bulanan.
- Jumlah kunjungan: stepper 2-52.
- Mode perawatan: Datang per kunjungan atau Live-in.
- Lokasi layanan: Rumah atau Rumah sakit.

Mapping:

```json
{
  "visit_plan": "recurring",
  "recurrence": "weekly",
  "visit_count": 4,
  "care_mode": "visit",
  "location_type": "hospital"
}
```

UX rule:
- Live-in hanya tampil/aktif saat `visit_plan=recurring`.
- Transport fee hanya dijelaskan sebagai "dihitung otomatis berdasarkan jarak dan kebijakan layanan".
- Rumah sakit perlu menampilkan info uang makan jika dikenakan oleh backend.

### 6. Pilih Jadwal

Tujuan: menentukan waktu kunjungan pertama.

Komponen:
- Date picker.
- Time picker.
- Untuk recurring, tampilkan preview jadwal: "4 kunjungan mingguan mulai 20 Juli 2026".

Mapping:

```json
{
  "scheduled_at": "2026-07-20 09:00:00"
}
```

UX rule:
- Jadwal harus setelah waktu sekarang.
- Jika service `requires_schedule=true`, tombol lanjut disabled sampai jadwal dipilih.

### 7. Catatan dan Promo

Tujuan: mengumpulkan detail klinis ringan dan promo.

Komponen:
- Catatan keluhan/kebutuhan.
- Input promo code.
- Tombol validasi promo.

Endpoint promo:

```http
POST /api/patient/service-bookings/check-promo-code
```

UX rule:
- Catatan contoh: "Pasien demam sejak malam", "Mohon bawa alat cek tekanan darah".
- Promo invalid tidak menahan booking, cukup tampilkan pesan dan beri opsi hapus kode.

### 8. Review Booking

Tujuan: memastikan pasien memahami penerima, lokasi, jadwal, mitra otomatis, dan biaya.

Komponen:
- Ringkasan service.
- Penerima layanan.
- Alamat.
- Jadwal atau jadwal pertama.
- Pola kunjungan.
- Breakdown biaya:
  - Harga layanan.
  - Markup.
  - Diskon.
  - Transport.
  - Uang makan.
  - Total.

Endpoint submit:

```http
POST /api/patient/service-bookings
```

UX rule:
- Setelah submit sukses, jangan tampilkan "mencari mitra" terlalu lama jika response sudah punya `assigned_partner_user_id`.
- Status awal terbaru adalah `waiting_partner_acceptance`.

### 9. Menunggu Mitra Menerima

Tujuan: memberi kejelasan bahwa pesanan sudah dikirim ke mitra terpilih.

Komponen:
- Status: "Menunggu mitra menerima".
- Nama service, jadwal, kode booking.
- Tombol refresh.
- Tombol "Batalkan pesanan".
- Info: "Pembayaran dilakukan setelah mitra menerima pesanan."

Data:
- `booking.status=pending`
- `payment.status=pending`
- `matchmaking_status=waiting_partner_acceptance`

Realtime:
- Subscribe notifikasi user `private-user.{userId}.notifications`.
- Event penting: `service_booking.accepted`, `service_booking.status_updated`.

Endpoint cancel:

```http
PATCH /api/patient/service-bookings/{id}/cancel
```

Body opsional:

```json
{
  "notes": "Saya ingin membatalkan pesanan."
}
```

UX rule:
- Tombol cancel hanya tampil saat `booking.status=pending`, `accepted_at=null`, dan payment belum `paid`.
- Setelah sukses, booking menjadi `cancelled`.
- Jika payment masih `pending`, backend mengubah payment menjadi `expired`.
- Jika mitra sudah menerima atau pembayaran sudah lunas, arahkan pasien ke flow bantuan/call center atau kebijakan refund.

### 10. Pembayaran

Tujuan: pasien menyelesaikan pembayaran setelah mitra accept.

Trigger:
- Mitra menerima booking dan status menjadi `confirmed`.

Endpoint:

```http
PATCH /api/patient/service-bookings/{id}/pay
```

Komponen:
- Ringkasan tagihan.
- Metode pembayaran Midtrans/Snap.
- CTA "Bayar sekarang".
- Status pembayaran: pending, paid, failed, expired.

UX rule:
- Jika payment sudah paid, langsung arahkan ke status booking.
- Jika Snap token lama masih aktif, gunakan ulang redirect/token dari backend.

### 11. Status dan Tracking

Tujuan: memantau progress setelah pembayaran.

State utama:

```text
confirmed -> Pesanan diterima
scheduled -> Dijadwalkan
on_the_way -> Mitra menuju lokasi
completed -> Selesai
cancelled -> Dibatalkan
```

Endpoint detail:

```http
GET /api/patient/service-bookings/{id}
```

Endpoint tracking:

```http
GET /api/patient/service-bookings/{id}/tracking
```

Realtime tracking:

```text
private-service-booking.{id}.tracking
event: service-booking.location.updated
```

UX rule:
- Map hanya jadi layar utama saat `status=on_the_way`.
- Sebelum `on_the_way`, tampilkan timeline dan jadwal.
- Jika lokasi mitra belum tersedia, tampilkan last known state, bukan map kosong.

### 12. Konfirmasi Selesai

Tujuan: menutup layanan dan memicu payout mitra jika belum diproses.

Endpoint:

```http
PATCH /api/patient/service-bookings/{id}/confirm-completion
```

Komponen:
- Ringkasan layanan.
- Catatan opsional.
- CTA "Konfirmasi selesai".

UX rule:
- Tampilkan konfirmasi final sebelum submit.
- Setelah sukses, arahkan ke ringkasan layanan dan histori.

## Flow Mitra

```text
Mitra login
  -> Dashboard pesanan
  -> Pesanan baru dari matchmaking
  -> Detail pesanan
  -> Accept
  -> Menunggu pasien bayar
  -> Mulai perjalanan
  -> Kirim lokasi realtime
  -> Tambah catatan penanganan
  -> Selesaikan pesanan
  -> Saldo masuk
```

### Dashboard Mitra

Komponen:
- Tab: Baru, Diterima, Hari ini, Dalam perjalanan, Selesai.
- Card booking: kode booking, layanan, pasien, jadwal, jarak, status pembayaran.
- Badge "Belum dibayar" untuk booking confirmed tetapi payment pending.

Endpoint:

```http
GET /api/mitra/service-bookings?status=pending
```

Realtime:

```text
private-partner.{partnerId}.service-bookings
event: service-booking.matched
```

### Detail Pesanan Baru

Komponen:
- Detail layanan.
- Profil pasien penerima.
- Alamat dan jarak.
- Jadwal.
- Catatan pasien.
- Estimasi pendapatan.
- CTA "Terima pesanan".

Endpoint:

```http
PATCH /api/mitra/service-bookings/{id}/accept
```

UX rule:
- Setelah accept, tampilkan status "Menunggu pasien membayar".
- Tombol "Mulai perjalanan" disabled sampai payment `paid`.

### Mulai Perjalanan dan Tracking

Endpoint:

```http
PATCH /api/mitra/service-bookings/{id}/start-journey
PATCH /api/mitra/service-bookings/{id}/location
```

UX rule:
- Minta permission lokasi sebelum mulai perjalanan.
- Kirim lokasi berkala hanya saat status `on_the_way`.
- Jika permission ditolak, mitra tetap bisa melihat alamat, tetapi tampilkan warning operasional.

### Catatan Penanganan dan Selesai

Endpoint:

```http
POST /api/mitra/service-bookings/{id}/treatment-histories
PATCH /api/mitra/service-bookings/{id}/complete
```

UX rule:
- Catatan penanganan dibuat ringan: title wajib, description opsional.
- Sebelum complete, tampilkan ringkasan dan konfirmasi.
- Setelah complete, tampilkan informasi saldo/payout.

## Flow Admin

```text
Admin login
  -> Kelola kategori layanan
  -> Kelola master service
  -> Kelola gambar, harga, durasi, aturan alamat/jadwal
  -> Kelola partner service dan verifikasi mitra
  -> Kelola kebijakan biaya booking
  -> Monitor booking dan payment
```

### Master Service

Komponen:
- Table service: gambar, kode, nama, kategori, mode, harga, aktif, butuh alamat, butuh jadwal.
- Filter kategori, mode, status aktif.
- Form upload image.
- Toggle `requires_address`, `requires_schedule`, `requires_matchmaking`.

Endpoint:

```http
GET /api/admin/services
POST /api/admin/services
PATCH /api/admin/services/{service}
POST /api/admin/services/{service}
```

UX rule:
- Untuk upload gambar gunakan multipart `POST`, bukan multipart PATCH.
- Preview harus memakai `image_url` dari response.

### Kebijakan Biaya Booking

Komponen:
- Ambang jarak transport.
- Biaya transport per visit.
- Uang makan rumah sakit per visit.
- Toggle aktif.

Endpoint:

```http
GET /api/admin/service-booking-fees
PUT /api/admin/service-booking-fees
```

UX rule:
- Beri contoh simulasi: recurring 4 visit, jarak 12 km, lokasi hospital.
- Jelaskan bahwa perubahan hanya berlaku untuk booking baru karena booking menyimpan snapshot.

### Monitoring Booking

Komponen:
- Filter status booking dan payment.
- Detail booking dengan timeline histories.
- Penerima layanan, mitra assigned, biaya snapshot.
- Link payment dan payout.

Endpoint:

```http
GET /api/admin/service-bookings
```

## State Machine

```text
pending
  -> confirmed      mitra accept
  -> cancelled      sebelum payment/payout

confirmed
  -> scheduled      dijadwalkan/diproses
  -> on_the_way     mitra berangkat, payment wajib paid
  -> cancelled      hanya jika belum paid/payout

scheduled
  -> on_the_way     mitra berangkat, payment wajib paid
  -> cancelled      hanya jika belum paid/payout

on_the_way
  -> completed      mitra complete atau pasien confirm completion

completed           final
cancelled           final
```

Payment state:

```text
pending -> paid | failed | expired | refunded
```

Kombinasi UX penting:

| Booking | Payment | UI Pasien | UI Mitra |
| --- | --- | --- | --- |
| `pending` | `pending` | Menunggu mitra menerima | Pesanan baru, bisa accept |
| `confirmed` | `pending` | Bayar sekarang | Menunggu pasien bayar |
| `confirmed`/`scheduled` | `paid` | Menunggu mitra berangkat | Bisa mulai perjalanan |
| `on_the_way` | `paid` | Tracking map | Kirim lokasi, tambah catatan |
| `completed` | `paid` | Ringkasan selesai | Saldo/payout tercatat |
| `cancelled` | pending/refund | Pesanan dibatalkan | Pesanan dibatalkan |

## Error dan Empty State

### Tidak ada mitra tersedia

Pesan UI:

```text
Belum ada mitra tersedia untuk layanan ini. Coba jadwal lain atau layanan serupa.
```

Action:
- Kembali ke detail service.
- Tawarkan layanan serupa dalam kategori yang sama.

### Profil pasien belum lengkap

Pesan UI:

```text
Lengkapi alamat pasien terlebih dahulu agar mitra bisa datang ke lokasi yang tepat.
```

Action:
- Edit profil pasien.

### Payment belum lunas

Pesan UI mitra:

```text
Pasien belum menyelesaikan pembayaran. Perjalanan bisa dimulai setelah pembayaran lunas.
```

Action:
- Refresh status.

### Lokasi realtime belum tersedia

Pesan UI pasien:

```text
Mitra sudah berangkat. Lokasi realtime akan muncul saat perangkat mitra mengirim GPS.
```

Action:
- Tampilkan alamat tujuan dan timeline.

## Endpoint Ringkas

Pasien:

```http
GET   /api/patient/service-bookings/services
GET   /api/patient/service-bookings/services/{service}
GET   /api/patient/members
POST  /api/patient/members
POST  /api/patient/service-bookings
GET   /api/patient/service-bookings/{id}
PATCH /api/patient/service-bookings/{id}/pay
PATCH /api/patient/service-bookings/{id}/cancel
GET   /api/patient/service-bookings/{id}/tracking
PATCH /api/patient/service-bookings/{id}/confirm-completion
```

Mitra:

```http
GET   /api/mitra/service-bookings
GET   /api/mitra/service-bookings/{id}
PATCH /api/mitra/service-bookings/{id}/accept
PATCH /api/mitra/service-bookings/{id}/start-journey
PATCH /api/mitra/service-bookings/{id}/location
POST  /api/mitra/service-bookings/{id}/treatment-histories
PATCH /api/mitra/service-bookings/{id}/complete
```

Admin:

```http
GET  /api/admin/services
POST /api/admin/services
GET  /api/admin/service-categories
GET  /api/admin/service-booking-fees
PUT  /api/admin/service-booking-fees
GET  /api/admin/service-bookings
```

## Copywriting Status

Gunakan label yang mudah dipahami user:

| Backend | Label Pasien | Label Mitra |
| --- | --- | --- |
| `pending` | Menunggu mitra menerima | Pesanan baru |
| `confirmed` | Mitra menerima pesanan | Pesanan diterima |
| `scheduled` | Menunggu jadwal layanan | Terjadwal |
| `on_the_way` | Mitra menuju lokasi | Menuju lokasi |
| `completed` | Layanan selesai | Selesai |
| `cancelled` | Dibatalkan | Dibatalkan |

Payment:

| Backend | Label |
| --- | --- |
| `pending` | Belum dibayar |
| `paid` | Lunas |
| `failed` | Gagal |
| `expired` | Kedaluwarsa |
| `refunded` | Dikembalikan |

## Rekomendasi Navigasi Mobile

Tab pasien:
- Beranda
- Layanan
- Pesanan
- Notifikasi
- Profil

Tab mitra:
- Beranda
- Pesanan
- Jadwal
- Saldo
- Profil

Entry point penting:
- Dari notifikasi `service_booking.accepted`, buka detail booking dan fokuskan CTA bayar.
- Dari notifikasi `service_booking.on_the_way`, buka tracking map.
- Dari notifikasi `service_booking.completed`, buka ringkasan dan konfirmasi selesai jika belum.
