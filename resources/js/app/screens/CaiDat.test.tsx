import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it, vi } from 'vitest';
import { AppRoutes } from '../App';
import { SETTINGS_KEY, StoreProvider } from '../state/store';
import { giaApi, toiMau } from '../../test/fakeApi';
import { renderApp, renderAppCoTaiKhoan } from '../../test/render';

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

describe('Cài đặt: phần tài khoản', () => {
    const phien = [
        { browser: 'Chrome', os: 'Windows', device_type: 'desktop', ip: '203.0.113.5', last_active_at: 1764201600, is_current: true },
        { browser: 'Safari', os: 'iOS', device_type: 'phone', ip: '198.51.100.7', last_active_at: 1764100000, is_current: false },
    ];

    function moi(tuyenThem: Parameters<typeof giaApi>[0] = {}) {
        const goi = giaApi({
            'GET /api/v1/toi': () => ({ json: { data: toiMau() } }),
            'GET /api/v1/toi/phien': () => ({ json: { data: phien, supported: true } }),
            'PUT /api/v1/toi/ho-so': (b) => ({ json: { data: toiMau({ course: { ...toiMau().course!, ...(b as object) } }) } }),
            ...tuyenThem,
        });
        renderAppCoTaiKhoan('/cai-dat');
        return goi;
    }

    it('chọn mục tiêu và trình độ: gửi lên tài khoản, báo đã lưu', async () => {
        const goi = moi();
        fireEvent.click(await screen.findByRole('radio', { name: 'Đi làm' }));
        expect(await screen.findByText('Đã lưu.')).toBeTruthy();
        fireEvent.click(screen.getByRole('radio', { name: 'A2 · Sơ cấp' }));
        await vi.waitFor(() => expect(goi.filter((g) => g.method === 'PUT').map((g) => g.body)).toEqual([{ goal: 'work' }, { level: 2 }]));
    });

    it('tên để trống thì báo ngay, không gửi; máy chủ từ chối thì hiện lỗi của máy chủ', async () => {
        const goi = moi({ 'PUT /api/v1/toi/ho-so': () => ({ status: 422, json: { message: 'x', errors: { name: ['Tên không được dài quá 100 ký tự.'] } } }) });
        const o = await screen.findByLabelText('Tên của bạn');
        fireEvent.change(o, { target: { value: '   ' } });
        fireEvent.click(screen.getByRole('button', { name: 'Lưu tên' }));
        expect(await screen.findByText('Nhập tên của bạn.')).toBeTruthy();
        expect(o.getAttribute('aria-invalid')).toBe('true');
        expect(goi.some((g) => g.method === 'PUT')).toBe(false);

        fireEvent.change(o, { target: { value: 'Lan Anh' } });
        fireEvent.click(screen.getByRole('button', { name: 'Lưu tên' }));
        expect(await screen.findByText('Tên không được dài quá 100 ký tự.')).toBeTruthy();
    });

    it('danh sách máy đang đăng nhập: ghi rõ máy này; đăng xuất máy khác cần đúng mật khẩu', async () => {
        const goi = moi({
            'POST /api/v1/toi/phien/dang-xuat-may-khac': (b) =>
                (b as { password: string }).password === 'dung-mat-khau-1'
                    ? { json: { data: { remaining: 1 } } }
                    : { status: 422, json: { message: 'x', errors: { password: ['Mật khẩu không đúng.'] } } },
        });
        expect(await screen.findByText('Chrome trên Windows')).toBeTruthy();
        expect(screen.getByText('Máy này')).toBeTruthy();
        expect(screen.getByText('Safari trên iOS')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Đăng xuất các máy khác' }));
        const matKhau = screen.getByLabelText('Nhập mật khẩu để đăng xuất các máy khác');
        fireEvent.click(screen.getByRole('button', { name: 'Đăng xuất các máy khác' }));
        expect(await screen.findByText('Nhập mật khẩu của bạn.')).toBeTruthy();

        fireEvent.change(matKhau, { target: { value: 'sai-roi' } });
        fireEvent.click(screen.getByRole('button', { name: 'Đăng xuất các máy khác' }));
        expect(await screen.findByText('Mật khẩu không đúng.')).toBeTruthy();

        fireEvent.change(matKhau, { target: { value: 'dung-mat-khau-1' } });
        fireEvent.click(screen.getByRole('button', { name: 'Đăng xuất các máy khác' }));
        expect(await screen.findByText('Đã đăng xuất mọi máy khác.')).toBeTruthy();
        expect(goi.filter((g) => g.url === '/api/v1/toi/phien').length).toBe(2);
    });

    it('có nút đăng xuất gửi về trang đăng xuất của máy chủ', async () => {
        moi();
        const nut = await screen.findByRole('button', { name: 'Đăng xuất' });
        const form = nut.closest('form')!;
        expect(form.getAttribute('method')).toBe('post');
        expect(form.getAttribute('action')).toBe('/dang-xuat');
    });
});
