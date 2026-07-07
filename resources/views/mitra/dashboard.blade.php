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
            background: #f4f7fb;
            color: #182235;
        }

        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #f4f7fb; }
        button, input, select, textarea { font: inherit; }
        button { border: 0; cursor: pointer; }
        button:disabled { cursor: not-allowed; opacity: .55; }
        h1, h2, h3, p { margin-top: 0; letter-spacing: 0; }

        .login-card {
            width: min(440px, calc(100vw - 32px));
            margin: 48px auto;
            padding: 22px;
            border: 1px solid #dfe7f1;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 20px 60px rgba(24, 34, 53, .1);
        }

        .login-card h1 { margin-bottom: 6px; font-size: 22px; }
        label { display: block; margin: 14px 0 6px; color: #475569; font-size: 13px; font-weight: 700; }
        input, select, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 7px; background: #fff; color: #182235; }
        input, select { height: 40px; padding: 0 11px; }
        textarea { min-height: 42px; padding: 10px 11px; resize: vertical; }

        .primary-button, .secondary-button, .danger-button, .ghost-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 38px;
            padding: 9px 12px;
            border-radius: 7px;
            font-weight: 750;
            white-space: nowrap;
        }

        .primary-button { background: #0f766e; color: #fff; }
        .secondary-button { border: 1px solid #cbd5e1; background: #fff; color: #182235; }
        .danger-button { background: #dc2626; color: #fff; }
        .ghost-button { background: transparent; color: #475569; }

        .app { display: none; min-height: 100vh; }
        .app.active { display: grid; grid-template-columns: 260px minmax(0, 1fr); }

        .sidebar {
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            gap: 16px;
            min-height: 100vh;
            padding: 18px;
            border-right: 1px solid #dfe7f1;
            background: #fff;
        }

        .brand { display: flex; align-items: center; gap: 10px; }
        .brand-mark {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #0f766e;
            color: #fff;
            font-weight: 900;
        }
        .brand h1 { margin: 0; font-size: 17px; }
        .brand span { color: #64748b; font-size: 12px; }

        .profile-box { padding: 12px; border: 1px solid #dfe7f1; border-radius: 8px; background: #f8fafc; }
        .profile-box b { display: block; margin-bottom: 4px; font-size: 14px; }

        .nav { display: grid; align-content: start; gap: 6px; }
        .nav-button {
            display: grid;
            grid-template-columns: 28px 1fr auto;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 10px;
            border-radius: 7px;
            background: transparent;
            color: #475569;
            text-align: left;
            font-weight: 750;
        }
        .nav-button.active { background: #e7f8f5; color: #0f766e; }
        .nav-icon {
            display: grid;
            place-items: center;
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: #eef2f7;
            color: #475569;
            font-size: 14px;
        }
        .nav-button.active .nav-icon { background: #0f766e; color: #fff; }

        .shell { min-width: 0; }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid #dfe7f1;
            background: rgba(255, 255, 255, .94);
        }
        .topbar h2 { margin: 0; font-size: 20px; }
        .topbar-actions { display: flex; align-items: center; gap: 8px; }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 32px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eef2f7;
            color: #475569;
            font-size: 12px;
            font-weight: 800;
        }
        .status.online { background: #ccfbf1; color: #0f766e; }
        .dot { width: 8px; height: 8px; border-radius: 999px; background: currentColor; }

        .content { padding: 18px 20px 24px; }
        .stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
        .stat { padding: 14px; border: 1px solid #dfe7f1; border-radius: 8px; background: #fff; }
        .stat span { color: #64748b; font-size: 12px; font-weight: 750; }
        .stat b { display: block; margin-top: 6px; font-size: 24px; }

        .view { display: none; }
        .view.active { display: block; }
        .workspace { display: grid; grid-template-columns: 360px minmax(0, 1fr); gap: 14px; }
        .panel { min-width: 0; border: 1px solid #dfe7f1; border-radius: 8px; background: #fff; }
        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 13px;
            border-bottom: 1px solid #dfe7f1;
        }
        .panel-title { margin: 0; font-size: 15px; }
        .filters { display: grid; grid-template-columns: minmax(0, 1fr) 120px; gap: 8px; padding: 13px; border-bottom: 1px solid #dfe7f1; }
        .list { display: grid; gap: 8px; max-height: calc(100vh - 270px); overflow: auto; padding: 12px; }
        .list-item { width: 100%; padding: 12px; border: 1px solid #dfe7f1; border-radius: 8px; background: #fbfcfe; color: #182235; text-align: left; }
        .list-item.active { border-color: #0f766e; background: #f0fdfa; }
        .list-item h3 { margin-bottom: 5px; font-size: 14px; }
        .muted { color: #64748b; font-size: 13px; line-height: 1.5; }
        .badge-row { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .badge {
            display: inline-flex;
            align-items: center;
            min-height: 22px;
            padding: 3px 7px;
            border-radius: 999px;
            background: #eef2f7;
            color: #475569;
            font-size: 12px;
            font-weight: 800;
        }
        .badge.green { background: #ccfbf1; color: #0f766e; }
        .badge.red { background: #fee2e2; color: #b91c1c; }

        .detail { display: grid; grid-template-rows: auto minmax(260px, 1fr) auto; min-height: calc(100vh - 206px); }
        .detail-body { padding: 14px; }
        .info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .info { padding: 10px; border: 1px solid #e5ebf3; border-radius: 7px; background: #fbfcfe; }
        .info span { display: block; margin-bottom: 4px; color: #64748b; font-size: 12px; font-weight: 750; }
        .info b { font-size: 14px; }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .messages {
            display: grid;
            align-content: start;
            gap: 10px;
            max-height: calc(100vh - 435px);
            min-height: 230px;
            overflow: auto;
            margin-top: 14px;
            padding: 12px;
            border: 1px solid #dfe7f1;
            border-radius: 8px;
            background: #f8fafc;
        }
        .message { max-width: 78%; padding: 10px 12px; border-radius: 8px; background: #fff; box-shadow: 0 1px 0 rgba(24, 34, 53, .04); }
        .message.mine { justify-self: end; background: #ccfbf1; }
        .message b { display: block; margin-bottom: 4px; font-size: 12px; }
        .composer { display: grid; grid-template-columns: minmax(0, 1fr) 100px; gap: 8px; padding: 13px; border-top: 1px solid #dfe7f1; }

        .notifications-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 14px; }
        .notification-item { display: grid; gap: 6px; padding: 12px; border-bottom: 1px solid #e5ebf3; }
        .notification-item.unread { background: #f0fdfa; }
        .empty { padding: 18px; color: #64748b; font-size: 13px; }
        .log { max-height: 120px; overflow: auto; margin-top: 14px; padding: 10px; border-radius: 8px; background: #0f172a; color: #dbeafe; font-size: 12px; white-space: pre-wrap; }

        @media (max-width: 1080px) {
            .app.active { grid-template-columns: 1fr; }
            .sidebar { min-height: auto; border-right: 0; border-bottom: 1px solid #dfe7f1; }
            .nav { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .workspace, .notifications-grid { grid-template-columns: 1fr; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 640px) {
            .topbar, .topbar-actions { align-items: stretch; flex-direction: column; }
            .stats, .info-grid, .filters, .composer, .nav { grid-template-columns: 1fr; }
            .content { padding: 14px; }
        }
    </style>
</head>
<body>
<div id="login-card" class="login-card">
    <h1>Dashboard Mitra</h1>
    <p class="muted">Login untuk menerima konsultasi, order layanan, chat pasien, dan notifikasi realtime.</p>
    <form id="login-form">
        <label for="email">Email mitra</label>
        <input id="email" name="email" type="email" autocomplete="username" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button id="login-button" type="submit" class="primary-button" style="width:100%;margin-top:16px;">Login</button>
    </form>
    <pre id="login-log" class="log"></pre>
</div>

<div id="app" class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">M</div>
            <div><h1>Mitra Console</h1><span>Realtime care dashboard</span></div>
        </div>
        <div class="profile-box">
            <b id="profile-name">Belum login</b>
            <div id="profile-meta" class="muted">-</div>
        </div>
        <nav class="nav" aria-label="Menu dashboard">
            <button type="button" class="nav-button active" data-view="consultations"><span class="nav-icon">K</span><span>Konsultasi</span><span id="nav-consultation-count" class="badge">0</span></button>
            <button type="button" class="nav-button" data-view="orders"><span class="nav-icon">O</span><span>Order</span><span id="nav-order-count" class="badge">0</span></button>
            <button type="button" class="nav-button" data-view="notifications"><span class="nav-icon">N</span><span>Notifikasi</span><span id="nav-notification-count" class="badge">0</span></button>
        </nav>
        <button id="logout-button" type="button" class="secondary-button">Logout</button>
    </aside>

    <div class="shell">
        <header class="topbar">
            <div>
                <h2 id="page-title">Konsultasi</h2>
                <p id="page-subtitle" class="muted" style="margin-bottom:0;">Kelola konsultasi dan chat pasien.</p>
            </div>
            <div class="topbar-actions">
                <span id="connection-status" class="status"><span class="dot"></span>Idle</span>
                <button id="refresh-all-button" type="button" class="secondary-button">Refresh</button>
            </div>
        </header>

        <main class="content">
            <div class="stats">
                <div class="stat"><span>Konsultasi Aktif</span><b id="stat-active-consultations">0</b></div>
                <div class="stat"><span>Order Aktif</span><b id="stat-active-orders">0</b></div>
                <div class="stat"><span>Notif Belum Dibaca</span><b id="stat-unread-notifications">0</b></div>
                <div class="stat"><span>Status Socket</span><b id="stat-socket">Idle</b></div>
            </div>

            <section id="view-consultations" class="view active">
                <div class="workspace">
                    <div class="panel">
                        <div class="panel-head"><h2 class="panel-title">List Konsultasi</h2><button id="refresh-consultations-button" type="button" class="ghost-button">Refresh</button></div>
                        <div class="filters">
                            <input id="consultation-search" type="search" placeholder="Cari pasien/kode/keluhan">
                            <select id="consultation-status-filter">
                                <option value="">Semua</option><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="ongoing">Ongoing</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div id="consultation-list" class="list"><div class="empty">Belum ada data.</div></div>
                    </div>
                    <div class="panel detail">
                        <div class="panel-head">
                            <div><h2 id="consultation-title" class="panel-title">Pilih konsultasi</h2><p id="consultation-subtitle" class="muted" style="margin-bottom:0;">Detail dan chat akan tampil di sini.</p></div>
                            <span id="consultation-badge" class="badge">-</span>
                        </div>
                        <div class="detail-body">
                            <div id="consultation-detail"><div class="empty">Pilih salah satu konsultasi dari list.</div></div>
                            <div id="messages" class="messages"><span class="muted">Belum ada chat.</span></div>
                        </div>
                        <form id="message-form" class="composer">
                            <textarea id="message-input" rows="2" placeholder="Tulis pesan..." disabled></textarea>
                            <button id="send-button" type="submit" class="primary-button" disabled>Kirim</button>
                        </form>
                    </div>
                </div>
            </section>

            <section id="view-orders" class="view">
                <div class="workspace">
                    <div class="panel">
                        <div class="panel-head"><h2 class="panel-title">List Order Layanan</h2><button id="refresh-orders-button" type="button" class="ghost-button">Refresh</button></div>
                        <div class="filters">
                            <input id="order-search" type="search" placeholder="Cari pasien/kode/layanan">
                            <select id="order-status-filter">
                                <option value="">Semua</option><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="scheduled">Scheduled</option><option value="on_the_way">On the way</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div id="order-list" class="list"><div class="empty">Belum ada order.</div></div>
                    </div>
                    <div class="panel">
                        <div class="panel-head">
                            <div><h2 id="order-title" class="panel-title">Pilih order</h2><p id="order-subtitle" class="muted" style="margin-bottom:0;">Detail order layanan akan tampil di sini.</p></div>
                            <span id="order-badge" class="badge">-</span>
                        </div>
                        <div id="order-detail" class="detail-body"><div class="empty">Pilih salah satu order dari list.</div></div>
                    </div>
                </div>
            </section>

            <section id="view-notifications" class="view">
                <div class="notifications-grid">
                    <div class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title">Notifikasi</h2>
                            <div class="topbar-actions"><button id="mark-all-read-button" type="button" class="secondary-button">Tandai Dibaca</button><button id="refresh-notifications-button" type="button" class="ghost-button">Refresh</button></div>
                        </div>
                        <div id="notification-list"><div class="empty">Belum ada notifikasi.</div></div>
                    </div>
                    <div class="panel">
                        <div class="panel-head"><h2 class="panel-title">Realtime</h2></div>
                        <div class="detail-body"><p class="muted">Notifikasi baru dari broadcast akan masuk ke list ini tanpa refresh manual.</p><pre id="event-log" class="log"></pre></div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
    const reverbKey = @json($reverbKey);
    const reverbHost = @json($reverbHost);
    const reverbPort = Number(@json($reverbPort));
    const reverbScheme = @json($reverbScheme);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const $ = (selector) => document.querySelector(selector);
    const loginCard = $('#login-card');
    const app = $('#app');
    const loginForm = $('#login-form');
    const loginButton = $('#login-button');
    const loginLog = $('#login-log');
    const eventLog = $('#event-log');
    const navButtons = [...document.querySelectorAll('.nav-button')];
    const views = { consultations: $('#view-consultations'), orders: $('#view-orders'), notifications: $('#view-notifications') };

    let token = null;
    let user = null;
    let pusher = null;
    let activeConsultation = null;
    let activeBooking = null;
    let activeChatChannel = null;
    let notificationChannel = null;
    let bookingChannel = null;
    let consultations = [];
    let bookings = [];
    let notifications = [];
    let unreadCount = 0;
    let renderedMessageIds = new Set();

    function log(message, payload = null, target = loginLog) {
        const detail = payload ? `\n${JSON.stringify(payload, null, 2)}` : '';
        target.textContent = `[${new Date().toLocaleTimeString()}] ${message}${detail}\n\n${target.textContent}`;
    }

    function apiHeaders() {
        return { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}`, 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' };
    }

    async function apiFetch(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { ...apiHeaders(), ...(options.headers || {}) } });
        const payload = await response.json();
        if (!response.ok) throw payload;
        return payload;
    }

    function escapeHtml(value) {
        return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    function money(value) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
    }

    function formatDate(value) {
        return value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
    }

    function statusClass(status) {
        if (['completed', 'confirmed', 'ongoing', 'on_the_way'].includes(status)) return 'green';
        if (status === 'cancelled') return 'red';
        return '';
    }

    function setConnectionStatus(label, online = false) {
        $('#connection-status').innerHTML = `<span class="dot"></span>${escapeHtml(label)}`;
        $('#connection-status').classList.toggle('online', online);
        $('#stat-socket').textContent = label;
    }

    function setView(view) {
        navButtons.forEach((button) => button.classList.toggle('active', button.dataset.view === view));
        Object.entries(views).forEach(([key, element]) => element.classList.toggle('active', key === view));
        const titles = {
            consultations: ['Konsultasi', 'Kelola konsultasi dan chat pasien.'],
            orders: ['Order', 'Kelola order layanan homecare dan tindakan.'],
            notifications: ['Notifikasi', 'Pantau notifikasi realtime dari sistem.'],
        };
        $('#page-title').textContent = titles[view][0];
        $('#page-subtitle').textContent = titles[view][1];
    }

    function updateStats() {
        const activeConsultations = consultations.filter((item) => !['completed', 'cancelled'].includes(item.status)).length;
        const activeOrders = bookings.filter((item) => !['completed', 'cancelled'].includes(item.status)).length;
        $('#nav-consultation-count').textContent = String(activeConsultations);
        $('#nav-order-count').textContent = String(activeOrders);
        $('#nav-notification-count').textContent = String(unreadCount);
        $('#stat-active-consultations').textContent = String(activeConsultations);
        $('#stat-active-orders').textContent = String(activeOrders);
        $('#stat-unread-notifications').textContent = String(unreadCount);
    }

    function bootPusher() {
        if (pusher) pusher.disconnect();
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
            auth: { headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' } },
            authorizer: (channel) => ({ authorize: (socketId, callback) => authorizeChannel(socketId, channel.name, callback) }),
        });
        pusher.connection.bind('state_change', (states) => setConnectionStatus(states.current, states.current === 'connected'));
        pusher.connection.bind('connected', () => { setConnectionStatus('connected', true); subscribeNotifications(); subscribeBookingMatches(); });
        pusher.connection.bind('error', (error) => { setConnectionStatus('error', false); log('Reverb error.', error, eventLog); });
    }

    async function authorizeChannel(socketId, channelName, callback) {
        try {
            const response = await fetch('/api/broadcasting/auth', { method: 'POST', headers: apiHeaders(), body: JSON.stringify({ socket_id: socketId, channel_name: channelName }) });
            const payload = await response.json();
            if (!response.ok) throw payload;
            callback(null, payload);
        } catch (error) {
            log('Channel auth failed.', error, eventLog);
            callback(error, null);
        }
    }

    function subscribeNotifications() {
        if (!pusher || !user || notificationChannel) return;
        notificationChannel = pusher.subscribe(`private-user.${user.id}.notifications`);
        notificationChannel.bind('notification.created', (payload) => {
            notifications = [payload, ...notifications.filter((item) => Number(item.id) !== Number(payload.id))];
            unreadCount += 1;
            renderNotifications();
            updateStats();
            log('Notifikasi baru.', payload, eventLog);
        });
    }

    function subscribeBookingMatches() {
        if (!pusher || !user || bookingChannel) return;
        bookingChannel = pusher.subscribe(`private-partner.${user.id}.service-bookings`);
        bookingChannel.bind('service-booking.matched', async (payload) => {
            log('Order layanan baru.', payload, eventLog);
            await loadBookings();
            setView('orders');
        });
    }

    async function loadAll() {
        await Promise.allSettled([loadConsultations(), loadBookings(), loadNotifications()]);
        updateStats();
    }

    async function loadConsultations() {
        $('#consultation-list').innerHTML = '<div class="empty">Memuat konsultasi...</div>';
        const params = new URLSearchParams({ per_page: '50' });
        if ($('#consultation-status-filter').value) params.set('status', $('#consultation-status-filter').value);
        if ($('#consultation-search').value.trim()) params.set('search', $('#consultation-search').value.trim());
        try {
            const payload = await apiFetch(`/api/mitra/consultations?${params.toString()}`);
            consultations = payload.data.data || [];
            renderConsultations();
            updateStats();
        } catch (error) {
            $('#consultation-list').innerHTML = '<div class="empty">Gagal memuat konsultasi.</div>';
            log('Load consultations failed.', error, eventLog);
        }
    }

    function renderConsultations() {
        if (!consultations.length) {
            $('#consultation-list').innerHTML = '<div class="empty">Belum ada konsultasi.</div>';
            return;
        }
        $('#consultation-list').innerHTML = consultations.map((consultation) => `
            <button type="button" class="list-item ${activeConsultation?.id === consultation.id ? 'active' : ''}" data-consultation-id="${consultation.id}">
                <h3>${escapeHtml(consultation.consultation_code || `Konsultasi #${consultation.id}`)}</h3>
                <div class="muted">${escapeHtml(consultation.patient?.name || '-')} - ${escapeHtml(consultation.patient?.email || '-')}</div>
                <div class="badge-row"><span class="badge ${statusClass(consultation.status)}">${escapeHtml(consultation.status)}</span><span class="badge">${escapeHtml(consultation.service_type)}</span></div>
            </button>
        `).join('');
        document.querySelectorAll('[data-consultation-id]').forEach((button) => button.addEventListener('click', () => openConsultation(Number(button.dataset.consultationId))));
    }

    async function openConsultation(id) {
        try {
            const payload = await apiFetch(`/api/mitra/consultations/${id}`);
            activeConsultation = payload.data;
            renderConsultationDetail();
            subscribeConsultationChat(activeConsultation.id);
            renderConsultations();
        } catch (error) {
            log('Open consultation failed.', error, eventLog);
        }
    }

    function renderConsultationDetail() {
        const c = activeConsultation;
        $('#consultation-title').textContent = c.consultation_code || `Konsultasi #${c.id}`;
        $('#consultation-subtitle').textContent = `${c.patient?.name || '-'} - ${c.patient?.email || '-'}`;
        $('#consultation-badge').textContent = c.status;
        $('#consultation-badge').className = `badge ${statusClass(c.status)}`;
        $('#consultation-detail').innerHTML = `
            <div class="info-grid">
                <div class="info"><span>Pasien</span><b>${escapeHtml(c.patient?.name || '-')}</b></div>
                <div class="info"><span>Tipe</span><b>${escapeHtml(c.service_type)}</b></div>
                <div class="info"><span>Jadwal</span><b>${escapeHtml(formatDate(c.scheduled_at))}</b></div>
                <div class="info"><span>Biaya</span><b>${escapeHtml(money(c.consultation_fee))}</b></div>
            </div>
            <div class="actions">
                <button type="button" class="primary-button" data-consultation-status="confirmed" ${c.status !== 'pending' ? 'disabled' : ''}>Terima</button>
                <button type="button" class="primary-button" data-consultation-status="ongoing" ${!['pending', 'confirmed'].includes(c.status) ? 'disabled' : ''}>Mulai</button>
                <button type="button" class="secondary-button" data-consultation-status="completed" ${!['ongoing', 'confirmed'].includes(c.status) ? 'disabled' : ''}>Selesai</button>
                <button type="button" class="danger-button" data-consultation-status="cancelled" ${['completed', 'cancelled'].includes(c.status) ? 'disabled' : ''}>Batalkan</button>
            </div>
            <p style="margin-top:14px;"><b>Keluhan:</b> ${escapeHtml(c.complaint || '-')}</p>
            <p><b>Catatan:</b> ${escapeHtml(c.notes || '-')}</p>
            <p><b>Diagnosis:</b> ${escapeHtml(c.diagnosis || '-')}</p>
        `;
        document.querySelectorAll('[data-consultation-status]').forEach((button) => button.addEventListener('click', () => updateConsultationStatus(button.dataset.consultationStatus)));
        renderMessages(c.messages || []);
        $('#message-input').disabled = false;
        $('#send-button').disabled = false;
    }

    function renderMessages(messages) {
        renderedMessageIds = new Set();
        if (!messages.length) {
            $('#messages').innerHTML = '<span class="muted">Belum ada chat.</span>';
            return;
        }
        messages.forEach((message) => renderedMessageIds.add(Number(message.id)));
        $('#messages').innerHTML = messages.map((message) => messageHtml(message)).join('');
        $('#messages').scrollTop = $('#messages').scrollHeight;
    }

    function appendMessage(message) {
        if (message.id && renderedMessageIds.has(Number(message.id))) return;
        if (message.id) renderedMessageIds.add(Number(message.id));
        if ($('#messages').querySelector('.muted')) $('#messages').innerHTML = '';
        $('#messages').insertAdjacentHTML('beforeend', messageHtml(message));
        $('#messages').scrollTop = $('#messages').scrollHeight;
    }

    function messageHtml(message) {
        const mine = Number(message.sender_user_id) === Number(user.id);
        const senderName = message.sender?.name || (mine ? user.name : 'Pasien');
        return `<div class="message ${mine ? 'mine' : ''}"><b>${escapeHtml(senderName)}</b><div>${escapeHtml(message.message || '')}</div></div>`;
    }

    function subscribeConsultationChat(consultationId) {
        if (!pusher) return;
        if (activeChatChannel) {
            pusher.unsubscribe(activeChatChannel.name);
            activeChatChannel = null;
        }
        const channelName = `private-consultation.${consultationId}`;
        activeChatChannel = pusher.subscribe(channelName);
        activeChatChannel.bind('pusher:subscription_succeeded', () => log(`Subscribed to ${channelName}.`, null, eventLog));
        activeChatChannel.bind('pusher:subscription_error', (error) => log('Chat subscription failed.', error, eventLog));
        activeChatChannel.bind('chat.message.created', (payload) => {
            if (Number(payload.consultation_id) === Number(activeConsultation?.id)) appendMessage(payload);
        });
    }

    async function updateConsultationStatus(status) {
        if (!activeConsultation) return;
        try {
            const payload = await apiFetch(`/api/mitra/consultations/${activeConsultation.id}/status`, { method: 'PATCH', body: JSON.stringify({ status }) });
            activeConsultation = { ...activeConsultation, ...payload.data, messages: activeConsultation.messages || [] };
            renderConsultationDetail();
            await loadConsultations();
        } catch (error) {
            log('Update consultation status failed.', error, eventLog);
        }
    }

    async function sendMessage() {
        const message = $('#message-input').value.trim();
        if (!message || !activeConsultation) return;
        $('#message-input').value = '';
        $('#send-button').disabled = true;
        try {
            const payload = await apiFetch(`/api/mitra/consultations/${activeConsultation.id}/messages`, { method: 'POST', body: JSON.stringify({ message_type: 'text', message }) });
            appendMessage(payload.data);
        } catch (error) {
            $('#message-input').value = message;
            log('Send message failed.', error, eventLog);
        } finally {
            $('#send-button').disabled = false;
        }
    }

    async function loadBookings() {
        $('#order-list').innerHTML = '<div class="empty">Memuat order...</div>';
        const params = new URLSearchParams({ per_page: '50', assigned_partner_user_id: String(user.id) });
        if ($('#order-status-filter').value) params.set('status', $('#order-status-filter').value);
        try {
            const payload = await apiFetch(`/api/mitra/service-bookings?${params.toString()}`);
            const rawBookings = payload.data.data || [];
            const search = $('#order-search').value.trim().toLowerCase();
            bookings = search ? rawBookings.filter((item) => [item.booking_code, item.patient?.name, item.patient?.email, item.service?.name].filter(Boolean).join(' ').toLowerCase().includes(search)) : rawBookings;
            renderBookings();
            updateStats();
        } catch (error) {
            $('#order-list').innerHTML = '<div class="empty">Gagal memuat order.</div>';
            log('Load orders failed.', error, eventLog);
        }
    }

    function renderBookings() {
        if (!bookings.length) {
            $('#order-list').innerHTML = '<div class="empty">Belum ada order layanan.</div>';
            return;
        }
        $('#order-list').innerHTML = bookings.map((booking) => `
            <button type="button" class="list-item ${activeBooking?.id === booking.id ? 'active' : ''}" data-booking-id="${booking.id}">
                <h3>${escapeHtml(booking.booking_code || `Order #${booking.id}`)}</h3>
                <div class="muted">${escapeHtml(booking.service?.name || '-')}</div>
                <div class="muted">${escapeHtml(booking.patient?.name || '-')}</div>
                <div class="badge-row"><span class="badge ${statusClass(booking.status)}">${escapeHtml(booking.status)}</span><span class="badge">${escapeHtml(money(booking.total_amount))}</span></div>
            </button>
        `).join('');
        document.querySelectorAll('[data-booking-id]').forEach((button) => button.addEventListener('click', () => openBooking(Number(button.dataset.bookingId))));
    }

    async function openBooking(id) {
        try {
            const payload = await apiFetch(`/api/mitra/service-bookings/${id}`);
            activeBooking = payload.data;
            renderBookingDetail();
            renderBookings();
        } catch (error) {
            log('Open order failed.', error, eventLog);
        }
    }

    function renderBookingDetail() {
        const b = activeBooking;
        $('#order-title').textContent = b.booking_code || `Order #${b.id}`;
        $('#order-subtitle').textContent = `${b.service?.name || '-'} - ${b.patient?.name || '-'}`;
        $('#order-badge').textContent = b.status;
        $('#order-badge').className = `badge ${statusClass(b.status)}`;
        $('#order-detail').innerHTML = `
            <div class="info-grid">
                <div class="info"><span>Pasien</span><b>${escapeHtml(b.patient?.name || '-')}</b></div>
                <div class="info"><span>Layanan</span><b>${escapeHtml(b.service?.name || '-')}</b></div>
                <div class="info"><span>Jadwal</span><b>${escapeHtml(formatDate(b.scheduled_at))}</b></div>
                <div class="info"><span>Total</span><b>${escapeHtml(money(b.total_amount))}</b></div>
                <div class="info"><span>Alamat</span><b>${escapeHtml(b.address?.address || '-')}</b></div>
                <div class="info"><span>Dibuat</span><b>${escapeHtml(formatDate(b.created_at))}</b></div>
            </div>
            <div class="actions">
                <button type="button" class="primary-button" data-booking-action="accept" ${!['pending', 'scheduled'].includes(b.status) ? 'disabled' : ''}>Terima</button>
                <button type="button" class="primary-button" data-booking-action="start-journey" ${!['confirmed', 'scheduled'].includes(b.status) ? 'disabled' : ''}>Berangkat</button>
                <button type="button" class="secondary-button" data-booking-action="complete" ${!['confirmed', 'on_the_way'].includes(b.status) ? 'disabled' : ''}>Selesai</button>
                <button type="button" class="danger-button" data-booking-action="cancelled" ${['completed', 'cancelled'].includes(b.status) ? 'disabled' : ''}>Batalkan</button>
            </div>
            <p style="margin-top:14px;"><b>Catatan:</b> ${escapeHtml(b.notes || '-')}</p>
            <h3>Histori</h3>
            ${(b.histories || []).length ? (b.histories || []).map((history) => `<div class="notification-item"><b>${escapeHtml(history.title || '-')}</b><span class="muted">${escapeHtml(history.description || '-')}</span></div>`).join('') : '<div class="empty">Belum ada histori.</div>'}
        `;
        document.querySelectorAll('[data-booking-action]').forEach((button) => button.addEventListener('click', () => updateBooking(button.dataset.bookingAction)));
    }

    async function updateBooking(action) {
        if (!activeBooking) return;
        const endpoint = action === 'cancelled' ? `/api/mitra/service-bookings/${activeBooking.id}/status` : `/api/mitra/service-bookings/${activeBooking.id}/${action}`;
        const body = action === 'cancelled' ? { status: 'cancelled', notes: 'Dibatalkan dari dashboard mitra.' } : { notes: 'Diproses dari dashboard mitra.' };
        try {
            const payload = await apiFetch(endpoint, { method: 'PATCH', body: JSON.stringify(body) });
            activeBooking = payload.data;
            renderBookingDetail();
            await loadBookings();
        } catch (error) {
            log('Update order failed.', error, eventLog);
        }
    }

    async function loadNotifications() {
        try {
            const payload = await apiFetch('/api/shared/notifications?per_page=30');
            notifications = payload.data.data || [];
            unreadCount = Number(payload.unread_count || 0);
            renderNotifications();
            updateStats();
        } catch (error) {
            $('#notification-list').innerHTML = '<div class="empty">Gagal memuat notifikasi.</div>';
            log('Load notifications failed.', error, eventLog);
        }
    }

    function renderNotifications() {
        if (!notifications.length) {
            $('#notification-list').innerHTML = '<div class="empty">Belum ada notifikasi.</div>';
            return;
        }
        $('#notification-list').innerHTML = notifications.map((notification) => `
            <div class="notification-item ${notification.read_at ? '' : 'unread'}">
                <div style="display:flex;justify-content:space-between;gap:10px;"><b>${escapeHtml(notification.title || '-')}</b><span class="badge">${escapeHtml(notification.type || '-')}</span></div>
                <span class="muted">${escapeHtml(notification.body || '-')}</span>
                <span class="muted">${escapeHtml(formatDate(notification.created_at))}</span>
                ${notification.read_at ? '' : `<button type="button" class="ghost-button" data-notification-read="${notification.id}" style="justify-self:start;">Tandai dibaca</button>`}
            </div>
        `).join('');
        document.querySelectorAll('[data-notification-read]').forEach((button) => button.addEventListener('click', () => markNotificationRead(Number(button.dataset.notificationRead))));
    }

    async function markNotificationRead(id) {
        try {
            const payload = await apiFetch(`/api/shared/notifications/${id}/read`, { method: 'PATCH' });
            unreadCount = Number(payload.unread_count || 0);
            notifications = notifications.map((item) => Number(item.id) === id ? payload.data : item);
            renderNotifications();
            updateStats();
        } catch (error) {
            log('Mark notification read failed.', error, eventLog);
        }
    }

    async function markAllNotificationsRead() {
        try {
            await apiFetch('/api/shared/notifications/read-all', { method: 'PATCH' });
            unreadCount = 0;
            notifications = notifications.map((item) => ({ ...item, read_at: item.read_at || new Date().toISOString() }));
            renderNotifications();
            updateStats();
        } catch (error) {
            log('Mark all notifications read failed.', error, eventLog);
        }
    }

    async function logout() {
        if (token) {
            try {
                await apiFetch('/api/shared/logout', { method: 'POST' });
            } catch (error) {
                log('Logout API failed, clearing local session.', error, eventLog);
            }
        }

        if (pusher) pusher.disconnect();
        token = null; user = null; pusher = null; notificationChannel = null; bookingChannel = null; activeChatChannel = null;
        activeConsultation = null; activeBooking = null; consultations = []; bookings = []; notifications = []; unreadCount = 0;
        eventLog.textContent = '';
        loginCard.style.display = 'block';
        app.classList.remove('active');
        setConnectionStatus('Idle', false);
    }

    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        loginButton.disabled = true;
        try {
            const response = await fetch('/api/mitra/login', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ email: $('#email').value, password: $('#password').value }),
            });
            const payload = await response.json();
            if (!response.ok) throw payload;
            token = payload.user_api_token;
            user = payload.data;
            $('#profile-name').textContent = user.name;
            $('#profile-meta').textContent = `${user.email} - partner #${user.id}`;
            loginCard.style.display = 'none';
            app.classList.add('active');
            bootPusher();
            await loadAll();
        } catch (error) {
            log('Login failed.', error);
        } finally {
            loginButton.disabled = false;
        }
    });

    navButtons.forEach((button) => button.addEventListener('click', () => setView(button.dataset.view)));
    $('#refresh-all-button').addEventListener('click', loadAll);
    $('#refresh-consultations-button').addEventListener('click', loadConsultations);
    $('#refresh-orders-button').addEventListener('click', loadBookings);
    $('#refresh-notifications-button').addEventListener('click', loadNotifications);
    $('#mark-all-read-button').addEventListener('click', markAllNotificationsRead);
    $('#logout-button').addEventListener('click', logout);

    $('#consultation-status-filter').addEventListener('change', loadConsultations);
    $('#consultation-search').addEventListener('input', () => { window.clearTimeout($('#consultation-search')._timer); $('#consultation-search')._timer = window.setTimeout(loadConsultations, 350); });
    $('#order-status-filter').addEventListener('change', loadBookings);
    $('#order-search').addEventListener('input', () => { window.clearTimeout($('#order-search')._timer); $('#order-search')._timer = window.setTimeout(loadBookings, 350); });
    $('#message-form').addEventListener('submit', (event) => { event.preventDefault(); sendMessage(); });
</script>
</body>
</html>
