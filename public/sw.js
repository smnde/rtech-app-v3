// Service worker RS Retail.
// Strategi: network-first untuk navigasi (Inertia butuh respons segar + header
// X-Inertia), cache-first untuk aset ber-hash (immutable). POST/PATCH/DELETE
// tidak pernah di-cache.

const VERSION = 'v2';
const STATIC_CACHE = `rsr-static-${VERSION}`;
const PAGE_CACHE = `rsr-page-${VERSION}`;
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) =>
            cache.addAll([
                OFFLINE_URL,
                '/manifest.webmanifest',
                '/icons/icon-512.png',
                '/icons/icon-512.png',
            ])
        ).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => !key.endsWith(VERSION))
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

const isAsset = (url) =>
    url.pathname.startsWith('/build/') ||
    url.pathname.startsWith('/icons/') ||
    /\.(css|js|mjs|png|jpg|jpeg|svg|webp|gif|ico|woff2?|ttf)$/.test(url.pathname);

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Hanya GET yang aman di-cache; transaksi kasir jangan pernah dari cache.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Inertia partial reload — biarkan lewat, responsnya berbentuk JSON khusus.
    if (request.headers.get('X-Inertia')) return;

    // Aset ber-hash: cache-first.
    if (isAsset(url) && !request.headers.has('Range')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Navigasi halaman: network-first, fallback ke cache lalu halaman offline.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(PAGE_CACHE).then((cache) => cache.put(request, copy));
                        return response;
                    }
                    return response;
                })
                .catch(() =>
                    caches.match(request).then((cached) => cached ?? caches.match(OFFLINE_URL))
                )
        );
    }
});

// Terima perintah dari halaman (mis. tombol "Muat ulang" saat ada versi baru).
self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') self.skipWaiting();
});
