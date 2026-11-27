import { describe, expect, it } from 'vitest';
import { cacDong, canNho, chuMucLuc, maPhan, tachCau, tachNhan, tachTenBai, tachTieng, tieuDeNho, tieuDePhan } from './nguPhap';

describe('chữ trong bài ngữ pháp', () => {
    it('tô đậm thuật ngữ, chữ trong ngoặc kép, chữ viết hoa hết', () => {
        expect(canNho('Sau mit dùng Dativ, luôn học KÈM mạo từ: „der Tisch“.')).toEqual([
            { chu: 'Sau mit dùng ', dam: false },
            { chu: 'Dativ', dam: true },
            { chu: ', luôn học ', dam: false },
            { chu: 'KÈM', dam: true },
            { chu: ' mạo từ: ', dam: false },
            { chu: '„der Tisch“', dam: true },
            { chu: '.', dam: false },
        ]);
        expect(canNho('Không có gì')).toEqual([{ chu: 'Không có gì', dam: false }]);
        expect(canNho('')).toEqual([]);
    });

    it('tách đoạn dài thành câu; câu ngắn và chữ viết tắt (z.B.) thì giữ liền', () => {
        expect(tachCau('Động từ chia đứng ở vị trí thứ hai trong câu. Phần còn lại đứng cuối câu, ví dụ Perfekt.')).toEqual([
            'Động từ chia đứng ở vị trí thứ hai trong câu.',
            'Phần còn lại đứng cuối câu, ví dụ Perfekt.',
        ]);
        expect(tachCau('Ngắn thôi. Vẫn liền.')).toEqual(['Ngắn thôi. Vẫn liền.']);
        expect(tachCau('Viele Verben mit Dativ, z.B. Helfen und Danken sind wichtig.')).toEqual(['Viele Verben mit Dativ, z.B. Helfen und Danken sind wichtig.']);
        expect(cacDong('dòng một\ndòng hai')).toEqual(['dòng một', 'dòng hai']);
    });

    it('đoạn dài có " · " thì tách thành từng ý; đoạn ngắn giữ nguyên', () => {
        const dai = 'der Tisch → die Tische (thêm -e) · die Lampe → die Lampen (thêm -n) · das Kind → die Kinder (thêm -er)';
        expect(cacDong(dai)).toEqual(['der Tisch → die Tische (thêm -e)', 'die Lampe → die Lampen (thêm -n)', 'das Kind → die Kinder (thêm -er)']);
        expect(cacDong('a · b')).toEqual(['a · b']);
    });

    it('nhãn ngắn ở đầu dòng', () => {
        expect(tachNhan('Ghi nhớ: động từ đứng thứ hai')).toEqual({ nhan: 'Ghi nhớ', chu: 'động từ đứng thứ hai' });
        expect(tachNhan('Nhãn quá dài quá dài quá dài quá dài: x')).toEqual({ nhan: null, chu: 'Nhãn quá dài quá dài quá dài quá dài: x' });
        expect(tachNhan('Không có nhãn.')).toEqual({ nhan: null, chu: 'Không có nhãn.' });
    });

    it('tách dòng nhiều thứ tiếng: dòng có chữ Hán là 中, có dấu tiếng Việt là VI, còn lại là EN', () => {
        expect(tachTieng('The verb comes second.\n动词放在第二位。\nĐộng từ đứng thứ hai.')).toEqual({
            en: 'The verb comes second.',
            zh: '动词放在第二位。',
            vi: 'Động từ đứng thứ hai.',
        });
        expect(tachTieng('Personal pronouns 人称代词')).toEqual({ en: 'Personal pronouns', zh: '人称代词' });
        expect(tachTieng('人称代词')).toEqual({ zh: '人称代词' });
        expect(tachTieng('Chỉ tiếng Việt')).toEqual({ main: 'Chỉ tiếng Việt' });
    });

    it('tiêu đề, mục lục, mã neo, tên bài theo dàn ý', () => {
        expect(tieuDePhan('Dativ – S. 12, 14')).toBe('Dativ');
        expect(tieuDePhan('Giới từ (→ Bài 5) đi với Dativ')).toBe('Giới từ đi với Dativ');
        expect(tieuDeNho('Ví dụ (A1)')).toBe('Ví dụ');
        expect(chuMucLuc('1. Giống của danh từ – quy tắc')).toBe('Giống của danh từ');
        expect(chuMucLuc('Một tiêu đề rất dài để thử xem mục lục có cắt bớt hay không')).toHaveLength(42);
        expect(maPhan('Bảng mạo từ', 5)).toBe('p-bang-mao-tu-5');
        expect(tachTenBai('Genus: der, die, das  ·  Giống: der, die, das')).toEqual({ duc: 'Genus: der, die, das', viet: 'Giống: der, die, das' });
        expect(tachTenBai('Nền tảng')).toEqual({ duc: 'Nền tảng', viet: null });
    });
});
