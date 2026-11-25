// Lặp lại ngắt quãng 6 mức, theo luật của Lernbuch (srs.py):
// - Khoảng ôn theo mức mới: 1 → 1 ngày, 2 → 3, 3 → 7, 4 → 14, 5 → 30.
// - Quên: mức 1 trở xuống về mức 1, cao hơn lùi 2 mức (thấp nhất 1); ôn lại sau 10 phút, tính 1 lần quên.
// - Khó: giữ mức, khoảng ôn còn một nửa (ít nhất 1 ngày).
// - Tốt: lên 1 mức. Dễ: lên 2 mức. Thẻ mới tính như mức 1.

export type Grade = 'quen' | 'kho' | 'tot' | 'de';

export const GRADES: { id: Grade; label: string; key: string }[] = [
    { id: 'quen', label: 'Quên', key: '1' },
    { id: 'kho', label: 'Khó', key: '2' },
    { id: 'tot', label: 'Tốt', key: '3' },
    { id: 'de', label: 'Dễ', key: '4' },
];

export const LEVEL_NAMES = ['Mới', 'Đang học', 'Quen', 'Khá', 'Thuộc', 'Thành thạo'] as const;
export const STAGE_NAMES = ['Hạt', 'Mầm', 'Lá', 'Nụ', 'Hoa', 'Quả'] as const;
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
    if (days < 1) return `${Math.round(days * 1440)} phút`;
    const text = Number.isInteger(days) ? String(days) : days.toFixed(1).replace('.', ',');
    return `${text} ngày`;
}
