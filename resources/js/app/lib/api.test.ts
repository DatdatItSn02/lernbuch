import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { giaApi } from '../../test/fakeApi';
import { api, chuyenTrang, LoiApi } from './api';

let diToi: ReturnType<typeof vi.fn<(duong: string) => void>>;

beforeEach(() => {
    diToi = vi.fn<(duong: string) => void>();
    vi.spyOn(chuyenTrang, 'toi').mockImplementation(diToi);
});

afterEach(() => {
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
});

describe('api: gọi /api/v1', () => {
    it('gửi JSON kèm mã XSRF lấy từ cookie, trả về dữ liệu', async () => {
        document.cookie = 'XSRF-TOKEN=ma%3Dthu';
        const goi = giaApi({ 'PUT /api/v1/toi/cai-dat': (b) => ({ json: { data: b } }) });
        const kq = await api.sua<{ data: unknown }>('/toi/cai-dat', { theme: 'dark' });
        expect(kq.data).toEqual({ theme: 'dark' });
        expect(goi[0].headers['X-XSRF-TOKEN']).toBe('ma=thu');
        expect(goi[0].headers.Accept).toBe('application/json');
        expect(goi[0].headers['Content-Type']).toBe('application/json');
    });

    it('401: chuyển về trang đăng nhập và báo lỗi', async () => {
        giaApi({ 'GET /api/v1/toi': () => ({ status: 401, json: { code: 'chua_dang_nhap', message: 'Bạn cần đăng nhập lại.' } }) });
        await expect(api.lay('/toi')).rejects.toMatchObject({ trangThai: 401, ma: 'chua_dang_nhap' });
        expect(diToi).toHaveBeenCalledWith('/dang-nhap');
    });

    it('403 chưa xác minh email: chuyển tới trang gửi lại thư', async () => {
        giaApi({ 'GET /api/v1/toi': () => ({ status: 403, json: { code: 'chua_xac_minh', message: '' } }) });
        await expect(api.lay('/toi')).rejects.toBeInstanceOf(LoiApi);
        expect(diToi).toHaveBeenCalledWith('/xac-minh-email');
    });

    it('403 tài khoản bị khoá: chuyển về trang đăng nhập (máy chủ đã đăng xuất phiên)', async () => {
        giaApi({ 'GET /api/v1/hom-nay': () => ({ status: 403, json: { code: 'tai_khoan_bi_khoa', message: 'Tài khoản này đã bị khoá.' } }) });
        await expect(api.lay('/hom-nay')).rejects.toMatchObject({ trangThai: 403, ma: 'tai_khoan_bi_khoa' });
        expect(diToi).toHaveBeenCalledWith('/dang-nhap');
    });

    it('403 khác (không có quyền): không chuyển trang', async () => {
        giaApi({ 'GET /api/v1/hom-nay': () => ({ status: 403, json: { code: 'khong_co_quyen', message: '' } }) });
        await expect(api.lay('/hom-nay')).rejects.toMatchObject({ trangThai: 403, ma: 'khong_co_quyen' });
        expect(diToi).not.toHaveBeenCalled();
    });

    it('422: lấy được lỗi của từng ô, không chuyển trang', async () => {
        giaApi({ 'POST /api/v1/toi/phien/dang-xuat-may-khac': () => ({ status: 422, json: { message: 'x', errors: { password: ['Mật khẩu không đúng.'] } } }) });
        const loi = await api.gui('/toi/phien/dang-xuat-may-khac', { password: 'sai' }).catch((e: unknown) => e);
        expect(loi).toBeInstanceOf(LoiApi);
        expect((loi as LoiApi).loiCua('password')).toBe('Mật khẩu không đúng.');
        expect((loi as LoiApi).loiCua('email')).toBeUndefined();
        expect(diToi).not.toHaveBeenCalled();
    });

    it('mất mạng: lỗi mat_mang, trạng thái 0', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
        await expect(api.lay('/toi')).rejects.toMatchObject({ trangThai: 0, ma: 'mat_mang' });
    });

    it('máy chủ trả trang lỗi không phải JSON: vẫn báo lỗi đúng trạng thái', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('<html>500</html>', { status: 500 })));
        await expect(api.lay('/toi')).rejects.toMatchObject({ trangThai: 500, ma: null });
    });
});
