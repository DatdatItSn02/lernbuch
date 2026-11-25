import { fireEvent, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderApp } from '../../test/render';
import { fold } from './ChuDe';

const bodyRows = () => {
    const table = screen.queryByRole('table');
    return table ? within(table).getAllByRole('row').slice(1) : [];
};
const lemmas = () => bodyRows().map((r) => within(r).getAllByRole('cell')[1].textContent);

describe('Trang chủ đề: sổ từ hai cột', () => {
    it('đầu trang có đường dẫn, tên, số từ, mức thuộc và hai nút học', () => {
        renderApp('/chu-de/tiem-banh');
        expect(screen.getByRole('heading', { level: 1, name: 'Bánh mì · bánh ngọt · tiệm bánh' })).toBeTruthy();
        const crumbs = within(screen.getByRole('navigation', { name: 'Vị trí' })).getAllByRole('listitem');
        expect(crumbs.map((c) => c.textContent)).toEqual(['Chủ đề', 'Ăn uống', 'Bánh và tiệm bánh']);
        expect(screen.getByText('24 từ · 5 đến hạn · 9 từ mới')).toBeTruthy();
        const levels = within(screen.getByRole('group', { name: 'Lọc theo mức thuộc' })).getAllByRole('button');
        expect(levels.map((b) => b.getAttribute('aria-label'))).toEqual([
            '9 Hạt (Mới)',
            '3 Mầm (Đang học)',
            '4 Lá (Quen)',
            '4 Nụ (Khá)',
            '2 Hoa (Thuộc)',
            '2 Quả (Thành thạo)',
        ]);
        expect(screen.getByRole('link', { name: /Học tiếp/ }).getAttribute('href')).toBe('/hoc?chu-de=tiem-banh');
        expect(screen.getByRole('link', { name: 'Ôn 5 thẻ' }).getAttribute('href')).toBe('/hoc?chu-de=tiem-banh&on=1');
        expect(document.title).toBe('Bánh mì · bánh ngọt · tiệm bánh · Lernbuch');
    });

    it('20 từ mỗi trang, trang 2 có 4 từ còn lại', () => {
        renderApp('/chu-de/tiem-banh');
        expect(bodyRows()).toHaveLength(20);
        expect(lemmas()[0]).toContain('Brötchen');
        expect(screen.getByText('Trang 1/2')).toBeTruthy();
        fireEvent.click(screen.getByRole('link', { name: 'Trang sau' }));
        expect(bodyRows()).toHaveLength(4);
        expect(screen.getByTestId('duong-dan').textContent).toBe('/chu-de/tiem-banh?trang=2');
        expect(screen.queryByRole('link', { name: 'Trang sau' })).toBeNull();
        expect(document.activeElement?.textContent).toContain('trang 2/2');
    });

    it('số trang hỏng thì về trang 1, quá lớn thì về trang cuối', () => {
        renderApp('/chu-de/tiem-banh?trang=abc');
        expect(screen.getByText('Trang 1/2')).toBeTruthy();
    });

    it('số trang quá lớn thì hiện trang cuối', () => {
        renderApp('/chu-de/tiem-banh?trang=99');
        expect(screen.getByText('Trang 2/2')).toBeTruthy();
        expect(bodyRows()).toHaveLength(4);
    });

    it('che cột tiếng Đức: chỉ còn nút Xem; chạm vào thì hiện từ của dòng đó', () => {
        renderApp('/chu-de/tiem-banh');
        fireEvent.click(screen.getByRole('radio', { name: 'Tiếng Đức' }));
        expect(screen.queryByRole('link', { name: /Brötchen/ })).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Xem tiếng Đức của “bánh mì nhỏ (ổ tròn)”' }));
        expect(screen.getByRole('link', { name: 'das Brötchen' })).toBeTruthy();
        // Các dòng khác vẫn che
        expect(screen.queryByRole('link', { name: 'das Brot' })).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Hiện hết' }));
        expect(screen.getByRole('link', { name: 'das Brot' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Che lại' })).toBeTruthy();
    });

    it('che cột tiếng Việt', () => {
        renderApp('/chu-de/tiem-banh');
        fireEvent.click(screen.getByRole('radio', { name: 'Tiếng Việt' }));
        expect(screen.queryByText('bánh mì nhỏ (ổ tròn)')).toBeNull();
        expect(screen.getByRole('button', { name: 'Xem tiếng Việt của “Brötchen”' })).toBeTruthy();
    });

    it('chạm một mức trên thanh 6 mức để lọc, chạm lần nữa hoặc Bỏ lọc để bỏ', () => {
        renderApp('/chu-de/tiem-banh');
        const fruit = screen.getByRole('button', { name: '2 Quả (Thành thạo)' });
        fireEvent.click(fruit);
        expect(fruit.getAttribute('aria-pressed')).toBe('true');
        expect(lemmas()).toEqual(['das Croissantdie Croissants', 'die Butterthường chỉ dùng số ít']);
        expect(screen.getByText('2/24 từ khớp bộ lọc')).toBeTruthy();
        fireEvent.click(fruit);
        expect(fruit.getAttribute('aria-pressed')).toBe('false');
        expect(bodyRows()).toHaveLength(20);
        fireEvent.click(screen.getByRole('button', { name: '2 Hoa (Thuộc)' }));
        fireEvent.click(screen.getByRole('button', { name: 'Bỏ lọc' }));
        expect(bodyRows()).toHaveLength(20);
        expect(screen.queryByRole('button', { name: 'Bỏ lọc' })).toBeNull();
    });

    it('lọc theo loại từ: động từ', () => {
        renderApp('/chu-de/tiem-banh');
        fireEvent.change(screen.getByRole('combobox', { name: 'Loại từ' }), { target: { value: 'verb' } });
        expect(lemmas()).toEqual(['backenbackt (bäckt) · backte · hat gebacken']);
    });

    it('phím / đưa tiêu điểm vào ô tìm; gõ không dấu vẫn ra từ có dấu', () => {
        renderApp('/chu-de/tiem-banh');
        fireEvent.keyDown(window, { key: '/' });
        const search = screen.getByRole('searchbox', { name: 'Tìm trong chủ đề' });
        expect(document.activeElement).toBe(search);
        fireEvent.change(search, { target: { value: 'brotchen' } });
        expect(lemmas()).toHaveLength(1);
        expect(lemmas()[0]).toContain('Brötchen');
        fireEvent.change(search, { target: { value: 'banh keo' } });
        expect(lemmas()).toEqual([]);
    });

    it('tìm theo nghĩa tiếng Việt không dấu', () => {
        renderApp('/chu-de/tiem-banh');
        fireEvent.change(screen.getByRole('searchbox', { name: 'Tìm trong chủ đề' }), { target: { value: 'tho lam banh' } });
        expect(lemmas()).toEqual(['der Bäckerdie Bäcker']);
    });

    it('không có từ nào khớp thì báo và cho bỏ lọc', () => {
        renderApp('/chu-de/tiem-banh');
        const search = screen.getByRole('searchbox', { name: 'Tìm trong chủ đề' });
        fireEvent.change(search, { target: { value: 'xyz' } });
        expect(screen.getByText('Không có từ nào khớp bộ lọc.')).toBeTruthy();
        expect(screen.queryByRole('table')).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Bỏ lọc' }));
        expect(bodyRows()).toHaveLength(20);
        expect((search as HTMLInputElement).value).toBe('');
    });

    it('Esc trong ô tìm xoá chữ đã gõ', () => {
        renderApp('/chu-de/tiem-banh');
        const search = screen.getByRole('searchbox', { name: 'Tìm trong chủ đề' });
        fireEvent.change(search, { target: { value: 'brot' } });
        fireEvent.keyDown(search, { key: 'Escape' });
        expect((search as HTMLInputElement).value).toBe('');
    });

    it('đổi bộ lọc khi đang ở trang 2 thì về trang 1', () => {
        renderApp('/chu-de/tiem-banh?trang=2');
        fireEvent.click(screen.getByRole('button', { name: '9 Hạt (Mới)' }));
        expect(screen.getByTestId('duong-dan').textContent).toBe('/chu-de/tiem-banh');
    });

    it('chủ đề không có thẻ đến hạn thì không có nút Ôn', () => {
        renderApp('/chu-de/trai-cay');
        expect(screen.getByRole('link', { name: 'Ôn 7 thẻ' })).toBeTruthy();
        expect(screen.queryByText('Trang 1/1')).toBeNull();
    });

    it('chủ đề không có thì báo không tìm thấy', () => {
        renderApp('/chu-de/khong-co');
        expect(screen.getByRole('heading', { level: 1, name: 'Không có chủ đề này' })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Về danh sách chủ đề' }).getAttribute('href')).toBe('/chu-de');
    });
});

describe('fold: bỏ dấu để tìm', () => {
    it('bỏ dấu tiếng Đức, tiếng Việt, đổi ß thành ss, viết thường', () => {
        expect(fold('Brötchen')).toBe('brotchen');
        expect(fold('Süß')).toBe('suss');
        expect(fold('Bánh mì · đồ ngọt')).toBe('banh mi · do ngot');
        expect(fold('ĐƯỜNG')).toBe('duong');
    });
});
