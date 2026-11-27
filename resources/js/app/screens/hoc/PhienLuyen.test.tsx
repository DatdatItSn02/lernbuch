import 'fake-indexeddb/auto';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { giaApi, type LanGoi, type Tuyen } from '../../../test/fakeApi';
import { fakeSpeech } from '../../../test/fakeSpeech';
import { phienMau, theHoc } from '../../../test/noiDungGia';
import { renderApp } from '../../../test/render';
import { datLaiChoTest, type LuotCham } from '../../lib/hangCho';
import type { ConLai, DapAnNhieu, PhienHoc, TheHoc } from '../../lib/hoc';
import { GIAY_NGHE } from './PhienLuyen';

const key = (k: string, doi: Partial<KeyboardEventInit> = {}) => fireEvent.keyDown(window, { key: k, ...doi });
const where = () => screen.getByTestId('duong-dan').textContent;
/** Chữ của các vùng thông báo (bỏ ô ghi đường dẫn của test, cũng có vai trò status). */
const bao = () =>
    screen
        .queryAllByRole('status')
        .filter((e) => e.tagName !== 'OUTPUT')
        .map((e) => e.textContent)
        .join(' ');
const nhan: Tuyen = (body) => {
    const n = (body as { reviews: unknown[] }).reviews.length;
    return { json: { data: { received: n, duplicates: 0, skipped: [], coins: 0, too_fast: 0, cards: [], today: { reviewed: n, goal: 20, coins: 0, streak: 1 } } } };
};
const nhieu = (id: number, front: string, vi: string): DapAnNhieu => ({ id, front, article: null, word: front, gender: null, meanings: { vi } });
/** Các câu giọng trình duyệt đã đọc: chữ và tốc độ (0,95 thường, 0,6 chậm). */
const doc = (synth: ReturnType<typeof fakeSpeech>) => synth.spoken.map((u) => [u.text, u.rate]);
const DAP_AN_NHIEU = [nhieu(90, 'eins', 'số một'), nhieu(91, 'zwei', 'số hai'), nhieu(92, 'drei', 'số ba'), nhieu(93, 'vier', 'số bốn')];
const coNhieu = (c: TheHoc): TheHoc => ({ ...c, options: DAP_AN_NHIEU });

function moPhien(path: string, phien: PhienHoc, conLai: ConLai = { review: 0, new: 0 }): LanGoi[] {
    const goi = giaApi({
        'POST /api/v1/hoc/phien': () => ({ status: 201, json: { data: phien } }),
        'POST /api/v1/hoc/luot-cham': nhan,
        'GET /api/v1/hoc/con-lai': () => ({ json: { data: conLai } }),
    });
    renderApp(path);
    return goi;
}

const guiLuot = (goi: LanGoi[]) => goi.filter((g) => g.url === '/api/v1/hoc/luot-cham').flatMap((g) => (g.body as { reviews: LuotCham[] }).reviews);
const cham = (goi: LanGoi[]) => guiLuot(goi).map((l) => [l.card_id, l.rating, l.mode]);

beforeEach(async () => {
    await datLaiChoTest();
});

afterEach(async () => {
    vi.restoreAllMocks();
    await datLaiChoTest();
});

