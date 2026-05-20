// CRCMIS Service Worker — handles Web Push notifications
self.addEventListener('push', function (event) {
  if (!event.data) return;

  let data = {};
  try { data = event.data.json(); } catch (_) { data = { title: event.data.text() }; }

  const title   = data.title  ?? 'CRCMIS Notification';
  const options = {
    body:    data.body    ?? '',
    icon:    data.icon    ?? '/images/pshslogo.png',
    badge:   '/images/pshslogo.png',
    data:    data.data    ?? {},
    tag:     data.tag     ?? 'crcmis-notification',
    renotify: true,
    actions: [{ action: 'view', title: 'View' }],
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  const url = event.notification.data?.url ?? '/dashboard';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
      for (const client of list) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.navigate(url);
          return client.focus();
        }
      }
      if (clients.openWindow) return clients.openWindow(url);
    })
  );
});

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(clients.claim()));
