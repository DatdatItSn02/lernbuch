import { fireEvent, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderApp } from '../test/render';

const where = () => screen.getByTestId('duong-dan').textContent;

describe('App: điều hướng', () => {
    it('mở /app thì chuyển sang Hôm nay, có tên app và đủ 5 mục điều hướng', () => {
        renderApp('/');
        expect(where()).toBe('/hom-nay');
        expect(screen.getByRole('link', { name: 'Lernbuch' })).toBeTruthy();
        // Thanh trên (máy tính) và thanh dưới (điện thoại) cùng có 5 mục; CSS ẩn bớt một thanh theo cỡ màn hình
        const navs = screen.getAllByRole('navigation', { name: 'Điều hướng chính' });
        expect(navs).toHaveLength(2);
        for (const label of ['Hôm nay', 'Chủ đề', 'Ngữ pháp', 'Tra từ', 'Cài đặt']) {
            expect(screen.getAllByRole('link', { name: label })).toHaveLength(2);
        }
        expect(document.title).toBe('Hôm nay · Lernbuch');
    });

    it('đường dẫn lạ hiện trang không tìm thấy, có đường về Hôm nay', () => {
        renderApp('/khong-co-trang-nay');
        expect(screen.getByRole('heading', { level: 1, name: 'Không tìm thấy trang này' })).toBeTruthy();
        fireEvent.click(screen.getByRole('link', { name: 'Về Hôm nay' }));
        expect(where()).toBe('/hom-nay');
    });

    it('Ngữ pháp và Tra từ báo rõ là sẽ có trong bản G1', () => {
        renderApp('/ngu-phap');
        expect(screen.getByRole('heading', { level: 1, name: 'Ngữ pháp' })).toBeTruthy();
        expect(screen.getByText(/sẽ có trong bản G1/)).toBeTruthy();
    });

    it('bấm mục điều hướng thì đổi màn hình, đổi tiêu đề và đặt tiêu điểm vào nội dung', () => {
        renderApp('/hom-nay');
        fireEvent.click(screen.getAllByRole('link', { name: 'Cài đặt' })[0]);
        expect(where()).toBe('/cai-dat');
        expect(document.title).toBe('Cài đặt · Lernbuch');
        expect(document.activeElement?.id).toBe('noi-dung');
    });

    it('trang học không có thanh điều hướng', () => {
        renderApp('/hoc');
        expect(screen.queryByRole('navigation', { name: 'Điều hướng chính' })).toBeNull();
        expect(screen.getByRole('button', { name: 'Thoát phiên học (Esc)' })).toBeTruthy();
    });
});
