# WebSocket Chat Realtime dengan Laravel Reverb

## Overview

Implementasi WebSocket untuk fitur chat realtime antara patient dan dokter menggunakan Laravel Reverb.

## Fitur

- **Real-time Messaging**: Pesan chat dikirim dan diterima secara realtime
- **Private Channels**: Hanya patient dan dokter terkait yang bisa akses chat consultation tertentu
- **Online Status**: Tracking status online/offline user
- **Message Read Status**: Update status baca pesan secara realtime
- **Matchmaking Mitra**: Notifikasi booking layanan dikirim realtime ke mitra yang terpilih
- **Secure Authorization**: Setiap user harus diotorisasi sebelum bisa subscribe channel

## Instalasi

### 1. Pastikan dependensi sudah terinstall

```bash
composer require laravel/reverb
```

### 2. Konfigurasi Environment

Tambahkan konfigurasi berikut ke file `.env`:

```env
BROADCAST_CONNECTION=reverb

# Reverb Configuration
REVERB_APP_ID=medic-app
REVERB_APP_KEY=medic-app-key
REVERB_APP_SECRET=medic-app-secret
REVERB_ALLOWED_ORIGINS=*
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Untuk broadcast dari app ke server Reverb
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

### 3. Jalankan Reverb Server

```bash
# Jalankan Reverb server
php artisan reverb:start

# Atau dengan production mode
php artisan reverb:start --host=0.0.0.0 --port=8080
```

### 4. Jalankan Laravel Server (tab terpisah)

```bash
php artisan serve
```

## Arsitektur

### Broadcast Channels

1. **`consultation.{id}`** - Private channel untuk setiap consultation
    - Hanya patient dan dokter yang terkait bisa subscribe
    - Digunakan untuk mengirim dan menerima pesan chat

2. **`user.{userId}.chat`** - Private channel untuk user
    - Untuk notifikasi personal ke user tertentu

3. **`online-users`** - Presence channel
    - Untuk tracking status online user
    - Halaman `/mitra/login` menampilkan daftar user online dari channel ini

4. **`partner.{partnerId}.service-bookings`** - Private channel untuk booking layanan mitra
    - Hanya akun mitra dengan ID yang sama yang bisa subscribe
    - Digunakan untuk notifikasi matchmaking saat pasien membuat booking layanan

### Events

**`App\Events\ChatMessageCreated`**

- Dipicu ketika pesan baru dibuat
- Broadcast ke private channel `consultation.{id}`
- Mengirim data pesan lengkap termasuk info pengirim

**`App\Events\ServiceBookingMatched`**

- Dipicu ketika pasien membuat service booking dan sistem memilih mitra
- Broadcast ke private channel `partner.{partnerId}.service-bookings`
- Event name: `.service-booking.matched`
- Payload berisi ringkasan booking, pasien, layanan, alamat, dan skor matchmaking

### Models

**`App\Models\ConsultationMessage`**

- Otomatis broadcast ketika pesan dibuat
- Method `markAsRead()` untuk menandai pesan sudah dibaca

## API Endpoints

### Authorization Endpoint

```
POST /api/broadcasting/auth
Headers: Authorization: Bearer {token}
Body: channel, socket_id
```

### Send Message (Existing)

```
POST /api/patient/consultations/{consultation}/messages
POST /api/mitra/consultations/{consultation}/messages
```

### Accept Service Booking

```
PATCH /api/mitra/service-bookings/{serviceBooking}/accept
Headers: Authorization: Bearer {token}
Body: { "notes": "Accepted from mitra web test." }
```

## Frontend Integration

### 1. Install Pusher JS (compatibility dengan Reverb)

```bash
npm install pusher-js
```

### 2. Konfigurasi JavaScript

```javascript
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "https") === "https",
    enabledTransports: ["ws", "wss"],
    authEndpoint: "/api/broadcasting/auth",
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem("token")}`,
        },
    },
});
```

### 3. Subscribe ke Channel dan Listen Events

```javascript
// Subscribe ke consultation channel
const consultationId = 1;

Echo.private(`consultation.${consultationId}`)
    .listen(".chat.message.created", (e) => {
        // Pesan baru diterima
        console.log("New message:", e);
        // Tampilkan pesan di chat UI
        displayMessage(e);
    })
    .listen(".App\\Events\\ChatMessageCreated", (e) => {
        // Alternative listener dengan nama event lengkap
        console.log("Message created:", e);
    });
```

### 3b. Subscribe Channel Booking Mitra

