import 'fake-indexeddb/auto';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { giaApi, type LanGoi, type Tuyen } from '../../test/fakeApi';
import { fakeSpeech } from '../../test/fakeSpeech';
import { phienMau, theHoc } from '../../test/noiDungGia';
import { renderApp } from '../../test/render';
import { cacLuot, datLaiChoTest, type LuotCham } from '../lib/hangCho';
import { danhDauDaCham, datLaiOfflineChoTest, giuPhien, goiCua, phamVi, phienDoCon, taiChuDe } from '../lib/offline';
import type { ConLai, PhienHoc } from '../lib/hoc';
import { LA_ULID } from '../lib/ulid';
import { initialState } from '../state/store';
import { sessionLine } from './Hoc';

const key = (k: string) => fireEvent.keyDown(window, { key: k });
const word = () => screen.getByRole('heading', { level: 1 }).textContent;
const counter = () => screen.getByText(/^\d+\/\d+$/).textContent;
const status = () => screen.getAllByRole('status')[0].textContent;
const where = () => screen.getByTestId('duong-dan').textContent;
const cham = (k = '3') => {
    key(' ');
    key(k);
};

// Phiên mẫu: hai thẻ ôn (die Zahl mức 2, das Brot mức 1) xen với hai từ mới (zahlen, zählen)
const PHIEN = () => phienMau([theHoc(10, 2), theHoc(8), theHoc(2, 1), theHoc(9)]);

const nhan: Tuyen = (body) => {
    const n = (body as { reviews: unknown[] }).reviews.length;
    return { json: { data: { received: n, duplicates: 0, skipped: [], coins: 5 * n, too_fast: 0, cards: [], today: { reviewed: n, goal: 20, coins: 5 * n, streak: 1 } } } };
};

function moPhien(path = '/hoc', phien: PhienHoc = PHIEN(), conLai: ConLai = { review: 0, new: 3 }, doi: Record<string, Tuyen> = {}, state = initialState()): LanGoi[] {
    const goi = giaApi({
        'POST /api/v1/hoc/phien': () => ({ status: 201, json: { data: phien } }),
        'POST /api/v1/hoc/luot-cham': nhan,
        'GET /api/v1/hoc/con-lai': () => ({ json: { data: conLai } }),
        ...doi,
    });
    renderApp(path, state);
    return goi;
}

const guiLuot = (goi: LanGoi[]) => goi.filter((g) => g.url === '/api/v1/hoc/luot-cham').flatMap((g) => (g.body as { reviews: LuotCham[] }).reviews);

beforeEach(async () => {
    await datLaiChoTest();
});

afterEach(async () => {
    await datLaiChoTest();
});

