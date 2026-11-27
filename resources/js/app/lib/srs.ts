import { so, t } from '../i18n';
// Lặp lại ngắt quãng 6 mức, theo luật của Lernbuch (srs.py). Máy chủ tính thật (app/Support/Hoc/LapLich.php);
// bản này tính y hệt để hiện hạn trên nút chấm và chạy khi mất mạng. tests/chung/lap-lich.json là bảng ca thử chung.
// - Khoảng ôn theo mức mới: 1 → 1 ngày, 2 → 3, 3 → 7, 4 → 14, 5 → 30.
// - Quên: mức 1 trở xuống về mức 1, cao hơn lùi 2 mức (thấp nhất 1); ôn lại sau 10 phút; thẻ đã học thì tính 1 lần quên.
// - Khó: giữ mức, khoảng ôn còn một nửa, làm tròn xuống, ít nhất 1 ngày.
// - Tốt: lên 1 mức. Dễ: lên 2 mức. Thẻ mới tính như mức 1.
// - Hạn ôn: 00:00 (theo múi giờ người học) của lúc chấm + số ngày + 12 giờ, như Lernbuch, để đổi giờ mùa hè không lệch ngày.

export type Grade = 'quen' | 'kho' | 'tot' | 'de';
/** Số của cách chấm khi gửi máy chủ: 0 Quên, 1 Khó, 2 Tốt, 3 Dễ. */
export type SoCham = 0 | 1 | 2 | 3;
export const SO_CHAM: Record<Grade, SoCham> = { quen: 0, kho: 1, tot: 2, de: 3 };

// Tên nút chấm lấy từ từ điển mỗi lần đọc (getter), nên đúng ngôn ngữ giao diện hiện tại.
export const GRADES: { id: Grade; readonly label: string; key: string }[] = [
    { id: 'quen', key: '1', get label() { return t('cham.quen'); } },
    { id: 'kho', key: '2', get label() { return t('cham.kho'); } },
    { id: 'tot', key: '3', get label() { return t('cham.tot'); } },
    { id: 'de', key: '4', get label() { return t('cham.de'); } },
];

const TEN_MUC = ['muc.moi', 'muc.dangHoc', 'muc.quen', 'muc.kha', 'muc.thuoc', 'muc.thanhThao'] as const;
const TEN_GIAI_DOAN = ['giaiDoan.hat', 'giaiDoan.mam', 'giaiDoan.la', 'giaiDoan.nu', 'giaiDoan.hoa', 'giaiDoan.qua'] as const;
/** Tên mức thuộc 0–5 (Mới … Thành thạo). */
export const levelName = (i: number) => t(TEN_MUC[i]);
/** Tên giai đoạn cây của mức 0–5 (Hạt … Quả). */
export const stageName = (i: number) => t(TEN_GIAI_DOAN[i]);
export const MAX_LEVEL = 5;
const NGAY_THEO_MUC = [0, 1, 3, 7, 14, 30];
export const RELEARN_MINUTES = 10;
const HOC_LAI_GIAY = RELEARN_MINUTES * 60;

function kiem(muc: number, cham: number) {
    if (!Number.isInteger(muc) || muc < 0 || muc > MAX_LEVEL) throw new RangeError(`Mức không hợp lệ: ${muc}`);
    if (!Number.isInteger(cham) || cham < 0 || cham > 3) throw new RangeError(`Cách chấm không hợp lệ: ${String(cham)}`);
}

export function mucMoi(muc: number, cham: number): number {
    kiem(muc, cham);
    const goc = Math.max(muc, 1);
    if (cham === 0) return muc <= 1 ? 1 : Math.max(1, muc - 2);
    if (cham === 1) return goc;
    if (cham === 2) return Math.min(MAX_LEVEL, goc + 1);
    return Math.min(MAX_LEVEL, goc + 2);
}

/** Số ngày ghi trên nút chấm; Quên không tính theo ngày (ôn lại sau 10 phút) nên trả null. */
export function soNgay(muc: number, cham: number): number | null {
    kiem(muc, cham);
    if (cham === 0) return null;
    const ngay = NGAY_THEO_MUC[mucMoi(muc, cham)];
    return cham === 1 ? Math.max(1, Math.floor(ngay / 2)) : ngay;
}

