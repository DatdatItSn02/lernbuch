// Kho trên máy (IndexedDB "lernbuch") dùng chung: hàng chờ lượt chấm, chủ đề đã tải để học khi mất mạng, phiên học đang dở.
// Bản 1 chỉ có hàng chờ; bản 2 thêm hai bảng kia, giữ nguyên lượt chấm đang chờ của bản cũ.

export const TEN_DB = 'lernbuch';
export const PHIEN_BAN_DB = 2;

/** Lượt chấm chờ gửi, khoá là uid. */
export const BANG_LUOT = 'luot-cham';
/** Chủ đề đã tải để học khi mất mạng, khoá là slug. */
export const BANG_CHU_DE = 'chu-de-tai';
/** Phiên học đang dở (lập lúc có mạng), khoá là "hien-tai". */
export const BANG_PHIEN = 'phien-do';

/** Tab khác còn mở kho bản cũ thì chờ chừng này cho tab đó đóng kho, quá thì coi như không dùng được. */
const CHO_TAB_CU_MS = 10_000;

let moDb: Promise<IDBDatabase> | null = null;

/** Mở kho (một lần cho cả app). Kho bị chặn, hỏng hay trình duyệt không có IndexedDB thì lỗi; nơi gọi tự lo phương án khác. */
export function moKho(): Promise<IDBDatabase> {
    moDb ??= new Promise<IDBDatabase>((xong, hong) => {
        if (typeof indexedDB === 'undefined') {
            hong(new DOMException('IndexedDB unavailable', 'NotSupportedError'));
            return;
        }
        const r = indexedDB.open(TEN_DB, PHIEN_BAN_DB);
        let henBiChan: ReturnType<typeof setTimeout> | null = null;
        r.onupgradeneeded = () => {
            const d = r.result;
            if (!d.objectStoreNames.contains(BANG_LUOT)) d.createObjectStore(BANG_LUOT, { keyPath: 'uid' });
            if (!d.objectStoreNames.contains(BANG_CHU_DE)) d.createObjectStore(BANG_CHU_DE, { keyPath: 'slug' });
            if (!d.objectStoreNames.contains(BANG_PHIEN)) d.createObjectStore(BANG_PHIEN, { keyPath: 'khoa' });
        };
        r.onsuccess = () => {
            if (henBiChan !== null) clearTimeout(henBiChan);
            const d = r.result;
            // Bản app mới hơn mở kho bản mới hơn: đóng kho này để tab kia không phải chờ
            d.onversionchange = () => {
                d.close();
                moDb = null;
            };
            xong(d);
        };
        r.onerror = () => hong(r.error);
        // Một tab cũ còn giữ kho bản 1: chờ tab đó đóng (thường là tải lại), quá lâu thì báo lỗi
        r.onblocked = () => {
            henBiChan ??= setTimeout(() => hong(new DOMException('IndexedDB blocked', 'InvalidStateError')), CHO_TAB_CU_MS);
        };
    }).catch((e: unknown) => {
        moDb = null;
        throw e;
    });
    return moDb;
}

/** Một giao dịch trên một bảng; xong thì trả kết quả của yêu cầu (nếu có). */
export function giaoDich<T>(bang: string, kieu: IDBTransactionMode, viec: (b: IDBObjectStore) => IDBRequest<T> | void): Promise<T | undefined> {
    return moKho().then(
        (d) =>
            new Promise<T | undefined>((xong, hong) => {
                const gd = d.transaction(bang, kieu);
                const yc = viec(gd.objectStore(bang));
                gd.oncomplete = () => xong(yc ? yc.result : undefined);
                gd.onerror = () => hong(gd.error);
                gd.onabort = () => hong(gd.error);
            }),
    );
}

/** Chỉ dùng trong test: đóng và xoá hẳn kho. */
export async function xoaKhoChoTest(): Promise<void> {
    if (moDb) {
        const d = await moDb.catch(() => null);
        d?.close();
        moDb = null;
    }
    if (typeof indexedDB === 'undefined') return;
    await new Promise<void>((xong) => {
        const r = indexedDB.deleteDatabase(TEN_DB);
        r.onsuccess = r.onerror = r.onblocked = () => xong();
    });
}
