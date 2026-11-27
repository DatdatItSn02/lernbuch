import { describe, expect, it } from 'vitest';
import bang from '../../../../tests/chung/lap-lich.json';
import { cham, dauNgay, dauNgayMai, formatInterval, gioDiaPhuong, grade, mucMoi, soNgay } from './srs';

// Mục tự kiểm 3: cùng bảng ca thử với Pest (tests/Unit/LapLichTest.php), nên máy chủ và app luôn tính như nhau.

type Ca = (typeof bang.ca)[number];
const giay = (iso: string) => Date.parse(iso) / 1000;

describe('bảng ca thử chung với máy chủ', () => {
    it.each(bang.ca.map((ca) => [ca.ten, ca] as const))('%s', (_ten, ca: Ca) => {
        const luc = giay(ca.luc);
        const kq = cham(ca.muc, ca.cham, luc, ca.mui_gio);
        expect(gioDiaPhuong(luc, ca.mui_gio)).toBe(ca.luc_dia_phuong);
        expect(kq.muc).toBe(ca.muc_moi);
        expect(gioDiaPhuong(kq.han, ca.mui_gio)).toBe(ca.han);
        expect(kq.quen).toBe(ca.quen);
        expect(soNgay(ca.muc, ca.cham)).toBe(ca.so_ngay);
        expect(mucMoi(ca.muc, ca.cham)).toBe(ca.muc_moi);
    });

    it.each(bang.loi.map((ca) => [ca.ten, ca] as const))('lỗi: %s', (_ten, ca) => {
        expect(() => cham(ca.muc, ca.cham, 1_764_200_000, 'Asia/Ho_Chi_Minh')).toThrow(RangeError);
        expect(() => mucMoi(ca.muc, ca.cham)).toThrow(RangeError);
        expect(() => soNgay(ca.muc, ca.cham)).toThrow(RangeError);
    });

    it('bảng phủ đủ 6 mức × 4 cách chấm', () => {
        expect(new Set(bang.ca.map((ca) => `${ca.muc}-${ca.cham}`)).size).toBe(24);
    });
});

describe('đầu ngày theo múi giờ', () => {
    it('ngày 23 giờ và 25 giờ ở Berlin', () => {
        const berlin = 'Europe/Berlin';
        expect(gioDiaPhuong(dauNgayMai(giay('2026-03-29T08:00:00Z'), berlin), berlin)).toBe('2026-03-30T00:00:00');
        expect(dauNgayMai(giay('2026-10-25T09:00:00Z'), berlin) - dauNgay(giay('2026-10-25T09:00:00Z'), berlin)).toBe(25 * 3600);
        expect(dauNgayMai(giay('2026-03-29T09:00:00Z'), berlin) - dauNgay(giay('2026-03-29T09:00:00Z'), berlin)).toBe(23 * 3600);
    });

    it('múi giờ lệch nửa giờ và cách xa UTC', () => {
        expect(gioDiaPhuong(dauNgay(giay('2026-11-27T20:00:00Z'), 'Asia/Kolkata'), 'Asia/Kolkata')).toBe('2026-11-28T00:00:00');
        expect(gioDiaPhuong(dauNgay(giay('2026-11-27T20:00:00Z'), 'Pacific/Kiritimati'), 'Pacific/Kiritimati')).toBe('2026-11-28T00:00:00');
        expect(gioDiaPhuong(dauNgay(giay('2026-11-27T20:00:00Z'), 'America/Los_Angeles'), 'America/Los_Angeles')).toBe('2026-11-27T00:00:00');
    });
});

describe('nút chấm', () => {
    it('ghi khoảng ôn theo mức mới; Quên ôn lại sau 10 phút', () => {
        expect(grade(2, 'tot')).toEqual({ level: 3, intervalDays: 7, lapsed: false });
        expect(grade(2, 'kho')).toEqual({ level: 2, intervalDays: 1, lapsed: false });
        expect(grade(0, 'quen')).toEqual({ level: 1, intervalDays: 10 / 1440, lapsed: false });
        expect(grade(4, 'quen')).toEqual({ level: 2, intervalDays: 10 / 1440, lapsed: true });
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
        expect(formatInterval(30)).toBe('30 ngày');
    });
});
