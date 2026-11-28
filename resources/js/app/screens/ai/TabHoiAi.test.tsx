import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { GiaiThichTu, TrangThaiAi } from '../../lib/ai';
import { toiMau, type LanGoi, type Tuyen } from '../../../test/fakeApi';
import { giaNoiDung, tuyenThe } from '../../../test/noiDungGia';
import { renderAppCoTaiKhoan } from '../../../test/render';

// Tab Hỏi AI, chức năng Giải thích từ (mục tự kiểm 2 phần giao diện): kết quả hiện như chữ, lỗi nói rõ, số lượt còn lại.

const SAN_SANG: TrangThaiAi = { state: 'ready', consent_version: '2026-11', limit: 50, used: 3, reset_at: 1764262800 };
const KET_QUA: GiaiThichTu = {
    meaning: 'bánh mì nhỏ, ổ tròn',
    usage: 'Dùng khi mua bánh buổi sáng.',
    examples: [{ de: 'Ich hätte gern zwei Brötchen.', vi: 'Cho tôi hai cái bánh mì nhỏ.' }],
    common_mistakes: ['Quên số nhiều không đổi: die Brötchen.'],
};
const META = { cached: false, limit: 50, used: 4, remaining: 46, reset_at: 1764262800 };

async function moTab(doi: Record<string, Tuyen> = {}, ai: TrangThaiAi = SAN_SANG): Promise<LanGoi[]> {
    const goi = giaNoiDung({
        ...tuyenThe(1),
        'GET /api/v1/toi': () => ({ json: { data: toiMau({ ai }) } }),
        'POST /api/v1/ai/giai-thich-tu': () => ({ json: { data: KET_QUA, meta: META } }),
        ...doi,
    });
    renderAppCoTaiKhoan('/tu/1');
    fireEvent.click(await screen.findByRole('tab', { name: 'Hỏi AI' }));
    await screen.findByRole('button', { name: 'Giải thích từ này' });
    return goi;
}

const bamGiaiThich = () => fireEvent.click(screen.getByRole('button', { name: 'Giải thích từ này' }));

describe('Tab Hỏi AI', () => {
    it('bấm giải thích: đang trả lời rồi bốn phần; gửi đúng mã thẻ; số lượt còn lại từ máy chủ', async () => {
        const goi = await moTab();
        expect(screen.getByText('Còn 47 lượt AI hôm nay.')).toBeTruthy();
        expect(screen.getByText(/AI có thể sai/)).toBeTruthy();

        bamGiaiThich();
        expect(screen.getByText('AI đang trả lời…')).toBeTruthy();
        expect((screen.getByRole('button', { name: 'Giải thích từ này' }) as HTMLButtonElement).disabled).toBe(true);

        expect(await screen.findByText('bánh mì nhỏ, ổ tròn')).toBeTruthy();
        const panel = screen.getByRole('tabpanel');
        expect(within(panel).getAllByRole('heading', { level: 3 }).map((h) => h.textContent)).toEqual(['Nghĩa', 'Cách dùng', 'Ví dụ', 'Lỗi hay gặp']);
        expect(within(panel).getByText('Ich hätte gern zwei Brötchen.').getAttribute('lang')).toBe('de');
        expect(screen.getByText('Còn 46 lượt AI hôm nay.')).toBeTruthy();
        expect(screen.queryByText('AI đang trả lời…')).toBeNull();
        expect(goi.find((g) => g.url === '/api/v1/ai/giai-thich-tu')?.body).toEqual({ card_id: 1 });
    });

    it('phần rỗng bị ẩn; chữ có thẻ HTML hiện nguyên văn, không sinh phần tử', async () => {
        await moTab({
            'POST /api/v1/ai/giai-thich-tu': () => ({ json: { data: { meaning: '<b>x</b><script>alert(1)</script>', usage: '', examples: [], common_mistakes: [] }, meta: META } }),
        });
        bamGiaiThich();

        expect(await screen.findByText('<b>x</b><script>alert(1)</script>')).toBeTruthy();
        const panel = screen.getByRole('tabpanel');
        expect(panel.querySelector('script, b')).toBeNull();
        expect(within(panel).getAllByRole('heading', { level: 3 }).map((h) => h.textContent)).toEqual(['Nghĩa']);
    });

    it('hết lượt: chữ của máy chủ và giờ có lượt mới theo múi giờ tài khoản; không có nút Thử lại', async () => {
        await moTab({
            'POST /api/v1/ai/giai-thich-tu': () => ({
                status: 429,
                json: { code: 'ai_het_luot', message: 'Bạn đã dùng hết lượt AI hôm nay.', reset_at: 1764262800, limit: 50 },
            }),
        });
        bamGiaiThich();

        const loi = await screen.findByRole('alert');
        expect(within(loi).getByText('Bạn đã dùng hết lượt AI hôm nay.')).toBeTruthy();
        expect(within(loi).getByText('Có lượt mới lúc 00:00.')).toBeTruthy();
        expect(within(loi).queryByRole('button')).toBeNull();
    });

    it('quá tải hay lỗi tạm thời: chữ của máy chủ và nút Thử lại gọi lại', async () => {
        let lan = 0;
        const goi = await moTab({
            'POST /api/v1/ai/giai-thich-tu': () =>
                ++lan === 1
                    ? { status: 503, json: { code: 'ai_qua_tai', message: 'AI đang quá tải, thử lại sau ít phút.' } }
                    : { json: { data: KET_QUA, meta: META } },
        });
        bamGiaiThich();

        const loi = await screen.findByRole('alert');
        expect(within(loi).getByText('AI đang quá tải, thử lại sau ít phút.')).toBeTruthy();
        fireEvent.click(within(loi).getByRole('button', { name: 'Thử lại' }));
        expect(await screen.findByText('bánh mì nhỏ, ổ tròn')).toBeTruthy();
        expect(screen.queryByRole('alert')).toBeNull();
        expect(goi.filter((g) => g.url === '/api/v1/ai/giai-thich-tu')).toHaveLength(2);
    });

    it('mất mạng: báo cần có mạng', async () => {
        await moTab();
        const goc = globalThis.fetch;
        vi.stubGlobal('fetch', (url: string, init?: RequestInit) => (url.includes('/ai/') ? Promise.reject(new TypeError('Failed to fetch')) : goc(url, init)));
        bamGiaiThich();

        expect((await screen.findByRole('alert')).textContent).toContain('Cần có mạng để hỏi AI.');
    });

    it('máy chủ báo cần đồng ý lại: tải lại hồ sơ và hiện bước đồng ý', async () => {
        let lanToi = 0;
        const goi = await moTab({
            'GET /api/v1/toi': () => ({ json: { data: toiMau({ ai: ++lanToi === 1 ? SAN_SANG : { state: 'need_consent', min_age: 18, consent_version: '2026-11' } }) } }),
            'POST /api/v1/ai/giai-thich-tu': () => ({ status: 403, json: { code: 'ai_can_dong_y', message: 'Bạn cần đồng ý gửi nội dung cho AI trước khi dùng.' } }),
        });
        bamGiaiThich();

        expect(await screen.findByRole('heading', { name: 'Trước khi dùng AI' })).toBeTruthy();
        await waitFor(() => expect(goi.filter((g) => g.url === '/api/v1/toi')).toHaveLength(2));
    });
});
