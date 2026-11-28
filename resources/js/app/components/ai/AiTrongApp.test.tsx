import 'fake-indexeddb/auto';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { datLaiChoTest } from '../../lib/hangCho';
import type { KetQuaCau, KetQuaDapAn, KetQuaTraTu, TrangThaiAi } from '../../lib/ai';
import { META, SAN_SANG } from '../../../test/aiGia';
import { giaApi, toiMau, type LanGoi, type Tuyen } from '../../../test/fakeApi';
import { BAI_GIONG, CAU_LB3, DS_BAI_TAP, nhanKetQua } from '../../../test/luyenTapGia';
import { ketQuaTra, LICH_SU, phienMau, theHoc } from '../../../test/noiDungGia';
import { renderAppCoTaiKhoan } from '../../../test/render';

// AI ở các màn đang có (mục tự kiểm 10, phần giao diện): Tra từ AI, Hỏi AI trong bài ngữ pháp, Kiểm đáp án,
// Giải thích từ trong phiên học, Viết câu, tuỳ chọn AI tự giải thích khi sai. AI chưa mở thì không có nút nào.

const GIAI_THICH = { meaning: 'đếm', usage: '', examples: [], common_mistakes: [] };
const DAP_AN: KetQuaDapAn = { ok: false, why: 'Buch là giống trung.', better: 'Das Buch ist interessant.' };

function mo(path: string, ai: TrangThaiAi = SAN_SANG, them: Record<string, Tuyen> = {}, autoExplain = false): LanGoi[] {
    const goi = giaApi({
        'GET /api/v1/toi': () => ({ json: { data: toiMau({ ai, settings: { ...toiMau().settings, ai_auto_explain: autoExplain } }) } }),
        'GET /api/v1/tra-tu': (_b, u) => ({ json: ketQuaTra(u.searchParams.get('q') ?? '') }),
        'GET /api/v1/tra-tu/lich-su': () => ({ json: { data: LICH_SU } }),
        'POST /api/v1/tra-tu/lich-su': () => ({ status: 201, json: { data: { id: 40 } } }),
        'GET /api/v1/ngu-phap/g-A1-22': () => ({ json: { data: BAI_GIONG } }),
        'GET /api/v1/bai-tap/phien': () => ({ json: { data: CAU_LB3 } }),
        'POST /api/v1/bai-tap/ket-qua': () => nhanKetQua(),
        'GET /api/v1/bai-tap': () => ({ json: { data: DS_BAI_TAP } }),
        'POST /api/v1/ai/kiem-dap-an': () => ({ json: { data: DAP_AN, meta: META } }),
        'POST /api/v1/ai/giai-thich-tu': () => ({ json: { data: GIAI_THICH, meta: META } }),
        ...them,
    });
    renderAppCoTaiKhoan(path);
    return goi;
}
const daGui = (goi: LanGoi[], duong: string) => goi.filter((g) => g.method === 'POST' && g.url === `/api/v1${duong}`);

beforeEach(async () => {
    await datLaiChoTest();
});
afterEach(async () => {
    vi.restoreAllMocks();
    await datLaiChoTest();
});