/** Chấm một thẻ lúc `luc` (Unix, giây) với người học ở múi giờ `muiGio`: mức mới, hạn ôn (Unix), có tính một lần quên không. */
export function cham(muc: number, soCham: number, luc: number, muiGio: string): { muc: number; han: number; quen: boolean } {
    const moi = mucMoi(muc, soCham);
    const ngay = soNgay(muc, soCham);
    if (ngay === null) return { muc: moi, han: luc + HOC_LAI_GIAY, quen: muc >= 1 };
    return { muc: moi, han: dauNgay(luc + ngay * 86400 + 43200, muiGio), quen: false };
}

export type GradeResult = {
    level: number;
    /** Khoảng ôn ghi trên nút, tính bằng ngày (10 phút = 10/1440 ngày). */
    intervalDays: number;
    lapsed: boolean;
};

/** Chấm theo tên nút (dùng cho giao diện): mức mới và khoảng ôn để ghi trên nút. */
export function grade(level: number, g: Grade): GradeResult {
    const c = (SO_CHAM as Record<string, SoCham | undefined>)[g];
    if (c === undefined) throw new RangeError(`Cách chấm không hợp lệ: ${String(g)}`);
    const ngay = soNgay(level, c);
    return { level: mucMoi(level, c), intervalDays: ngay ?? RELEARN_MINUTES / 1440, lapsed: c === 0 && level >= 1 };
}

/** Hạn ôn ghi ngắn trên nút chấm: "10 phút", "1 ngày", "30 ngày". */
export function formatInterval(days: number): string {
    if (days < 1) return t('thoiGian.phut', { n: Math.round(days * 1440) });
    return t('thoiGian.ngay', { n: so(days) });
}

// ---------- Giờ theo múi giờ của người học (không theo giờ của máy) ----------

const dinhDang = new Map<string, Intl.DateTimeFormat>();

type Phan = { y: number; mo: number; d: number; h: number; mi: number; s: number };

function phanGio(ms: number, muiGio: string): Phan {
    let f = dinhDang.get(muiGio);
    if (!f) {
        f = new Intl.DateTimeFormat('en-US', {
            timeZone: muiGio,
            hourCycle: 'h23',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
        dinhDang.set(muiGio, f);
    }
    const p: Record<string, number> = {};
    for (const x of f.formatToParts(ms)) if (x.type !== 'literal') p[x.type] = Number(x.value);
    return { y: p.year, mo: p.month, d: p.day, h: p.hour === 24 ? 0 : p.hour, mi: p.minute, s: p.second };
}

/** Độ lệch giờ địa phương so với UTC (giây) tại thời điểm `luc`. */
function doLech(luc: number, muiGio: string): number {
    const p = phanGio(luc * 1000, muiGio);
    return Date.UTC(p.y, p.mo - 1, p.d, p.h, p.mi, p.s) / 1000 - luc;
}

/** 00:00 của ngày chứa `luc` (Unix, giây) theo múi giờ; ngày không có 00:00 vì đổi giờ thì lấy lúc đầu tiên của ngày. */
export function dauNgay(luc: number, muiGio: string): number {
    const p = phanGio(luc * 1000, muiGio);
    const nuaDem = Date.UTC(p.y, p.mo - 1, p.d) / 1000; // 00:00 của ngày đó, viết như thể là UTC
    const lech = doLech(luc, muiGio);
    // Đổi giờ thường rơi gần nửa đêm: thử độ lệch 6 giờ trước và 6 giờ sau nửa đêm
    const truoc = doLech(nuaDem - lech - 6 * 3600, muiGio);
    const sau = doLech(nuaDem - lech + 6 * 3600, muiGio);
    for (const o of [truoc, sau]) {
        const q = phanGio((nuaDem - o) * 1000, muiGio);
        if (q.y === p.y && q.mo === p.mo && q.d === p.d && q.h === 0 && q.mi === 0 && q.s === 0) return nuaDem - o;
    }
    return nuaDem - truoc; // 00:00 không có: ngày bắt đầu lúc đổi giờ
}

/** Đầu ngày hôm sau (ngày có 23 hay 25 giờ vẫn đúng). */
export const dauNgayMai = (luc: number, muiGio: string) => dauNgay(dauNgay(luc, muiGio) + 36 * 3600, muiGio);

const hai = (n: number) => String(n).padStart(2, '0');

/** Giờ địa phương "YYYY-MM-DDTHH:mm:ss" của `luc` (Unix, giây) theo múi giờ. */
export function gioDiaPhuong(luc: number, muiGio: string): string {
    const p = phanGio(luc * 1000, muiGio);
    return `${p.y}-${hai(p.mo)}-${hai(p.d)}T${hai(p.h)}:${hai(p.mi)}:${hai(p.s)}`;
}
