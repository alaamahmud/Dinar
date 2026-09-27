// عامل خدمة بسيط: يتيح تثبيت المنصة كتطبيق، ويعرض آخر نسخة محفوظة عند انقطاع الإنترنت.
const CACHE = 'dinar-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET' || new URL(req.url).origin !== location.origin) return;

    event.respondWith(
        fetch(req)
            .then((res) => {
                if (res.ok && (req.mode === 'navigate' || req.url.includes('/build/'))) {
                    const copy = res.clone();
                    caches.open(CACHE).then((c) => c.put(req, copy));
                }
                return res;
            })
            .catch(() => caches.match(req)),
    );
});
