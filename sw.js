const CACHE_NAME = 'kaminarfisio-v20260413';
const ASSETS = [
    './css/style.css',
    './js/app.js',
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
    'https://fonts.googleapis.com/icon?family=Material+Icons+Outlined',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'
];

// Install Event
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            console.log('SW: Pre-caching assets');
            return cache.addAll(ASSETS);
        }).then(() => self.skipWaiting())
    );
});

// Activate Event
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(keys
                .filter(key => key !== CACHE_NAME)
                .map(key => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Event
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    const isPhp = url.pathname.endsWith('.php') || url.pathname.endsWith('/') || url.pathname === '';
    const isApi = url.pathname.includes('/api/');
    const isSameOrigin = url.origin === self.location.origin;

    // 1. API: Network Only
    if (isApi) return;

    // 2. PHP pages and dynamic navigations: Network only
    if (isSameOrigin && isPhp) {
        event.respondWith(fetch(event.request));
        return;
    }

    // 3. Static assets: Cache First
    event.respondWith(
        caches.match(event.request).then(cacheRes => {
            const fetchRes = fetch(event.request).then(networkRes => {
                if (networkRes && (networkRes.ok || networkRes.type === 'opaque')) {
                    const copy = networkRes.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
                }
                return networkRes;
            });
            return cacheRes || fetchRes;
        })
    );
});
