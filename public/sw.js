/*
 * Offline helper for the installed app.
 * - Built files (scripts, styles, fonts) and icons are kept after first use.
 * - The New sale screen is saved each time it opens online, and shown from the saved copy when offline.
 * - Other pages need the internet; offline they show a short page with a button to the sale screen.
 * Bills made offline are kept by the sale screen itself and uploaded when the internet is back.
 */
const CACHE = 'cpos-offline-v1';
const SALE_SCREEN = '/sales/create';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(['/icons/app-192.png', '/manifest.webmanifest']).catch(() => null)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

const offlinePage = () => new Response(
    `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Offline</title>
    <style>body{font-family:system-ui,sans-serif;background:#f1f5f9;color:#0f172a;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
    .box{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:32px;max-width:420px;text-align:center}
    a{display:inline-block;margin-top:16px;background:#475569;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:600}</style></head>
    <body><div class="box"><h1 style="font-size:20px;margin:0 0 8px">No internet connection</h1>
    <p style="color:#475569;margin:0">This page needs the internet. You can keep selling: bills are saved on this computer and uploaded when the internet is back.</p>
    <p dir="rtl" style="color:#475569">انٹرنیٹ نہیں ہے۔ آپ فروخت جاری رکھ سکتے ہیں، بل اسی کمپیوٹر پر محفوظ ہوں گے۔</p>
    <a href="${SALE_SCREEN}">Open New sale</a></div></body></html>`,
    { headers: { 'Content-Type': 'text/html; charset=utf-8' } },
);

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Built files have unique names per version, so a saved copy is always right.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((saved) => saved || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }

                return response;
            })),
        );

        return;
    }

    if (request.mode !== 'navigate') {
        return;
    }

    const isSaleScreen = url.pathname === SALE_SCREEN;

    event.respondWith(
        fetch(request)
            .then((response) => {
                // Keep a fresh copy of the sale screen, but never a login page or a held-bill version.
                if (isSaleScreen && response.ok && !response.redirected && response.headers.get('X-Offline-Copy') === '1') {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(SALE_SCREEN, copy));
                }

                return response;
            })
            .catch(async () => {
                const saved = isSaleScreen ? await caches.match(SALE_SCREEN) : null;

                return saved || offlinePage();
            }),
    );
});