describe('Học: phiên flashcard từ máy chủ', () => {
    it('mở trang thì xin máy chủ một phiên; thẻ đầu chỉ có từ, chưa có nghĩa tới khi lật', async () => {
        const goi = moPhien();
        expect(await screen.findByRole('heading', { level: 1, name: 'die Zahl' })).toBeTruthy();
        expect(counter()).toBe('1/4');
        expect(screen.queryByText('con số')).toBeNull();
        expect(screen.queryByRole('group', { name: 'Tự chấm' })).toBeNull();
        const xin = goi.filter((g) => g.url === '/api/v1/hoc/phien');
        expect(xin).toHaveLength(1);
        expect(xin[0]).toMatchObject({ method: 'POST', body: { topic: null, review_only: false, mode: 'flashcard' } });
    });

    it('học riêng một chủ đề, chỉ ôn: gửi đúng chủ đề; Esc về trang chủ đề đó', async () => {
        const goi = moPhien('/hoc?chu-de=so-dem-tu-vung&on=1');
        await screen.findByRole('heading', { level: 1, name: 'die Zahl' });
        expect(goi.find((g) => g.url === '/api/v1/hoc/phien')?.body).toEqual({ topic: 'so-dem-tu-vung', review_only: true, mode: 'flashcard' });
        key('Escape');
        expect(where()).toBe('/chu-de/so-dem-tu-vung');
    });

    it('bấm chuột: lật thì tiêu điểm vào đáp án, chấm xong thì vào nút Lật thẻ của thẻ mới', async () => {
        moPhien();
        fireEvent.click(await screen.findByRole('button', { name: /Lật thẻ/ }));
        expect(document.activeElement?.getAttribute('aria-label')).toBe('Đáp án');
        fireEvent.click(screen.getByRole('button', { name: /^Tốt/ }));
        expect(word()).toBe('zahlen');
        await waitFor(() => expect(document.activeElement?.textContent).toContain('Lật thẻ'));
    });

    it('Space lật thẻ: đủ ba thứ tiếng; nút chấm ghi hạn ôn mới theo luật 6 mức', async () => {
        moPhien();
        await screen.findByRole('heading', { level: 1, name: 'die Zahl' });
        key(' ');
        expect(screen.getByText('con số')).toBeTruthy();
        expect(screen.getByText('number')).toBeTruthy();
        expect(screen.getByText('数字')).toBeTruthy();
        const grades = within(screen.getByRole('group', { name: 'Tự chấm' })).getAllByRole('button');
        // die Zahl đang ở mức 2 (Quen): Khó giữ mức, nửa của 3 ngày làm tròn xuống còn 1 ngày
        expect(grades.map((b) => b.textContent)).toEqual(['Quên10 phút 1', 'Khó1 ngày 2', 'Tốt7 ngày 3', 'Dễ14 ngày 4']);
        expect(document.activeElement?.getAttribute('aria-label')).toBe('Đáp án');
    });

    it('chưa lật thì phím 1–4 không chấm', async () => {
        moPhien();
        await screen.findByRole('heading', { level: 1, name: 'die Zahl' });
        key('3');
        expect(counter()).toBe('1/4');
        expect(word()).toBe('die Zahl');
    });

    it('chấm Tốt: sang thẻ kế, báo thẻ vừa lên mức; lượt chấm vào hàng chờ trên máy đủ thông tin', async () => {
        moPhien();
        await screen.findByRole('heading', { level: 1, name: 'die Zahl' });
        const truoc = Date.now();
        cham('3');
        expect(counter()).toBe('2/4');
        expect(word()).toBe('zahlen');
        expect(status()).toContain('die Zahl lên mức Khá');
        await waitFor(async () => expect(await cacLuot()).toHaveLength(1));
        const [l] = await cacLuot();
        expect(l).toMatchObject({ card_id: 10, session_id: 77, mode: 'flashcard', rating: 2 });
        expect(l.uid).toMatch(LA_ULID);
        expect(l.reviewed_at_ms).toBeGreaterThanOrEqual(truoc);
        expect(l.duration_ms).toBeGreaterThanOrEqual(0);
    });

    it('Quên thì thẻ quay lại trong phiên sau vài thẻ (phiên dài thêm một thẻ)', async () => {
        moPhien();
        await screen.findByRole('heading', { level: 1, name: 'die Zahl' });
        cham('1');
        expect(counter()).toBe('2/5');
        expect(status()).toContain('die Zahl sẽ quay lại sau vài thẻ');
        for (let i = 0; i < 3; i++) cham('3');
        expect(word()).toBe('die Zahl');
        // Lần gặp lại: mức sau khi Quên (Đang học)
        expect(screen.getByText('Mầm · Đang học')).toBeTruthy();
    });

    it('P nghe từ, S nghe chậm, E nghe câu ví dụ (cả trước khi lật); thẻ không có câu ví dụ thì E không đọc', async () => {
        const synth = fakeSpeech();
        moPhien('/hoc', phienMau([theHoc(1), theHoc(2)]));
        await screen.findByRole('heading', { level: 1, name: 'das Brötchen' });
        key('p');
        key('s');
        key('e');
        expect(synth.spoken.map((u) => [u.text, u.rate])).toEqual([
            ['das Brötchen', 0.95],
            ['das Brötchen', 0.6],
            ['Ich möchte zwei Brötchen, bitte.', 0.95],
        ]);
        cham('3');
        await screen.findByRole('heading', { level: 1, name: 'das Brot' });
        key('e');
        expect(synth.spoken).toHaveLength(3);
    });

    it('← xem lại thẻ vừa chấm, → về thẻ đang học', async () => {
        moPhien();
        await screen.findByRole('heading', { level: 1, name: 'die Zahl' });
        cham('4');
        key('ArrowLeft');
        expect(word()).toBe('die Zahl');
        expect(status()).toContain('Đang xem lại thẻ đã chấm: Dễ');
        key('3'); // đang xem lại thì không chấm
        key('ArrowRight');
        expect(word()).toBe('zahlen');
        expect(counter()).toBe('2/4');
    });

    it('xong phiên: gửi hết lượt chấm, hỏi máy chủ còn gì, mời Học tiếp', async () => {
        const goi = moPhien('/hoc', PHIEN(), { review: 1, new: 12 });
        await screen.findByRole('heading', { level: 1, name: 'die Zahl' });
        for (let i = 0; i < 4; i++) cham('3');
        expect(screen.getByRole('heading', { level: 1, name: 'Xong phiên' })).toBeTruthy();
        expect(screen.getByText('Đã học 4 thẻ: 2 từ mới, 2 thẻ ôn. 4 thẻ lên mức.')).toBeTruthy();
        expect(screen.getByText('Quên 0 · Khó 0 · Tốt 4 · Dễ 0')).toBeTruthy();
        const grown = within(screen.getByRole('region', { name: 'Cây lớn lên' })).getAllByRole('listitem');
        expect(grown.map((li) => li.textContent)).toEqual(['die Zahltừ Quen lênKhá', 'zahlentừ Mới lênQuen', 'das Brottừ Đang học lênQuen', 'zählentừ Mới lênQuen']);

        await waitFor(() => expect(guiLuot(goi).map((l) => l.card_id)).toEqual([10, 8, 2, 9]));
        // Hỏi phiên tới sau khi máy chủ đã nhận hết; số từ mới không quá số mỗi lượt (10)
        const next = await screen.findByRole('link', { name: /Học tiếp/ });
        expect(next.textContent).toContain('1 ôn · 10 mới');
        expect(next.getAttribute('href')).toBe('/hoc');
        expect(goi.find((g) => g.url.startsWith('/api/v1/hoc/con-lai'))?.url).toBe('/api/v1/hoc/con-lai');
        expect(screen.getByRole('link', { name: 'Về Hôm nay' }).getAttribute('href')).toBe('/hom-nay');
    });

    it('học hết ở chủ đề: chỉ còn đường về trang chủ đề', async () => {
        const goi = moPhien('/hoc?chu-de=so-dem-tu-vung', PHIEN(), { review: 0, new: 0 });
        await screen.findByRole('heading', { level: 1, name: 'die Zahl' });
        for (let i = 0; i < 4; i++) cham('4');
        expect(await screen.findByText('Đã học hết thẻ đến hạn và từ mới ở đây.')).toBeTruthy();
        expect(screen.queryByRole('link', { name: /Học tiếp|Ôn tiếp/ })).toBeNull();
        expect(screen.getByRole('link', { name: 'Về chủ đề' }).getAttribute('href')).toBe('/chu-de/so-dem-tu-vung');
        expect(goi.find((g) => g.url.startsWith('/api/v1/hoc/con-lai'))?.url).toBe('/api/v1/hoc/con-lai?chu_de=so-dem-tu-vung');
    });

    it('còn thẻ ôn mà hết từ mới thì mời Ôn tiếp; thẻ Quên quay lại tối đa 2 lần nhưng chỉ lượt đầu được gửi', async () => {
        const goi = moPhien('/hoc?on=1', PHIEN(), { review: 3, new: 0 });
        await screen.findByRole('heading', { level: 1, name: 'die Zahl' });
        for (let i = 0; i < 4; i++) cham('1');
        // Thẻ quay lại: ghi "Ôn lại"; nút chấm không ghi hạn ôn vì lượt này không đổi mức
        expect(word()).toBe('die Zahl');
        expect(screen.getByText('Ôn lại')).toBeTruthy();
        key(' ');
        expect(screen.getByRole('button', { name: /^Quên/ }).textContent).toContain('lát nữa');
        expect(screen.getByRole('button', { name: /^Tốt/ }).textContent).toContain('xong');
        key('1');
        for (let i = 0; i < 7; i++) cham('1');
        const tiep = await screen.findByRole('link', { name: /Ôn tiếp/ });
        expect(tiep.textContent).toContain('3 ôn · 0 mới');
        expect(tiep.getAttribute('href')).toBe('/hoc?on=1');
        // Chỉ lượt đầu của mỗi thẻ được tính (như Lernbuch): 4 thẻ Quên, các lượt ôn lại chỉ để luyện
        expect(screen.getByText('Quên 4 · Khó 0 · Tốt 0 · Dễ 0')).toBeTruthy();
        expect(guiLuot(goi).map((l) => l.card_id).sort((a, b) => a - b)).toEqual([2, 8, 9, 10]);
    });

    it('mất mạng giữa phiên: vẫn chấm tiếp; đầu trang và bảng tổng kết ghi số lượt chờ gửi', async () => {
        const goi = moPhien();
        const fetchGia = globalThis.fetch;
        vi.stubGlobal('fetch', (url: string, init?: RequestInit) =>
            url === '/api/v1/hoc/luot-cham' ? Promise.reject(new TypeError('Failed to fetch')) : fetchGia(url, init),
        );
        await screen.findByRole('heading', { level: 1, name: 'die Zahl' });
        for (let i = 0; i < 4; i++) cham('3');
        expect(await screen.findByText('4 lượt chấm đang chờ gửi; có mạng sẽ tự gửi.')).toBeTruthy();
        expect(await screen.findByText('4 lượt chờ gửi')).toBeTruthy();
        expect((await cacLuot()).map((l) => l.card_id).sort((a, b) => a - b)).toEqual([2, 8, 9, 10]);
        // Chưa gửi được thì chưa hỏi phiên tới
        expect(goi.some((g) => g.url.startsWith('/api/v1/hoc/con-lai'))).toBe(false);
    });

    it('phiên không có thẻ nào thì báo rõ, không hiện thẻ trống', async () => {
        moPhien('/hoc', phienMau([]));
        expect(await screen.findByRole('heading', { level: 1, name: 'Chưa có thẻ nào để học' })).toBeTruthy();
        expect(counter()).toBe('0/0');
        fireEvent.click(screen.getByRole('button', { name: 'Quay lại' }));
        expect(where()).toBe('/hom-nay');
    });

    it('không mở được phiên (mất mạng) thì báo và cho thử lại', async () => {
        let lan = 0;
        const goi = giaApi({
            'POST /api/v1/hoc/phien': () => (lan++ === 0 ? { status: 503, json: { message: 'bảo trì' } } : { status: 201, json: { data: PHIEN() } }),
        });
        renderApp('/hoc');
        expect((await screen.findByRole('alert')).textContent).toContain('Không tải được nội dung.');
        fireEvent.click(screen.getByRole('button', { name: 'Thử lại' }));
        expect(await screen.findByRole('heading', { level: 1, name: 'die Zahl' })).toBeTruthy();
        expect(goi.filter((g) => g.url === '/api/v1/hoc/phien')).toHaveLength(2);
    });

    it('nghĩa hiện theo các thứ tiếng đã bật; tắt cả ba thì báo cách bật lại', async () => {
        const state = initialState();
        state.settings = { ...state.settings, langs: { vi: true, en: false, zh: false } };
        moPhien('/hoc', PHIEN(), undefined, {}, state);
        await screen.findByRole('heading', { level: 1, name: 'die Zahl' });
        key(' ');
        expect(screen.getByText('con số')).toBeTruthy();
        expect(screen.queryByText('number')).toBeNull();
    });

    it('tắt cả ba thứ tiếng thì báo cách bật lại', async () => {
        const state = initialState();
        state.settings = { ...state.settings, langs: { vi: false, en: false, zh: false } };
        moPhien('/hoc', PHIEN(), undefined, {}, state);
        await screen.findByRole('heading', { level: 1, name: 'die Zahl' });
        key(' ');
        expect(screen.getByText(/Đang tắt cả ba thứ tiếng/)).toBeTruthy();
    });

    it('câu ví dụ hiện sau khi lật, kèm bản dịch tiếng Việt', async () => {
        moPhien('/hoc', phienMau([theHoc(1)]));
        await screen.findByRole('heading', { level: 1, name: 'das Brötchen' });
        expect(screen.queryByText('Ich möchte zwei Brötchen, bitte.')).toBeNull();
        key(' ');
        expect(screen.getByText('Ich möchte zwei Brötchen, bitte.')).toBeTruthy();
        expect(screen.getByText('Cho tôi hai cái bánh mì nhỏ.')).toBeTruthy();
    });

    it('trình duyệt không đọc được thành tiếng thì báo lỗi ngay dưới nút nghe', async () => {
        moPhien();
        fireEvent.click(await screen.findByRole('button', { name: 'Nghe (phím P)' }));
        expect(await screen.findByText('Trình duyệt này không đọc được thành tiếng.')).toBeTruthy();
    });
});

