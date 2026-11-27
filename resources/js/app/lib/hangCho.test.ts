import 'fake-indexeddb/auto';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { giaApi } from '../../test/fakeApi';
import { cacLuot, datLaiChoTest, docTrangThai, guiDi, khiNhanLo, LO_GUI, themLuot, type LuotCham } from './hangCho';
import { ulid } from './ulid';

// Hàng chờ lượt chấm (mục tự kiểm 5 phần trên máy): lượt nằm trong IndexedDB tới khi máy chủ nhận; mất mạng thì giữ, có mạng thì gửi.

const GOC = 1_764_208_800_000;
const luot = (i: number, doi: Partial<LuotCham> = {}): LuotCham => ({
    uid: ulid(GOC + i * 1000, () => new Uint8Array(16).fill(i % 256)),
    card_id: 100 + i,
    session_id: 7,
    mode: 'flashcard',
    rating: 2,
    reviewed_at_ms: GOC + i * 1000,
    duration_ms: 3000,
    ...doi,
});
const nhan = (body: unknown) => {
    const n = (body as { reviews: unknown[] }).reviews.length;
    return { json: { data: { received: n, duplicates: 0, skipped: [], coins: 5 * n, too_fast: 0, cards: [], today: { reviewed: n, goal: 20, coins: 5 * n, streak: 1 } } } };
};

beforeEach(async () => {
    await datLaiChoTest();
});

afterEach(async () => {
    await datLaiChoTest();
});

describe('hàng chờ lượt chấm', () => {
    it('lượt nằm trong IndexedDB và đếm số chờ; máy chủ nhận xong thì xoá, gửi theo giờ chấm', async () => {
        const goi = giaApi({ 'POST /api/v1/hoc/luot-cham': nhan });
        const nhanDuoc = vi.fn();
        khiNhanLo(nhanDuoc);

        await themLuot(luot(2));
        await themLuot(luot(1));
        expect(docTrangThai().cho).toBe(2);
        expect((await cacLuot()).map((l) => l.card_id).sort()).toEqual([101, 102]);

        await guiDi();
        expect(goi).toHaveLength(1);
        expect((goi[0].body as { reviews: LuotCham[] }).reviews.map((l) => l.card_id)).toEqual([101, 102]);
        expect(await cacLuot()).toEqual([]);
        expect(docTrangThai()).toEqual({ cho: 0, dangGui: false, loi: null });
        expect(nhanDuoc).toHaveBeenCalledWith(expect.objectContaining({ received: 2, coins: 10 }));
    });

    it('nhiều lượt thì chia lô ' + LO_GUI + ', gửi lần lượt', async () => {
        const goi = giaApi({ 'POST /api/v1/hoc/luot-cham': nhan });
        for (let i = 0; i < 250; i++) await themLuot(luot(i));

        await guiDi();
        expect(goi.map((g) => (g.body as { reviews: unknown[] }).reviews.length)).toEqual([100, 100, 50]);
        expect(docTrangThai().cho).toBe(0);
    });

    it('mất mạng thì giữ lượt, báo mất mạng; có mạng lại (sự kiện online) thì gửi', async () => {
        let coMang = false;
        const goi = giaApi({ 'POST /api/v1/hoc/luot-cham': nhan });
        const fetchGia = globalThis.fetch;
        vi.stubGlobal('fetch', (...args: Parameters<typeof fetch>) => (coMang ? fetchGia(...args) : Promise.reject(new TypeError('Failed to fetch'))));
        const { batDauHangCho } = await import('./hangCho');

        await themLuot(luot(1));
        await guiDi();
        expect(docTrangThai()).toEqual({ cho: 1, dangGui: false, loi: 'mat-mang' });
        expect(await cacLuot()).toHaveLength(1);

        batDauHangCho();
        await guiDi(); // lần gửi lúc mở app: vẫn mất mạng
        coMang = true;
        window.dispatchEvent(new Event('online'));
        await vi.waitFor(() => expect(docTrangThai().cho).toBe(0));
        expect(goi).toHaveLength(1);
        expect(docTrangThai().loi).toBeNull();
    });

    it('máy chủ lỗi (500) thì giữ lượt để gửi lại; 422 (lô hỏng) thì bỏ lô đó, gửi tiếp lô sau', async () => {
        let lan = 0;
        giaApi({ 'POST /api/v1/hoc/luot-cham': () => ({ status: 500, json: { message: 'lỗi' } }) });
        await themLuot(luot(1));
        await guiDi();
        expect(docTrangThai()).toMatchObject({ cho: 1, loi: 'may-chu' });

        const goi = giaApi({
            'POST /api/v1/hoc/luot-cham': (body) => (lan++ === 0 ? { status: 422, json: { message: 'sai', errors: { 'reviews.0.uid': ['sai'] } } } : nhan(body)),
        });
        vi.spyOn(console, 'warn').mockImplementation(() => {});
        for (let i = 2; i < 2 + LO_GUI; i++) await themLuot(luot(i));
        await guiDi();
        expect(goi).toHaveLength(2);
        expect(await cacLuot()).toEqual([]);
        expect(docTrangThai().loi).toBeNull();
    });

    it('gọi gửi nhiều lần cùng lúc thì chỉ gửi một lần', async () => {
        const goi = giaApi({ 'POST /api/v1/hoc/luot-cham': nhan });
        await themLuot(luot(1));
        await Promise.all([guiDi(), guiDi(), guiDi()]);
        expect(goi).toHaveLength(1);
    });

    it('tải lại trang (mở lại module): lượt chưa gửi vẫn còn trong IndexedDB', async () => {
        giaApi({ 'POST /api/v1/hoc/luot-cham': () => ({ status: 503, json: {} }) });
        await themLuot(luot(1));
        await themLuot(luot(2));
        await guiDi();

        vi.resetModules();
        const moi = await import('./hangCho');
        expect((await moi.cacLuot()).map((l) => l.card_id).sort()).toEqual([101, 102]);
        await moi.datLaiChoTest();
    });

    it('IndexedDB bị chặn thì giữ lượt trong bộ nhớ, vẫn gửi được', async () => {
        vi.spyOn(console, 'warn').mockImplementation(() => {});
        vi.spyOn(indexedDB, 'open').mockImplementation(() => {
            throw new DOMException('chặn', 'InvalidStateError');
        });
        const goi = giaApi({ 'POST /api/v1/hoc/luot-cham': nhan });

        await themLuot(luot(1));
        expect(docTrangThai().cho).toBe(1);
        await guiDi();
        expect(goi).toHaveLength(1);
        expect(await cacLuot()).toEqual([]);
    });
});