describe('Quiz', () => {
    it('chọn bằng phím 1–4; sai thì thẻ quay lại để luyện nhưng chỉ lượt đầu được tính; tổng kết đúng số', async () => {
        const goi = moPhien('/hoc?che-do=quiz', phienMau([coNhieu(theHoc(10)), coNhieu(theHoc(9))], { mode: 'quiz' }));
        const nhom = await screen.findByRole('group', { name: 'Các đáp án' });
        expect(goi.find((g) => g.url === '/api/v1/hoc/phien')?.body).toMatchObject({ mode: 'quiz' });
        const dapAn = () => within(screen.getByRole('group', { name: 'Các đáp án' })).getAllByRole('button');
        expect(dapAn()).toHaveLength(4);
        expect(within(nhom).getByRole('button', { name: /con số/ })).toBeTruthy();

        // Thẻ 1 (die Zahl): đúng
        key(String(dapAn().findIndex((b) => b.textContent?.includes('con số')) + 1));
        expect(bao()).toContain('Đúng');
        key('Enter');
        // Thẻ 2 (zählen): sai, rồi gặp lại ở cuối phiên và chọn đúng
        await waitFor(() => expect(dapAn().some((b) => b.textContent?.includes('đếm'))).toBe(true));
        key(String(dapAn().findIndex((b) => !b.textContent?.includes('đếm')) + 1));
        expect(bao()).toContain('Chưa đúng');
        key('Enter');
        expect(await screen.findByText('Ôn lại')).toBeTruthy();
        key(String(dapAn().findIndex((b) => b.textContent?.includes('đếm')) + 1));
        key('Enter');

        expect(await screen.findByRole('heading', { name: 'Xong phiên' })).toBeTruthy();
        expect(screen.getByText('Quên 1 · Khó 0 · Tốt 1 · Dễ 0')).toBeTruthy();
        await waitFor(() => expect(cham(goi)).toEqual([[10, 2, 'quiz'], [9, 0, 'quiz']]));
    });

    it('Esc thoát về Hôm nay; chế độ lạ trên đường dẫn thì học Flashcard', async () => {
        const goi = moPhien('/hoc?che-do=quiz', phienMau([coNhieu(theHoc(10))], { mode: 'quiz' }));
        await screen.findByRole('group', { name: 'Các đáp án' });
        key('Escape');
        expect(where()).toBe('/hom-nay');
        expect(goi.filter((g) => g.url === '/api/v1/hoc/phien')).toHaveLength(1);
    });

    it('chế độ lạ trên đường dẫn thì xin phiên Flashcard', async () => {
        const goi = moPhien('/hoc?che-do=hack', phienMau([theHoc(10)]));
        expect(await screen.findByRole('heading', { level: 1, name: 'die Zahl' })).toBeTruthy();
        expect(goi.find((g) => g.url === '/api/v1/hoc/phien')?.body).toMatchObject({ mode: 'flashcard' });
    });

    it('P nghe từ, S nghe chậm, cả trước khi chọn (từ tiếng Đức đã hiện sẵn)', async () => {
        const synth = fakeSpeech();
        moPhien('/hoc?che-do=quiz', phienMau([coNhieu(theHoc(10))], { mode: 'quiz' }));
        await screen.findByRole('group', { name: 'Các đáp án' });
        key('p', { code: 'KeyP' });
        key('s', { code: 'KeyS' });
        // Ctrl+P (in trang) không phải phím nghe
        key('p', { code: 'KeyP', ctrlKey: true });
        expect(doc(synth)).toEqual([
            ['die Zahl', 0.95],
            ['die Zahl', 0.6],
        ]);
    });
});

