// Chữ giao diện của app học. Bản tiếng Việt (vi.ts) là bản gốc và đầy đủ; ngôn ngữ khác chỉ cần dịch phần đã có,
// chỗ nào thiếu thì hiện tiếng Việt, không bao giờ hiện mã khoá. Thêm ngôn ngữ: tạo en.ts kiểu BanDich rồi themBanDich('en', en).
// Ngôn ngữ lấy từ <html lang> do máy chủ đặt theo ngôn ngữ giao diện của tài khoản; đổi ngôn ngữ thì tải lại trang.
import { vi } from './vi';

type ChuoiHoa<T> = { [K in keyof T]: T[K] extends string ? string : ChuoiHoa<T[K]> };
type MotPhan<T> = { [K in keyof T]?: T[K] extends string ? string : MotPhan<T[K]> };
type DuongDan<T, P extends string = ''> = {
    [K in keyof T & string]: T[K] extends string ? `${P}${K}` : DuongDan<T[K], `${P}${K}.`>;
}[keyof T & string];

export type TuDien = ChuoiHoa<typeof vi>;
export type BanDich = MotPhan<TuDien>;
export type Khoa = DuongDan<TuDien>;
export type ThamSo = Record<string, string | number>;

const GOC = 'vi';
const banDich: Record<string, BanDich> = { [GOC]: vi };
let hienTai = GOC;

export function themBanDich(ma: string, ban: BanDich): void {
    banDich[ma] = ban;
}

/** Đặt ngôn ngữ giao diện (mã như 'vi', 'en', 'zh-TW'); chưa có bản dịch thì chữ vẫn hiện tiếng Việt. */
export function datNgonNgu(ma: string | null | undefined): void {
    hienTai = (ma || GOC).toLowerCase();
}

export function ngonNgu(): string {
    return hienTai;
}

function lay(ban: BanDich | undefined, khoa: string): string | undefined {
    let nut: unknown = ban;
    for (const phan of khoa.split('.')) {
        if (nut === null || typeof nut !== 'object') return undefined;
        nut = (nut as Record<string, unknown>)[phan];
    }
    return typeof nut === 'string' ? nut : undefined;
}

/** Bản dịch của ngôn ngữ hiện tại; mã vùng (zh-TW) thiếu thì thử mã chung (zh), rồi tới tiếng Việt. */
export function t(khoa: Khoa, thamSo?: ThamSo): string {
    const chuoi = lay(banDich[hienTai], khoa) ?? lay(banDich[hienTai.split('-')[0]], khoa) ?? lay(vi, khoa) ?? khoa;
    if (!thamSo) return chuoi;
    return chuoi.replace(/\{(\w+)\}/g, (nguyen, ten: string) => (ten in thamSo ? String(thamSo[ten]) : nguyen));
}

/** Số viết theo ngôn ngữ giao diện (tiếng Việt: 1.234 và 1,5). */
export function so(n: number, toiDaSauDauPhay = 1): string {
    return new Intl.NumberFormat(hienTai, { maximumFractionDigits: toiDaSauDauPhay }).format(n);
}
