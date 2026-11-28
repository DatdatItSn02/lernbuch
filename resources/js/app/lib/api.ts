// Gọi API /api/v1 của Lernbuch bằng cookie phiên (cùng tên miền), kèm mã chống giả mạo XSRF do Laravel đặt trong cookie.
// Lỗi trả về dạng LoiApi: trangThai (401, 403, 422…), ma (vd chua_xac_minh) và lỗi từng ô khi nhập sai.
import { baoMayChu } from './mang';

export class LoiApi extends Error {
    constructor(
        public readonly trangThai: number,
        public readonly ma: string | null,
        public readonly loiO: Record<string, string[]> = {},
        message = '',
        /** Toàn bộ thân JSON của lỗi (vd reset_at khi hết lượt AI). */
        public readonly than: Record<string, unknown> = {},
    ) {
        super(message || `API ${trangThai}`);
        this.name = 'LoiApi';
    }

    /** Lỗi đầu tiên của một ô (422), để hiện ngay dưới ô đó. */
    loiCua(o: string): string | undefined {
        return this.loiO[o]?.[0];
    }
}

function maXsrf(): string | null {
    const dong = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    return dong ? decodeURIComponent(dong.slice('XSRF-TOKEN='.length)) : null;
}

/** Hết đăng nhập, bị khoá hoặc chưa xác minh email: đưa về trang của máy chủ (đăng nhập, gửi lại thư). Test thay hàm này. */
export const chuyenTrang = { toi: (duong: string) => window.location.assign(duong) };

function yeuCau(phuongThuc: string, duLieu: unknown, keepalive = false): RequestInit {
    const xsrf = maXsrf();
    return {
        method: phuongThuc,
        credentials: 'same-origin',
        keepalive,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(duLieu === undefined ? {} : { 'Content-Type': 'application/json' }),
            ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
        },
        body: duLieu === undefined ? undefined : JSON.stringify(duLieu),
    };
}

async function goi<T>(phuongThuc: 'GET' | 'PUT' | 'POST' | 'DELETE', duong: string, duLieu?: unknown): Promise<T> {
    let r: Response;
    try {
        r = await fetch(`/api/v1${duong}`, yeuCau(phuongThuc, duLieu));
    } catch {
        baoMayChu(false);
        throw new LoiApi(0, 'mat_mang');
    }
    // Service worker trả bản đã lưu (mất mạng) thì có đầu này: vẫn dùng được, nhưng là chưa tới máy chủ
    baoMayChu(r.headers?.get?.('X-Lernbuch-Ban-Luu') !== '1');

    const json = (await r.json().catch(() => null)) as ({ code?: string; message?: string; errors?: Record<string, string[]> } & Record<string, unknown>) | null;
    if (r.ok) return json as T;

    const loi = new LoiApi(r.status, json?.code ?? null, json?.errors ?? {}, json?.message, json ?? {});
    // Hết đăng nhập, hoặc tài khoản bị khoá (máy chủ đã đăng xuất phiên này): về trang đăng nhập
    if (r.status === 401 || (r.status === 403 && loi.ma === 'tai_khoan_bi_khoa')) chuyenTrang.toi('/dang-nhap');
    else if (r.status === 403 && loi.ma === 'chua_xac_minh') chuyenTrang.toi('/xac-minh-email');
    throw loi;
}

export const api = {
    lay: <T>(duong: string) => goi<T>('GET', duong),
    sua: <T>(duong: string, duLieu: unknown) => goi<T>('PUT', duong, duLieu),
    gui: <T>(duong: string, duLieu: unknown) => goi<T>('POST', duong, duLieu),
    xoa: <T = null>(duong: string) => goi<T>('DELETE', duong),
    /** Gửi PUT lúc rời trang (keepalive: trình duyệt gửi nốt dù trang đã đóng); không chờ kết quả. */
    guiKhiRoiTrang: (duong: string, duLieu: unknown) => {
        fetch(`/api/v1${duong}`, yeuCau('PUT', duLieu, true)).catch(() => {});
    },
};
