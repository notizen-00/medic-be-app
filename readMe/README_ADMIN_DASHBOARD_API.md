# Admin Dashboard API — Integration Guide (Backend ↔ Nuxt)

Panduan integrasi API untuk aplikasi **Admin Dashboard** (Nuxt). Semua endpoint di bawah berada di bawah grup `Route::prefix('admin')` dengan middleware `auth:sanctum` + `admin`.

---

## 1. Konvensi Umum

| Item | Nilai |
| ---- | ----- |
| Base URL | `https://backend.perawatku.tech/api` |
| Auth | Bearer Token (`sanctum`) di header `Authorization` |
| Content-Type | `application/json` (atau `multipart/form-data` untuk upload file) |
| Pagination | Query `per_page` (default 20, maks 100). Response terbungkus Laravel paginator: `data`, `current_page`, `last_page`, `total`, dll. |
| Response sukses | `{ "message": "...", "data": ... }` (sebagian endpoint pakai `{ "success": true, "data": ... }`) |

Header wajib untuk semua request (kecuali login):

```
Authorization: Bearer <TOKEN_ADMIN>
Accept: application/json
```

---

## 2. Autentikasi Admin

### Login
```
POST /api/admin/login
```
**Body (JSON):**
```json
{
  "email": "admin@perawatku.tech",
  "password": "rahasia"
}
```
**Response:** mengembalikan `token` (sanctum) yang digunakan sebagai Bearer token.

---

## 3. Kategori Layanan (`service-categories`)

### List
```
GET /api/admin/service-categories
```
**Query:** `search` (string), `is_active` (boolean 0/1), `per_page` (int)

### Detail
```
GET /api/admin/service-categories/{id}
```

### Create
```
POST /api/admin/service-categories
```
**Body (JSON):**
```json
{
  "name": "Layanan Medis",
  "slug": "layanan-medis",          // opsional, auto dari name bila kosong
  "icon": "stethoscope",            // opsional
  "sort_order": 1,                  // opsional
  "is_active": true                 // opsional
}
```

### Update
```
PATCH /api/admin/service-categories/{id}
```
**Body (JSON):** field sama dengan create, semua opsional (`sometimes`).

### Delete
```
DELETE /api/admin/service-categories/{id}
```
> Gagal (422) bila masih memiliki service terkait.

---

## 4. Layanan / Service (`services`)

### List
```
GET /api/admin/services
```
**Query:** `service_category_id` (int), `category_id` (int), `service_type` (enum), `service_mode` (enum), `is_active` (bool), `requires_address` (bool), `requires_schedule` (bool), `requires_matchmaking` (bool), `search` (string), `per_page` (int)

Nilai enum:
- `service_type`: `consultation`, `procedure`, `caregiver`, `homecare`, `dokter_homecare`, `perawat_homecare`, `bidan_homecare`, `konsultasi_tindakan`
- `service_mode`: `chat`, `voice`, `video`, `visit`

### Detail
```
GET /api/admin/services/{id}
```

### Create
```
POST /api/admin/services
```
Gunakan **`multipart/form-data`** bila mengirim gambar.

**Body:**
| Field | Type | Keterangan |
| ----- | ---- | ---------- |
| service_code | string | opsional, auto-generate bila kosong (unique) |
| service_category_id | int | opsional (alias `category_id`) |
| name | string | **wajib** (max 255) |
| slug | string | opsional |
| service_type | string | **wajib** (enum di atas) |
| service_mode | string | opsional (enum, default `visit`) |
| category | string | opsional |
| description | string | opsional |
| base_price | numeric | **wajib** (min 0) |
| duration_minutes | int | opsional (default 60) |
| requires_address | bool | opsional |
| requires_schedule | bool | opsional |
| requires_matchmaking | bool | opsional |
| sort_order | int | opsional |
| is_active | bool | opsional |
| is_homecare | bool | opsional |
| image | file | opsional, jpg/jpeg/png/webp (maks 2 MB) |
| image_path | string | opsional, path manual |
| remove_image | bool | opsional |