describe('Phím nghe khi gõ', () => {
    it('Typing: chưa trả lời thì P, Alt+P không đọc (khỏi lộ đáp án); trả lời rồi thì P, S đọc từ', async () => {
        const synth = fakeSpeech();
        moPhien('/hoc?che-do=typing', phienMau([theHoc(9)], { mode: 'typing' }));
        const o = (await screen.findByRole('textbox', { name: 'Câu trả lời' })) as HTMLInputElement;
        fireEvent.keyDown(o, { key: 'p', code: 'KeyP', altKey: true });
        key('p', { code: 'KeyP' });
        expect(synth.spoken).toHaveLength(0);
        fireEvent.change(o, { target: { value: 'zählen' } });
        fireEvent.keyDown(o, { key: 'Enter' });
        expect(bao()).toContain('Đúng');
        key('p', { code: 'KeyP' });
        key('s', { code: 'KeyS' });
        expect(doc(synth)).toEqual([
            ['zählen', 0.95],
            ['zählen', 0.6],
        ]);
    });

    it('Listening: đọc từ ngay khi hiện; đang gõ thì Alt+P, Alt+S đọc lại, P không có Alt là chữ p', async () => {
        const synth = fakeSpeech();
        moPhien('/hoc?che-do=listening', phienMau([theHoc(9)], { mode: 'listening' }));
        const o = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        await waitFor(() => expect(doc(synth)).toEqual([['zählen', 0.95]]));
        fireEvent.keyDown(o, { key: 'p', code: 'KeyP' });
        expect(synth.spoken).toHaveLength(1);
        fireEvent.keyDown(o, { key: 'p', code: 'KeyP', altKey: true });
        fireEvent.keyDown(o, { key: 's', code: 'KeyS', altKey: true });
        expect(doc(synth)).toEqual([
            ['zählen', 0.95],
            ['zählen', 0.95],
            ['zählen', 0.6],
        ]);
    });

    it('Đặt câu: P nghe câu, S nghe chậm', async () => {
        const synth = fakeSpeech();
        moPhien('/hoc?che-do=sentence', phienMau([theHoc(1)], { mode: 'sentence' }));
        await screen.findByRole('group', { name: 'Câu của bạn' });
        key('p', { code: 'KeyP' });
        key('s', { code: 'KeyS' });
        expect(doc(synth)).toEqual([
            ['Ich möchte zwei Brötchen, bitte.', 0.95],
            ['Ich möchte zwei Brötchen, bitte.', 0.6],
        ]);
    });
});

describe('Typing và Listening', () => {
    it('Typing: thiếu mạo từ được Khó; gợi ý thì tối đa Khó; nút chữ đặc biệt chèn ä ö ü ß vào ô', async () => {
        const goi = moPhien('/hoc?che-do=typing', phienMau([theHoc(1), theHoc(2)], { mode: 'typing' }));
        const o = (await screen.findByRole('textbox', { name: 'Câu trả lời' })) as HTMLInputElement;
        expect(screen.getByText('bánh mì nhỏ (ổ tròn)')).toBeTruthy();

        fireEvent.change(o, { target: { value: 'Br' } });
        fireEvent.click(screen.getByRole('button', { name: 'Gõ ö' }));
        expect(o.value).toBe('Brö');
        fireEvent.change(o, { target: { value: 'Brötchen' } });
        fireEvent.keyDown(o, { key: 'Enter' });
        expect(bao()).toContain('Đúng, nhưng thiếu mạo từ');
        key('Enter');

        // Thẻ 2: xem gợi ý chữ đầu rồi gõ đúng hẳn: vẫn chỉ được Khó
        const o2 = (await screen.findByRole('textbox', { name: 'Câu trả lời' })) as HTMLInputElement;
        fireEvent.click(screen.getByRole('button', { name: 'Gợi ý chữ đầu' }));
        expect(screen.getByText('B _ _ _')).toBeTruthy();
        fireEvent.change(o2, { target: { value: 'das Brot' } });
        fireEvent.click(screen.getByRole('button', { name: /Kiểm tra/ }));
        expect(bao()).toContain('Đúng');
        expect(bao()).toContain('có gợi ý');
        fireEvent.click(screen.getByRole('button', { name: /Tiếp/ }));

        await screen.findByRole('heading', { name: 'Xong phiên' });
        await waitFor(() => expect(cham(goi)).toEqual([[1, 1, 'typing'], [2, 1, 'typing']]));
    });

    it('Typing: ô trống thì chưa chấm; sai mạo từ là Quên', async () => {
        const goi = moPhien('/hoc?che-do=typing', phienMau([theHoc(10)], { mode: 'typing' }));
        const o = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        fireEvent.keyDown(o, { key: 'Enter' });
        expect(bao()).toBe('');
        fireEvent.change(o, { target: { value: 'der Zahl' } });
        fireEvent.keyDown(o, { key: 'Enter' });
        expect(bao()).toContain('Sai mạo từ');
        expect(guiLuot(goi)).toHaveLength(0);
    });

    it('Listening: gõ lại từ vừa nghe; hết 30 giây thì chấm Quên với lý do hết giờ', async () => {
        const goi = moPhien('/hoc?che-do=listening', phienMau([theHoc(9), theHoc(8)], { mode: 'listening' }));
        const o = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        expect(screen.getByRole('timer', { name: 'Thời gian còn lại' })).toBeTruthy();
        // Nghĩa chưa hiện khi nghe (chỉ hiện khi bấm gợi ý)
        expect(screen.queryByText('đếm')).toBeNull();
        fireEvent.change(o, { target: { value: 'zaehlen' } });
        fireEvent.keyDown(o, { key: 'Enter' });
        expect(bao()).toContain('Đúng');

        // Thẻ 2: đồng hồ giả từ trước khi thẻ hiện (đếm ngược bắt đầu lúc thẻ hiện)
        vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval', 'Date'] });
        try {
            key('Enter');
            await screen.findByRole('timer');
            act(() => {
                vi.advanceTimersByTime(GIAY_NGHE * 1000 + 200);
            });
            expect(bao()).toContain('Hết giờ');
        } finally {
            vi.useRealTimers();
        }
        key('Enter');
        expect(await screen.findByText('Ôn lại')).toBeTruthy();
        key('Escape');
        await waitFor(() => expect(cham(goi)).toEqual([[9, 2, 'listening'], [8, 0, 'listening']]));
    });
});