```javascript
const partnerId = currentUser.id;

Echo.private(`partner.${partnerId}.service-bookings`)
    .listen(".service-booking.matched", (event) => {
        console.log("Booking matched:", event.booking, event.matchmaking);
    });
```

### 4. Kirim Pesan dengan Update Realtime

```javascript
// Kirim pesan biasa (otomatis broadcast via event)
axios.post(`/api/patient/consultations/${consultationId}/messages`, {
    message: "Hello doctor",
    message_type: "text",
});

// Tambahkan ke UI lokal untuk instant feedback
addMessageToUI({
    message: "Hello doctor",
    sender: currentUser,
    created_at: new Date().toISOString(),
});
```

### 5. Track Online Status

```javascript
// Join presence channel untuk online status
Echo.join("online-users")
    .here((users) => {
        // User online saat join
        updateOnlineUsers(users);
    })
    .joining((user) => {
        // User baru online
        userJoined(user);
    })
    .leaving((user) => {
        // User offline
        userLeft(user);
    })
    .error((error) => {
        console.error("Presence channel error:", error);
    });
```

## Environment Variables untuk Frontend

Tambahkan ke file `.env` frontend (misalnya `.env` Vite):

```env
VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}
VITE_REVERB_HOST=${VITE_REVERB_HOST:-localhost}
VITE_REVERB_PORT=${VITE_REVERB_PORT:-8080}
VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME:-http}
```

## Keamanan

1. **Authorization Channel**: Setiap channel memiliki callback authorization
2. **Private Channels**: Pesan hanya bisa diterima oleh authorized users
3. **Sanctum Authentication**: Menggunakan token untuk autentikasi
4. **Input Validation**: Validasi pesan sebelum disimpan dan dibroadcast

## Troubleshooting

### WebSocket tidak terhubung

1. Pastikan Reverb server sedang berjalan
2. Cek CORS configuration
3. Periksa console browser untuk error

### Pesan tidak muncul realtime

1. Pastikan event implementing `ShouldBroadcast`
2. Cek channel authorization
3. Verifikasi Broadcast::channel di `routes/channels.php`

### "Channel not authorized"

1. Pastikan user sudah login dengan token valid
2. Verifikasi user terkait dengan consultation
3. Periksa konfigurasi authorization di `routes/channels.php`

## File Struktur

```
app/
├── Events/
│   └── ChatMessageCreated.php
├── Models/
│   └── ConsultationMessage.php (updated)
routes/
├── channels.php (created)
└── api.php (updated)
bootstrap/
└── app.php (updated)
config/
└── reverb.php (created)
.env (updated)
```

## Testing

### Manual Testing

#### Test Matchmaking Mitra

1. Jalankan Laravel app dan Reverb.
2. Buka `http://localhost:8081/mitra/login` atau `https://domain-kamu/mitra/login`.
3. Login sebagai akun mitra.
4. Pastikan status koneksi berubah menjadi `subscribed`.
5. Pastikan panel `Online` menampilkan akun yang tersambung ke presence channel `online-users`.
6. Login sebagai pasien dari aplikasi/API.
7. Buat booking melalui `POST /api/patient/service-bookings`.
8. Jika matchmaking memilih mitra yang sedang login di halaman test, kartu booking akan muncul realtime.
9. Klik `Accept` untuk memanggil endpoint `PATCH /api/mitra/service-bookings/{id}/accept`.

Catatan halaman test:
- Tombol `Test Raw WS` hanya untuk memastikan proxy WebSocket sudah bisa handshake ke Reverb.
- Log `pusher:connection_established` atau status HTTP `101 Switching Protocols` berarti koneksi WebSocket sudah sampai ke Reverb.
- Subscribe private channel dan presence channel tetap membutuhkan `POST /api/broadcasting/auth` dengan Bearer token.

#### Test Chat Consultation

1. Login sebagai patient
2. Buat consultation baru
3. Login sebagai dokter (tab berbeda)
4. Subscribe ke channel yang sama
5. Kirim pesan dari salah satu sisi
6. Verifikasi pesan muncul realtime di sisi lain

### Unit Testing

```php
use App\Events\ChatMessageCreated;
use App\Models\ConsultationMessage;

class ChatMessageCreatedTest extends TestCase
{
    public function test_message_broadcasts_to_correct_channel()
    {
        $consultation = Consultation::factory()->create();
        $message = ConsultationMessage::factory()->create([
            'consultation_id' => $consultation->id,
        ]);

        $event = new ChatMessageCreated($message);

        $this->assertContains(
            "private-consultation.{$consultation->id}",
            array_map(fn($channel) => $channel->name, $event->broadcastOn())
        );
    }
}
```
