// Mã lượt chấm (ULID): 10 chữ thời gian (mili giây) + 16 chữ ngẫu nhiên, Crockford base32 viết hoa.
// Máy chủ dùng mã này để bỏ lượt gửi trùng; mã xếp theo chữ cũng là xếp theo giờ tạo.

const BANG = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
const THOI_GIAN_TOI_DA = 2 ** 48 - 1;

export function ulid(ms: number = Date.now(), ngauNhien: (n: number) => Uint8Array = (n) => crypto.getRandomValues(new Uint8Array(n))): string {
    if (!Number.isInteger(ms) || ms < 0 || ms > THOI_GIAN_TOI_DA) throw new RangeError(`Thời gian không hợp lệ cho ULID: ${ms}`);
    let thoiGian = '';
    let t = ms;
    for (let i = 0; i < 10; i++) {
        thoiGian = BANG[t % 32] + thoiGian;
        t = Math.floor(t / 32);
    }
    const b = ngauNhien(16);
    let phanSau = '';
    for (let i = 0; i < 16; i++) phanSau += BANG[b[i] % 32];
    return thoiGian + phanSau;
}

export const LA_ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/;

let msCuoi = -1;
let ngauNhienCuoi: Uint8Array | null = null;

/**
 * ULID tăng dần: tạo nhiều mã trong cùng một mili giây thì phần ngẫu nhiên tăng thêm 1, nên thứ tự chữ luôn đúng thứ tự tạo
 * (máy chủ xếp lượt cùng giờ chấm theo mã lượt).
 */
export function ulidTangDan(ms: number = Date.now()): string {
    if (ms <= msCuoi && ngauNhienCuoi) {
        const b = ngauNhienCuoi;
        for (let i = 15; i >= 0; i--) {
            b[i] = (b[i] + 1) % 32;
            if (b[i] !== 0) break;
        }
        return ulid(msCuoi, () => b);
    }
    msCuoi = ms;
    ngauNhienCuoi = crypto.getRandomValues(new Uint8Array(16)).map((x) => x % 32);
    const b = ngauNhienCuoi;
    return ulid(ms, () => b);
}
