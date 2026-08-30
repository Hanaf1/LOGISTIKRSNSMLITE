const cacheName = 'cache-v4';
const precacheResources = [
  '/',
  'assets/jscripts/bootstrap.min.js',
  'assets/css/flatly.min.css',
];

self.addEventListener('install', event => {
  //console.log('Service worker install event!');
  self.skipWaiting();
  event.waitUntil(
    caches.open(cacheName)
      .then(cache => {
        return cache.addAll(precacheResources);
      })
  );
});

self.addEventListener('activate', event => {
  //console.log('Service worker activate event!');
  event.waitUntil(
    caches.keys()
      .then(cacheNames => Promise.all(
        cacheNames
          .filter(name => name !== cacheName)
          .map(name => caches.delete(name))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  //console.log('Fetch intercepted for:', event.request.url);
  if (event.request.method !== 'GET') {
    return;
  }
  if (event.request.url.includes('/admin/') || event.request.mode === 'navigate') {
    return;
  }
  
  event.respondWith(caches.match(event.request)
    .then(cachedResponse => {
        if (cachedResponse) {
          return cachedResponse;
        }
        return fetch(event.request).catch(() => {
          return new Response('', {
            status: 503,
            statusText: 'Service Unavailable'
          });
        });
      })
    );
});

self.addEventListener('push', event => {
  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (error) {
    payload = { body: event.data ? event.data.text() : 'Ada notifikasi baru.' };
  }

  const title = payload.title || 'RSU Nurusyifa';
  const options = {
    body: payload.body || 'Ada notifikasi mLITE baru.',
    icon: payload.icon || 'assets/images/icon-192x192.png',
    badge: payload.badge || 'assets/images/icon-128x128.png',
    tag: payload.tag || 'mlite-notification',
    renotify: true,
    requireInteraction: true,
    data: { url: payload.url || '/' },
    vibrate: [200, 100, 200],
    actions: [{ action: 'open', title: 'Buka Persetujuan' }]
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const destination = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin).href;
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
      for (const client of windowClients) {
        if (client.url === destination && 'focus' in client) return client.focus();
      }
      return clients.openWindow ? clients.openWindow(destination) : undefined;
    })
  );
});
