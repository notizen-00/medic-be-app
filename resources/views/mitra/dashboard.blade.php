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

        .availability-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e5ebf3;
        }
        .availability-label { font-size: 12px; font-weight: 750; color: #475569; }
        .availability-status { font-size: 12px; font-weight: 800; }
        .availability-status.is-active { color: #0f766e; }
        .availability-status.is-inactive { color: #b91c1c; }

        /* Toggle switch */
        .toggle-switch { position: relative; display: inline-block; width: 42px; height: 24px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .toggle-track {
            position: absolute; inset: 0;
            background: #cbd5e1;
            border-radius: 999px;
            cursor: pointer;
            transition: background .2s;
        }
        .toggle-track::after {
            content: '';
            position: absolute;
            top: 3px; left: 3px;
            width: 18px; height: 18px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,.25);
            transition: transform .2s;
        }
        .toggle-switch input:checked + .toggle-track { background: #0f766e; }
        .toggle-switch input:checked + .toggle-track::after { transform: translateX(18px); }
        .toggle-switch input:disabled + .toggle-track { opacity: .5; cursor: not-allowed; }

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
        .stats { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
        .stat { padding: 14px; border: 1px solid #dfe7f1; border-radius: 8px; background: #fff; }
        .stat span { color: #64748b; font-size: 12px; font-weight: 750; }
        .stat b { display: block; margin-top: 6px; font-size: 24px; }
        .stat.stat-availability { display: flex; flex-direction: column; justify-content: space-between; }
        .stat.stat-availability .stat-toggle-row { display: flex; align-items: center; justify-content: space-between; margin-top: 8px; }
        .stat-availability-value { font-size: 14px; font-weight: 800; }
        .stat-availability-value.is-active { color: #0f766e; }
        .stat-availability-value.is-inactive { color: #b91c1c; }

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
        .amount-breakdown {
            display: grid;
            gap: 7px;
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #dfe7f1;
            border-radius: 7px;
            background: #f8fafc;
        }
        .amount-breakdown-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            color: #475569;
            font-size: 12px;
            font-weight: 750;
        }
        .amount-breakdown-row b { color: #182235; font-size: 13px; text-align: right; }
        .amount-breakdown-row.total {
            margin-top: 2px;
            padding-top: 8px;
            border-top: 1px solid #dfe7f1;
            color: #0f766e;
            font-size: 13px;
        }
        .amount-breakdown-row.total b { color: #0f766e; font-size: 15px; }
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
        .balance-grid { display: grid; grid-template-columns: 320px minmax(0, 1fr); gap: 14px; }
        .balance-summary { display: grid; gap: 10px; padding: 14px; }
        .balance-amount { font-size: 28px; font-weight: 900; color: #0f766e; }
        .transaction-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .transaction-table th, .transaction-table td { padding: 10px; border-bottom: 1px solid #e5ebf3; text-align: left; vertical-align: top; }
        .transaction-table th { color: #475569; font-size: 12px; font-weight: 800; background: #f8fafc; }
        .amount-in { color: #0f766e; font-weight: 850; }
        .amount-out { color: #b91c1c; font-weight: 850; }
        .empty { padding: 18px; color: #64748b; font-size: 13px; }
        .log { max-height: 120px; overflow: auto; margin-top: 14px; padding: 10px; border-radius: 8px; background: #0f172a; color: #dbeafe; font-size: 12px; white-space: pre-wrap; }

        .order-alert {
            position: fixed;
            inset: 0;
            z-index: 10;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, .58);
        }
        .order-alert.active { display: flex; }
        .order-alert-panel {
            width: min(520px, 100%);
            overflow: hidden;
            border: 1px solid #dfe7f1;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 24px 80px rgba(15, 23, 42, .28);
        }
        .order-alert-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 16px;
            border-bottom: 1px solid #dfe7f1;
        }
        .order-alert-head h2 { margin: 0 0 5px; font-size: 18px; }
        .order-alert-body { display: grid; gap: 10px; padding: 16px; }
        .order-alert-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .order-alert-field { padding: 10px; border: 1px solid #e5ebf3; border-radius: 7px; background: #fbfcfe; }
        .order-alert-field span { display: block; margin-bottom: 4px; color: #64748b; font-size: 12px; font-weight: 750; }
        .order-alert-field b { overflow-wrap: anywhere; font-size: 14px; }
        .order-alert-address { grid-column: 1 / -1; }
        .order-alert-actions {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 10px;
            padding: 14px 16px 16px;
            border-top: 1px solid #dfe7f1;
        }

        @media (max-width: 1080px) {
            .app.active { grid-template-columns: 1fr; }
            .sidebar { min-height: auto; border-right: 0; border-bottom: 1px solid #dfe7f1; }
            .nav { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .workspace, .notifications-grid, .balance-grid { grid-template-columns: 1fr; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 640px) {
            .topbar, .topbar-actions { align-items: stretch; flex-direction: column; }
            .stats, .info-grid, .filters, .composer, .nav, .order-alert-grid, .order-alert-actions { grid-template-columns: 1fr; }
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
            <div class="availability-row">
                <div>
                    <div class="availability-label">Status Ketersediaan</div>
                    <div id="availability-status-text" class="availability-status is-inactive">Tidak Aktif</div>
                </div>
                <label class="toggle-switch" aria-label="Toggle ketersediaan">
                    <input type="checkbox" id="availability-toggle" disabled>
                    <span class="toggle-track"></span>
                </label>
            </div>
        </div>
        <nav class="nav" aria-label="Menu dashboard">
            <button type="button" class="nav-button active" data-view="consultations"><span class="nav-icon">K</span><span>Konsultasi</span><span id="nav-consultation-count" class="badge">0</span></button>
            <button type="button" class="nav-button" data-view="orders"><span class="nav-icon">O</span><span>Order</span><span id="nav-order-count" class="badge">0</span></button>
            <button type="button" class="nav-button" data-view="balance"><span class="nav-icon">S</span><span>Saldo</span><span id="nav-balance-amount" class="badge">Rp0</span></button>
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
                <div class="stat"><span>Saldo Mitra</span><b id="stat-balance">Rp0</b></div>
                <div class="stat"><span>Notif Belum Dibaca</span><b id="stat-unread-notifications">0</b></div>
                <div class="stat"><span>Status Socket</span><b id="stat-socket">Idle</b></div>
                <div class="stat stat-availability">
                    <span>Ketersediaan</span>
                    <div class="stat-toggle-row">
                        <span id="stat-availability-value" class="stat-availability-value is-inactive">Tidak Aktif</span>
                        <label class="toggle-switch" aria-label="Toggle ketersediaan">
                            <input type="checkbox" id="stat-availability-toggle" disabled>
                            <span class="toggle-track"></span>
                        </label>
                    </div>
                </div>
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

            <section id="view-balance" class="view">
                <div class="balance-grid">
                    <div class="panel">
                        <div class="panel-head"><h2 class="panel-title">Saldo Mitra</h2><button id="refresh-balance-button" type="button" class="ghost-button">Refresh</button></div>
                        <div class="balance-summary">
                            <div>
                                <span class="muted">Saldo tersedia</span>
                                <div id="balance-current" class="balance-amount">Rp0</div>
                            </div>
                            <div class="info"><span>Saldo ditahan</span><b id="balance-reserved">Rp0</b></div>
                            <div class="info"><span>Total topup</span><b id="balance-total-topup">Rp0</b></div>
                            <div class="info"><span>Total refund</span><b id="balance-total-refund">Rp0</b></div>
                            <div class="info"><span>Total keluar</span><b id="balance-total-deduction">Rp0</b></div>
                            <div class="info"><span>Status</span><b id="balance-status">-</b></div>
                        </div>
                    </div>
                    <div class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title">History Transaksi</h2>
                            <div class="topbar-actions">
                                <select id="balance-type-filter" aria-label="Filter tipe transaksi">
                                    <option value="">Semua tipe</option>
                                    <option value="topup">Topup</option>
                                    <option value="refund">Refund</option>
                                    <option value="deduction">Deduction</option>
                                    <option value="adjustment">Adjustment</option>
                                    <option value="transfer">Transfer</option>
                                    <option value="payment">Payment</option>
                                </select>
                                <select id="balance-status-filter" aria-label="Filter status transaksi">
                                    <option value="">Semua status</option>
                                    <option value="pending">Pending</option>
                                    <option value="completed">Completed</option>
                                    <option value="failed">Failed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div id="balance-history"><div class="empty">Belum ada transaksi.</div></div>
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

<div id="order-alert" class="order-alert" role="dialog" aria-modal="true" aria-labelledby="order-alert-title">
    <div class="order-alert-panel">
        <div class="order-alert-head">
            <div>
                <h2 id="order-alert-title">Order layanan baru</h2>
                <p id="order-alert-subtitle" class="muted" style="margin-bottom:0;">Ada pesanan pasien yang cocok untuk akun ini.</p>
            </div>
            <span id="order-alert-status" class="badge">baru</span>
        </div>
        <div class="order-alert-body">
            <div class="order-alert-grid">
                <div class="order-alert-field"><span>Kode</span><b id="order-alert-code">-</b></div>
                <div class="order-alert-field"><span>Layanan</span><b id="order-alert-service">-</b></div>
                <div class="order-alert-field"><span>Pasien</span><b id="order-alert-patient">-</b></div>
                <div class="order-alert-field"><span>Total</span><b id="order-alert-total">-</b></div>
                <div class="order-alert-field"><span>Jadwal</span><b id="order-alert-schedule">-</b></div>
                <div class="order-alert-field"><span>Jarak</span><b id="order-alert-distance">-</b></div>
                <div class="order-alert-address"><div id="order-alert-breakdown" class="amount-breakdown"></div></div>
                <div class="order-alert-field order-alert-address"><span>Alamat</span><b id="order-alert-address">-</b></div>
            </div>
            <p id="order-alert-notes" class="muted" style="margin-bottom:0;"></p>
        </div>
        <div class="order-alert-actions">
            <button id="order-alert-dismiss-button" type="button" class="secondary-button">Tidak</button>
            <button id="order-alert-accept-button" type="button" class="primary-button">Terima</button>
        </div>
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
    const views = { consultations: $('#view-consultations'), orders: $('#view-orders'), balance: $('#view-balance'), notifications: $('#view-notifications') };
    const availabilityToggle = $('#availability-toggle');
    const statAvailabilityToggle = $('#stat-availability-toggle');

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
    let balanceSummary = null;
    let balanceTransactions = [];
    let notifications = [];
    let unreadCount = 0;
    let renderedMessageIds = new Set();
    let pendingMatchedBooking = null;
    let isAvailable = false;

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

    function numberValue(value) {
        const parsed = Number(value || 0);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function bookingPayoutBreakdown(booking) {
        const breakdown = booking?.partner_payout_breakdown || {};
        const serviceBase = numberValue(breakdown.service_base_amount ?? (numberValue(booking?.subtotal) - numberValue(booking?.markup_amount)));
        const transportFee = numberValue(breakdown.transport_fee ?? booking?.transport_fee);
        const mealFee = numberValue(breakdown.meal_fee ?? booking?.meal_fee);
        const partnerPayout = numberValue(breakdown.partner_payout_amount ?? booking?.partner_payout_amount ?? booking?.total_amount);

        return {
            serviceBase,
            transportFee,
            mealFee,
            appMarkup: numberValue(breakdown.app_markup_amount ?? booking?.markup_amount),
            patientTotal: numberValue(breakdown.patient_total_amount ?? booking?.patient_total_amount),
            partnerPayout,
            transportApplied: Boolean(breakdown.transport_fee_applied ?? transportFee > 0),
            mealApplied: Boolean(breakdown.meal_fee_applied ?? mealFee > 0),
        };
    }

    function bookingPayoutAmount(booking) {
        return bookingPayoutBreakdown(booking).partnerPayout;
    }

    function bookingPayoutBreakdownHtml(booking, compact = false) {
        const amount = bookingPayoutBreakdown(booking);
        const rows = [
            `<div class="amount-breakdown-row"><span>Biaya layanan</span><b>${escapeHtml(money(amount.serviceBase))}</b></div>`,
        ];

        if (amount.transportApplied) {
            rows.push(`<div class="amount-breakdown-row"><span>Transportasi</span><b>${escapeHtml(money(amount.transportFee))}</b></div>`);
        }

        if (amount.mealApplied) {
            rows.push(`<div class="amount-breakdown-row"><span>Uang makan</span><b>${escapeHtml(money(amount.mealFee))}</b></div>`);
        }

        if (!compact && amount.patientTotal > 0) {
            rows.push(`<div class="amount-breakdown-row"><span>Total bayar pasien</span><b>${escapeHtml(money(amount.patientTotal))}</b></div>`);
        }

        rows.push(`<div class="amount-breakdown-row total"><span>Diterima mitra</span><b>${escapeHtml(money(amount.partnerPayout))}</b></div>`);

        return rows.join('');
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
            balance: ['Saldo', 'Pantau saldo mitra dan history transaksi.'],
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
        $('#nav-balance-amount').textContent = money(balanceSummary?.available_balance ?? 0);
        $('#nav-notification-count').textContent = String(unreadCount);
        $('#stat-active-consultations').textContent = String(activeConsultations);
        $('#stat-active-orders').textContent = String(activeOrders);
        $('#stat-balance').textContent = money(balanceSummary?.available_balance ?? 0);
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
            showOrderAlert(payload);
        });
    }

    function normalizeMatchedBooking(payload) {
        const booking = payload?.booking || payload || {};
        if (!booking.id) return null;

        const loadedBooking = bookings.find((item) => Number(item.id) === Number(booking.id));
        return {
            ...(loadedBooking || {}),
            ...booking,
            service: booking.service || loadedBooking?.service || null,
            patient: booking.patient || loadedBooking?.patient || null,
            patient_member: booking.patient_member || loadedBooking?.patient_member || null,
            address: booking.address || loadedBooking?.address || null,
            matchmaking: payload?.matchmaking || booking.matchmaking || loadedBooking?.matchmaking || null,
        };
    }

    function showOrderAlert(payload) {
        const booking = normalizeMatchedBooking(payload);
        if (!booking) return;

        pendingMatchedBooking = booking;
        const patientName = booking.patient_member?.name || booking.patient?.name || '-';
        const distance = booking.matchmaking?.distance_km;

        $('#order-alert-code').textContent = booking.booking_code || `Order #${booking.id}`;
        $('#order-alert-service').textContent = booking.service?.name || '-';
        $('#order-alert-patient').textContent = patientName;
        $('#order-alert-total').textContent = money(bookingPayoutAmount(booking));
        $('#order-alert-breakdown').innerHTML = bookingPayoutBreakdownHtml(booking);
        $('#order-alert-schedule').textContent = formatDate(booking.scheduled_at);
        $('#order-alert-distance').textContent = distance === null || distance === undefined ? '-' : `${Number(distance).toFixed(2)} km`;
        $('#order-alert-address').textContent = booking.address?.address || '-';
        $('#order-alert-status').textContent = booking.status || 'baru';
        $('#order-alert-status').className = `badge ${statusClass(booking.status)}`;
        $('#order-alert-notes').textContent = booking.notes ? `Catatan: ${booking.notes}` : '';
        $('#order-alert-accept-button').disabled = !['pending', 'scheduled'].includes(booking.status);
        $('#order-alert-dismiss-button').disabled = false;
        $('#order-alert').classList.add('active');
    }

    function hideOrderAlert() {
        $('#order-alert').classList.remove('active');
        pendingMatchedBooking = null;
        $('#order-alert-accept-button').disabled = false;
        $('#order-alert-dismiss-button').disabled = false;
    }

    async function acceptPendingMatchedBooking() {
        if (!pendingMatchedBooking) return;

        const bookingId = pendingMatchedBooking.id;
        $('#order-alert-accept-button').disabled = true;
        $('#order-alert-dismiss-button').disabled = true;

        try {
            const payload = await apiFetch(`/api/mitra/service-bookings/${bookingId}/accept`, { method: 'PATCH', body: JSON.stringify({ notes: 'Diterima dari dashboard mitra.' }) });
            activeBooking = payload.data;
            hideOrderAlert();
            setView('orders');
            await loadBookings();
            await openBooking(bookingId);
            log('Order layanan diterima.', payload, eventLog);
        } catch (error) {
            log('Accept order failed.', error, eventLog);
            $('#order-alert-accept-button').disabled = false;
            $('#order-alert-dismiss-button').disabled = false;
        }
    }

    function setAvailabilityUI(value) {
        isAvailable = Boolean(value);
        const label = isAvailable ? 'Aktif' : 'Tidak Aktif';
        const cls = isAvailable ? 'is-active' : 'is-inactive';

        availabilityToggle.checked = isAvailable;
        statAvailabilityToggle.checked = isAvailable;

        const statusText = $('#availability-status-text');
        statusText.textContent = label;
        statusText.className = `availability-status ${cls}`;

        const statValue = $('#stat-availability-value');
        statValue.textContent = label;
        statValue.className = `stat-availability-value ${cls}`;
    }

    async function loadProfile() {
        try {
            const payload = await apiFetch('/api/mitra/profile');
            const profile = payload.data?.partner_profile;
            if (profile !== undefined && profile !== null) {
                setAvailabilityUI(profile.is_available);
            }
            availabilityToggle.disabled = false;
            statAvailabilityToggle.disabled = false;
        } catch (error) {
            log('Load profile failed.', error, eventLog);
        }
    }

    async function toggleAvailability(newValue) {
        availabilityToggle.disabled = true;
        statAvailabilityToggle.disabled = true;
        const prev = isAvailable;

        // Optimistic update
        setAvailabilityUI(newValue);

        try {
            const payload = await apiFetch('/api/mitra/profile/availability', {
                method: 'PATCH',
                body: JSON.stringify({ is_available: newValue }),
            });
            setAvailabilityUI(payload.data.is_available);
            log(`Availability diubah: ${payload.message}`, null, eventLog);
        } catch (error) {
            // Rollback jika gagal
            setAvailabilityUI(prev);
            log('Toggle availability gagal.', error, eventLog);
        } finally {
            availabilityToggle.disabled = false;
            statAvailabilityToggle.disabled = false;
        }
    }

    async function loadAll() {
        await Promise.allSettled([loadConsultations(), loadBookings(), loadBalance(), loadNotifications()]);
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
            bookings = search ? rawBookings.filter((item) => [item.booking_code, item.patient_member?.name, item.patient?.name, item.patient?.email, item.service?.name].filter(Boolean).join(' ').toLowerCase().includes(search)) : rawBookings;
            renderBookings();
            updateStats();
        } catch (error) {
            $('#order-list').innerHTML = '<div class="empty">Gagal memuat order.</div>';
            log('Load orders failed.', error, eventLog);
        }
    }

    async function loadBalance() {
        $('#balance-history').innerHTML = '<div class="empty">Memuat transaksi...</div>';
        const params = new URLSearchParams({ per_page: '30' });
        if ($('#balance-type-filter').value) params.set('type', $('#balance-type-filter').value);
        if ($('#balance-status-filter').value) params.set('status', $('#balance-status-filter').value);

        try {
            const [summaryPayload, historyPayload] = await Promise.all([
                apiFetch('/api/mitra/balance'),
                apiFetch(`/api/mitra/balance/history?${params.toString()}`),
            ]);

            balanceSummary = summaryPayload.data.summary || summaryPayload.data;
            balanceTransactions = historyPayload.data.data || [];
            renderBalance();
            updateStats();
        } catch (error) {
            $('#balance-history').innerHTML = '<div class="empty">Gagal memuat transaksi saldo.</div>';
            log('Load balance failed.', error, eventLog);
        }
    }

    function renderBalance() {
        const summary = balanceSummary || {};
        $('#balance-current').textContent = money(summary.available_balance ?? summary.current_balance ?? 0);
        $('#balance-reserved').textContent = money(summary.reserved_balance ?? 0);
        $('#balance-total-topup').textContent = money(summary.total_topup ?? 0);
        $('#balance-total-refund').textContent = money(summary.total_refund ?? 0);
        $('#balance-total-deduction').textContent = money(summary.total_deduction ?? 0);
        $('#balance-status').textContent = summary.status || '-';

        if (!balanceTransactions.length) {
            $('#balance-history').innerHTML = '<div class="empty">Belum ada transaksi saldo.</div>';
            return;
        }

        $('#balance-history').innerHTML = `
            <table class="transaction-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Tipe</th>
                        <th>Deskripsi</th>
                        <th>Nominal</th>
                        <th>Saldo Akhir</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${balanceTransactions.map(transactionRow).join('')}
                </tbody>
            </table>
        `;
    }

    function transactionRow(transaction) {
        const amount = Number(transaction.amount || 0);
        const amountClass = amount >= 0 ? 'amount-in' : 'amount-out';
        const reference = transaction.reference_type && transaction.reference_id
            ? `<div class="muted">${escapeHtml(transaction.reference_type)} #${escapeHtml(transaction.reference_id)}</div>`
            : '';

        return `
            <tr>
                <td>${escapeHtml(formatDate(transaction.created_at))}</td>
                <td><span class="badge">${escapeHtml(transaction.type || '-')}</span></td>
                <td>
                    <b>${escapeHtml(transaction.description || transaction.reference_number || '-')}</b>
                    ${reference}
                </td>
                <td class="${amountClass}">${escapeHtml(money(amount))}</td>
                <td>${escapeHtml(money(transaction.balance_after))}</td>
                <td><span class="badge ${transaction.status === 'completed' ? 'green' : transaction.status === 'failed' ? 'red' : ''}">${escapeHtml(transaction.status || '-')}</span></td>
            </tr>
        `;
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
                <div class="muted">${escapeHtml(booking.patient_member?.name || booking.patient?.name || '-')}</div>
                <div class="badge-row"><span class="badge ${statusClass(booking.status)}">${escapeHtml(booking.status)}</span><span class="badge">Diterima ${escapeHtml(money(bookingPayoutAmount(booking)))}</span></div>
                <div class="amount-breakdown">${bookingPayoutBreakdownHtml(booking, true)}</div>
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
        $('#order-subtitle').textContent = `${b.service?.name || '-'} - ${b.patient_member?.name || b.patient?.name || '-'}`;
        $('#order-badge').textContent = b.status;
        $('#order-badge').className = `badge ${statusClass(b.status)}`;
        $('#order-detail').innerHTML = `
            <div class="info-grid">
                <div class="info"><span>Pasien</span><b>${escapeHtml(b.patient_member?.name || b.patient?.name || '-')}</b></div>
                <div class="info"><span>Hubungan</span><b>${escapeHtml(b.patient_member?.relationship || '-')}</b></div>
                <div class="info"><span>Layanan</span><b>${escapeHtml(b.service?.name || '-')}</b></div>
                <div class="info"><span>Jadwal</span><b>${escapeHtml(formatDate(b.scheduled_at))}</b></div>
                <div class="info"><span>Diterima Mitra</span><b>${escapeHtml(money(bookingPayoutAmount(b)))}</b></div>
                <div class="info"><span>Total Bayar Pasien</span><b>${escapeHtml(money(bookingPayoutBreakdown(b).patientTotal || b.patient_total_amount || b.total_amount))}</b></div>
                <div class="info"><span>Alamat</span><b>${escapeHtml(b.address?.address || '-')}</b></div>
                <div class="info"><span>Dibuat</span><b>${escapeHtml(formatDate(b.created_at))}</b></div>
            </div>
            <div class="amount-breakdown">${bookingPayoutBreakdownHtml(b)}</div>
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
        activeConsultation = null; activeBooking = null; consultations = []; bookings = []; balanceSummary = null; balanceTransactions = []; notifications = []; unreadCount = 0;
        hideOrderAlert();
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
            await Promise.all([loadAll(), loadProfile()]);
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
    $('#refresh-balance-button').addEventListener('click', loadBalance);
    $('#refresh-notifications-button').addEventListener('click', loadNotifications);
    $('#mark-all-read-button').addEventListener('click', markAllNotificationsRead);
    $('#logout-button').addEventListener('click', logout);
    $('#order-alert-accept-button').addEventListener('click', acceptPendingMatchedBooking);
    $('#order-alert-dismiss-button').addEventListener('click', hideOrderAlert);

    availabilityToggle.addEventListener('change', () => toggleAvailability(availabilityToggle.checked));
    statAvailabilityToggle.addEventListener('change', () => toggleAvailability(statAvailabilityToggle.checked));

    $('#consultation-status-filter').addEventListener('change', loadConsultations);
    $('#consultation-search').addEventListener('input', () => { window.clearTimeout($('#consultation-search')._timer); $('#consultation-search')._timer = window.setTimeout(loadConsultations, 350); });
    $('#order-status-filter').addEventListener('change', loadBookings);
    $('#order-search').addEventListener('input', () => { window.clearTimeout($('#order-search')._timer); $('#order-search')._timer = window.setTimeout(loadBookings, 350); });
    $('#balance-type-filter').addEventListener('change', loadBalance);
    $('#balance-status-filter').addEventListener('change', loadBalance);
    $('#message-form').addEventListener('submit', (event) => { event.preventDefault(); sendMessage(); });
</script>
</body>
</html>
