/*
 * Tribio Caja service worker (ERP Fase 4d). Scope: /dashboard/caja only.
 * - The register page: network first, the last copy when there is no connection.
 * - Its scripts, styles and fonts: cached copy first, refreshed in the background.
 * - Everything else (JSON, sales, other pages) goes straight to the network and is never cached.
 */
const PAGES = 'tribio-pos-pages-v1';
const ASSETS = 'tribio-pos-assets-v1';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', event => {
    event.waitUntil(caches.keys()
        .then(keys => Promise.all(keys.filter(k => k.startsWith('tribio-pos-') && ![PAGES, ASSETS].includes(k)).map(k => caches.delete(k))))
        .then(() => self.clients.claim()));
});

function isRegisterPage(url) {
    return url.origin === self.location.origin && url.pathname === '/dashboard/caja';
}

function isAsset(request, url) {
    return ['script', 'style', 'font'].includes(request.destination)
        && (url.origin === self.location.origin ? url.pathname.startsWith('/build/') || url.pathname.startsWith('/pos/') : url.hostname === 'cdn.jsdelivr.net');
}

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);

    if (request.mode === 'navigate' && isRegisterPage(url)) {
        event.respondWith(fetch(request).then(response => {
            // Only a real register page is kept (not a redirect to the login or the lock screen's errors).
            if (response.ok && !response.redirected) {
                const copy = response.clone();
                caches.open(PAGES).then(cache => cache.put('/dashboard/caja', copy));
            }
            return response;
        }).catch(() => caches.open(PAGES).then(cache => cache.match('/dashboard/caja'))
            .then(cached => cached || new Response('<h1>Sin conexión</h1><p>Abre la caja una vez con internet para poder usarla sin conexión.</p>',
                { headers: { 'Content-Type': 'text/html; charset=utf-8' } }))));
        return;
    }

    if (isAsset(request, url)) {
        event.respondWith(caches.open(ASSETS).then(cache => cache.match(request).then(cached => {
            const fresh = fetch(request).then(response => {
                if (response.ok || response.type === 'opaque') cache.put(request, response.clone());
                return response;
            }).catch(() => cached);
            return cached || fresh;
        })));
    }
});
