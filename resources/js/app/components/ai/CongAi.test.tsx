import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { TrangThaiAi } from '../../lib/ai';
import { toiMau, type LanGoi, type Tuyen } from '../../../test/fakeApi';
import { giaNoiDung, tuyenThe } from '../../../test/noiDungGia';
import { renderAppCoTaiKhoan } from '../../../test/render';

// Cổng vào AI ở tab Hỏi AI của thẻ từ (mục tự kiểm 6): khai ngày sinh, chưa đủ tuổi, xin đồng ý, rồi mới dùng được.

const SAN_SANG: TrangThaiAi = { state: 'ready', consent_version: '2026-11', limit: 50, used: 3, reset_at: 1764262800 };
const CAN_DONG_Y: TrangThaiAi = { state: 'need_consent', min_age: 18, consent_version: '2026-11' };

function moThe(ai: TrangThaiAi, doi: Record<string, Tuyen> = {}): LanGoi[] {
    const goi = giaNoiDung({ ...tuyenThe(1), 'GET /api/v1/toi': () => ({ json: { data: toiMau({ ai }) } }), ...doi });
    renderAppCoTaiKhoan('/tu/1');
    return goi;
}

async function moTabAi(ai: TrangThaiAi, doi: Record<string, Tuyen> = {}): Promise<LanGoi[]> {
    const goi = moThe(ai, doi);
    fireEvent.click(await screen.findByRole('tab', { name: 'Hỏi AI' }));
    // Phần AI tải riêng (lazy): chờ tới khi có nội dung trong tab
    await waitFor(() => expect(screen.getByRole('tabpanel').querySelector('h2, button')).toBeTruthy());
    return goi;
}

const chon = (nhan: string, giaTri: string) => fireEvent.change(screen.getByLabelText(nhan), { target: { value: giaTri } });

describe('Cổng AI', () => {
    it('AI chưa mở cho tài khoản (hidden): thẻ từ không có tab Hỏi AI', async () => {
        const goi = moThe({ state: 'hidden' });
        await screen.findByRole('heading', { level: 1 });
        await waitFor(() => expect(goi.map((g) => g.url)).toContain('/api/v1/toi'));
        await act(async () => {});
        expect(screen.getAllByRole('tab').map((tb) => tb.textContent)).toEqual(['Nghĩa và ví dụ', 'Ngữ pháp', 'Ghi chú']);
    });

    it('chưa đủ tuổi từ trước: không có tab Hỏi AI', async () => {
        const goi = moThe({ state: 'underage', min_age: 18 });
        await screen.findByRole('heading', { level: 1 });
        await waitFor(() => expect(goi.map((g) => g.url)).toContain('/api/v1/toi'));
        await act(async () => {});
        expect(screen.queryByRole('tab', { name: 'Hỏi AI' })).toBeNull();
    });

    it('khai ngày sinh: chọn thiếu hay ngày không có thật thì báo ngay, không gọi API', async () => {
        const goi = await moTabAi({ state: 'need_birth_date', min_age: 18 });
        expect(screen.getByRole('heading', { name: 'Ngày sinh của bạn' })).toBeTruthy();
        expect(screen.getByText(/AI chỉ dành cho người từ 18 tuổi/)).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Tiếp tục' }));
        expect(screen.getByRole('alert').textContent).toBe('Chọn đủ ngày, tháng, năm.');

        chon('Ngày', '31');
        chon('Tháng', '2');
        chon('Năm', '2000');
        fireEvent.click(screen.getByRole('button', { name: 'Tiếp tục' }));
        expect(screen.getByRole('alert').textContent).toBe('Ngày này không có thật.');
        expect(goi.some((g) => g.url === '/api/v1/toi/ngay-sinh')).toBe(false);
    });

    it('khai xong thì sang bước đồng ý, tiêu điểm ở tiêu đề mới; đồng ý thì gửi đúng phiên bản và tới nút Giải thích', async () => {
        const goi = await moTabAi(
            { state: 'need_birth_date', min_age: 18 },
            {
                'PUT /api/v1/toi/ngay-sinh': () => ({ json: { data: toiMau({ ai: CAN_DONG_Y }) } }),
                'PUT /api/v1/toi/dong-y-ai': () => ({ json: { data: toiMau({ ai: SAN_SANG }) } }),
            },
        );
        chon('Ngày', '5');
        chon('Tháng', '3');
        chon('Năm', '2000');
        fireEvent.click(screen.getByRole('button', { name: 'Tiếp tục' }));

        const tieuDe = await screen.findByRole('heading', { name: 'Trước khi dùng AI' });
        expect(goi.find((g) => g.url === '/api/v1/toi/ngay-sinh')?.body).toEqual({ birth_date: '2000-03-05' });
        await waitFor(() => expect(document.activeElement).toBe(tieuDe));

        for (const y of ['Google (dịch vụ Gemini)', 'bậc miễn phí của Google', 'không gửi tên, email', 'Microsoft (Azure Speech)', 'AI có thể trả lời sai', 'rút lại đồng ý']) {
            expect(screen.getByText(new RegExp(y.replace(/[()]/g, '\\$&')))).toBeTruthy();
        }
        expect(within(screen.getByRole('tabpanel')).getAllByRole('listitem')).toHaveLength(6);

        fireEvent.click(screen.getByRole('button', { name: 'Tôi đồng ý' }));
        expect(await screen.findByRole('button', { name: 'Giải thích từ này' })).toBeTruthy();
        expect(goi.find((g) => g.url === '/api/v1/toi/dong-y-ai')?.body).toEqual({ granted: true, version: '2026-11' });
    });

    it('khai xong mà chưa đủ tuổi: hiện câu giải thích, không có phần AI; tab vẫn mở tới khi rời trang', async () => {
        await moTabAi({ state: 'need_birth_date', min_age: 18 }, { 'PUT /api/v1/toi/ngay-sinh': () => ({ json: { data: toiMau({ ai: { state: 'underage', min_age: 18 } }) } }) });
        chon('Ngày', '1');
        chon('Tháng', '1');
        chon('Năm', '2015');
        fireEvent.click(screen.getByRole('button', { name: 'Tiếp tục' }));

        expect(await screen.findByText('AI chỉ dành cho người từ 18 tuổi. Phần học còn lại bạn dùng bình thường.')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Giải thích từ này' })).toBeNull();
        expect(screen.getByRole('tab', { name: 'Hỏi AI' })).toBeTruthy();
    });

    it('lỗi của máy chủ khi khai ngày sinh hiện dưới các ô', async () => {
        await moTabAi(
            { state: 'need_birth_date', min_age: 18 },
            { 'PUT /api/v1/toi/ngay-sinh': () => ({ status: 422, json: { message: 'x', errors: { birth_date: ['Ngày sinh không được ở tương lai.'] } } }) },
        );
        chon('Ngày', '1');
        chon('Tháng', '1');
        chon('Năm', '2001');
        fireEvent.click(screen.getByRole('button', { name: 'Tiếp tục' }));

        expect((await screen.findByRole('alert')).textContent).toBe('Ngày sinh không được ở tương lai.');
        expect((screen.getByRole('button', { name: 'Tiếp tục' }) as HTMLButtonElement).disabled).toBe(false);
    });
});
