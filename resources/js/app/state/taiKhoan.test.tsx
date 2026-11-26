import { act, fireEvent, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { giaApi, toiMau } from '../../test/fakeApi';
import { renderAppCoTaiKhoan } from '../../test/render';
import { defaultSettings } from './store';
import { CHO_GUI_MS, CHO_KEY, khacNhau, tuApi, veApi } from './taiKhoan';

describe('đổi cài đặt giữa app và API', () => {
    it('0 từ mới mỗi lượt là "tất cả", ngôn ngữ nghĩa là danh sách mã', () => {
        const s = tuApi({ new_per_session: 0, daily_goal: 20, theme: 'dark', font_size: 'lg', season: 'winter', meaning_langs: ['vi', 'zh'] });
        expect(s).toEqual({ theme: 'dark', fontSize: 'lg', season: 'winter', newPerSession: 'all', langs: { vi: true, en: false, zh: true } });
        expect(veApi(s)).toEqual({ theme: 'dark', font_size: 'lg', season: 'winter', new_per_session: 0, meaning_langs: ['vi', 'zh'] });
    });

    it('giá trị lạ từ máy chủ về mặc định, không làm vỡ app', () => {
        const s = tuApi({ new_per_session: 7, daily_goal: 20, theme: 'tim' as 'dark', font_size: 'md', season: 'auto', meaning_langs: null as unknown as string[] });
        expect(s.theme).toBe('light');
        expect(s.newPerSession).toBe(10);
        expect(s.langs).toEqual({ vi: false, en: false, zh: false });
    });

    it('chỉ gửi phần đã đổi; không đổi gì thì không gửi', () => {
        expect(khacNhau(defaultSettings, { ...defaultSettings })).toBeNull();
        expect(khacNhau(defaultSettings, { ...defaultSettings, theme: 'dark', langs: { vi: true, en: false, zh: true } })).toEqual({
            theme: 'dark',
            langs: { vi: true, en: false, zh: true },
        });
    });
});

describe('TaiKhoanProvider', () => {
    it('mở app: áp cài đặt của tài khoản (máy khác đã đổi sang giao diện tối)', async () => {
        giaApi({ 'GET /api/v1/toi': () => ({ json: { data: toiMau({ settings: { ...toiMau().settings, theme: 'dark', new_per_session: 30 } }) } }) });
        renderAppCoTaiKhoan('/cai-dat');
        expect(await screen.findByText(/lưu theo tài khoản/)).toBeTruthy();
        expect(document.documentElement.dataset.theme).toBe('dark');
        expect((screen.getByRole('radio', { name: '30' }) as HTMLInputElement).checked).toBe(true);
    });

    it('đổi cài đặt: chờ ngừng bấm rồi gửi một lần phần đã đổi, báo đã lưu', async () => {
        const goi = giaApi({
            'GET /api/v1/toi': () => ({ json: { data: toiMau() } }),
            'PUT /api/v1/toi/cai-dat': (b) => ({ json: { data: { ...toiMau().settings, ...(b as object) } } }),
        });
        renderAppCoTaiKhoan('/cai-dat');
        await screen.findByText(/lưu theo tài khoản/);
        vi.useFakeTimers();
        fireEvent.click(screen.getByRole('radio', { name: 'Tối' }));
        fireEvent.click(screen.getByRole('radio', { name: 'Lớn' }));
        expect(goi.filter((g) => g.method === 'PUT')).toHaveLength(0);
        await act(() => vi.advanceTimersByTimeAsync(CHO_GUI_MS + 10));
        vi.useRealTimers();
        const put = goi.filter((g) => g.method === 'PUT');
        expect(put).toHaveLength(1);
        expect(put[0].body).toEqual({ theme: 'dark', font_size: 'lg' });
        expect(await screen.findByText('Đã lưu.')).toBeTruthy();
    });

    it('lưu lên máy chủ hỏng: báo rõ, cài đặt trên máy vẫn đổi', async () => {
        giaApi({
            'GET /api/v1/toi': () => ({ json: { data: toiMau() } }),
            'PUT /api/v1/toi/cai-dat': () => ({ status: 500, json: { message: 'Server Error' } }),
        });
        renderAppCoTaiKhoan('/cai-dat');
        await screen.findByText(/lưu theo tài khoản/);
        fireEvent.click(screen.getByRole('radio', { name: 'Tối' }));
        expect(await screen.findByText(/Chưa lưu được lên tài khoản/, {}, { timeout: 2000 })).toBeTruthy();
        expect(document.documentElement.dataset.theme).toBe('dark');
    });

    it('đổi rồi tải lại trang ngay (chưa kịp gửi): lần mở sau, phần chờ trên máy thắng và được gửi tiếp', async () => {
        window.localStorage.setItem(CHO_KEY, JSON.stringify({ theme: 'dark', fontSize: 'cuc-lon' }));
        const goi = giaApi({
            'GET /api/v1/toi': () => ({ json: { data: toiMau() } }),
            'PUT /api/v1/toi/cai-dat': (b) => ({ json: { data: { ...toiMau().settings, ...(b as object) } } }),
        });
        renderAppCoTaiKhoan('/cai-dat');
        await screen.findByText(/lưu theo tài khoản/);
        expect(document.documentElement.dataset.theme).toBe('dark');
        await vi.waitFor(() => expect(goi.filter((g) => g.method === 'PUT').map((g) => g.body)).toEqual([{ theme: 'dark' }]), { timeout: 2000 });
        await vi.waitFor(() => expect(window.localStorage.getItem(CHO_KEY)).toBeNull());
    });

    it('rời trang ngay sau khi đổi: gửi luôn bằng keepalive, không chờ', async () => {
        const goi = giaApi({ 'GET /api/v1/toi': () => ({ json: { data: toiMau() } }) });
        renderAppCoTaiKhoan('/cai-dat');
        await screen.findByText(/lưu theo tài khoản/);
        fireEvent.click(screen.getByRole('radio', { name: 'Tối' }));
        expect(JSON.parse(window.localStorage.getItem(CHO_KEY) ?? 'null')).toEqual({ theme: 'dark' });
        window.dispatchEvent(new Event('pagehide'));
        const put = goi.filter((g) => g.method === 'PUT');
        expect(put.map((g) => g.body)).toEqual([{ theme: 'dark' }]);
        const init = vi.mocked(fetch).mock.calls.at(-1)?.[1];
        expect(init?.keepalive).toBe(true);
    });

    it('không tải được tài khoản (mất mạng): app vẫn chạy, cài đặt lưu trên trình duyệt', async () => {
        const fetchGia = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'));
        vi.stubGlobal('fetch', fetchGia);
        renderAppCoTaiKhoan('/cai-dat');
        await vi.waitFor(() => expect(fetchGia).toHaveBeenCalled());
        await act(async () => {});
        expect(screen.getByText('Cài đặt lưu trên trình duyệt này.')).toBeTruthy();
        expect(screen.queryByRole('heading', { name: 'Tài khoản' })).toBeNull();
    });
});
