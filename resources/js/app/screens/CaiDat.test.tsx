import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it, vi } from 'vitest';
import { AppRoutes } from '../App';
import { SETTINGS_KEY, StoreProvider } from '../state/store';
import { renderApp } from '../../test/render';

const saved = () => JSON.parse(window.localStorage.getItem(SETTINGS_KEY) ?? 'null');

/** Mở app như người dùng thật: cài đặt đọc từ localStorage (không truyền initial). */
function openReal(path: string) {
    return render(
        <StoreProvider>
            <MemoryRouter initialEntries={[path]}>
                <AppRoutes />
            </MemoryRouter>
        </StoreProvider>,
    );
}

describe('Cài đặt', () => {
    it('đổi giao diện tối: gắn data-theme lên html và lưu lại', () => {
        renderApp('/cai-dat');
        expect(screen.getByText('Cài đặt lưu trên trình duyệt này.')).toBeTruthy();
        fireEvent.click(screen.getByRole('radio', { name: 'Tối' }));
        expect(document.documentElement.dataset.theme).toBe('dark');
        expect(saved().theme).toBe('dark');
    });

    it('cỡ chữ, mùa, số từ mới, thứ tiếng của nghĩa đều lưu', () => {
        renderApp('/cai-dat');
        fireEvent.click(screen.getByRole('radio', { name: 'Lớn' }));
        fireEvent.click(screen.getByRole('radio', { name: 'Đông' }));
        fireEvent.click(screen.getByRole('radio', { name: '30' }));
        fireEvent.click(screen.getByRole('checkbox', { name: 'Tiếng Trung' }));
        expect(document.documentElement.dataset.fontSize).toBe('lg');
        expect(document.documentElement.dataset.season).toBe('winter');
        expect(saved()).toMatchObject({ fontSize: 'lg', season: 'winter', newPerSession: 30, langs: { vi: true, en: true, zh: false } });
    });

    it('mở lại app thì dùng cài đặt đã lưu', () => {
        window.localStorage.setItem(SETTINGS_KEY, JSON.stringify({ theme: 'dark', fontSize: 'sm', newPerSession: 50 }));
        openReal('/cai-dat');
        expect((screen.getByRole('radio', { name: 'Tối' }) as HTMLInputElement).checked).toBe(true);
        expect((screen.getByRole('radio', { name: 'Nhỏ' }) as HTMLInputElement).checked).toBe(true);
        expect((screen.getByRole('radio', { name: '50' }) as HTMLInputElement).checked).toBe(true);
        expect(document.documentElement.dataset.theme).toBe('dark');
    });

    it('dữ liệu lưu bị hỏng thì dùng mặc định, không vỡ trang', () => {
        window.localStorage.setItem(SETTINGS_KEY, '{không phải JSON');
        openReal('/cai-dat');
        expect((screen.getByRole('radio', { name: 'Sáng' }) as HTMLInputElement).checked).toBe(true);
        expect((screen.getByRole('radio', { name: 'Vừa' }) as HTMLInputElement).checked).toBe(true);
    });

    it('giá trị lạ trong dữ liệu lưu bị bỏ, giá trị đúng giữ lại', () => {
        window.localStorage.setItem(SETTINGS_KEY, JSON.stringify({ theme: 'tim', fontSize: 'lg', newPerSession: 7, langs: { zh: false, en: 'co' } }));
        openReal('/cai-dat');
        expect(document.documentElement.dataset.theme).toBe('light');
        expect((screen.getByRole('radio', { name: 'Lớn' }) as HTMLInputElement).checked).toBe(true);
        expect((screen.getByRole('radio', { name: '10' }) as HTMLInputElement).checked).toBe(true);
        expect((screen.getByRole('checkbox', { name: 'Tiếng Trung' }) as HTMLInputElement).checked).toBe(false);
        expect((screen.getByRole('checkbox', { name: 'Tiếng Anh' }) as HTMLInputElement).checked).toBe(true);
    });

    it('trình duyệt chặn lưu thì báo rõ, cài đặt vẫn đổi được trong lúc dùng', () => {
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new DOMException('Hết chỗ', 'QuotaExceededError');
        });
        renderApp('/cai-dat');
        expect(screen.getByRole('alert').textContent).toContain('Trình duyệt đang chặn lưu');
        fireEvent.click(screen.getByRole('radio', { name: 'Tối' }));
        expect(document.documentElement.dataset.theme).toBe('dark');
    });

    it('mùa "Theo tháng" ghi rõ mùa đang dùng', () => {
        renderApp('/cai-dat');
        expect(screen.getByRole('radio', { name: /^Theo tháng \(đang (xuân|hạ|thu|đông)\)$/ })).toBeTruthy();
    });
});
