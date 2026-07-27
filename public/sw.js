// Service worker minimal — hanya supaya PWA installable, tanpa caching/offline
self.addEventListener("install", () => {
    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil(self.clients.claim());
});

// Sengaja tidak ada event 'fetch' -> semua request tetap langsung ke jaringan seperti biasa
