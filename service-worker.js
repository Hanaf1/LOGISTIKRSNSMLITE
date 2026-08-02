const cacheName = 'cache-v3';
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
