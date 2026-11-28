import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { KetQuaDich, KetQuaSua, TrangThaiAi } from '../../lib/ai';
import { SAN_SANG, META } from '../../../test/aiGia';
import { giaApi, toiMau, type Tuyen } from '../../../test/fakeApi';
import { renderAppCoTaiKhoan } from '../../../test/render';

// Trang Hỏi AI: cổng, điều hướng, bốn tab; Dịch và Sửa ngữ pháp (mục tự kiểm 10, phần giao diện).

const DICH: KetQuaDich = {
    translation: 'Ich möchte einen Tisch für zwei Personen reservieren.',
    literal: '',
    words: [{ de: 'reservieren', vi: 'đặt trước', note: 'động từ tách được? không' }],
    grammar: 'möchte + động từ nguyên mẫu ở cuối câu.',
    natural: '<b>Einen Tisch für zwei, bitte.</b>',
};

function mo(path: string, ai: TrangThaiAi = SAN_SANG, them: Record<string, Tuyen> = {}) {
    const goi = giaApi({
        'GET /api/v1/toi': () => ({ json: { data: toiMau({ ai }) } }),
        'GET /api/v1/ai/tro-chuyen': () => ({ json: { data: [] } }),
        'GET /api/v1/ai/bai-hoc': () => ({ json: { data: [] } }),
        ...them,
    });
    renderAppCoTaiKhoan(path);
    return goi;
}

describe('Trang Hỏi AI', () => {
    it('AI chưa mở cho tài khoản: không có mục Hỏi AI, mở thẳng đường dẫn thì Không tìm thấy', async () => {
        mo('/hoi-ai', { state: 'hidden' });
        expect(await screen.findByRole('heading', { name: 'Không tìm thấy trang này' })).toBeTruthy();
        expect(screen.queryByRole('link', { name: 'Hỏi AI' })).toBeNull();
    });

    it('có mục Hỏi AI ở thanh trên và nút ở đầu trang điện thoại; chưa đồng ý thì thấy bước đồng ý', async () => {
        mo('/hoi-ai', { state: 'need_consent', min_age: 18, consent_version: '2026-11' });
        expect(await screen.findByRole('heading', { name: 'Trước khi dùng AI' })).toBeTruthy();
        expect(screen.getAllByRole('link', { name: 'Hỏi AI' }).length).toBe(2);
        expect(screen.queryByRole('tab')).toBeNull();
    });

    it('bốn tab; đổi tab ghi vào ?tab; mở bằng ?tab=sua thì vào thẳng tab đó', async () => {
        mo('/hoi-ai?tab=sua');
        const sua = await screen.findByRole('tab', { name: 'Sửa ngữ pháp' });
        expect(sua.getAttribute('aria-selected')).toBe('true');
        expect(screen.getAllByRole('tab').map((t) => t.textContent)).toEqual(['Dịch', 'Sửa ngữ pháp', 'Trò chuyện', 'Bài học AI']);
        fireEvent.click(screen.getByRole('tab', { name: 'Trò chuyện' }));
        expect(screen.getByTestId('duong-dan').textContent).toBe('/hoi-ai?tab=tro-chuyen');
        fireEvent.click(screen.getByRole('tab', { name: 'Dịch' }));
        expect(screen.getByTestId('duong-dan').textContent).toBe('/hoi-ai');
    });

    it('Dịch: gửi chữ và chiều dịch; hiện các phần có chữ, ẩn phần rỗng; thẻ HTML hiện nguyên văn; số lượt còn lại', async () => {
        const goi = mo('/hoi-ai', SAN_SANG, { 'POST /api/v1/ai/dich': () => ({ json: { data: DICH, meta: META } }) });
        fireEvent.change(await screen.findByLabelText('Câu cần dịch'), { target: { value: 'Tôi muốn đặt bàn cho hai người.' } });
        fireEvent.click(screen.getByRole('button', { name: 'Dịch' }));
        expect(await screen.findByText(DICH.translation)).toBeTruthy();
        expect(goi.find((g) => g.method === 'POST')?.body).toEqual({ text: 'Tôi muốn đặt bàn cho hai người.', target: 'de' });
        expect(screen.queryByText('Dịch sát chữ')).toBeNull();
        expect(screen.getByText('Nghĩa từng từ')).toBeTruthy();
        expect(screen.getByText('<b>Einen Tisch für zwei, bitte.</b>')).toBeTruthy();
        expect(document.querySelector('b')).toBeNull();
        expect(screen.getByText('Còn 46 lượt AI hôm nay.')).toBeTruthy();

        fireEvent.click(screen.getByRole('radio', { name: 'Đức → Việt' }));
        fireEvent.click(screen.getByRole('button', { name: 'Dịch' }));
        await waitFor(() => expect(goi.filter((g) => g.method === 'POST').at(-1)?.body).toEqual({ text: 'Tôi muốn đặt bàn cho hai người.', target: 'vi' }));
    });

    it('Sửa ngữ pháp: không có lỗi thì nói rõ; hết lượt thì có giờ có lượt mới, không có nút Thử lại', async () => {
        const sua: KetQuaSua = { corrected: 'Am Wochenende fahre ich ans Meer.', errors: [], natural: '', level: 'A2' };
        let lan = 0;
        mo('/hoi-ai?tab=sua', SAN_SANG, {
            'POST /api/v1/ai/sua-ngu-phap': () =>
                ++lan === 1
                    ? { json: { data: sua, meta: META } }
                    : { status: 429, json: { code: 'ai_het_luot', message: 'Bạn đã dùng hết lượt AI hôm nay.', reset_at: 1764262800 } },
        });
        const o = await screen.findByLabelText('Đoạn tiếng Đức của bạn');
        fireEvent.change(o, { target: { value: 'Am Wochenende fahre ich ans Meer.' } });
        fireEvent.click(screen.getByRole('button', { name: 'Sửa ngữ pháp' }));
        expect(await screen.findByText('Không thấy lỗi nào.')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Sửa ngữ pháp' }));
        const bao = await screen.findByRole('alert');
        expect(within(bao).getByText('Bạn đã dùng hết lượt AI hôm nay.')).toBeTruthy();
        expect(within(bao).getByText(/Có lượt mới lúc/)).toBeTruthy();
        expect(within(bao).queryByRole('button', { name: 'Thử lại' })).toBeNull();
    });
});
