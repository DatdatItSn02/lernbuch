import 'fake-indexeddb/auto';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { AppRoutes } from '../App';
import { cacLuot, datLaiChoTest, themLuot } from '../lib/hangCho';
import { KHO_API, cacGoi, datLaiOfflineChoTest, giuPhien, phamVi, phienDoCon, taiChuDe } from '../lib/offline';
import { SETTINGS_KEY, StoreProvider } from '../state/store';
import { giaApi, toiMau } from '../../test/fakeApi';
import { giaCaches } from '../../test/fakeCaches';
import { phienMau, theHoc } from '../../test/noiDungGia';
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

describe('Cài đặt: cài app và đăng xuất trên máy học offline', () => {
    const toi = { 'GET /api/v1/toi': () => ({ json: { data: toiMau() } }), 'GET /api/v1/toi/phien': () => ({ json: { data: [], supported: true } }) };

    afterEach(async () => {
        datLaiOfflineChoTest();
        await datLaiChoTest();
    });

    it('đăng xuất: gửi nốt lượt chấm, dọn chủ đề đã tải, phiên dở và bản lưu trên máy, rồi mới gửi form', async () => {
        const gui = vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {});
        const kho = giaCaches();
        const goi = giaApi({
            ...toi,
            'GET /api/v1/hoc/tai/so': () => ({ json: { data: { topic: { slug: 'so', name: 'Số', collection: 'vocab' }, timezone: 'UTC', new_per_session: 10, saved_at: 1, cards: [theHoc(8)] } } }),
            'POST /api/v1/hoc/luot-cham': () => ({
                json: { data: { received: 1, duplicates: 0, skipped: [], coins: 0, too_fast: 0, cards: [], today: { reviewed: 1, goal: 20, coins: 0, streak: 1 } } },
            }),
        });
        await taiChuDe('so');
        await giuPhien(phienMau([theHoc(8)], { started_at: Math.floor(Date.now() / 1000) }), phamVi(null, false, 'flashcard'));
        await (await caches.open(KHO_API)).put('/api/v1/toi', new Response('{}'));
        await themLuot({ uid: '01JDTEST0000000000000000AB', card_id: 8, session_id: null, mode: 'flashcard', rating: 2, reviewed_at_ms: Date.now(), duration_ms: 3000 });
        renderAppCoTaiKhoan('/cai-dat');

        fireEvent.click(await screen.findByRole('button', { name: 'Đăng xuất' }));
        expect(screen.getByText('Đang gửi nốt lượt chấm và dọn dữ liệu trên máy…')).toBeTruthy();
        await waitFor(() => expect(gui).toHaveBeenCalledTimes(1));
        expect(goi.filter((g) => g.url === '/api/v1/hoc/luot-cham')).toHaveLength(1);
        expect(await cacLuot()).toEqual([]);
        expect(await cacGoi()).toEqual([]);
        expect(await phienDoCon(phamVi(null, false, 'flashcard'), Math.floor(Date.now() / 1000), 'UTC')).toBeNull();
        expect(kho.has(KHO_API)).toBe(false);
    });

    it('không có mạng thì báo cần có mạng để đăng xuất, không gửi form', async () => {
        const gui = vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {});
        giaApi(toi);
        renderAppCoTaiKhoan('/cai-dat');
        const nut = await screen.findByRole('button', { name: 'Đăng xuất' });
        vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);
        fireEvent.click(nut);
        expect(screen.getByText('Cần có mạng để đăng xuất.')).toBeTruthy();
        expect(gui).not.toHaveBeenCalled();
    });

    it('cài app: trình duyệt cho cài thì có nút mở hộp cài; chưa cho thì hướng dẫn cài từ menu trình duyệt', async () => {
        giaApi(toi);
        renderAppCoTaiKhoan('/cai-dat');
        expect(await screen.findByRole('heading', { name: 'Cài app lên máy' })).toBeTruthy();
        expect(screen.getByText(/mở menu của trình duyệt và chọn “Cài đặt ứng dụng”/)).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Cài Lernbuch' })).toBeNull();

        const prompt = vi.fn(async () => {});
        const suKien = Object.assign(new Event('beforeinstallprompt', { cancelable: true }), { prompt, userChoice: Promise.resolve({ outcome: 'accepted' }) });
        act(() => {
            window.dispatchEvent(suKien);
        });
        expect(suKien.defaultPrevented).toBe(true);
        fireEvent.click(await screen.findByRole('button', { name: 'Cài Lernbuch' }));
        await waitFor(() => expect(prompt).toHaveBeenCalledTimes(1));
        // Đã đồng ý cài: không mở hộp cài lần nữa
        await waitFor(() => expect(screen.queryByRole('button', { name: 'Cài Lernbuch' })).toBeNull());
    });
});

describe('Cài đặt: mục AI', () => {
    it('AI chưa mở cho tài khoản: không có mục AI', async () => {
        giaApi({ 'GET /api/v1/toi': () => ({ json: { data: toiMau() } }) });
        renderAppCoTaiKhoan('/cai-dat');
        await screen.findByRole('heading', { name: 'Tài khoản' });
        expect(screen.queryByRole('heading', { name: 'AI' })).toBeNull();
    });

    it('đã đồng ý: bấm Rút đồng ý thì gửi granted false rồi hiện câu chưa đồng ý', async () => {
        const goi = giaApi({
            'GET /api/v1/toi': () => ({ json: { data: toiMau({ ai: { state: 'ready', consent_version: '2026-11', limit: 50, used: 0, reset_at: 1764262800 } }) } }),
            'PUT /api/v1/toi/dong-y-ai': () => ({ json: { data: toiMau({ ai: { state: 'need_consent', min_age: 18, consent_version: '2026-11' } }) } }),
        });
        renderAppCoTaiKhoan('/cai-dat');
        expect(await screen.findByText('Bạn đã đồng ý gửi nội dung cho AI.')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Rút đồng ý' }));

        expect(await screen.findByText(/Bạn chưa đồng ý dùng AI/)).toBeTruthy();
        expect(goi.find((g) => g.url === '/api/v1/toi/dong-y-ai')?.body).toEqual({ granted: false, version: '2026-11' });
    });

    it('chưa khai ngày sinh hay chưa đủ tuổi: một câu giải thích, không có nút', async () => {
        giaApi({ 'GET /api/v1/toi': () => ({ json: { data: toiMau({ ai: { state: 'need_birth_date', min_age: 18 } }) } }) });
        renderAppCoTaiKhoan('/cai-dat');
        expect(await screen.findByText(/Bạn chưa khai ngày sinh/)).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Rút đồng ý' })).toBeNull();
    });
});
