import { useSyncExternalStore } from 'react';

// Có mạng không, để báo cho người học: trình duyệt báo mất mạng (navigator.onLine, sự kiện online/offline), hoặc
// trình duyệt vẫn báo có mạng mà lần gọi máy chủ gần nhất không tới được, hay chỉ có bản service worker đã lưu
// (mạng chập chờn, Wi-Fi không ra được Internet). Lần gọi máy chủ kế tiếp tới được thì hết báo.
// Chỉ để hiện dòng báo; việc gửi, tải vẫn tự thử và tự lo khi hỏng.

let khongToiMayChu = false;
const nguoiNghe = new Set<() => void>();

function bao() {
    for (const f of nguoiNghe) f();
}

function nghe(f: () => void): () => void {
    nguoiNghe.add(f);
    window.addEventListener('online', f);
    window.addEventListener('offline', f);
    return () => {
        nguoiNghe.delete(f);
        window.removeEventListener('online', f);
        window.removeEventListener('offline', f);
    };
}

const doc = () => (typeof navigator === 'undefined' || navigator.onLine !== false) && !khongToiMayChu;

/** api.ts gọi sau mỗi lần gọi máy chủ: toi = có câu trả lời mới của máy chủ (không phải bản lưu, không phải lỗi mạng). */
export function baoMayChu(toi: boolean): void {
    if (khongToiMayChu === !toi) return;
    khongToiMayChu = !toi;
    bao();
}

export function useCoMang(): boolean {
    return useSyncExternalStore(nghe, doc, () => true);
}

/** Chỉ dùng trong test. */
export function datLaiMangChoTest(): void {
    khongToiMayChu = false;
}
