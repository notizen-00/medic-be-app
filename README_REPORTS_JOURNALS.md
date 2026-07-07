# Reports & Journals (Admin API)

Semua endpoint di bawah ini ada di group `auth:sanctum` + `admin` middleware.

## Reports

### Orders report

`GET /api/admin/reports/orders`

Query params (opsional):
- `from` (date)
- `to` (date)
- `status`
- `patient_user_id`
- `pharmacy_id`

### Customers report (ringkas)

`GET /api/admin/reports/customers`

Query params (opsional): `from`, `to`

### Profit & Loss (basic)

`GET /api/admin/reports/profit-loss`

Query params (opsional): `from`, `to`

Catatan:
- COGS order dihitung dari snapshot di `order_items` (`unit_cost` / `total_cost`) saat checkout.
- Untuk service booking, `markup_amount` dianggap revenue platform.

## Journals (Operasional Keuangan)

### List

`GET /api/admin/journals?from=YYYY-MM-DD&to=YYYY-MM-DD&status=draft|posted|void`

### Create draft entry

`POST /api/admin/journals`

Body example:
```json
{
  "entry_date": "2026-05-23",
  "description": "Pembayaran service booking #SB-001",
  "reference_type": "service_booking",
  "reference_id": 1,
  "lines": [
    { "account_code": "1101", "account_name": "Kas", "debit": 100000, "credit": 0 },
    { "account_code": "4101", "account_name": "Pendapatan Jasa", "debit": 0, "credit": 100000 }
  ]
}
```

Validasi:
- Minimal 2 lines
- Total debit harus sama dengan total credit

### Post entry

`POST /api/admin/journals/{journalEntry}/post`
