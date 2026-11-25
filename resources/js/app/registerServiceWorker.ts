/** Đăng ký service worker ở phạm vi gốc để app cài được (PWA). Lỗi đăng ký không chặn app. */
export function registerServiceWorker(): void {
    if (!('serviceWorker' in navigator) || import.meta.env.DEV) {
        return;
    }
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((error: unknown) => {
            console.warn('Không đăng ký được service worker', error);
        });
    });
}
