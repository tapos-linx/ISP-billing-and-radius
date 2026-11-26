const CACHE_PREFIX = 'isp-selfcare-';
const CACHE_NAME = `${CACHE_PREFIX}v2.5.1-walled-garden`;

const PRECACHE_ASSETS = [
  '/',
  '/self-care',
  '/manifest.webmanifest',
];

const WALLED_GARDEN_FALLBACK_PAYLOAD = {
  status: 'offline_walled_garden',
  offline: true,
  message: 'Subscriber session restricted to ISP Walled Garden isolation network.',
  billing: {
    payment_required: true,
    bkash_merchant_number: '01700000000',
    nagad_merchant_number: '01800000000',
    support_hotline: '16200',
  },
};

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => Promise.all(PRECACHE_ASSETS.map((asset) => cache.add(asset).catch(() => undefined))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => Promise.all(cacheNames.map((name) => {
        if (name.startsWith(CACHE_PREFIX) && name !== CACHE_NAME) {
          return caches.delete(name);
        }
        return undefined;
      })))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response.ok && response.headers.get('X-ISP-PWA-Cacheable') === 'true') {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          }
          return response;
        })
        .catch(async () => {
          const cached = await caches.match(request);
          if (cached) {
            return cached;
          }
          return new Response(JSON.stringify(WALLED_GARDEN_FALLBACK_PAYLOAD), {
            status: 503,
            headers: {
              'Content-Type': 'application/json',
              'X-Walled-Garden-Offline': 'true',
              'Cache-Control': 'no-store',
            },
          });
        })
    );
    return;
  }

  event.respondWith(
    caches.match(request).then(async (cached) => {
      if (cached) {
        fetch(request)
          .then((response) => {
            if (response.ok) {
              const clone = response.clone();
              caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
            }
          })
          .catch(() => undefined);
        return cached;
      }

      try {
        const response = await fetch(request);
        if (response.ok) {
          const clone = response.clone();
          await caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
        }
        return response;
      } catch {
        if (request.mode === 'navigate') {
          const shell = await caches.match('/self-care') || await caches.match('/');
          if (shell) {
            return shell;
          }
        }
        return Response.error();
      }
    })
  );
});
