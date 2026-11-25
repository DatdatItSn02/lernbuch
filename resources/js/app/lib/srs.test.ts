import { describe, expect, it } from 'vitest';
import { formatInterval, grade } from './srs';

describe('lặp lại ngắt quãng', () => {
    it('Tốt lên 1 mức, Dễ lên 2 mức, khoảng ôn theo mức mới', () => {
        expect(grade(2, 'tot')).toMatchObject({ level: 3, intervalDays: 7, lapsed: false, coins: 5 });
        expect(grade(2, 'de')).toMatchObject({ level: 4, intervalDays: 14 });
        expect(grade(4, 'de')).toMatchObject({ level: 5, intervalDays: 30 });
        expect(grade(5, 'tot')).toMatchObject({ level: 5, intervalDays: 30 });
    });

    it('thẻ mới tính như mức 1', () => {
        expect(grade(0, 'tot')).toMatchObject({ level: 2, intervalDays: 3 });
        expect(grade(0, 'de')).toMatchObject({ level: 3, intervalDays: 7 });
        expect(grade(0, 'kho')).toMatchObject({ level: 1, intervalDays: 1 });
        expect(grade(0, 'quen')).toMatchObject({ level: 1, lapsed: true });
    });

    it('Quên: mức 1 trở xuống về 1, cao hơn lùi 2 mức, ôn lại sau 10 phút, không có xu', () => {
        expect(grade(1, 'quen')).toMatchObject({ level: 1, coins: 0 });
        expect(grade(2, 'quen').level).toBe(1);
        expect(grade(3, 'quen').level).toBe(1);
        expect(grade(4, 'quen').level).toBe(2);
        expect(grade(5, 'quen').level).toBe(3);
        expect(grade(5, 'quen').intervalDays * 1440).toBeCloseTo(10);
    });

    it('Khó: giữ mức, khoảng ôn còn một nửa nhưng ít nhất 1 ngày, nửa xu', () => {
        expect(grade(1, 'kho')).toMatchObject({ level: 1, intervalDays: 1, coins: 2.5 });
        expect(grade(2, 'kho')).toMatchObject({ level: 2, intervalDays: 1.5 });
        expect(grade(5, 'kho')).toMatchObject({ level: 5, intervalDays: 15 });
    });

    it('báo lỗi khi mức hoặc kiểu chấm sai', () => {
        expect(() => grade(-1, 'tot')).toThrow(RangeError);
        expect(() => grade(6, 'tot')).toThrow(RangeError);
        expect(() => grade(1.5, 'tot')).toThrow(RangeError);
        expect(() => grade(1, 'xyz' as never)).toThrow(RangeError);
    });

    it('ghi hạn ôn ngắn gọn', () => {
        expect(formatInterval(10 / 1440)).toBe('10 phút');
        expect(formatInterval(1)).toBe('1 ngày');
        expect(formatInterval(1.5)).toBe('1,5 ngày');
        expect(formatInterval(30)).toBe('30 ngày');
    });
});
