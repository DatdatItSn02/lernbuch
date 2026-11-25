import { fireEvent, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { initialState } from '../state/store';
import { renderApp } from '../../test/render';

const tab = (name: string) => screen.getByRole('tab', { name });
const panel = () => screen.getByRole('tabpanel');

describe('Thẻ từ: thẻ chia tab', () => {
    it('đầu thẻ có mạo từ tô màu, số nhiều, loại từ, trình độ, mức và hạn ôn', () => {
        renderApp('/tu/broetchen');
        const h1 = screen.getByRole('heading', { level: 1 });
        expect(h1.textContent).toBe('das Brötchen');
        expect(within(h1).getByText('das').className).toContain('text-das');
        expect(screen.getByText('die', { selector: '.text-plural' }).parentElement?.textContent).toBe('die Brötchen');
        expect(screen.getByText('danh từ · A1')).toBeTruthy();
        expect(screen.getByText('Lá · Quen')).toBeTruthy();
        expect(screen.getByText('Ôn hôm nay')).toBeTruthy();
        expect(document.title).toBe('Brötchen · Lernbuch');
    });

    it('tab đầu là Nghĩa và ví dụ: ba thứ tiếng, tối đa 3 ví dụ, mẹo viết tay', () => {
        renderApp('/tu/broetchen');
        expect(tab('Nghĩa và ví dụ').getAttribute('aria-selected')).toBe('true');
        expect(within(panel()).getByText('bánh mì nhỏ (ổ tròn)')).toBeTruthy();
        expect(within(panel()).getByText('bread roll')).toBeTruthy();
        expect(within(panel()).getByText('小面包')).toBeTruthy();
        expect(within(panel()).getAllByRole('button', { name: 'Nghe câu ví dụ' })).toHaveLength(3);
        expect(within(panel()).getByText(/ein Brötchen, zwei Brötchen/).className).toContain('font-hand');
    });

    it('phím mũi tên, Home, End chuyển tab và dời tiêu điểm theo', () => {
        renderApp('/tu/broetchen');
        const list = screen.getByRole('tablist');
        fireEvent.keyDown(list, { key: 'ArrowRight' });
        expect(tab('Ngữ pháp').getAttribute('aria-selected')).toBe('true');
        expect(document.activeElement).toBe(tab('Ngữ pháp'));
        expect(within(panel()).getByText('Số nhiều')).toBeTruthy();
        expect(within(panel()).getByText('(giống trung)')).toBeTruthy();
        fireEvent.keyDown(list, { key: 'End' });
        expect(tab('Ghi chú').getAttribute('aria-selected')).toBe('true');
        fireEvent.keyDown(list, { key: 'ArrowRight' });
        expect(tab('Nghĩa và ví dụ').getAttribute('aria-selected')).toBe('true');
        fireEvent.keyDown(list, { key: 'ArrowLeft' });
        expect(tab('Ghi chú').getAttribute('aria-selected')).toBe('true');
        fireEvent.keyDown(list, { key: 'Home' });
        expect(tab('Nghĩa và ví dụ').getAttribute('aria-selected')).toBe('true');
        // Chỉ tab đang chọn nằm trong thứ tự Tab
        expect(screen.getAllByRole('tab').map((t) => t.tabIndex)).toEqual([0, -1, -1]);
    });

    it('tắt EN thì ẩn nghĩa tiếng Anh; tắt cả ba thì nhắc bật lại', () => {
        renderApp('/tu/broetchen');
        const en = screen.getByRole('button', { name: 'EN (tiếng Anh)' });
        expect(en.getAttribute('aria-pressed')).toBe('true');
        fireEvent.click(en);
        expect(en.getAttribute('aria-pressed')).toBe('false');
        expect(screen.queryByText('bread roll')).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'VI (tiếng Việt)' }));
        fireEvent.click(screen.getByRole('button', { name: '中 (tiếng Trung)' }));
        expect(screen.getByText(/Đang tắt cả ba thứ tiếng/)).toBeTruthy();
    });

    it('nút sao bật tắt được', () => {
        renderApp('/tu/broetchen');
        const star = screen.getByRole('button', { name: 'Đánh dấu sao' });
        expect(star.getAttribute('aria-pressed')).toBe('true');
        fireEvent.click(star);
        expect(star.getAttribute('aria-pressed')).toBe('false');
    });

    it('động từ: ba dạng chính và bảng chia thì hiện tại', () => {
        renderApp('/tu/backen');
        expect(screen.getByText('động từ · A2')).toBeTruthy();
        fireEvent.click(tab('Ngữ pháp'));
        const tables = within(panel()).getAllByRole('table');
        expect(within(tables[0]).getAllByRole('cell').map((c) => c.textContent)).toEqual(['backt (bäckt)', 'backte', 'hat gebacken']);
        const rows = within(tables[1]).getAllByRole('row').map((r) => r.textContent);
        expect(rows).toEqual(['ichbacke', 'dubackst / bäckst', 'er/sie/esbackt / bäckt', 'wirbacken', 'ihrbackt', 'sie/Siebacken']);
    });

    it('tính từ: so sánh hơn và so sánh nhất', () => {
        renderApp('/tu/lecker');
        fireEvent.click(tab('Ngữ pháp'));
        expect(within(panel()).getByText('leckerer')).toBeTruthy();
        expect(within(panel()).getByText('am leckersten')).toBeTruthy();
    });

    it('danh từ không có số nhiều thì ghi rõ', () => {
        renderApp('/tu/butter');
        expect(screen.getAllByText('thường chỉ dùng số ít').length).toBeGreaterThan(0);
    });

    it('ghi chú: gõ được, đếm chữ, giới hạn 500, giữ khi đổi tab', () => {
        renderApp('/tu/broetchen');
        fireEvent.click(tab('Ghi chú'));
        const box = screen.getByRole('textbox', { name: 'Ghi chú riêng cho từ này' });
        expect(box.getAttribute('maxlength')).toBe('500');
        fireEvent.change(box, { target: { value: 'ngon' } });
        expect(screen.getByText('4/500')).toBeTruthy();
        fireEvent.click(tab('Ngữ pháp'));
        fireEvent.click(tab('Ghi chú'));
        expect((screen.getByRole('textbox', { name: 'Ghi chú riêng cho từ này' }) as HTMLTextAreaElement).value).toBe('ngon');
    });

    it('từ trước và từ sau trong cùng chủ đề; từ đầu không có từ trước', () => {
        renderApp('/tu/broetchen');
        expect(screen.queryByRole('link', { name: /Từ trước/ })).toBeNull();
        fireEvent.click(screen.getByRole('link', { name: 'Từ sau: Brot' }));
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('das Brot');
        expect(screen.getByRole('link', { name: 'Từ trước: Brötchen' })).toBeTruthy();
        expect(screen.getByText('2/24')).toBeTruthy();
    });

    it('từ chưa học ghi "Chưa học"; đổi từ thì về tab đầu', () => {
        renderApp('/tu/kleingeld');
        expect(screen.getByText('Chưa học')).toBeTruthy();
        fireEvent.click(tab('Ghi chú'));
        fireEvent.click(screen.getByRole('link', { name: 'Từ sau: backen' }));
        expect(tab('Nghĩa và ví dụ').getAttribute('aria-selected')).toBe('true');
    });

    it('có đường về trang chủ đề', () => {
        renderApp('/tu/apfel');
        expect(screen.getByRole('link', { name: 'Táo · chuối · cam' }).getAttribute('href')).toBe('/chu-de/trai-cay');
    });

    it('từ không có thì báo không tìm thấy', () => {
        renderApp('/tu/khong-co');
        expect(screen.getByRole('heading', { level: 1, name: 'Không có từ này' })).toBeTruthy();
    });

    it('nghĩa ẩn theo cài đặt đã chọn từ trước', () => {
        const state = initialState({ ...initialState().settings, langs: { vi: true, en: false, zh: false } });
        renderApp('/tu/brot', state);
        expect(screen.getByText('bánh mì')).toBeTruthy();
        expect(screen.queryByText('bread')).toBeNull();
        expect(screen.queryByText('面包')).toBeNull();
    });
});