### Update
```
PATCH /api/admin/services/{id}
```
**Body:** field sama dengan create (semua opsional). Untuk ganti gambar kirim `image` (file). Untuk hapus gambar kirim `remove_image=true`.

### Delete
```
DELETE /api/admin/services/{id}
```
> Gagal (422) bila masih dipakai mitra atau booking.

**Response service selalu menyertakan:**
```json
{
  "image": "services/abc.webp",
  "image_url": "https://backend.perawatku.tech/storage/services/abc.webp"
}
```

---

## 5. Service Markup (`service-markup`)

### List
```
GET /api/admin/service-markup
```
**Query:** `service_id` (int), `is_active` (bool), `per_page` (int)

### Detail
```
GET /api/admin/service-markup/{id}
```

### Create
```
POST /api/admin/service-markup
```
**Body (JSON):**
```json
{
  "service_id": 1,
  "markup_type": "percentage",     // "percentage" | "fixed"
  "markup_value": 10,              // numeric min 0
  "min_final_price": 50000,        // opsional
  "priority": 1,                   // opsional
  "notes": "Markup wilayah Jakarta" // opsional
}
```
> Hanya boleh 1 setting aktif per service (422 bila sudah ada).

### Update
```
PATCH /api/admin/service-markup/{id}
```
**Body (JSON):** field di atas opsional (`sometimes|...`), termasuk `is_active` (bool).

### Toggle Status
```
PATCH /api/admin/service-markup/{id}/toggle-status
```
**Body (JSON):**
```json
{ "is_active": true }
```

### Delete
```
DELETE /api/admin/service-markup/{id}
```

---

## 6. Promo Code (`promo-codes`)

### List
```
GET /api/admin/promo-codes
```
**Query:** `is_active` (bool), `service_id` (int), `search` (string), `per_page` (int)

### Detail
```
GET /api/admin/promo-codes/{id}
```

### Create
```
POST /api/admin/promo-codes
```
**Body (JSON):**
```json
{
  "code": "DISKON10",
  "name": "Diskon 10%",
  "description": "Promo akhir tahun",
  "discount_type": "percentage",   // "percentage" | "fixed"
  "discount_value": 10,            // numeric min 0
  "min_purchase": 100000,          // opsional
  "max_discount": 50000,           // opsional
  "max_uses": 100,                 // opsional
  "max_uses_per_user": 1,          // opsional
  "valid_from": "2026-07-01",      // opsional (date)
  "valid_until": "2026-12-31",     // opsional (date, >= valid_from)
  "service_id": 1                  // opsional
}
```

### Update
```
PATCH /api/admin/promo-codes/{id}
```
**Body (JSON):** field di atas opsional (`sometimes`), termasuk `is_active` (bool).

### Toggle Status
```
PATCH /api/admin/promo-codes/{id}/toggle-status
```
**Body (JSON):**
```json
{ "is_active": true }
```

### Delete
```
DELETE /api/admin/promo-codes/{id}
```

---

## 7. Mitra / Partner (`partners`)

### List semua mitra
```
GET /api/admin/partners
```
**Query:** `profession` (`dokter`|`perawat`|`bidan`), `search` (string), `is_available` (bool), `per_page` (int)

### List dokter / perawat / bidan
```
GET /api/admin/doctors
GET /api/admin/nurses
GET /api/admin/midwives
```
(sama dengan list, `profession` sudah difilter otomatis)

### Verifikasi mitra
```
PATCH /api/admin/partners/{user_id}/verify
```
**Body:** kosong. Mengubah `verification_status` mitra menjadi `verified`.

---

## 8. Pengajuan Layanan Mitra (`partner-services` & `service-applications`)

