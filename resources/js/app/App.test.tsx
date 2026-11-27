import { act, fireEvent, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { renderApp } from '../test/render';

const where = () => screen.getByTestId('duong-dan').textContent;

describe('App: điều hướng', () => {
    it('mở /app thì chuyển sang Hôm nay, có tên app và đủ mục điều hướng', () => {
        renderApp('/');
        expect(where()).toBe('/hom-nay');
        expect(screen.getByRole('link', { name: 'Lernbuch' })).toBeTruthy();
        // Máy tính: 7 mục trên thanh trên. Điện thoại: 5 mục ở thanh dưới, Sổ tay và Cài đặt là nút trên thanh trên.
        // CSS ẩn bớt theo cỡ màn hình, nên mỗi mục có hai liên kết.
        const navs = screen.getAllByRole('navigation', { name: 'Điều hướng chính' });
        expect(navs).toHaveLength(2);
        expect(within(navs[0]).getAllByRole('link')).toHaveLength(7);
        expect(within(navs[1]).getAllByRole('link').map((a) => a.textContent)).toEqual(['Hôm nay', 'Chủ đề', 'Ngữ pháp', 'Bài tập', 'Tra từ']);
        for (const label of ['Hôm nay', 'Chủ đề', 'Ngữ pháp', 'Bài tập', 'Tra từ', 'Sổ tay', 'Cài đặt']) {
            expect(screen.getAllByRole('link', { name: label })).toHaveLength(2);
        }
        expect(document.title).toBe('Hôm nay · Lernbuch');
    });

    it('mất mạng thì báo ngay dưới thanh trên cùng (vẫn học được chủ đề đã tải); có mạng lại thì ẩn', () => {
        const coMang = vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);
        renderApp('/chu-de');
        const bao = 'Không có mạng: vẫn học được chủ đề đã tải về máy; lượt chấm sẽ tự gửi khi có mạng.';
        expect(screen.getByText(bao).closest('[role="status"]')).toBeTruthy();

        coMang.mockReturnValue(true);
        act(() => {
            window.dispatchEvent(new Event('online'));
        });
        expect(screen.queryByText(bao)).toBeNull();
    });

    it('đường dẫn lạ hiện trang không tìm thấy, có đường về Hôm nay', () => {
        renderApp('/khong-co-trang-nay');
        expect(screen.getByRole('heading', { level: 1, name: 'Không tìm thấy trang này' })).toBeTruthy();
        fireEvent.click(screen.getByRole('link', { name: 'Về Hôm nay' }));
        expect(where()).toBe('/hom-nay');
    });

    it('Bảng động từ thuộc mục Ngữ pháp, Số nhiều thuộc mục Bài tập', () => {
        renderApp('/dong-tu');
        expect(screen.getAllByRole('link', { name: 'Ngữ pháp' }).every((a) => a.getAttribute('aria-current') === 'page')).toBe(true);
        renderApp('/so-nhieu');
        expect(screen.getAllByRole('link', { name: 'Bài tập' }).some((a) => a.getAttribute('aria-current') === 'page')).toBe(true);
    });

    it('bấm mục điều hướng thì đổi màn hình, đổi tiêu đề và đặt tiêu điểm vào nội dung', () => {
        renderApp('/hom-nay');
        fireEvent.click(screen.getAllByRole('link', { name: 'Cài đặt' })[0]);
        expect(where()).toBe('/cai-dat');
        expect(document.title).toBe('Cài đặt · Lernbuch');
        expect(document.activeElement?.id).toBe('noi-dung');
    });

    it('trang học và lượt làm bài không có thanh điều hướng', () => {
        renderApp('/hoc');
        expect(screen.queryByRole('navigation', { name: 'Điều hướng chính' })).toBeNull();
        expect(screen.getByRole('button', { name: 'Thoát phiên học (Esc)' })).toBeTruthy();
        renderApp('/bai-tap/lam');
        renderApp('/bai-tap/dien-cau');
        renderApp('/bai-tap/chia-dong-tu');
        expect(screen.queryByRole('navigation', { name: 'Điều hướng chính' })).toBeNull();
    });
});
