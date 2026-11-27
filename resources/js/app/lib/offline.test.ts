import 'fake-indexeddb/auto';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { giaApi, type Tuyen } from '../../test/fakeApi';
import { giaCaches } from '../../test/fakeCaches';
import { phienMau, theHoc } from '../../test/noiDungGia';
import { cacLuot, datLaiChoTest, guiDi, themLuot, type LuotCham } from './hangCho';
import type { TheHoc } from './hoc';
import {
    KHO_API,
    KHO_AUDIO,
    MOI_TOI_DA,
    ON_TOI_DA,
    batDauOffline,
    boPhienDo,
    cacGoi,
    capNhatTuMayChu,
    chamTrenMay,
    danhDauDaCham,
    datLaiOfflineChoTest,
    donMay,
    giuPhien,
    goiCua,
    lapPhienTrenMay,
    phamVi,
    phienDoCon,
    phienKhiMatMang,
    taiChuDe,
    xenKe,
    xoaChuDeTai,
    type GoiChuDe,
    type TienDoTai,
} from './offline';
import { dauNgay } from './srs';
import { ulidTangDan } from './ulid';

const TZ = 'Asia/Ho_Chi_Minh';
/** Thứ Sáu 27/11/2026 09:00 giờ Việt Nam; đầu ngày 00:00, đầu ngày mai 00:00 hôm sau. */
const NOW = 1795744800;
const DAU_NGAY = 1795712400;
const NGAY_MAI = 1795798800;

const the = (id: number, box = 0, due: number | null = null, doi: Partial<TheHoc> = {}): TheHoc => ({ ...theHoc(id, box), due_at: due, ...doi });

function goi(slug: string, cards: TheHoc[], doi: Partial<GoiChuDe> = {}): GoiChuDe {
    return { slug, topic: { slug, name: `Chủ đề ${slug}`, collection: 'vocab' }, timezone: TZ, new_per_session: 10, saved_at: NOW - 3600, cards, ...doi };
}

const tuyenGoi = (...ds: GoiChuDe[]): Record<string, Tuyen> =>
    Object.fromEntries(ds.map((g) => [`GET /api/v1/hoc/tai/${g.slug}`, () => ({ json: { data: { ...g, slug: undefined } } })]));

/** Tải các gói qua API giả (như bấm Tải để học khi không có mạng). */
async function taiGia(...ds: GoiChuDe[]) {
    giaApi(tuyenGoi(...ds));
    for (const g of ds) await taiChuDe(g.slug);
}

const luot = (card_id: number): LuotCham => ({
    uid: ulidTangDan(Date.now()),
    card_id,
    session_id: null,
    mode: 'flashcard',
    rating: 2,
    reviewed_at_ms: Date.now(),
    duration_ms: 3000,
});

beforeEach(async () => {
    datLaiOfflineChoTest();
    await datLaiChoTest();
});

afterEach(async () => {
    datLaiOfflineChoTest();
    await datLaiChoTest();
});

