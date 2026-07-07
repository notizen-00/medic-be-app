<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mitra Reverb Test</title>
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f6f8fb;
            color: #172033;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        main {
            width: min(980px, 100%);
            display: grid;
            grid-template-columns: minmax(280px, 360px) 1fr;
            gap: 18px;
        }

        section {
            background: #ffffff;
            border: 1px solid #dfe5ee;
            border-radius: 8px;
            padding: 18px;
            box-shadow: 0 16px 40px rgba(26, 38, 62, 0.08);
        }

        h1, h2 {
            margin: 0 0 14px;
            letter-spacing: 0;
        }

        h1 {
            font-size: 22px;
        }

        h2 {
            font-size: 16px;
        }

        label {
            display: block;
            margin: 12px 0 6px;
            font-size: 13px;
            font-weight: 650;
            color: #46536a;
        }

        input, select, button, textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font: inherit;
        }

        input, select, textarea {
            padding: 10px 11px;
            background: #ffffff;
            color: #172033;
        }

        textarea {
            min-height: 86px;
            resize: vertical;
        }

        button {
            margin-top: 14px;
            padding: 10px 12px;
            border-color: #0f766e;
            background: #0f766e;
            color: #ffffff;
            font-weight: 700;
            cursor: pointer;
        }

        button.secondary {
            border-color: #cbd5e1;
            background: #ffffff;
            color: #172033;
        }

        button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 110px 110px;
            gap: 8px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 9px;
            border-radius: 999px;
            background: #eef2f7;
            color: #46536a;
            font-size: 13px;
            font-weight: 700;
        }

        .dot {
            width: 9px;
            height: 9px;
            border-radius: 99px;
            background: #94a3b8;
        }

        .status.online .dot {
            background: #14b8a6;
        }

        .muted {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .booking {
            border: 1px solid #dfe5ee;
            border-radius: 8px;
            padding: 14px;
            margin-top: 12px;
            background: #fbfcfe;
        }

        .booking strong {
            display: block;
            margin-bottom: 6px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 8px 0;
            color: #46536a;
            font-size: 13px;
        }

        pre {
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 220px;
            overflow: auto;
            margin: 12px 0 0;
            padding: 12px;
            border-radius: 8px;
            background: #0f172a;
            color: #dbeafe;
            font-size: 12px;
        }

        @media (max-width: 820px) {
            main {
                grid-template-columns: 1fr;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main>
    <section>
        <h1>Mitra Reverb Test</h1>
        <p class="muted">Login sebagai mitra, lalu halaman ini akan menunggu order pasien yang match ke akun tersebut.</p>

        <form id="login-form">
            <label for="email">Email mitra</label>
            <input id="email" name="email" type="email" autocomplete="username" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <label>Koneksi Reverb</label>
            <div class="grid">
                <input id="ws-host" value="{{ $reverbHost }}" aria-label="WebSocket host">
                <input id="ws-port" value="{{ $reverbPort }}" aria-label="WebSocket port">
                <select id="ws-scheme" aria-label="WebSocket scheme">
                    <option value="http" @selected($reverbScheme === 'http')>ws</option>
                    <option value="https" @selected($reverbScheme === 'https')>wss</option>
                </select>
            </div>

            <button id="login-button" type="submit">Login dan Listen</button>
        </form>

        <button id="disconnect-button" class="secondary" type="button" disabled>Disconnect</button>
    </section>

    <section>
        <div class="row">
            <h2>Matchmaking</h2>
            <span id="connection-status" class="status"><span class="dot"></span><span>Idle</span></span>
        </div>

        <p id="profile" class="muted">Belum login.</p>
        <div id="bookings"></div>

        <h2 style="margin-top: 18px;">Log</h2>
        <pre id="log"></pre>
    </section>
</main>

<script>
    const reverbKey = @json($reverbKey);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const form = document.querySelector('#login-form');
    const loginButton = document.querySelector('#login-button');
    const disconnectButton = document.querySelector('#disconnect-button');
    const statusEl = document.querySelector('#connection-status');
    const profileEl = document.querySelector('#profile');
    const bookingsEl = document.querySelector('#bookings');
    const logEl = document.querySelector('#log');

    let pusher = null;
    let probeSocket = null;
    let token = null;
    let user = null;

    function writeLog(message, payload = null) {
        const time = new Date().toLocaleTimeString();
        const detail = payload ? `\n${JSON.stringify(payload, null, 2)}` : '';
        logEl.textContent = `[${time}] ${message}${detail}\n\n${logEl.textContent}`;
    }

    function setStatus(label, online = false) {
        statusEl.classList.toggle('online', online);
        statusEl.querySelector('span:last-child').textContent = label;
    }

    function renderBooking(payload) {
        const booking = payload.booking || payload;
        const item = document.createElement('div');
        item.className = 'booking';
        item.innerHTML = `
            <strong>${booking.booking_code || 'Booking #' + booking.id}</strong>
            <div class="row"><span>Layanan</span><b>${booking.service?.name || '-'}</b></div>
            <div class="row"><span>Pasien</span><b>${booking.patient?.name || '-'}</b></div>
            <div class="row"><span>Status</span><b>${booking.status || '-'}</b></div>
            <div class="row"><span>Total</span><b>${booking.total_amount || '-'}</b></div>
            <button type="button" data-accept="${booking.id}">Accept</button>
        `;
        bookingsEl.prepend(item);

        item.querySelector('[data-accept]').addEventListener('click', async (event) => {
            event.currentTarget.disabled = true;
            await acceptBooking(booking.id);
        });
    }

    async function acceptBooking(bookingId) {
        try {
            const response = await fetch(`/api/mitra/service-bookings/${bookingId}/accept`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    notes: 'Accepted from mitra web test.',
                }),
            });
            const payload = await response.json();

            if (!response.ok) {
                throw payload;
            }

            writeLog('Booking accepted.', payload);
        } catch (error) {
            writeLog('Accept booking failed.', error);
        }
    }

    function connect() {
        const host = document.querySelector('#ws-host').value.trim();
        const port = Number(document.querySelector('#ws-port').value.trim());
        const scheme = document.querySelector('#ws-scheme').value;
        const useTls = scheme === 'https';
        const wsProtocol = useTls ? 'wss' : 'ws';
        const wsUrl = `${wsProtocol}://${host}:${port}/app/${reverbKey}?protocol=7&client=mitra-test&version=1.0&flash=false`;

        disconnect();
        setStatus('Connecting', false);
        writeLog('Connecting to Reverb.', {
            url: wsUrl,
            host,
            port,
            scheme: wsProtocol,
        });

        probeWebSocket(wsUrl);

        if (typeof Pusher === 'undefined') {
            setStatus('Pusher missing', false);
            writeLog('Pusher JS belum ter-load. Cek koneksi browser ke CDN js.pusher.com.');
            return;
        }

        try {
            pusher = new Pusher(reverbKey, {
                cluster: 'mt1',
                encrypted: useTls,
                wsHost: host,
                wsPort: port,
                wssPort: port,
                forceTLS: useTls,
                enabledTransports: useTls ? ['wss'] : ['ws'],
                disableStats: true,
                authEndpoint: '/api/broadcasting/auth',
                auth: {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${token}`,
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            });
        } catch (error) {
            setStatus('Connect failed', false);
            writeLog('Failed to initialize Pusher.', error);
            return;
        }

        pusher.connection.bind('state_change', (states) => {
            setStatus(states.current, states.current === 'connected');
            writeLog(`Connection: ${states.previous} -> ${states.current}`);
        });

        pusher.connection.bind('error', (error) => {
            writeLog('Connection error.', error);
        });

        pusher.connection.bind('connected', () => {
            setStatus('connected', true);
            writeLog('WebSocket connected.');
        });

        pusher.connection.bind('unavailable', () => {
            setStatus('unavailable', false);
            writeLog('WebSocket unavailable. Check Reverb server and proxy /app/ upgrade config.');
        });

        pusher.connection.bind('failed', () => {
            setStatus('failed', false);
            writeLog('WebSocket failed. The browser could not establish a connection to Reverb.');
        });

        pusher.connection.bind('disconnected', () => {
            setStatus('disconnected', false);
            writeLog('WebSocket disconnected.');
        });

        const channelName = `private-partner.${user.id}.service-bookings`;
        const channel = pusher.subscribe(channelName);

        channel.bind('pusher:subscription_succeeded', () => {
            writeLog(`Subscribed to ${channelName}.`);
        });

        channel.bind('pusher:subscription_error', (error) => {
            writeLog('Subscription failed.', error);
        });

        channel.bind('service-booking.matched', (payload) => {
            writeLog('New matched booking received.', payload);
            renderBooking(payload);
        });

        disconnectButton.disabled = false;
    }

    function disconnect() {
        if (probeSocket) {
            probeSocket.close();
            probeSocket = null;
        }

        if (pusher) {
            pusher.disconnect();
            pusher = null;
        }
        disconnectButton.disabled = true;
        setStatus('Idle', false);
    }

    function probeWebSocket(url) {
        try {
            probeSocket = new WebSocket(url);
        } catch (error) {
            writeLog('Native WebSocket probe could not start.', error);
            return;
        }

        probeSocket.addEventListener('open', () => {
            writeLog('Native WebSocket probe connected. Proxy/TLS path is reachable.');
            probeSocket.close();
        });

        probeSocket.addEventListener('message', (event) => {
            writeLog('Native WebSocket probe received message.', event.data);
        });

        probeSocket.addEventListener('error', () => {
            writeLog('Native WebSocket probe error. Check browser Network tab for the ws request and server/proxy logs.');
        });

        probeSocket.addEventListener('close', (event) => {
            writeLog('Native WebSocket probe closed.', {
                code: event.code,
                reason: event.reason || null,
                wasClean: event.wasClean,
            });
            probeSocket = null;
        });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        loginButton.disabled = true;
        setStatus('Logging in', false);

        try {
            const response = await fetch('/api/mitra/login', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    email: document.querySelector('#email').value,
                    password: document.querySelector('#password').value,
                }),
            });
            const payload = await response.json();

            if (!response.ok) {
                throw payload;
            }

            token = payload.user_api_token;
            user = payload.data;
            profileEl.textContent = `${user.name} (${user.email}) - partner #${user.id}`;
            writeLog('Login berhasil.', { id: user.id, name: user.name, role: user.role });
            connect();
        } catch (error) {
            setStatus('Login failed', false);
            writeLog('Login gagal.', error);
        } finally {
            loginButton.disabled = false;
        }
    });

    disconnectButton.addEventListener('click', disconnect);
</script>
</body>
</html>
