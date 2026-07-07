/* TheraPano Web Push Service Worker — place at /sw-push.js */
'use strict';

const CACHE_NAME = 'therapano-push-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(clients.claim()));

self.addEventListener('push', (event) => {
    if (!event.data) return;

    let data;
    try { data = event.data.json(); }
    catch { data = { title: 'TheraPano', body: event.data.text() || 'Neue Benachrichtigung' }; }

    const options = {
        body: data.body || '',
        icon: '/assets/img/icon-192.png',
        badge: '/assets/img/badge-72.png',
        data: data.data || {},
        vibrate: [100, 50, 100],
        tag: data.data?.notification_type || 'general',
        requireInteraction: false,
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'TheraPano', options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const data = event.notification.data || {};
    const url = resolveUrl(data);

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const c of list) {
                if (c.url.includes(self.location.origin)) {
                    c.postMessage({ type: 'NOTIFICATION_CLICKED', data });
                    return c.focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});

function resolveUrl(data) {
    const map = {
        invoice_detail: '/rechnungen/', appointment_detail: '/kalender',
        homework_detail: '/hausaufgaben/', message_detail: '/nachrichten',
        patient_detail: '/patienten/', owner_detail: '/tierhalter/',
    };
    const base = map[data.screen] || '/';
    if (data.screen === 'invoice_detail' && data.invoice_id)   return base + data.invoice_id;
    if (data.screen === 'homework_detail' && data.homework_id) return base + data.homework_id;
    if (data.screen === 'patient_detail' && data.patient_id)   return base + data.patient_id;
    if (data.screen === 'owner_detail' && data.owner_id)       return base + data.owner_id;
    return base;
}