describe('lập phiên trên máy như máy chủ (PhienHoc.php)', () => {
    it('thẻ đến hạn trước đầu ngày mai, hạn sớm trước; từ mới theo thứ tự trong chủ đề, đủ số mỗi phiên, xen kẽ', () => {
        const nhieu = [{ id: 8, front: 'zahlen', article: null, word: 'zahlen', gender: null, meanings: { vi: 'trả tiền' } }];
        const zahl = the(10, 2, NOW - 86400, { options: nhieu });
        const g = goi('so', [the(3, 3, NGAY_MAI + 43200), the(2, 1, NOW + 3600), the(8), zahl, the(9), the(11)], { new_per_session: 2 });
        const p = lapPhienTrenMay([g], { cheDo: 'quiz', chiOn: false, now: NOW }, { slug: 'so', name: 'Số' });

        // Ôn: die Zahl (quá hạn từ hôm qua), das Brot (hạn chiều nay); das Mehl hạn ngày mai thì chưa. Mới: zahlen, zählen
        expect(p.cards.map((c) => c.id)).toEqual([10, 8, 2, 9]);
        expect(p).toMatchObject({ id: null, mode: 'quiz', started_at: NOW, offline: true, topic: { slug: 'so', name: 'Số' }, counts: { review: 2, new: 2 } });
        expect(p.new_topic).toEqual({ slug: 'so-dem-tu-vung', name: 'Số đếm' });
        // Thẻ giữ nguyên như trong gói, kể cả đáp án nhiễu (Quiz, Ghép cặp, Hỗn hợp cần)
        expect(p.cards[0]).toEqual(zahl);
    });

    it('chỉ ôn thì không có từ mới; Đặt câu chỉ lấy thẻ có câu ví dụ chính', () => {
        const g = goi('banh', [the(1), the(2), the(10, 2, NOW - 60), the(4, 1, NOW - 120, { example: { sentence: 'Wir backen Brot.', translations: {} } })]);
        expect(lapPhienTrenMay([g], { cheDo: 'flashcard', chiOn: true, now: NOW }, null).cards.map((c) => c.id)).toEqual([4, 10]);
        expect(lapPhienTrenMay([g], { cheDo: 'sentence', chiOn: false, now: NOW }, null).cards.map((c) => c.id)).toEqual([4, 1]);
    });

    it(`ôn tối đa ${ON_TOI_DA} thẻ; "tất cả từ mới" lấy hết từ mới của một chủ đề rồi dừng (tối đa ${MOI_TOI_DA})`, () => {
        const nhieu = Array.from({ length: 60 }, (_, i) => ({ ...the(11, 1, NOW - i), id: 1000 + i }));
        const a = goi('a', [...nhieu, the(8), the(9)], { new_per_session: 0, saved_at: NOW - 7200 });
        const b = goi('b', [the(1), the(2)], { saved_at: NOW - 3600 });
        const p = lapPhienTrenMay([a, b], { cheDo: 'flashcard', chiOn: false, now: NOW }, null);

        expect(p.counts).toEqual({ review: ON_TOI_DA, new: 2 });
        expect(p.cards[0].id).toBe(1059);
        expect(p.cards.filter((c) => c.box === 0).map((c) => c.id)).toEqual([8, 9]);
    });

    it('xen kẽ như máy chủ: cứ k thẻ ôn thì một từ mới, dư để cuối', () => {
        expect(xenKe([1, 2, 3, 4, 5], [91, 92])).toEqual([1, 2, 91, 3, 4, 92, 5]);
        expect(xenKe([1], [91, 92, 93])).toEqual([1, 91, 92, 93]);
        expect(xenKe([], [91])).toEqual([91]);
        expect(xenKe([1, 2], [])).toEqual([1, 2]);
    });
});