describe('Đặt câu', () => {
    it('bấm chữ theo đúng thứ tự là Tốt; sai rồi sửa là Khó; Backspace bỏ chữ cuối', async () => {
        const goi = moPhien('/hoc?che-do=sentence', phienMau([theHoc(1)], { mode: 'sentence' }));
        const them = (chu: string) => fireEvent.click(screen.getByRole('button', { name: `Thêm “${chu}” vào câu` }));
        await screen.findByRole('group', { name: 'Câu của bạn' });
        const chu = ['Ich', 'möchte', 'zwei', 'Brötchen,', 'bitte.'];

        // Sai thứ tự: báo thử lại
        [...chu].reverse().forEach(them);
        key('Enter');
        expect(bao()).toContain('Chưa đúng, thử lại');
        for (let i = 0; i < chu.length; i++) key('Backspace');
        expect(within(screen.getByRole('group', { name: 'Câu của bạn' })).queryAllByRole('button')).toHaveLength(0);
        chu.forEach(them);
        fireEvent.click(screen.getByRole('button', { name: /Kiểm tra/ }));
        expect(bao()).toContain('Đúng sau khi sửa');
        key('Enter');

        await screen.findByRole('heading', { name: 'Xong phiên' });
        await waitFor(() => expect(cham(goi)).toEqual([[1, 1, 'sentence']]));
    });

    it('chưa đủ chữ thì chưa kiểm được; Xem đáp án là Quên', async () => {
        const goi = moPhien('/hoc?che-do=sentence', phienMau([theHoc(1)], { mode: 'sentence' }));
        await screen.findByRole('group', { name: 'Câu của bạn' });
        expect((screen.getByRole('button', { name: /Kiểm tra/ }) as HTMLButtonElement).disabled).toBe(true);
        fireEvent.click(screen.getByRole('button', { name: 'Xem đáp án' }));
        expect(bao()).toContain('Đáp án');
        expect(within(screen.getByRole('group', { name: 'Câu của bạn' })).getAllByRole('button').map((b) => b.textContent)).toEqual(['Ich', 'möchte', 'zwei', 'Brötchen,', 'bitte.']);
        await waitFor(() => expect(cham(goi)).toEqual([[1, 0, 'sentence']]));
    });

    it('phiên Đặt câu không có thẻ nào có câu ví dụ thì báo rõ', async () => {
        moPhien('/hoc?che-do=sentence', phienMau([], { mode: 'sentence' }));
        expect(await screen.findByText('Chưa có thẻ nào có câu ví dụ để đặt câu ở đây.')).toBeTruthy();
    });
});

