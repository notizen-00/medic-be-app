<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard Mitra</title>
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
            background: #f6f8fb;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid #dfe5ee;
            background: #ffffff;
        }

        main {
            display: grid;
            grid-template-columns: 320px 1fr;
            min-height: calc(100vh - 66px);
        }

        aside, section {
            padding: 16px;
        }

        aside {
            border-right: 1px solid #dfe5ee;
            background: #ffffff;
        }

        h1, h2, h3, p {
            margin-top: 0;
            letter-spacing: 0;
        }

        h1 {
            margin-bottom: 0;
            font-size: 20px;
        }

        h2 {
            font-size: 16px;
        }

        h3 {
            font-size: 14px;
            margin-bottom: 6px;
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
            resize: vertical;
        }

        button {
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

        button.danger {
            border-color: #dc2626;
            background: #dc2626;
        }

        button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .login-card {
            max-width: 420px;
            margin: 32px auto;
            padding: 18px;
            border: 1px solid #dfe5ee;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 16px 40px rgba(26, 38, 62, 0.08);
        }

        .app {
            display: none;
        }

        .app.active {
            display: grid;
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

        .status.online {
            background: #ccfbf1;
            color: #0f766e;
        }

        .muted {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .toolbar {
            display: grid;
            grid-template-columns: 1fr 120px;
            gap: 8px;
            margin-bottom: 12px;
        }

        .consultation-list {
            display: grid;
            gap: 10px;
        }

        .consultation-item {
            text-align: left;
            padding: 12px;
            border: 1px solid #dfe5ee;
            border-radius: 8px;
            background: #fbfcfe;
            color: #172033;
        }

        .consultation-item.active {
            border-color: #0f766e;
            background: #f0fdfa;
        }

        .badge {
            display: inline-flex;
            padding: 3px 7px;
            border-radius: 999px;
            background: #eef2f7;
            color: #46536a;
            font-size: 12px;
            font-weight: 700;
        }

        .detail {
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 12px;
            height: calc(100vh - 98px);
        }

        .detail-card {
            padding: 14px;
            border: 1px solid #dfe5ee;
            border-radius: 8px;
            background: #ffffff;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .actions button {
            width: auto;
        }

        .messages {
            overflow: auto;
            display: grid;
            align-content: start;
            gap: 10px;
            padding: 14px;
            border: 1px solid #dfe5ee;
            border-radius: 8px;
            background: #ffffff;
        }

        .message {
            max-width: 76%;
            padding: 10px 12px;
            border-radius: 8px;
            background: #eef2f7;
        }

        .message.mine {
            justify-self: end;
            background: #ccfbf1;
        }

        .message b {
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
        }

        .composer {
            display: grid;
            grid-template-columns: 1fr 120px;
            gap: 8px;
        }

        .log {
            max-height: 120px;
            overflow: auto;
            margin-top: 12px;
            padding: 10px;
            border-radius: 8px;
            background: #0f172a;
            color: #dbeafe;
            font-size: 12px;
            white-space: pre-wrap;
        }

        @media (max-width: 860px) {
            main.app.active {
                grid-template-columns: 1fr;
            }

            aside {
                border-right: 0;
                border-bottom: 1px solid #dfe5ee;
            }

            .detail {
                height: auto;
                min-height: 70vh;
            }
        }
    </style>
</head>
<body>
<div id="login-card" class="login-card">
    <h1>Dashboard Mitra</h1>
    <p class="muted">Login untuk menerima konsultasi dan chat pasien.</p>

    <form id="login-form">
        <label for="email">Email mitra</label>
        <input id="email" name="email" type="email" autocomplete="username" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <button id="login-button" type="submit" style="margin-top:14px;">Login</button>
    </form>

    <pre id="login-log" class="log"></pre>
</div>

<main id="app" class="app">
    <aside>
        <h2>Konsultasi</h2>
        <p id="profile" class="muted">Belum login.</p>

        <div class="toolbar">
            <select id="status-filter">
                <option value="">Semua</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="ongoing">Ongoing</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <button id="refresh-button" type="button" class="secondary">Refresh</button>
        </div>

        <div id="consultation-list" class="consultation-list">
            <span class="muted">Belum ada data.</span>
        </div>
    </aside>

    <section class="detail">
        <header style="padding:0;border:0;background:transparent;">
            <div>
                <h1 id="detail-title">Pilih konsultasi</h1>
                <p id="detail-subtitle" class="muted">Detail dan chat akan tampil di sini.</p>
            </div>
            <span id="connection-status" class="status">Idle</span>
        </header>

        <div>
            <div id="detail-card" class="detail-card">
                <p class="muted">Pilih salah satu konsultasi dari daftar.</p>
            </div>
            <div id="messages" class="messages" style="margin-top:12px;">
                <span class="muted">Belum ada chat.</span>
            </div>
        </div>

        <form id="message-form" class="composer">
            <textarea id="message-input" rows="2" placeholder="Tulis pesan..." disabled></textarea>
            <button id="send-button" type="submit" disabled>Kirim</button>
        </form>
    </section>
</main>

<script>
    const reverbKey = @json($reverbKey);
    const reverbHost = @json($reverbHost);
    const reverbPort = Number(@json($reverbPort));
    const reverbScheme = @json($reverbScheme);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const loginCard = document.querySelector('#login-card');
    const app = document.querySelector('#app');
    const loginForm = document.querySelector('#login-form');
    const loginButton = document.querySelector('#login-button');
    const loginLog = document.querySelector('#login-log');
    const profileEl = document.querySelector('#profile');
    const statusFilter = document.querySelector('#status-filter');
    const refreshButton = document.querySelector('#refresh-button');
    const listEl = document.querySelector('#consultation-list');
    const detailTitle = document.querySelector('#detail-title');
    const detailSubtitle = document.querySelector('#detail-subtitle');
    const detailCard = document.querySelector('#detail-card');
    const messagesEl = document.querySelector('#messages');
    const messageForm = document.querySelector('#message-form');
    const messageInput = document.querySelector('#message-input');
    const sendButton = document.querySelector('#send-button');
    const connectionStatus = document.querySelector('#connection-status');

    let token = null;
    let user = null;
    let pusher = null;
    let activeConsultation = null;
    let activeChannel = null;
    let consultations = [];
    let renderedMessageIds = new Set();

    function writeLog(message, payload = null) {
        const detail = payload ? `\n${JSON.stringify(payload, null, 2)}` : '';
        loginLog.textContent = `[${new Date().toLocaleTimeString()}] ${message}${detail}\n\n${loginLog.textContent}`;
    }

    function apiHeaders() {
        return {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        };
    }

    async function apiFetch(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                ...apiHeaders(),
                ...(options.headers || {}),
            },
        });
        const payload = await response.json();

        if (!response.ok) {
            throw payload;
        }

        return payload;
    }

    function setConnectionStatus(label, online = false) {
        connectionStatus.textContent = label;
        connectionStatus.classList.toggle('online', online);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function bootPusher() {
        if (pusher) {
            pusher.disconnect();
        }

        const useTls = reverbScheme === 'https';
        Pusher.logToConsole = false;

        pusher = new Pusher(reverbKey, {
            cluster: 'mt1',
            encrypted: useTls,
            wsHost: reverbHost,
            wsPort: reverbPort,
            wssPort: reverbPort,
            forceTLS: useTls,
            enabledTransports: ['ws', 'wss'],
            enableStats: false,
            authEndpoint: '/api/broadcasting/auth',
            auth: {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
            authorizer: (channel) => ({
                authorize: (socketId, callback) => authorizeChannel(socketId, channel.name, callback),
            }),
        });

        pusher.connection.bind('state_change', (states) => {
            setConnectionStatus(states.current, states.current === 'connected');
        });

        pusher.connection.bind('connected', () => {
            setConnectionStatus('connected', true);
        });

        pusher.connection.bind('error', (error) => {
            setConnectionStatus('error', false);
            writeLog('Reverb error.', error);
        });
    }

    async function authorizeChannel(socketId, channelName, callback) {
        try {
            const response = await fetch('/api/broadcasting/auth', {
                method: 'POST',
                headers: apiHeaders(),
                body: JSON.stringify({
                    socket_id: socketId,
                    channel_name: channelName,
                }),
            });
            const payload = await response.json();

            if (!response.ok) {
                throw payload;
            }

            callback(null, payload);
        } catch (error) {
            writeLog('Channel auth failed.', error);
            callback(error, null);
        }
    }

    async function loadConsultations() {
        listEl.innerHTML = '<span class="muted">Memuat konsultasi...</span>';
        const params = new URLSearchParams({ per_page: '50' });

        if (statusFilter.value) {
            params.set('status', statusFilter.value);
        }

        try {
            const payload = await apiFetch(`/api/mitra/consultations?${params.toString()}`);
            consultations = payload.data.data || [];
            renderConsultations();
        } catch (error) {
            listEl.innerHTML = '<span class="muted">Gagal memuat konsultasi.</span>';
            writeLog('Load consultations failed.', error);
        }
    }

    function renderConsultations() {
        if (consultations.length === 0) {
            listEl.innerHTML = '<span class="muted">Belum ada konsultasi.</span>';
            return;
        }

        listEl.innerHTML = consultations.map((consultation) => `
            <button type="button" class="consultation-item ${activeConsultation?.id === consultation.id ? 'active' : ''}" data-id="${consultation.id}">
                <h3>${escapeHtml(consultation.consultation_code || `Konsultasi #${consultation.id}`)}</h3>
                <div class="muted">${escapeHtml(consultation.patient?.name || '-')}</div>
                <div style="margin-top:8px;">
                    <span class="badge">${escapeHtml(consultation.status)}</span>
                    <span class="badge">${escapeHtml(consultation.service_type)}</span>
                </div>
            </button>
        `).join('');

        listEl.querySelectorAll('[data-id]').forEach((button) => {
            button.addEventListener('click', () => openConsultation(Number(button.dataset.id)));
        });
    }

    async function openConsultation(id) {
        try {
            const payload = await apiFetch(`/api/mitra/consultations/${id}`);
            activeConsultation = payload.data;
            renderConsultationDetail();
            subscribeConsultationChat(activeConsultation.id);
            renderConsultations();
        } catch (error) {
            writeLog('Open consultation failed.', error);
        }
    }

    function renderConsultationDetail() {
        const c = activeConsultation;
        detailTitle.textContent = c.consultation_code || `Konsultasi #${c.id}`;
        detailSubtitle.textContent = `${c.patient?.name || '-'} - ${c.status}`;

        detailCard.innerHTML = `
            <div class="actions">
                <button type="button" data-status="confirmed" ${c.status !== 'pending' ? 'disabled' : ''}>Terima</button>
                <button type="button" data-status="ongoing" ${!['confirmed', 'pending'].includes(c.status) ? 'disabled' : ''}>Mulai</button>
                <button type="button" data-status="completed" ${!['ongoing', 'confirmed'].includes(c.status) ? 'disabled' : ''}>Selesai</button>
                <button type="button" class="danger" data-status="cancelled" ${['completed', 'cancelled'].includes(c.status) ? 'disabled' : ''}>Batalkan</button>
            </div>
            <p style="margin-top:12px;"><b>Keluhan:</b> ${escapeHtml(c.complaint || '-')}</p>
            <p><b>Catatan:</b> ${escapeHtml(c.notes || '-')}</p>
            <p><b>Diagnosis:</b> ${escapeHtml(c.diagnosis || '-')}</p>
        `;

        detailCard.querySelectorAll('[data-status]').forEach((button) => {
            button.addEventListener('click', () => updateConsultationStatus(button.dataset.status));
        });

        renderMessages(c.messages || []);
        messageInput.disabled = false;
        sendButton.disabled = false;
    }

    function renderMessages(messages) {
        renderedMessageIds = new Set();

        if (!messages.length) {
            messagesEl.innerHTML = '<span class="muted">Belum ada chat.</span>';
            return;
        }

        messages.forEach((message) => renderedMessageIds.add(Number(message.id)));
        messagesEl.innerHTML = messages.map((message) => messageHtml(message)).join('');
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendMessage(message) {
        if (message.id && renderedMessageIds.has(Number(message.id))) {
            return;
        }

        if (message.id) {
            renderedMessageIds.add(Number(message.id));
        }

        if (messagesEl.querySelector('.muted')) {
            messagesEl.innerHTML = '';
        }

        messagesEl.insertAdjacentHTML('beforeend', messageHtml(message));
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function messageHtml(message) {
        const mine = Number(message.sender_user_id) === Number(user.id);
        const senderName = message.sender?.name || (mine ? user.name : 'Pasien');

        return `
            <div class="message ${mine ? 'mine' : ''}">
                <b>${escapeHtml(senderName)}</b>
                <div>${escapeHtml(message.message || '')}</div>
            </div>
        `;
    }

    function subscribeConsultationChat(consultationId) {
        if (!pusher) {
            return;
        }

        if (activeChannel) {
            pusher.unsubscribe(activeChannel.name);
            activeChannel = null;
        }

        const channelName = `private-consultation.${consultationId}`;
        activeChannel = pusher.subscribe(channelName);

        activeChannel.bind('pusher:subscription_succeeded', () => {
            writeLog(`Subscribed to ${channelName}.`);
        });

        activeChannel.bind('pusher:subscription_error', (error) => {
            writeLog('Chat subscription failed.', error);
        });

        activeChannel.bind('chat.message.created', (payload) => {
            if (Number(payload.consultation_id) !== Number(activeConsultation?.id)) {
                return;
            }

            appendMessage(payload);
        });
    }

    async function updateConsultationStatus(status) {
        if (!activeConsultation) {
            return;
        }

        try {
            const payload = await apiFetch(`/api/mitra/consultations/${activeConsultation.id}/status`, {
                method: 'PATCH',
                body: JSON.stringify({ status }),
            });
            activeConsultation = {
                ...activeConsultation,
                ...payload.data,
                messages: activeConsultation.messages || [],
            };
            renderConsultationDetail();
            await loadConsultations();
        } catch (error) {
            writeLog('Update status failed.', error);
        }
    }

    async function sendMessage() {
        const message = messageInput.value.trim();

        if (!message || !activeConsultation) {
            return;
        }

        messageInput.value = '';
        sendButton.disabled = true;

        try {
            const payload = await apiFetch(`/api/mitra/consultations/${activeConsultation.id}/messages`, {
                method: 'POST',
                body: JSON.stringify({
                    message_type: 'text',
                    message,
                }),
            });

            appendMessage(payload.data);
        } catch (error) {
            messageInput.value = message;
            writeLog('Send message failed.', error);
        } finally {
            sendButton.disabled = false;
        }
    }

    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        loginButton.disabled = true;

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
            profileEl.textContent = `${user.name} (${user.email})`;
            loginCard.style.display = 'none';
            app.classList.add('active');
            bootPusher();
            await loadConsultations();
        } catch (error) {
            writeLog('Login failed.', error);
        } finally {
            loginButton.disabled = false;
        }
    });

    refreshButton.addEventListener('click', loadConsultations);
    statusFilter.addEventListener('change', loadConsultations);
    messageForm.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage();
    });
</script>
</body>
</html>
