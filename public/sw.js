// CRCMIS Service Worker v3 — handles Web Push notifications
self.addEventListener('push', function (event) {
  console.log('[SW] Push received', event.data ? 'with data' : 'no data');

  if (!event.data) {
    console.log('[SW] No data, showing fallback');
    event.waitUntil(self.registration.showNotification('CRCMIS', { body: 'New update' }));
    return;
  }

  let data = {};
  try {
    data = event.data.json();
    console.log('[SW] Push data:', JSON.stringify(data));
  } catch (e) {
    console.log('[SW] Parse error, using text:', event.data.text());
    data = { title: event.data.text() };
  }

  const title   = data.title  ?? 'CRCMIS Notification';
  const options = {
    body:     data.body    ?? '',
    icon:     '/images/pshslogo.png',
    data:     data.data    ?? {},
    tag:      data.tag     ?? ('crcmis-' + Date.now()),
    renotify: true,
  };

  console.log('[SW] Showing notification:', title, options.body);
  event.waitUntil(
    self.registration.showNotification(title, options)
      .then(() => console.log('[SW] Notification shown OK'))
      .catch(e  => console.error('[SW] showNotification failed:', e))
  );
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
