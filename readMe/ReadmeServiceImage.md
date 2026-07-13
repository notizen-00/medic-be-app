# 🆕 Update — Service Image Support

## Service Image

Master layanan (`services`) kini mendukung gambar/icon sehingga aplikasi mobile maupun admin panel dapat menampilkan ilustrasi pada setiap layanan.

### Database

Migration baru:

```bash
php artisan make:migration add_image_to_services_table --table=services
```

Field yang ditambahkan:

| Field | Type   | Nullable | Keterangan                                     |
| ----- | ------ | -------- | ---------------------------------------------- |
| image | string | ✅       | Path gambar service yang disimpan pada storage |

Jalankan migration:

```bash
php artisan migrate
php artisan storage:link
```

---

## Upload Image

Endpoint admin yang sudah mendukung upload gambar:

```
POST   /api/admin/services
POST   /api/admin/services/{id} (update dengan file)
PATCH  /api/admin/services/{id} (update JSON/tanpa file)
```

Gunakan `multipart/form-data`.

### Request

| Field        | Type    | Keterangan                        |
| ------------ | ------- | --------------------------------- |
| image        | file    | jpg, jpeg, png, webp (maks. 2 MB) |
| remove_image | boolean | Menghapus gambar lama saat update |

Contoh:

```
Content-Type: multipart/form-data

name=Pasang Infus
base_price=150000
service_type=procedure
image=(file)
```

---

## Response

Setiap endpoint service akan mengembalikan informasi gambar.

Contoh:

```json
{
    "id": 1,
    "name": "Pasang Infus",
    "image": "services/abc123.webp",
    "image_url": "https://backend.perawatku.tech/storage/services/abc123.webp"
}
```

Keterangan:

- `image` merupakan path yang tersimpan di database.
- `image_url` merupakan URL siap digunakan oleh aplikasi Flutter, Nuxt, maupun frontend lainnya.

---

## Behaviour

### Create

- Upload gambar baru.
- Gambar disimpan pada:

```
storage/app/public/services
```

### Update

- Jika upload gambar baru, gambar lama otomatis dihapus.
- Jika `remove_image=true`, gambar lama dihapus dan field `image` menjadi `NULL`.
- Jika tidak mengirim field gambar, gambar sebelumnya tetap dipertahankan.

---

## Storage Structure

```
storage/
└── app/
    └── public/
        └── services/
            ├── infus.webp
            ├── kateter.jpg
            └── fisioterapi.png
```

Public URL:

```
/storage/services/{filename}
```

---

## Flutter Integration

Contoh penggunaan:

```dart
Image.network(service.imageUrl)
```

atau

```dart
CachedNetworkImage(
  imageUrl: service.imageUrl,
)
```

Frontend tidak perlu membangun URL secara manual karena backend telah menyediakan field `image_url`.

### Update gambar dari Flutter

Gunakan `MultipartRequest('POST', ...)`, bukan multipart PATCH:

```dart
final request = http.MultipartRequest(
  'POST',
  Uri.parse('$baseUrl/api/admin/services/$serviceId'),
)
  ..headers['Authorization'] = 'Bearer $token'
  ..headers['Accept'] = 'application/json'
  ..files.add(await http.MultipartFile.fromPath('image', imageFile.path));

final response = await request.send();
```

Field harus bernama tepat `image`. Jangan mengirim `image_url`, path lokal, hasil `XFile.toString()`, atau object JSON sebagai nilai `image`.
