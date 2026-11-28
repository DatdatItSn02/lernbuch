import { fireEvent, screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { BaiHocDaLuu, NoiDungBaiHoc } from '../../lib/ai';
import { META, SAN_SANG } from '../../../test/aiGia';
import { giaApi, toiMau, type Tuyen } from '../../../test/fakeApi';
import { renderAppCoTaiKhoan } from '../../../test/render';
import { tachTu } from './BaiHocAi';

// Bài học AI (mục tự kiểm 10, phần giao diện): soạn, danh sách, làm bài, chấm điểm, xoá.

const BAI: NoiDungBaiHoc = {
    title: 'Im Café',
    level: 'A1',
    text: [{ de: 'Ich trinke Kaffee.', vi: 'Tôi uống cà phê.' }],
    new_words: [{ de: 'der Kaffee', vi: 'cà phê' }],
    questions: [{ q: 'Tôi uống gì?', choices: ['Tee', 'Kaffee', 'Wasser'], answer: 1 }],
    exercise: [{ de: 'Ich ____ Kaffee.', answer: 'trinke' }],
};
const LUU: BaiHocDaLuu = { id: 7, title: 'Im Café', level: 'A1', score: null, completed_at: null, created_at: 1764201600 };

function mo(path: string, them: Record<string, Tuyen> = {}) {
    const goi = giaApi({
        'GET /api/v1/toi': () => ({ json: { data: toiMau({ ai: SAN_SANG }) } }),
        'GET /api/v1/ai/bai-hoc': () => ({ json: { data: [{ ...LUU, score: 80 }] } }),
        'GET /api/v1/ai/bai-hoc/7': () => ({ json: { data: BAI, lesson: LUU } }),
        ...them,
    });
    renderAppCoTaiKhoan(path);
    return goi;
}

describe('Bài học AI', () => {
    it('tách từ theo dấu phẩy hay xuống dòng, bỏ ô trống', () => {
        expect(tachTu(' das Brot, trinken ,,\nder Kaffee ')).toEqual(['das Brot', 'trinken', 'der Kaffee']);
    });

    it('danh sách bài kèm điểm; nút gợi ý điền chủ đề; soạn bài gửi đúng thân rồi mở bài', async () => {
        const goi = mo('/hoi-ai?tab=bai-hoc', { 'POST /api/v1/ai/bai-hoc': () => ({ json: { data: BAI, meta: META, lesson: LUU } }) });
        expect(await screen.findByText('80 điểm', { exact: false })).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Ăn uống' }));
        expect((screen.getByLabelText('Chủ đề') as HTMLInputElement).value).toBe('Ăn uống');
        fireEvent.click(screen.getByRole('radio', { name: 'A2' }));
        fireEvent.change(screen.getByLabelText(/Từ muốn luyện/), { target: { value: 'das Brot, trinken' } });
        fireEvent.click(screen.getByRole('button', { name: 'Soạn bài' }));
        await waitFor(() => expect(screen.getByTestId('duong-dan').textContent).toBe('/hoi-ai/bai-hoc/7'));
        expect(goi.find((g) => g.method === 'POST')?.body).toEqual({ topic: 'Ăn uống', level: 'A2', words: ['das Brot', 'trinken'] });
    });

    it('quá 10 từ, từ có ký hiệu, chủ đề trống: báo ngay, không gọi API', async () => {
        const goi = mo('/hoi-ai?tab=bai-hoc');
        const chuDe = await screen.findByLabelText('Chủ đề');
        fireEvent.click(screen.getByRole('button', { name: 'Soạn bài' }));
        expect(screen.getByRole('alert').textContent).toBe('Hãy gõ nội dung trước.');
        fireEvent.change(chuDe, { target: { value: 'Đi lại' } });
        fireEvent.change(screen.getByLabelText(/Từ muốn luyện/), { target: { value: Array.from({ length: 11 }, (_, i) => `Wort${'abcdefghijk'[i]}`).join(', ') } });
        fireEvent.click(screen.getByRole('button', { name: 'Soạn bài' }));
        expect(screen.getByRole('alert').textContent).toBe('Tối đa 10 từ.');
        fireEvent.change(screen.getByLabelText(/Từ muốn luyện/), { target: { value: '<b>x</b>' } });
        fireEvent.click(screen.getByRole('button', { name: 'Soạn bài' }));
        expect(screen.getByRole('alert').textContent).toContain('Mỗi từ chỉ gồm chữ cái');
        expect(goi.some((g) => g.method === 'POST')).toBe(false);
    });

    it('làm bài: chọn đáp án, điền từ, chấm bài thì báo điểm và gửi điểm', async () => {
        const goi = mo('/hoi-ai/bai-hoc/7', { 'PUT /api/v1/ai/bai-hoc/7/ket-qua': (b) => ({ json: { data: { ...LUU, score: (b as { score: number }).score } } }) });
        expect(await screen.findByRole('heading', { name: 'Im Café' })).toBeTruthy();
        fireEvent.click(screen.getByRole('radio', { name: 'Kaffee' }));
        fireEvent.change(screen.getByLabelText(/Ich ____ Kaffee/), { target: { value: 'Trinke' } });
        fireEvent.click(screen.getByRole('button', { name: 'Chấm bài' }));
        expect(await screen.findByText('Bạn đúng 2/2 câu: 100 điểm.')).toBeTruthy();
        await waitFor(() => expect(goi.find((g) => g.method === 'PUT')?.body).toEqual({ score: 100 }));
    });

    it('bài không có (404): Không tìm thấy, có đường về danh sách bài', async () => {
        mo('/hoi-ai/bai-hoc/99');
        expect(await screen.findByText('Không tìm thấy bài học này.')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Về danh sách bài' })).toBeTruthy();
    });
});