describe('chủ đề đã tải', () => {
    it('tải chủ đề: lưu gói trên máy, chép audio của thẻ vào kho audio (file lỗi thì bỏ), báo tiến độ; tải lại thì thay gói', async () => {
        const kho = giaCaches();
        const g = goi('so-dem', [the(8, 0, null, { audio: '/audio/a.mp3' }), the(9, 0, null, { audio: '/audio/b.mp3' }), the(10)]);
        giaApi({ ...tuyenGoi(g), 'GET /audio/a.mp3': () => ({ json: 'mp3' }) });
        const tienDo: TienDoTai[] = [];

        expect(await taiChuDe('so-dem', (t) => tienDo.push(t))).toEqual({ slug: 'so-dem', name: 'Chủ đề so-dem', so_the: 3, saved_at: NOW - 3600, so_audio: 1 });
        expect(tienDo[0]).toEqual({ buoc: 'the', xong: 0, tong: 1 });
        expect(tienDo.at(-1)).toEqual({ buoc: 'audio', xong: 2, tong: 2 });
        expect((await goiCua('so-dem'))?.cards.map((c) => c.id)).toEqual([8, 9, 10]);
        expect([...(kho.get(KHO_AUDIO)?.keys() ?? [])]).toEqual(['http://localhost:3000/audio/a.mp3']);

        giaApi(tuyenGoi(goi('so-dem', [the(10, 3, NOW + 86400)], { saved_at: NOW })));
        await taiChuDe('so-dem');
        expect((await cacGoi()).map((x) => [x.slug, x.cards.length, x.saved_at])).toEqual([['so-dem', 1, NOW]]);
    });

    it('không tải được (mất mạng, máy chủ lỗi) thì báo lỗi, không lưu gì', async () => {
        giaApi({ 'GET /api/v1/hoc/tai/so-dem': () => ({ status: 500, json: { code: 'loi', message: '' } }) });
        await expect(taiChuDe('so-dem')).rejects.toMatchObject({ trangThai: 500 });
        expect(await cacGoi()).toEqual([]);
    });

    it('xoá chủ đề: xoá gói và audio chỉ chủ đề đó dùng; audio chủ đề khác còn dùng thì giữ', async () => {
        const kho = giaCaches();
        const a = goi('a', [the(8, 0, null, { audio: '/audio/chung.mp3' }), the(9, 0, null, { audio: '/audio/rieng.mp3' })]);
        const b = goi('b', [the(10, 0, null, { audio: '/audio/chung.mp3' })]);
        giaApi({ ...tuyenGoi(a, b), 'GET /audio/chung.mp3': () => ({ json: 1 }), 'GET /audio/rieng.mp3': () => ({ json: 2 }) });
        await taiChuDe('a');
        await taiChuDe('b');

        await xoaChuDeTai('a');
        expect((await cacGoi()).map((g) => g.slug)).toEqual(['b']);
        expect([...(kho.get(KHO_AUDIO)?.keys() ?? [])]).toEqual(['http://localhost:3000/audio/chung.mp3']);
    });

    it('chấm trên máy đổi mức và hạn ôn của thẻ trong gói như máy chủ; máy chủ nhận lượt thì lấy mức máy chủ tính', async () => {
        await taiGia(goi('so', [the(10, 2, NOW - 86400), the(8)]));

        await chamTrenMay(10, 2, NOW);
        await chamTrenMay(8, 0, NOW);
        const sau = (await goiCua('so'))!.cards;
        // Tốt từ mức 2: lên mức 3, ôn sau 7 ngày (12 giờ trưa ngày đó theo giờ người học); từ mới Quên: mức 1, ôn lại sau 10 phút
        expect(sau[0]).toMatchObject({ box: 3, due_at: dauNgay(NOW + 7 * 86400 + 43200, TZ) });
        expect(sau[1]).toMatchObject({ box: 1, due_at: NOW + 600 });

        await capNhatTuMayChu([{ id: 10, box: 1, due_at: NOW + 60 }]);
        expect((await goiCua('so'))!.cards[0]).toMatchObject({ box: 1, due_at: NOW + 60 });
    });

    it('mở app (batDauOffline): mỗi lô lượt chấm máy chủ nhận xong thì sửa mức thẻ trong gói', async () => {
        await taiGia(goi('so', [the(10, 2, NOW - 86400)]));
        batDauOffline();
        giaApi({
            'POST /api/v1/hoc/luot-cham': () => ({
                json: { data: { received: 1, duplicates: 0, skipped: [], coins: 5, too_fast: 0, cards: [{ id: 10, box: 4, due_at: NOW + 999 }], today: { reviewed: 1, goal: 20, coins: 5, streak: 1 } } },
            }),
        });
        await themLuot(luot(10));
        await guiDi();
        await vi.waitFor(async () => expect((await goiCua('so'))!.cards[0]).toMatchObject({ box: 4, due_at: NOW + 999 }));
    });
});