### List pengajuan layanan mitra
```
GET /api/admin/partner-services
```
**Query:** `partner_user_id` (int), `service_id` (int), `is_active` (bool), `is_verified` (bool), `search` (string), `per_page` (int)

### Verifikasi pengajuan layanan mitra
```
PATCH /api/admin/service-applications/{partnerService}/verify
```
**Body:** kosong.

---

## 9. Pasien (`patients`)

### List
```
GET /api/admin/patients
```
**Query:** `search` (string), `per_page` (int)

### Detail
```
GET /api/admin/patients/{user_id}
```

---

## 10. Orders (`orders`)

### List
```
GET /api/admin/orders
```
**Query:** `status` (string), `order_type` (string), `patient_user_id` (int), `pharmacy_id` (int), `search` (string), `per_page` (int)

### Detail
```
GET /api/admin/orders/{id}
```

---

## 11. Konsultasi (`consultations`)

### List
```
GET /api/admin/consultations
```
**Query:** `status` (string), `partner_user_id` (int), `patient_user_id` (int), `service_type` (string), `search` (string), `per_page` (int)

### Detail
```
GET /api/admin/consultations/{id}
```

---

## 12. Service Bookings (`service-bookings`)

### List
```
GET /api/admin/service-bookings
```
**Query:** `status` (string), `service_id` (int), `patient_user_id` (int), `assigned_partner_user_id` (int), `search` (string), `per_page` (int)

---

## 13. Payments (`payments`)

### List
```
GET /api/admin/payments
```
**Query:** `status` (string), `patient_user_id` (int), `search` (string), `per_page` (int)

---

## 14. Transactions (`transactions`)

### List transaksi saldo
```
GET /api/admin/transactions
```
**Query:** `search` (string), `per_page` (int)

---

## 15. Saldo / Balance (`balance`)

### List semua saldo user
```
GET /api/admin/balance
```
**Query:** `status` (string), `search` (string), `per_page` (int)

### List semua transaksi (audit)
```
GET /api/admin/balance/transactions
```
**Query:** `type` (string), `status` (string), `user_id` (int), `from_date` (date), `to_date` (date), `per_page` (int)

### Detail saldo user
```
GET /api/admin/balance/users/{user_id}
```

### History transaksi user
```
GET /api/admin/balance/users/{user_id}/history
```
**Query:** `type` (string), `status` (string), `per_page` (int)

### Refund saldo
```
POST /api/admin/balance/users/{user_id}/refund
```
**Body (JSON):**
```json
{
  "amount": 50000,
  "idempotency_key": "refund-order-12-v1",
  "reference_type": "order",         // opsional
  "reference_id": 12,                // opsional
  "description": "Refund pesanan batal" // opsional
}
```

`idempotency_key` wajib dan harus dibuat unik oleh client untuk satu aksi refund. Retry dengan key dan nominal yang sama mengembalikan transaksi yang sama tanpa menambah saldo dua kali. Jangan membuat key baru ketika hanya mengulang request akibat timeout.

### Adjustment saldo
```
POST /api/admin/balance/users/{user_id}/adjust
```
**Body (JSON):**
```json
{
  "amount": -10000,                  // bisa negatif (potong) / positif (tambah)
  "description": "Koreksi saldo"      // wajib
}
```

---

## 16. Shipments (`shipments`)

### List
```
GET /api/admin/shipments
```
**Query:** `status` (string), `courier_user_id` (int), `search` (string), `per_page` (int)

---

## 17. Apotek (`apotiks`)

### List apotek
```
GET /api/admin/apotiks
```
**Query:** `search` (string), `is_active` (bool), `per_page` (int)

---

## 18. Reports (`reports`)

### Laporan Orders
```
GET /api/admin/reports/orders
```
**Query:** `status` (string), `patient_user_id` (int), `pharmacy_id` (int), `from` (date), `to` (date)