describe('Tra từ AI', () => {
    it('từ điển không có từ: khối "chưa có" và nút hỏi AI; kết quả có mạo từ, dạng, nghĩa', async () => {
        const tra: KetQuaTraTu = { lemma: 'Fernweh', pos: 'Nomen', gender: 'n', plural: '', forms: [{ label: 'Genitiv', form: 'des Fernwehs' }], meanings_vi: ['nỗi nhớ những nơi xa'], examples: [], collocations: [], level: 'B2' };
        const goi = mo('/tra-tu?q=Fernweh', SAN_SANG, { 'POST /api/v1/ai/tra-tu': () => ({ json: { data: tra, meta: META } }) });
        expect(await screen.findByText('Từ điển của Lernbuch chưa có “Fernweh”.')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Hỏi AI về từ này' }));
        expect(await screen.findByText('das Fernweh')).toBeTruthy();
        expect(screen.getByText('des Fernwehs')).toBeTruthy();
        expect(screen.getByText('nỗi nhớ những nơi xa')).toBeTruthy();
        expect(daGui(goi, '/ai/tra-tu')[0].body).toEqual({ text: 'Fernweh' });
    });

    it('có kết quả thì không có khối AI; AI chưa mở thì cũng không có', async () => {
        mo('/tra-tu?q=brot');
        expect(await screen.findByRole('link', { name: /Brot/ })).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Hỏi AI về từ này' })).toBeNull();
    });

    it('AI chưa mở: từ không có chỉ báo không có kết quả', async () => {
        mo('/tra-tu?q=Fernweh', { state: 'hidden' });
        expect(await screen.findByText('Không có kết quả. Thử bỏ dấu hoặc gõ ngắn hơn.')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Hỏi AI về từ này' })).toBeNull();
    });
});

describe('Hỏi AI trong bài ngữ pháp', () => {
    it('một nút sau mỗi tiêu đề h2; mở phần thứ hai rồi giải thích: gửi đúng số phần', async () => {
        const goi = mo('/ngu-phap/g-A1-22', SAN_SANG, {
            'POST /api/v1/ai/ngu-phap/giai-thich': () => ({ json: { data: { summary: 'Bảng mạo từ ở Nominativ.', points: [], examples: [] }, meta: META } }),
        });
        const nut = await screen.findAllByRole('button', { name: /^Hỏi AI về phần/ });
        expect(nut).toHaveLength(BAI_GIONG.blocks.filter((k) => k.t === 'h2').length);
        fireEvent.click(nut[1]);
        expect(nut[1].getAttribute('aria-expanded')).toBe('true');
        fireEvent.click(screen.getByRole('button', { name: 'Giải thích phần này' }));
        expect(await screen.findByText('Bảng mạo từ ở Nominativ.')).toBeTruthy();
        expect(daGui(goi, '/ai/ngu-phap/giai-thich')[0].body).toEqual({ slug: 'g-A1-22', section: 1 });
    });

    it('AI chưa mở thì không có nút', async () => {
        mo('/ngu-phap/g-A1-22', { state: 'hidden' });
        expect(await screen.findByRole('heading', { name: /Ví dụ/ })).toBeTruthy();
        expect(screen.queryByRole('button', { name: /^Hỏi AI về phần/ })).toBeNull();
    });
});

describe('Kiểm đáp án trong bài tập', () => {
    async function traLoiSaiCauDien() {
        await screen.findByRole('group', { name: 'Các đáp án' });
        fireEvent.keyDown(window, { key: '2' });
        fireEvent.keyDown(window, { key: 'Enter' });
        const o = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        fireEvent.change(o, { target: { value: 'Ein' } });
        fireEvent.keyDown(o, { key: 'Enter' });
    }

    it('trắc nghiệm không có nút; câu điền trả lời sai có nút; kết quả nói rõ điểm không đổi', async () => {
        const goi = mo('/bai-tap/lam?bo=bt-LB3&n=20');
        await screen.findByRole('group', { name: 'Các đáp án' });
        expect(screen.queryByRole('button', { name: 'Hỏi AI: câu của tôi có đúng không?' })).toBeNull();
        fireEvent.keyDown(window, { key: '2' });
        expect(screen.queryByRole('button', { name: 'Hỏi AI: câu của tôi có đúng không?' })).toBeNull();
        fireEvent.keyDown(window, { key: 'Enter' });
        const o = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        fireEvent.change(o, { target: { value: 'Ein' } });
        fireEvent.keyDown(o, { key: 'Enter' });
        fireEvent.click(await screen.findByRole('button', { name: 'Hỏi AI: câu của tôi có đúng không?' }));
        expect(await screen.findByText('AI cho rằng câu của bạn chưa đúng.')).toBeTruthy();
        expect(screen.getByText('Điểm của câu này không đổi.')).toBeTruthy();
        expect(daGui(goi, '/ai/kiem-dap-an')[0].body).toEqual({ exercise_id: 102, text: 'Ein' });
    });

    it('bật AI tự giải thích: câu sai tự hỏi đúng một lần; tắt thì không tự hỏi', async () => {
        const goi = mo('/bai-tap/lam?bo=bt-LB3&n=20', SAN_SANG, {}, true);
        await traLoiSaiCauDien();
        expect(await screen.findByText('AI cho rằng câu của bạn chưa đúng.')).toBeTruthy();
        await waitFor(() => expect(daGui(goi, '/ai/kiem-dap-an')).toHaveLength(1));
    });

    it('tắt tự giải thích thì không tự hỏi; AI chưa mở thì không có nút', async () => {
        const goi = mo('/bai-tap/lam?bo=bt-LB3&n=20', { state: 'hidden' });
        await traLoiSaiCauDien();
        expect(screen.queryByRole('button', { name: 'Hỏi AI: câu của tôi có đúng không?' })).toBeNull();
        expect(daGui(goi, '/ai/kiem-dap-an')).toHaveLength(0);
    });
});

describe('Giải thích từ trong phiên luyện', () => {
    const phien = phienMau([theHoc(9)], { mode: 'typing' });
    const tuyen = { 'POST /api/v1/hoc/phien': () => ({ status: 201, json: { data: phien } }), 'GET /api/v1/hoc/con-lai': () => ({ json: { data: { review: 0, new: 0 } } }) };

    it('gõ sai: có Giải thích từ này và Kiểm đáp án theo thẻ; chưa tự hỏi khi tuỳ chọn tắt', async () => {
        const goi = mo('/hoc?che-do=typing', SAN_SANG, tuyen);
        const o = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        fireEvent.change(o, { target: { value: 'rechnen' } });
        fireEvent.keyDown(o, { key: 'Enter' });
        expect(await screen.findByRole('button', { name: 'Giải thích từ này' })).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Hỏi AI: câu của tôi có đúng không?' }));
        await waitFor(() => expect(daGui(goi, '/ai/kiem-dap-an')[0]?.body).toEqual({ card_id: 9, text: 'rechnen' }));
        expect(daGui(goi, '/ai/giai-thich-tu')).toHaveLength(0);
    });

    it('bật tự giải thích: gõ sai thì tự gọi Giải thích từ một lần', async () => {
        const goi = mo('/hoc?che-do=typing', SAN_SANG, tuyen, true);
        const o = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        fireEvent.change(o, { target: { value: 'rechnen' } });
        fireEvent.keyDown(o, { key: 'Enter' });
        await waitFor(() => expect(daGui(goi, '/ai/giai-thich-tu')).toHaveLength(1));
        expect(await screen.findByRole('heading', { name: 'Nghĩa' })).toBeTruthy();
    });
});

describe('Viết câu', () => {
    it('ô ở trang Bài tập khi AI mở; chấm câu: Gần đúng, chưa dùng từ được giao; hết lượt thì tổng kết', async () => {
        const cham: KetQuaCau = { verdict: 'gan_dung', word_ok: false, corrected: 'Ich zähle bis zehn.', errors: [], explain: 'Câu cần có "zählen".', use: '', tip: '' };
        const goi = mo('/bai-tap', SAN_SANG, {
            'GET /api/v1/bai-tap/viet-cau': () => ({ json: { data: [theHoc(9)] } }),
            'POST /api/v1/ai/viet-cau': () => ({ json: { data: cham, meta: META, saved: { id: 3 } } }),
        });
        fireEvent.click(await screen.findByRole('link', { name: /Viết câu/ }));
        const o = await screen.findByLabelText('Câu của bạn có dùng “zählen”');
        fireEvent.change(o, { target: { value: 'Ich zähle bis zehn.' } });
        fireEvent.click(screen.getByRole('button', { name: 'Chấm câu' }));
        expect(await screen.findByText('Gần đúng')).toBeTruthy();
        expect(screen.getByText('Câu chưa dùng từ được giao.')).toBeTruthy();
        expect(daGui(goi, '/ai/viet-cau')[0].body).toEqual({ card_id: 9, text: 'Ich zähle bis zehn.' });
        fireEvent.click(screen.getByRole('button', { name: 'Xem kết quả' }));
        expect(screen.getByText('Đúng 0 · Gần đúng 1 · Chưa đúng 0')).toBeTruthy();
    });

    it('AI chưa mở: không có ô Viết câu', async () => {
        mo('/bai-tap', { state: 'hidden' });
        expect(await screen.findByRole('link', { name: /Luyện nhanh/ })).toBeTruthy();
        expect(screen.queryByRole('link', { name: /Viết câu/ })).toBeNull();
    });
});

describe('Cài đặt: AI tự giải thích khi sai', () => {
    it('mặc định tắt; bấm thì lưu lên tài khoản rồi tải lại hồ sơ', async () => {
        const goi = mo('/cai-dat', SAN_SANG, { 'PUT /api/v1/toi/cai-dat': (b) => ({ json: { data: { ...toiMau().settings, ...(b as object) } } }) });
        const o = (await screen.findByRole('checkbox', { name: /AI tự giải thích khi trả lời sai/ })) as HTMLInputElement;
        expect(o.checked).toBe(false);
        expect(within(o.closest('label')!).getByText('Mỗi lần sai tốn một lượt AI.')).toBeTruthy();
        fireEvent.click(o);
        await waitFor(() => expect(goi.find((g) => g.method === 'PUT' && (g.body as Record<string, unknown>).ai_auto_explain !== undefined)?.body).toEqual({ ai_auto_explain: true }));
        await waitFor(() => expect(goi.filter((g) => g.url === '/api/v1/toi').length).toBeGreaterThanOrEqual(2));
    });
});
