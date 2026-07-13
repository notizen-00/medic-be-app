# PRD — Service Booking Sekali Visit dan Terjadwal

## Tujuan

Pasien memilih pola kunjungan sebelum memesan: sekali visit atau terjadwal (mingguan/bulanan). Sistem menghitung biaya transportasi untuk kunjungan terjadwal berjarak lebih dari ambang admin, mengecualikan live-in dari biaya transportasi, dan menambahkan uang makan untuk lokasi rumah sakit.

## Ruang lingkup dan aturan

- `visit_plan`: `once` atau `recurring`.
- Booking `recurring` wajib memilih `recurrence` (`weekly`/`monthly`) dan `visit_count` (2–52). Booking `once` selalu satu kunjungan.
- `care_mode`: `visit` atau `live_in`. Live-in hanya tersedia untuk booking terjadwal dan tidak dikenai transportasi.
- `location_type`: `home` atau `hospital`.
- Transport dikenakan per kunjungan hanya jika booking terjadwal, bukan live-in, dan jarak mitra–pasien **lebih besar** dari ambang (default 10 km).
- Uang makan dikenakan per kunjungan bila lokasi rumah sakit, termasuk live-in.
- Harga layanan dan markup dikalikan jumlah kunjungan. Promo diterapkan ke komponen layanan; biaya transportasi dan makan ditambahkan setelah diskon.
- Nilai jarak, biaya, dan kebijakan admin disimpan sebagai snapshot pada booking sehingga transaksi historis tidak berubah.
- Kontrak lama `booking_type=scheduled|daily` dipertahankan untuk kompatibilitas operasional.
- Request lama `booking_type=daily` tanpa `visit_plan` tetap menghitung harga berdasarkan `duration_days`, tetapi tidak memperoleh biaya transportasi baru.

## API

### Buat booking pasien

`POST /api/patient/service-bookings`

Field baru: `visit_plan`, `recurrence`, `visit_count`, `care_mode`, dan `location_type`. Respons pricing menampilkan `service_subtotal`, `transport_fee`, `meal_fee`, dan `total_amount`.

### Pengaturan admin

- `GET /api/admin/service-booking-fees`
- `PUT /api/admin/service-booking-fees`

Payload: `transport_distance_threshold_km`, `transport_fee_per_visit`, `hospital_meal_fee_per_visit`, dan opsional `is_active`.

## Acceptance criteria

1. Sekali visit tidak mendapat biaya transportasi meskipun jarak >10 km.
2. Mingguan/bulanan non-live-in dengan jarak > ambang mendapat biaya transportasi × jumlah visit.
3. Jarak tepat pada ambang tidak dikenai biaya.
4. Live-in tidak pernah mendapat biaya transportasi.
5. Lokasi rumah sakit mendapat uang makan × jumlah visit.
6. Total payment sama dengan total snapshot booking.
7. Hanya admin dapat membaca/mengubah kebijakan biaya.