describe('Học khi mất mạng (PWA)', () => {
    const giay = () => Math.floor(Date.now() / 1000);
    const goiSoDem = () => ({
        topic: { slug: 'so-dem-tu-vung', name: 'Số đếm', collection: 'vocab' },
        timezone: 'UTC',
        new_per_session: 10,
        saved_at: giay() - 3600,
        cards: [{ ...theHoc(10, 2), due_at: giay() - 3600 }, theHoc(8), { ...theHoc(2, 3), due_at: giay() + 5 * 86400 }],
    });

    /** Mất mạng: lập phiên, gửi lượt (POST) không tới được máy chủ; các tuyến khác như giaApi. */
    function matMang(doi: Record<string, Tuyen> = {}): LanGoi[] {
        const goi = giaApi(doi);
        const fetchGia = globalThis.fetch;
        vi.stubGlobal(
            'fetch',
            vi.fn((url: string, init?: RequestInit) => (init?.method === 'POST' ? Promise.reject(new TypeError('Failed to fetch')) : fetchGia(url, init))),
        );
        return goi;
    }

    beforeEach(() => datLaiOfflineChoTest());

    it('chủ đề đã tải: lập phiên trên máy, báo đang học khi không có mạng; lượt chấm chờ gửi, không có mã phiên, gói đổi mức', async () => {
        giaApi({ 'GET /api/v1/hoc/tai/so-dem-tu-vung': () => ({ json: { data: goiSoDem() } }) });
        await taiChuDe('so-dem-tu-vung');
        matMang();
        renderApp('/hoc?chu-de=so-dem-tu-vung');

        // Ôn die Zahl (đến hạn), rồi từ mới zahlen; das Brot hạn 5 ngày nữa thì chưa
        expect(await screen.findByRole('heading', { level: 1, name: 'die Zahl' })).toBeTruthy();
        expect(counter()).toBe('1/2');
        expect(screen.getByText('Đang học khi không có mạng: lượt chấm nằm trên máy, có mạng sẽ tự gửi.')).toBeTruthy();
        cham();
        expect(word()).toBe('zahlen');

        await waitFor(async () => expect((await cacLuot()).map((l) => [l.card_id, l.session_id, l.rating])).toEqual([[10, null, 2]]));
        await waitFor(async () => expect((await goiCua('so-dem-tu-vung'))?.cards[0].box).toBe(3));
    });

    it('có phiên dở cùng phạm vi (mở lúc có mạng): học tiếp từ thẻ chưa chấm, lượt chấm giữ mã phiên', async () => {
        // Phiên mẫu mở từ trước (giờ cố định): đặt lại là vừa mở hôm nay; die Zahl đã chấm trước lúc mất mạng
        await giuPhien({ ...PHIEN(), started_at: giay() - 1 }, phamVi(null, false, 'flashcard'));
        await danhDauDaCham(77, 10);
        matMang();
        renderApp('/hoc');

        expect(await screen.findByRole('heading', { level: 1, name: 'zahlen' })).toBeTruthy();
        expect(counter()).toBe('1/3');
        cham();
        await waitFor(async () => expect((await cacLuot()).map((l) => [l.card_id, l.session_id])).toEqual([[8, 77]]));
    });

    it('chưa tải chủ đề: báo không có mạng và cách tải chủ đề, có nút thử lại; có mạng lại thì vào phiên', async () => {
        matMang();
        renderApp('/hoc?chu-de=so-dem-tu-vung');
        expect(await screen.findByText(/Chủ đề này chưa tải về máy/)).toBeTruthy();
        expect(screen.getByText(/Tải để học khi không có mạng/)).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Về chủ đề' }).getAttribute('href')).toBe('/chu-de/so-dem-tu-vung');

        giaApi({ 'POST /api/v1/hoc/phien': () => ({ status: 201, json: { data: PHIEN() } }), 'POST /api/v1/hoc/luot-cham': nhan });
        fireEvent.click(screen.getByRole('button', { name: 'Thử lại' }));
        expect(await screen.findByRole('heading', { level: 1, name: 'die Zahl' })).toBeTruthy();
    });

    it('có mạng: phiên máy chủ lập được giữ trên máy, chấm thẻ nào đánh dấu thẻ đó, xong phiên thì bỏ', async () => {
        moPhien('/hoc', phienMau([theHoc(10, 2), theHoc(8)], { started_at: giay() - 1 }));
        await screen.findByRole('heading', { level: 1, name: 'die Zahl' });
        const pv = phamVi(null, false, 'flashcard');
        await waitFor(async () => expect((await phienDoCon(pv, giay(), 'UTC'))?.cards.map((c) => c.id)).toEqual([10, 8]));

        cham();
        await waitFor(async () => expect((await phienDoCon(pv, giay(), 'UTC'))?.cards.map((c) => c.id)).toEqual([8]));
        cham();
        await screen.findByText('Xong phiên');
        await waitFor(async () => expect(await phienDoCon(pv, giay(), 'UTC')).toBeNull());
    });
});

describe('sessionLine: câu tổng kết phiên', () => {
    it('bỏ phần bằng 0', () => {
        expect(sessionLine(7, 0)).toBe('Đã học 7 thẻ ôn.');
        expect(sessionLine(3, 3)).toBe('Đã học 3 từ mới.');
        expect(sessionLine(21, 9)).toBe('Đã học 21 thẻ: 9 từ mới, 12 thẻ ôn.');
    });
});
