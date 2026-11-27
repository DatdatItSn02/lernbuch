// Hàng chờ lượt chấm: mỗi lượt ghi vào IndexedDB của trình duyệt trước, rồi mới gửi máy chủ theo lô.
// Mất mạng, đóng tab hay tải lại trang thì lượt vẫn nằm đó; có mạng (sự kiện online, mở lại app, hẹn thử lại) thì tự gửi.
// Máy chủ bỏ lượt gửi trùng theo mã (uid), nên gửi lại một lô chưa chắc đã tới là an toàn.
import { useSyncExternalStore } from 'react';
import { api, LoiApi } from './api';
import type { CheDo } from './hoc';
import { quenDaTai } from './useApi';

export type LuotCham = {
    uid: string;
    card_id: number;
    session_id: number | null;
    mode: CheDo;
    rating: 0 | 1 | 2 | 3;
    reviewed_at_ms: number;
    duration_ms: number | null;
};

export type KetQuaLo = {
    received: number;
    duplicates: number;
    skipped: string[];
    coins: number;
    too_fast: number;
    cards: { id: number; box: number; due_at: number | null }[];
    today: { reviewed: number; goal: number; coins: number; streak: number };
};

export type TrangThaiHangCho = {
    /** Số lượt đang nằm chờ trên máy (chưa tới máy chủ). */
    cho: number;
    dangGui: boolean;
    /** Lần gửi gần nhất hỏng vì gì; null là ổn. */
    loi: 'mat-mang' | 'may-chu' | null;
};

/** Số lượt mỗi lô (máy chủ nhận tối đa 200). */
export const LO_GUI = 100;
/** Chấm xong chờ chừng này rồi mới gửi, để mấy lượt liền nhau đi chung một lô. */
export const CHO_GUI_MS = 1500;
/** Gửi hỏng thì thử lại sau 5 giây, rồi lâu dần tới 1 phút. */
const THU_LAI_MS = [5000, 15000, 30000, 60000];

const TEN_DB = 'lernbuch';
const BANG = 'luot-cham';

type Kho = {
    them(l: LuotCham): Promise<void>;
    tatCa(): Promise<LuotCham[]>;
    xoa(uids: string[]): Promise<void>;
};

let moDb: Promise<IDBDatabase> | null = null;

function db(): Promise<IDBDatabase> {
    moDb ??= new Promise<IDBDatabase>((xong, hong) => {
        const r = indexedDB.open(TEN_DB, 1);
        r.onupgradeneeded = () => {
            if (!r.result.objectStoreNames.contains(BANG)) r.result.createObjectStore(BANG, { keyPath: 'uid' });
        };
        r.onsuccess = () => xong(r.result);
        r.onerror = () => hong(r.error);
        r.onblocked = () => hong(new DOMException('IndexedDB blocked', 'InvalidStateError'));
    }).catch((e: unknown) => {
        moDb = null;
        throw e;
    });
    return moDb;
}

function giaoDich<T>(kieu: IDBTransactionMode, viec: (bang: IDBObjectStore) => IDBRequest<T> | void): Promise<T | undefined> {
    return db().then(
        (d) =>
            new Promise<T | undefined>((xong, hong) => {
                const gd = d.transaction(BANG, kieu);
                const yc = viec(gd.objectStore(BANG));
                gd.oncomplete = () => xong(yc ? yc.result : undefined);
                gd.onerror = () => hong(gd.error);
                gd.onabort = () => hong(gd.error);
            }),
    );
}

const khoIdb: Kho = {
    them: async (l) => {
        await giaoDich('readwrite', (b) => b.put(l));
    },
    tatCa: async () => ((await giaoDich<LuotCham[]>('readonly', (b) => b.getAll())) ?? []) as LuotCham[],
    xoa: async (uids) => {
        await giaoDich('readwrite', (b) => {
            for (const u of uids) b.delete(u);
        });
    },
};

// Trình duyệt không có IndexedDB (rất cũ, hoặc chế độ riêng tư chặn): giữ trong bộ nhớ, đóng tab thì mất
const nho = new Map<string, LuotCham>();
const khoNho: Kho = {
    them: async (l) => {
        nho.set(l.uid, l);
    },
    tatCa: async () => [...nho.values()],
    xoa: async (uids) => {
        for (const u of uids) nho.delete(u);
    },
};

let kho: Kho = typeof indexedDB === 'undefined' ? khoNho : khoIdb;

/** Chạy trên IndexedDB; hỏng (bị chặn, hết chỗ) thì chuyển hẳn sang bộ nhớ, giữ lượt đã có trong bộ nhớ. */
async function voiKho<T>(viec: (k: Kho) => Promise<T>): Promise<T> {
    try {
        return await viec(kho);
    } catch (e) {
        if (kho === khoNho) throw e;
        console.warn('Không dùng được IndexedDB, lượt chấm giữ trong bộ nhớ', e);
        kho = khoNho;
        return viec(kho);
    }
}

let trangThai: TrangThaiHangCho = { cho: 0, dangGui: false, loi: null };
const nguoiNghe = new Set<() => void>();
const nguoiNhan = new Set<(kq: KetQuaLo) => void>();

