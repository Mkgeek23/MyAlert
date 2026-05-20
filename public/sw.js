/**
 * MyAlert Service Worker
 *
 * Provides offline caching for static assets and app shell.
 * Network-first strategy for API/page requests, cache-first for static assets.
 */

const CACHE_NAME = 'myalert-v1';
const STATIC_ASSETS = [
    './',
    './assets/css/app.css',
    './assets/icons/icon-192.png',
    './assets/icons/icon-512.png',
    './manifest.json'
];

// Install: pre-cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

// Activate: clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

// Fetch: network-first for pages, cache-first for static assets
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Skip non-GET requests
    if (event.request.method !== 'GET') return;

    // Skip external requests (CDN, Discord, etc.)
    if (url.origin !== self.location.origin) return;

    // Static assets: cache-first
    if (url.pathname.match(/\.(css|js|png|jpg|svg|ico|woff2?)$/)) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                return cached || fetch(event.request).then((response) => {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                    return response;
                });
            })
        );
        return;
    }

    // Pages: network-first with cache fallback
    event.respondWith(
        fetch(event.request)
            .then((response) => {
                const clone = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                return response;
            })
            .catch(() => caches.match(event.request))
    );
});