describe('phiên đang dở và khi mất mạng', () => {
    const PHIEN = () => phienMau([theHoc(10, 2), theHoc(8), theHoc(2, 1), theHoc(9)], { started_at: NOW - 600 });

    it('phiên máy chủ lập được giữ; mất mạng mở lại đúng phạm vi thì học tiếp phần chưa chấm, giữ mã phiên', async () => {
        await giuPhien(PHIEN(), phamVi(null, false, 'flashcard'));
        await danhDauDaCham(77, 10);
        await danhDauDaCham(77, 8);
        await danhDauDaCham(12, 2); // phiên khác: không tính

        const kq = await phienKhiMatMang(null, { cheDo: 'flashcard', chiOn: false, now: NOW }, TZ);
        expect(kq?.nguon).toBe('phien-do');
        expect(kq?.phien).toMatchObject({ id: 77, offline: true, counts: { review: 1, new: 1 } });
        expect(kq?.phien.cards.map((c) => c.id)).toEqual([2, 9]);

        // Phạm vi khác (chế độ khác, chủ đề khác, chỉ ôn) thì không phải phiên này
        expect(await phienDoCon(phamVi(null, false, 'quiz'), NOW, TZ)).toBeNull();
        expect(await phienDoCon(phamVi('so-dem-tu-vung', false, 'flashcard'), NOW, TZ)).toBeNull();
        expect(await phienDoCon(phamVi(null, true, 'flashcard'), NOW, TZ)).toBeNull();
    });

    it('phiên dở của hôm qua, đã chấm hết, hay đã xong (bỏ) thì không học tiếp', async () => {
        const pv = phamVi(null, false, 'flashcard');
        await giuPhien(phienMau([theHoc(10, 2)], { started_at: DAU_NGAY - 60 }), pv);
        expect(await phienDoCon(pv, NOW, TZ)).toBeNull();

        await giuPhien(PHIEN(), pv);
        for (const id of [10, 8, 2, 9]) await danhDauDaCham(77, id);
        expect(await phienDoCon(pv, NOW, TZ)).toBeNull();

        await giuPhien(PHIEN(), pv);
        await boPhienDo(77);
        expect(await phienDoCon(pv, NOW, TZ)).toBeNull();
    });

    it('không có phiên dở: lập từ chủ đề đã tải; học riêng chủ đề chưa tải thì không có gì; ôn chung dùng mọi chủ đề đã tải', async () => {
        await taiGia(goi('a', [the(10, 2, NOW - 60), the(8)]), goi('b', [the(2, 1, NOW - 30)], { saved_at: NOW - 60 }));
        const tc = { cheDo: 'flashcard' as const, chiOn: false, now: NOW };

        const a = await phienKhiMatMang('a', tc, TZ);
        expect(a?.nguon).toBe('chu-de-tai');
        expect(a?.phien).toMatchObject({ id: null, topic: { slug: 'a', name: 'Chủ đề a' } });
        expect(a?.phien.cards.map((c) => c.id)).toEqual([10, 8]);

        expect(await phienKhiMatMang('chua-tai', tc, TZ)).toBeNull();
        // Ôn chung: thẻ đến hạn của cả hai chủ đề (hạn sớm trước), từ mới theo thứ tự tải
        expect((await phienKhiMatMang(null, tc, null))?.phien.cards.map((c) => c.id)).toEqual([10, 2, 8]);
    });
});

describe('đăng xuất: dọn máy', () => {
    it('gửi nốt lượt chờ rồi xoá gói, phiên dở, bản lưu API và audio', async () => {
        const kho = giaCaches();
        await taiGia(goi('a', [the(10, 2, NOW - 60)]));
        await giuPhien(phienMau([theHoc(10, 2)], { started_at: NOW - 60 }), phamVi(null, false, 'flashcard'));
        await (await caches.open(KHO_API)).put('/api/v1/toi', new Response('{}'));
        await (await caches.open(KHO_AUDIO)).put('/audio/a.mp3', new Response('mp3'));
        const goi1 = giaApi({
            'POST /api/v1/hoc/luot-cham': () => ({
                json: { data: { received: 1, duplicates: 0, skipped: [], coins: 0, too_fast: 0, cards: [], today: { reviewed: 1, goal: 20, coins: 0, streak: 1 } } },
            }),
        });
        await themLuot(luot(10));

        await donMay();
        expect(goi1.filter((g) => g.url === '/api/v1/hoc/luot-cham')).toHaveLength(1);
        expect(await cacGoi()).toEqual([]);
        expect(await phienDoCon(phamVi(null, false, 'flashcard'), NOW, TZ)).toBeNull();
        expect([...kho.keys()]).toEqual([]);
        expect(await cacLuot()).toEqual([]);
    });

    it('máy chủ không nhận lượt (lỗi) thì vẫn bỏ lượt chờ, để người dùng sau trên máy này không gửi nhầm', async () => {
        vi.spyOn(console, 'warn').mockImplementation(() => {});
        giaApi({ 'POST /api/v1/hoc/luot-cham': () => ({ status: 503, json: {} }) });
        await themLuot(luot(10));
        await donMay(50);
        expect(await cacLuot()).toEqual([]);
    });
});