function dat(doi: Partial<TrangThaiHangCho>) {
    trangThai = { ...trangThai, ...doi };
    for (const f of nguoiNghe) f();
}

export const docTrangThai = () => trangThai;

export function nghe(f: () => void): () => void {
    nguoiNghe.add(f);
    return () => nguoiNghe.delete(f);
}

/** Gọi f mỗi khi máy chủ nhận xong một lô (vd cập nhật số đã ôn hôm nay). */
export function khiNhanLo(f: (kq: KetQuaLo) => void): () => void {
    nguoiNhan.add(f);
    return () => nguoiNhan.delete(f);
}

export function useHangCho(): TrangThaiHangCho {
    return useSyncExternalStore(nghe, docTrangThai);
}

let henGui: ReturnType<typeof setTimeout> | null = null;
let lanThuLai = 0;

function hen(ms: number) {
    if (henGui !== null) clearTimeout(henGui);
    henGui = setTimeout(() => {
        henGui = null;
        void guiDi();
    }, ms);
}

/** Thêm một lượt vào hàng chờ, rồi hẹn gửi. */
export async function themLuot(l: LuotCham): Promise<void> {
    await voiKho((k) => k.them(l));
    dat({ cho: (await voiKho((k) => k.tatCa())).length });
    hen(CHO_GUI_MS);
}

export const cacLuot = () => voiKho((k) => k.tatCa());

let dangChay: Promise<void> | null = null;

/** Gửi hết hàng chờ theo lô. Đang gửi thì trả lại lần gửi đó (không gửi hai lần cùng lúc). */
export function guiDi(): Promise<void> {
    dangChay ??= chay()
        .catch((e: unknown) => {
            // Lỗi ngoài dự tính (kho lưu hỏng hẳn…): báo trạng thái, hẹn thử lại, không để lỗi lọt ra ngoài
            console.warn('Gửi lượt chấm không được', e);
            dat({ dangGui: false, loi: 'may-chu' });
            hen(THU_LAI_MS[Math.min(lanThuLai++, THU_LAI_MS.length - 1)]);
        })
        .finally(() => {
            dangChay = null;
        });
    return dangChay;
}

async function chay(): Promise<void> {
    if (henGui !== null) {
        clearTimeout(henGui);
        henGui = null;
    }
    dat({ dangGui: true });
    try {
        for (;;) {
            const ds = (await voiKho((k) => k.tatCa())).sort((a, b) => a.reviewed_at_ms - b.reviewed_at_ms || (a.uid < b.uid ? -1 : 1));
            if (ds.length === 0) {
                lanThuLai = 0;
                dat({ loi: null });
                return;
            }
            const lo = ds.slice(0, LO_GUI);
            try {
                const { data } = await api.gui<{ data: KetQuaLo }>('/hoc/luot-cham', { reviews: lo });
                await voiKho((k) => k.xoa(lo.map((l) => l.uid)));
                lanThuLai = 0;
                dat({ loi: null, cho: ds.length - lo.length });
                quenDaTai();
                for (const f of nguoiNhan) f(data);
            } catch (e) {
                if (e instanceof LoiApi && (e.trangThai === 404 || e.trangThai === 422)) {
                    // Máy chủ không nhận lô này (lỗi của app, gửi lại cũng vậy): bỏ để hàng chờ không kẹt mãi
                    console.warn('Bỏ lô lượt chấm máy chủ không nhận', e);
                    await voiKho((k) => k.xoa(lo.map((l) => l.uid)));
                    continue;
                }
                dat({ loi: e instanceof LoiApi && e.trangThai === 0 ? 'mat-mang' : 'may-chu' });
                hen(THU_LAI_MS[Math.min(lanThuLai++, THU_LAI_MS.length - 1)]);
                return;
            }
        }
    } finally {
        dat({ dangGui: false, cho: (await voiKho((k) => k.tatCa())).length });
    }
}

let daBatDau = false;

/** Mở app: đếm lượt còn chờ từ lần trước và gửi; có mạng lại thì gửi ngay. Gọi một lần. */
export function batDauHangCho(): void {
    if (daBatDau) return;
    daBatDau = true;
    window.addEventListener('online', () => void guiDi());
    void guiDi();
}

/** Chỉ dùng trong test: xoá hết, quên trạng thái. */
export async function datLaiChoTest(): Promise<void> {
    if (henGui !== null) clearTimeout(henGui);
    henGui = null;
    await dangChay?.catch(() => {});
    nho.clear();
    if (moDb) {
        const d = await moDb.catch(() => null);
        d?.close();
        moDb = null;
    }
    if (typeof indexedDB !== 'undefined') {
        await new Promise<void>((xong) => {
            const r = indexedDB.deleteDatabase(TEN_DB);
            r.onsuccess = r.onerror = r.onblocked = () => xong();
        });
    }
    kho = typeof indexedDB === 'undefined' ? khoNho : khoIdb;
    lanThuLai = 0;
    daBatDau = false;
    nguoiNhan.clear();
    trangThai = { cho: 0, dangGui: false, loi: null };
    for (const f of nguoiNghe) f();
}
