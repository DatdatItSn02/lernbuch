import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import bang from '../../../../tests/chung/cham-bai-tap.json';
import { giaApi } from '../../test/fakeApi';
import { CHO_KET_QUA, chamCau, deTron, docCachCham, ghiCachCham, guiKetQua, guiLaiKetQua, soKetQuaCho, strip, tachDe, type CauBaiTap, type KetQuaGui } from './baiTap';

type Ca = { ten: string; cau: Pick<CauBaiTap, 'kind' | 'prompt' | 'answers' | 'options' | 'case_sensitive' | 'frag'>; tra_loi: string; diem: number };

describe('chấm câu bài tập (bảng ca thử chung với máy chủ)', () => {
    it.each((bang.ca as Ca[]).map((c) => [c.ten, c] as const))('%s', (_ten, ca) => {
        expect(chamCau(ca.cau, ca.tra_loi)).toBe(ca.diem);
    });

    it('bỏ khoảng trắng thừa và dấu câu cuối, giữ hoa thường', () => {
        expect(strip('  Hallo   Welt!! ')).toBe('Hallo Welt');
        expect(strip('Hallo .')).toBe('Hallo ');
    });
});

describe('đề bài', () => {
    it('tách chữ đậm và chỗ trống', () => {
        expect(tachDe('Ich sehe ____ **Mann**.')).toEqual([
            { kieu: 'chu', chu: 'Ich sehe ' },
            { kieu: 'trong', chu: '' },
            { kieu: 'chu', chu: ' ' },
            { kieu: 'dam', chu: 'Mann' },
            { kieu: 'chu', chu: '.' },
        ]);
        expect(tachDe('___ ist groß')).toEqual([{ kieu: 'trong', chu: '' }, { kieu: 'chu', chu: ' ist groß' }]);
        expect(tachDe('a_b')).toEqual([{ kieu: 'chu', chu: 'a_b' }]);
        expect(deTron('**Das** Frau heißt Anna.')).toBe('Das Frau heißt Anna.');
    });
});

describe('gửi kết quả', () => {
    const kq = (uid: string): KetQuaGui => ({ uid, mode: 'set', grading: 'instant', set: 'bt-LB3', started_at_ms: 1, finished_at_ms: 2, items: [{ id: 1, answer: 'Der' }] });
    const nhan = { duplicate: false, coins: 5, correct: 1, total: 1, too_fast: false, items: [{ id: 1, score: 2 }], today: { coins: 5, coins_today: 5, exercises_today: 1 } };

    beforeEach(() => window.localStorage.clear());
    afterEach(() => window.localStorage.clear());

    it('gửi được thì không giữ lại gì', async () => {
        const goi = giaApi({ 'POST /api/v1/bai-tap/ket-qua': () => ({ json: { data: nhan } }) });
        expect(await guiKetQua(kq('A'))).toEqual(nhan);
        expect(goi).toHaveLength(1);
        expect(goi[0].body).toEqual(kq('A'));
        expect(soKetQuaCho()).toBe(0);
    });

    it('mất mạng: giữ trên máy, lần sau gửi tiếp theo thứ tự; máy chủ lỗi tạm thì dừng chờ lần sau', async () => {
        giaApi({});
        globalThis.fetch = (() => Promise.reject(new TypeError('Failed to fetch'))) as typeof fetch;
        expect(await guiKetQua(kq('A'))).toBeNull();
        expect(await guiKetQua(kq('B'))).toBeNull();
        expect(soKetQuaCho()).toBe(2);

        let lan = 0;
        giaApi({ 'POST /api/v1/bai-tap/ket-qua': () => (++lan === 2 ? { status: 503, json: { message: 'down' } } : { json: { data: nhan } }) });
        await guiLaiKetQua();
        expect(JSON.parse(window.localStorage.getItem(CHO_KET_QUA) ?? '[]').map((k: KetQuaGui) => k.uid)).toEqual(['B']);

        const goi = giaApi({ 'POST /api/v1/bai-tap/ket-qua': () => ({ json: { data: { ...nhan, duplicate: true } } }) });
        await guiLaiKetQua();
        expect(goi.map((g) => (g.body as KetQuaGui).uid)).toEqual(['B']);
        expect(soKetQuaCho()).toBe(0);
    });

    it('máy chủ từ chối hẳn (422): báo lỗi, không giữ lại để gửi mãi', async () => {
        giaApi({ 'POST /api/v1/bai-tap/ket-qua': () => ({ status: 422, json: { message: 'sai', errors: { items: ['Không có câu nào hợp lệ để ghi'] } } }) });
        await expect(guiKetQua(kq('A'))).rejects.toMatchObject({ trangThai: 422 });
        expect(soKetQuaCho()).toBe(0);

        window.localStorage.setItem(CHO_KET_QUA, JSON.stringify([kq('C')]));
        await guiLaiKetQua();
        expect(soKetQuaCho()).toBe(0);
    });

    it('hàng chờ hỏng (chữ lạ trong bộ nhớ trình duyệt) thì coi như trống', async () => {
        window.localStorage.setItem(CHO_KET_QUA, '{hỏng');
        expect(soKetQuaCho()).toBe(0);
        const goi = giaApi({});
        await guiLaiKetQua();
        expect(goi).toHaveLength(0);
    });

    it('nhớ cách chấm trên máy; giá trị lạ về chấm ngay từng câu', () => {
        expect(docCachCham()).toBe('instant');
        ghiCachCham('sheet');
        expect(docCachCham()).toBe('sheet');
        window.localStorage.setItem('lernbuch.baitap.cachcham', 'la');
        expect(docCachCham()).toBe('instant');
    });
});