describe('Ghép cặp và Hỗn hợp', () => {
    it('Ghép cặp: nối đúng thì khoá cặp; thẻ từng nối sai là Khó; xong vòng thì tổng kết', async () => {
        const goi = moPhien('/hoc?che-do=match', phienMau([theHoc(10), theHoc(9), theHoc(8)].map(coNhieu), { mode: 'match' }), { review: 0, new: 3 });
        const tu = await screen.findByRole('group', { name: 'Từ tiếng Đức' });
        const nghia = screen.getByRole('group', { name: 'Nghĩa' });
        const noi = (a: string, b: string) => {
            fireEvent.click(within(tu).getByRole('button', { name: a }));
            fireEvent.click(within(nghia).getByRole('button', { name: b }));
        };

        noi('zählen', 'đếm');
        expect((within(tu).getByRole('button', { name: 'zählen' }) as HTMLButtonElement).disabled).toBe(true);
        noi('zahlen', 'con số');
        expect(bao()).toContain('Chưa khớp');
        await waitFor(() => expect(bao()).not.toBe(''));
        await new Promise((r) => setTimeout(r, 600));
        noi('zahlen', 'trả tiền');
        noi('die Zahl', 'con số');

        expect(await screen.findByRole('heading', { name: 'Xong phiên' }, { timeout: 2000 })).toBeTruthy();
        const link = await screen.findByRole('link', { name: /Học tiếp/ });
        expect(link.getAttribute('href')).toBe('/hoc?che-do=match');
        expect(cham(goi)).toEqual([[9, 2, 'match'], [8, 1, 'match'], [10, 2, 'match']]);
    });

    it('Ghép cặp: chưa chọn từ thì P không đọc; chọn từ rồi thì P, S đọc từ vừa chọn', async () => {
        const synth = fakeSpeech();
        moPhien('/hoc?che-do=match', phienMau([theHoc(10), theHoc(9), theHoc(8)].map(coNhieu), { mode: 'match' }));
        const tu = await screen.findByRole('group', { name: 'Từ tiếng Đức' });
        key('p', { code: 'KeyP' });
        expect(synth.spoken).toHaveLength(0);
        fireEvent.click(within(tu).getByRole('button', { name: 'zählen' }));
        key('p', { code: 'KeyP' });
        fireEvent.click(within(tu).getByRole('button', { name: 'die Zahl' }));
        key('s', { code: 'KeyS' });
        expect(doc(synth)).toEqual([
            ['zählen', 0.95],
            ['die Zahl', 0.6],
        ]);
    });

    it('Hỗn hợp: kiểu câu hỏi theo mức của thẻ, lượt chấm ghi chế độ Hỗn hợp', async () => {
        vi.spyOn(Math, 'random').mockReturnValue(0.8);
        // Mức 0 (r = 0,8): Quiz; mức 2: Typing
        const goi = moPhien('/hoc?che-do=mixed', phienMau([coNhieu(theHoc(10)), { ...coNhieu(theHoc(9)), box: 2 }], { mode: 'mixed' }));
        await screen.findByRole('group', { name: 'Các đáp án' });
        const dung = within(screen.getByRole('group', { name: 'Các đáp án' })).getAllByRole('button').findIndex((b) => b.textContent?.includes('con số'));
        key(String(dung + 1));
        key('Enter');
        const o = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        fireEvent.change(o, { target: { value: 'zählen' } });
        fireEvent.keyDown(o, { key: 'Enter' });
        key('Enter');
        await screen.findByRole('heading', { name: 'Xong phiên' });
        await waitFor(() => expect(cham(goi)).toEqual([[10, 2, 'mixed'], [9, 2, 'mixed']]));
    });
});