describe('mã lượt ULID', () => {
    it('26 chữ Crockford base32, phần đầu là thời gian nên xếp theo chữ là xếp theo giờ', async () => {
        const { LA_ULID } = await import('./ulid');
        const a = ulid(GOC);
        const b = ulid(GOC + 1);
        expect(a).toMatch(LA_ULID);
        expect(a < b).toBe(true);
        expect(ulid(0, () => new Uint8Array(16))).toBe('0'.repeat(26));
        expect(ulid(2 ** 48 - 1, () => new Uint8Array(16).fill(31))).toBe('7ZZZZZZZZZZZZZZZZZZZZZZZZZ');
    });

    it('tạo nhiều mã trong cùng mili giây thì vẫn tăng dần theo thứ tự tạo', async () => {
        const { ulidTangDan } = await import('./ulid');
        const ds = Array.from({ length: 50 }, () => ulidTangDan(GOC));
        expect([...ds].sort()).toEqual(ds);
        expect(new Set(ds).size).toBe(50);
        expect(ds.every((u) => u.startsWith(ds[0].slice(0, 10)))).toBe(true);
        // Đồng hồ lùi lại: vẫn giữ mốc cũ để mã không giảm
        expect(ulidTangDan(GOC - 5000) > ds[49]).toBe(true);
        expect(ulidTangDan(GOC + 1).slice(0, 10) > ds[0].slice(0, 10)).toBe(true);
    });

    it('thời gian âm, lẻ hay quá lớn thì báo lỗi', () => {
        expect(() => ulid(-1)).toThrow(RangeError);
        expect(() => ulid(1.5)).toThrow(RangeError);
        expect(() => ulid(2 ** 48)).toThrow(RangeError);
    });
});
