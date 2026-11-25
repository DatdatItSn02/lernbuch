// Service worker khung của G0: chỉ để app cài được ra màn hình chính.
// Chưa lưu gì để học offline; phần đó làm ở G1 và G3.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {
    // Để trình duyệt tự tải như bình thường.
});
