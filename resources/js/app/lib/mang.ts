import { useSyncExternalStore } from 'react';

// Trình duyệt có báo là đang có mạng không (navigator.onLine, sự kiện online/offline). Có "mạng" chưa chắc gọi được máy chủ,
// nên chỉ dùng để báo cho người học; việc gửi, tải vẫn tự thử và tự lo khi hỏng.

function nghe(f: () => void): () => void {
    window.addEventListener('online', f);
    window.addEventListener('offline', f);
    return () => {
        window.removeEventListener('online', f);
        window.removeEventListener('offline', f);
    };
}

const doc = () => (typeof navigator === 'undefined' ? true : navigator.onLine !== false);

export function useCoMang(): boolean {
    return useSyncExternalStore(nghe, doc, () => true);
}
