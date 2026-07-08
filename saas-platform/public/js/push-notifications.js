/**
 * TheraPano Push Notification Client
 * Handles: Socket.io live notifications + Web Push subscription + Notification Center UI
 *
 * Include after the main app.js.
 * Requires: window.PUSH_CONFIG = { serverUrl, token, vapidPublicKey, userId, tenantId, role }
 */

(function () {
    'use strict';

    const cfg = window.PUSH_CONFIG || {};
    if (!cfg.serverUrl || !cfg.token) return;

    // ─── Socket.io Live Connection ────────────────────────────────────────────

    let socket = null;
    let unreadCount = 0;

    function initSocket() {
        // Socket.io must be loaded (CDN or bundled)
        if (typeof io === 'undefined') {
            console.warn('[push] Socket.io not loaded');
            return;
        }

        socket = io(cfg.serverUrl, {
            auth: { token: cfg.token },
            transports: ['websocket', 'polling'],
            reconnectionAttempts: 5,
            reconnectionDelay: 2000,
        });

        socket.on('connect', () => {
            console.info('[push] Connected to notification server');
        });

        socket.on('notification', (notif) => {
            unreadCount++;
            updateBadge(unreadCount);
            showToast(notif);
            prependToCenter(notif);
        });

        socket.on('unread_count', ({ count }) => {
            unreadCount = count;
            updateBadge(count);
        });

        socket.on('unread_count_update', ({ increment }) => {
            unreadCount += increment;
            updateBadge(unreadCount);
        });

        socket.on('connect_error', (err) => {
            console.warn('[push] Connection error:', err.message);
        });
    }

    // ─── Badge ────────────────────────────────────────────────────────────────

    function updateBadge(count) {
        const badges = document.querySelectorAll('.push-badge');
        badges.forEach(badge => {
            badge.textContent = count > 99 ? '99+' : count || '';
            badge.style.display = count > 0 ? 'inline-flex' : 'none';
        });
    }

    // ─── Toast Notification ───────────────────────────────────────────────────

    function showToast(notif) {
        const container = getOrCreateToastContainer();

        const toast = document.createElement('div');
        toast.className = 'push-toast push-toast--in';
        toast.innerHTML = `
            <div class="push-toast__icon">${getIcon(notif.notificationType)}</div>
            <div class="push-toast__body">
                <div class="push-toast__title">${escHtml(notif.title)}</div>
                <div class="push-toast__text">${escHtml(notif.body)}</div>
            </div>
            <button class="push-toast__close" aria-label="Schließen">&times;</button>
        `;

        const url = resolveUrl(notif.dataJson);
        if (url) {
            toast.style.cursor = 'pointer';
            toast.addEventListener('click', (e) => {
                if (!e.target.closest('.push-toast__close')) {
                    markRead(notif.id);
                    window.location.href = url;
                }
            });
        }

        toast.querySelector('.push-toast__close').addEventListener('click', () => {
            dismissToast(toast);
        });

        container.appendChild(toast);

        // Auto-dismiss after 6s
        setTimeout(() => dismissToast(toast), 6000);
    }

    function dismissToast(toast) {
        toast.classList.remove('push-toast--in');
        toast.classList.add('push-toast--out');
        setTimeout(() => toast.remove(), 300);
    }

    function getOrCreateToastContainer() {
        let c = document.getElementById('push-toast-container');
        if (!c) {
            c = document.createElement('div');
            c.id = 'push-toast-container';
            c.setAttribute('aria-live', 'polite');
            document.body.appendChild(c);
        }
        return c;
    }

    // ─── Notification Center ──────────────────────────────────────────────────

    function prependToCenter(notif) {
        const list = document.getElementById('push-notification-list');
        if (!list) return;

        const empty = list.querySelector('.push-center__empty');
        if (empty) empty.remove();

        const item = buildNotifItem(notif);
        list.insertBefore(item, list.firstChild);
    }

    function buildNotifItem(notif) {
        const url = resolveUrl(notif.dataJson);
        const time = formatTime(notif.createdAt || new Date().toISOString());
        const isRead = !!notif.readAt;

        const div = document.createElement('div');
        div.className = `push-notif-item${isRead ? '' : ' push-notif-item--unread'}`;
        div.dataset.id = notif.id;
        div.innerHTML = `
            <div class="push-notif-item__icon">${getIcon(notif.notificationType)}</div>
            <div class="push-notif-item__body">
                <div class="push-notif-item__title">${escHtml(notif.title)}</div>
                <div class="push-notif-item__text">${escHtml(notif.body)}</div>
                <div class="push-notif-item__time">${time}</div>
            </div>
        `;

        div.addEventListener('click', () => {
            markRead(notif.id);
            div.classList.remove('push-notif-item--unread');
            if (url) window.location.href = url;
        });

        return div;
    }

    async function loadNotifications(page = 1, unreadOnly = false) {
        const list = document.getElementById('push-notification-list');
        if (!list) return;

        try {
            const params = new URLSearchParams({ page, per_page: 20 });
            if (unreadOnly) params.append('unread', '1');

            const res = await fetch(`${cfg.serverUrl}/api/push/notifications?${params}`, {
                headers: { 'Authorization': `Bearer ${cfg.token}` },
            });
            const data = await res.json();

            if (page === 1) list.innerHTML = '';

            if (!data.items || data.items.length === 0) {
                if (page === 1) {
                    list.innerHTML = '<div class="push-center__empty">Keine Benachrichtigungen</div>';
                }
                return;
            }

            data.items.forEach(notif => {
                list.appendChild(buildNotifItem(notif));
            });

            unreadCount = data.unread_count || 0;
            updateBadge(unreadCount);
        } catch (err) {
            console.error('[push] loadNotifications error:', err);
        }
    }

    async function markRead(notifId) {
        if (!notifId) return;
        try {
            await fetch(`${cfg.serverUrl}/api/push/notifications/${notifId}/read`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${cfg.token}` },
            });
            if (socket) socket.emit('mark_read', { id: notifId });
        } catch { /* ignore */ }
    }

    async function markAllRead() {
        try {
            await fetch(`${cfg.serverUrl}/api/push/notifications/read-all`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${cfg.token}` },
            });
            if (socket) socket.emit('mark_all_read');

            document.querySelectorAll('.push-notif-item--unread').forEach(el => {
                el.classList.remove('push-notif-item--unread');
            });
            updateBadge(0);
        } catch { /* ignore */ }
    }

    // ─── Web Push Subscription ────────────────────────────────────────────────

    async function subscribeWebPush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
        if (!cfg.vapidPublicKey) return;

        try {
            const reg = await navigator.serviceWorker.register(cfg.serviceWorkerPath || '/sw-push.js');
            await navigator.serviceWorker.ready;

            let subscription = await reg.pushManager.getSubscription();
            if (!subscription) {
                subscription = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(cfg.vapidPublicKey),
                });
            }

            await fetch(`${cfg.serverUrl}/api/push/devices/register`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${cfg.token}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    token: JSON.stringify(subscription),
                    platform: 'web',
                    app_type: cfg.appType || 'therapist_trainer',
                    push_provider: 'webpush',
                }),
            });
        } catch (err) {
            console.warn('[push] Web Push subscribe error:', err.message);
        }
    }

    function updatePermissionBanner() {
        if (!cfg.enableWebPush || !('Notification' in window)) return;
        const banner = document.getElementById('push-permission-banner');
        if (!banner) return;
        // Show only when not yet decided; hide when granted or denied
        banner.style.display = Notification.permission === 'default' ? 'flex' : 'none';
    }

    async function requestWebPushPermission() {
        if (!('Notification' in window)) return;
        // Must be called from a user gesture (click) — browsers suppress
        // permission prompts triggered without interaction.
        const permission = await Notification.requestPermission();
        updatePermissionBanner();
        removeFloatingPrompt();
        if (permission === 'granted') {
            await subscribeWebPush();
        }
    }

    // ─── Floating permission prompt ───────────────────────────────────────────
    // Shown automatically when the user has not decided yet. Works on every
    // surface (app, portal, saas admin) without requiring template markup.

    const SNOOZE_KEY = 'push_perm_snooze_until';

    function isSnoozed() {
        try {
            const until = parseInt(localStorage.getItem(SNOOZE_KEY) || '0', 10);
            return until > Date.now();
        } catch { return false; }
    }

    function snoozePrompt(days) {
        try {
            localStorage.setItem(SNOOZE_KEY, String(Date.now() + days * 86400000));
        } catch { /* private mode */ }
    }

    function removeFloatingPrompt() {
        const el = document.getElementById('push-floating-prompt');
        if (el) el.remove();
    }

    function showFloatingPrompt() {
        if (document.getElementById('push-floating-prompt')) return;

        const box = document.createElement('div');
        box.id = 'push-floating-prompt';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-live', 'polite');
        box.style.cssText = [
            'position:fixed', 'bottom:1rem', 'right:1rem', 'z-index:99999',
            'display:flex', 'align-items:center', 'gap:0.75rem',
            'max-width:22rem', 'padding:0.85rem 1rem',
            'background:#1e1e2e', 'color:#f4f4f5',
            'border:1px solid rgba(255,255,255,.12)', 'border-radius:12px',
            'box-shadow:0 8px 30px rgba(0,0,0,.35)',
            'font-family:inherit', 'font-size:0.85rem', 'line-height:1.4',
        ].join(';');

        const text = document.createElement('div');
        text.style.cssText = 'flex:1;min-width:0;';
        text.innerHTML = '<strong style="display:block;margin-bottom:2px;">🔔 Benachrichtigungen aktivieren?</strong>'
            + '<span style="opacity:.75;">Erhalte sofort Push-Meldungen zu neuen Nachrichten, Terminen &amp; mehr.</span>';

        const btnCol = document.createElement('div');
        btnCol.style.cssText = 'display:flex;flex-direction:column;gap:0.35rem;flex-shrink:0;';

        const btnYes = document.createElement('button');
        btnYes.type = 'button';
        btnYes.textContent = 'Aktivieren';
        btnYes.style.cssText = 'background:#6366f1;color:#fff;border:none;border-radius:8px;padding:0.4rem 0.8rem;font-size:0.8rem;font-weight:600;cursor:pointer;';
        btnYes.addEventListener('click', () => { requestWebPushPermission(); });

        const btnLater = document.createElement('button');
        btnLater.type = 'button';
        btnLater.textContent = 'Später';
        btnLater.style.cssText = 'background:none;color:#a1a1aa;border:none;padding:0.2rem;font-size:0.75rem;cursor:pointer;';
        btnLater.addEventListener('click', () => {
            snoozePrompt(3);
            removeFloatingPrompt();
        });

        btnCol.appendChild(btnYes);
        btnCol.appendChild(btnLater);
        box.appendChild(text);
        box.appendChild(btnCol);
        document.body.appendChild(box);
    }

    async function initWebPush() {
        if (!cfg.enableWebPush) return;
        if (!('Notification' in window)) return;

        if (Notification.permission === 'granted') {
            // Already allowed — subscribe silently in the background
            await subscribeWebPush();
        } else if (Notification.permission === 'default'
            && cfg.vapidPublicKey
            && 'serviceWorker' in navigator
            && 'PushManager' in window
            && !isSnoozed()) {
            // Not decided yet: show a visible prompt so the user can trigger
            // the browser permission dialog via a real click.
            showFloatingPrompt();
        }
        // Show/hide the static activation banner (if the page has one)
        updatePermissionBanner();
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        return Uint8Array.from([...rawData].map(c => c.charCodeAt(0)));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    function getIcon(type) {
        const icons = {
            new_invoice: '🧾', invoice_paid: '✅', invoice_paid_staff: '✅',
            invoice_overdue: '⚠️', invoice_status_changed: '📄',
            new_homework: '📋', homework_updated: '📋',
            homework_completed: '✅', new_message: '💬', new_message_staff: '💬',
            appointment_booked: '📅', appointment_booked_staff: '📅',
            appointment_changed: '🔄', appointment_changed_staff: '🔄',
            appointment_cancelled: '❌', appointment_cancelled_staff: '❌',
            appointment_reminder_24h: '⏰', appointment_reminder_2h: '⏰',
            appointment_reminder_30min: '🔔', document_available: '📎',
            new_owner_registered: '👤', new_patient: '🐾', owner_upload: '📁',
            birthday_today: '🎂',
            new_package: '📦', new_training: '🎓', training_updated: '🎓',
            new_feedback: '📨', feedback_reply: '📨',
            system_warning: '⚠️', saas_new_practice: '🏥',
            saas_new_registration: '🏥', saas_new_invoice: '🧾',
            saas_feedback: '📨', saas_update_available: '⬆️',
            saas_subscription_changed: '📦', saas_trial_expiring: '⌛',
            saas_payment_failed: '💳', saas_system_error: '🚨',
        };
        return icons[type] || '🔔';
    }

    function resolveUrl(dataJson) {
        if (!dataJson) return null;
        // Short screen names used by PHP push dispatch
        const shortMap = {
            calendar: '/kalender',
            invoices: '/rechnungen',
            patient:  '/patienten/',
            owners:   '/tierhalter',
        };
        if (dataJson.screen && shortMap[dataJson.screen]) {
            const base = shortMap[dataJson.screen];
            if (dataJson.screen === 'patient' && dataJson.patient_id) return base + dataJson.patient_id;
            if (dataJson.screen === 'invoices' && dataJson.invoice_id) return base + '/' + dataJson.invoice_id;
            return base;
        }
        // Legacy detail-screen names
        const map = {
            invoice_detail: '/rechnungen/', appointment_detail: '/kalender',
            homework_detail: '/hausaufgaben/', message_detail: '/nachrichten',
            patient_detail: '/patienten/', owner_detail: '/tierhalter/',
        };
        const base = map[dataJson.screen];
        if (!base) return null;
        if (dataJson.screen === 'invoice_detail' && dataJson.invoice_id) return base + dataJson.invoice_id;
        if (dataJson.screen === 'homework_detail' && dataJson.homework_id) return base + dataJson.homework_id;
        if (dataJson.screen === 'patient_detail' && dataJson.patient_id) return base + dataJson.patient_id;
        if (dataJson.screen === 'owner_detail' && dataJson.owner_id) return base + dataJson.owner_id;
        return base;
    }

    function formatTime(isoString) {
        const d = new Date(isoString);
        const now = new Date();
        const diff = Math.floor((now - d) / 60000);
        if (diff < 1) return 'Gerade eben';
        if (diff < 60) return `vor ${diff} Min.`;
        if (diff < 1440) return `vor ${Math.floor(diff / 60)} Std.`;
        return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    // ─── Init ─────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', () => {
        initSocket();
        loadNotifications();
        initWebPush();

        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-push-mark-all-read]')) {
                markAllRead();
            }
            if (e.target.closest('[data-push-open-center]')) {
                loadNotifications();
            }
            if (e.target.closest('[data-push-request-permission]')) {
                requestWebPushPermission();
            }
        });
    });

    // Expose for external use
    window.PushNotifications = { markRead, markAllRead, loadNotifications, requestWebPushPermission };

})();