### Laporan Customers
```
GET /api/admin/reports/customers
```
**Query:** `from` (date), `to` (date)

### Laporan Profit & Loss
```
GET /api/admin/reports/profit-loss
```
**Query:** `from` (date), `to` (date)

---

## 19. Journals / Jurnal Keuangan (`journals`)

### List
```
GET /api/admin/journals
```
**Query:** `from` (date), `to` (date), `status` (`draft`|`posted`|`void`)

### Detail
```
GET /api/admin/journals/{id}
```
Response menyertakan `totals` (debit/credit) dan `is_balanced`.

### Create (draft)
```
POST /api/admin/journals
```
**Body (JSON):**
```json
{
  "entry_date": "2026-07-09",
  "description": "Penyesuaian akhir bulan",
  "reference_type": "order",     // opsional
  "reference_id": 42,            // opsional
  "lines": [
    {
      "account_code": "1-1000",
      "account_name": "Kas",
      "line_description": "Penerimaan",
      "debit": 100000,
      "credit": 0
    },
    {
      "account_code": "4-2000",
      "account_name": "Pendapatan",
      "debit": 0,
      "credit": 100000
    }
  ]
}
```
> `lines` minimal 2 baris dan **total debit = total credit** (422 bila tidak balance).

### Post (ubah draft → posted)
```
POST /api/admin/journals/{id}/post
```
**Body:** kosong. Hanya journal `draft` yang bisa di-post.

---

## 20. Registrations (`registrations`)

### List pendaftaran mitra & apotek
```
GET /api/admin/registrations/mitra
```
**Query:** `type` (`all`|`mitra`|`apotik`), `is_available`/`is_active` (bool), `search` (string), `per_page` (int)

---

## 21. Pengaturan Biaya Service Booking (`service-booking-fees`)

### Ambil kebijakan aktif

```http
GET /api/admin/service-booking-fees
```

### Ubah kebijakan

```http
PUT /api/admin/service-booking-fees
```

```json
{
  "transport_distance_threshold_km": 10,
  "transport_fee_per_visit": 25000,
  "hospital_meal_fee_per_visit": 15000,
  "is_active": true
}
```

Semua nominal menggunakan Rupiah. Transport dikenakan per visit hanya untuk booking recurring non-live-in dengan jarak lebih besar dari ambang. Uang makan dikenakan per visit jika lokasi rumah sakit. Perubahan setting hanya berlaku untuk booking baru karena booking menyimpan `fee_policy_snapshot`.

Validasi: ambang 0–1000 km, seluruh nominal minimal 0, dan `is_active` boolean. Endpoint ini membutuhkan token admin.

## 22. Contoh Integrasi Nuxt (`$fetch`)

```ts
// composables/useAdminApi.ts
const config = useRuntimeConfig()
const BASE = `${config.public.apiBase}/api/admin`

export const useAdminApi = () => {
  const headers = {
    Authorization: `Bearer ${useCookie('admin_token').value}`,
    Accept: 'application/json',
  }

  return {
    // List services
    getServices: (params: Record<string, any>) =>
      $fetch(`${BASE}/services`, { headers, query: params }),

    // Create service (dengan gambar -> multipart)
    createService: (formData: FormData) =>
      $fetch(`${BASE}/services`, {
        method: 'POST',
        headers: { Authorization: headers.Authorization },
        body: formData,
      }),

    // Update service
    updateService: (id: number, payload: Record<string, any>) =>
      $fetch(`${BASE}/services/${id}`, {
        method: 'PATCH',
        headers,
        body: payload,
      }),

    // Login
    login: (payload: { email: string; password: string }) =>
      $fetch(`${config.public.apiBase}/api/admin/login`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: payload,
      }),
  }
}
```

> Catatan: untuk endpoint upload file (service `image`), jangan set `Content-Type` manual — biarkan browser/`$fetch` mengisi `multipart/form-data` boundary otomatis dari `FormData`.
