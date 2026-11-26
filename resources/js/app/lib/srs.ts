import { so, t } from '../i18n';
// Lặp lại ngắt quãng 6 mức, theo luật của Lernbuch (srs.py):
// - Khoảng ôn theo mức mới: 1 → 1 ngày, 2 → 3, 3 → 7, 4 → 14, 5 → 30.
// - Quên: mức 1 trở xuống về mức 1, cao hơn lùi 2 mức (thấp nhất 1); ôn lại sau 10 phút, tính 1 lần quên.
// - Khó: giữ mức, khoảng ôn còn một nửa (ít nhất 1 ngày).
// - Tốt: lên 1 mức. Dễ: lên 2 mức. Thẻ mới tính như mức 1.

export type Grade = 'quen' | 'kho' | 'tot' | 'de';

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
const INTERVAL_DAYS = [0, 1, 3, 7, 14, 30];
export const RELEARN_MINUTES = 10;
/** Xu mỗi thẻ đúng ở chế độ Flashcard; Khó được nửa, Quên không có. */
export const FLASHCARD_COINS = 5;

export type GradeResult = {
    level: number;
    /** Hạn ôn kế tiếp tính bằng ngày (10 phút = 10/1440 ngày). */
    intervalDays: number;
    lapsed: boolean;
    coins: number;
};

export function grade(level: number, g: Grade): GradeResult {
    if (!Number.isInteger(level) || level < 0 || level > MAX_LEVEL) {
        throw new RangeError(`Mức không hợp lệ: ${level}`);
    }
    const base = Math.max(level, 1);
    switch (g) {
        case 'quen': {
            const next = base <= 1 ? 1 : Math.max(1, base - 2);
            return { level: next, intervalDays: RELEARN_MINUTES / 1440, lapsed: true, coins: 0 };
        }
        case 'kho':
            return { level: base, intervalDays: Math.max(1, INTERVAL_DAYS[base] / 2), lapsed: false, coins: FLASHCARD_COINS / 2 };
        case 'tot': {
            const next = Math.min(MAX_LEVEL, base + 1);
            return { level: next, intervalDays: INTERVAL_DAYS[next], lapsed: false, coins: FLASHCARD_COINS };
        }
        case 'de': {
            const next = Math.min(MAX_LEVEL, base + 2);
            return { level: next, intervalDays: INTERVAL_DAYS[next], lapsed: false, coins: FLASHCARD_COINS };
        }
        default:
            throw new RangeError(`Mức chấm không hợp lệ: ${String(g)}`);
    }
}

/** Hạn ôn ghi ngắn trên nút chấm: "10 phút", "1 ngày", "1,5 ngày", "30 ngày". */
export function formatInterval(days: number): string {
    if (days < 1) return t('thoiGian.phut', { n: Math.round(days * 1440) });
    return t('thoiGian.ngay', { n: so(days) });
}
