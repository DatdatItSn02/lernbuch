import { fireEvent, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { words } from '../data/demo';
import { initialState } from '../state/store';
import { renderWith } from '../../test/render';
import { HomNay } from './HomNay';

// Thứ Năm 26/11/2026, 9 giờ sáng
const NOW = new Date(2026, 10, 26, 9, 0);
const open = (state = initialState(), now = NOW) => renderWith(<HomNay now={now} />, '/hom-nay', state);
const strip = () => screen.getByRole('tablist', { name: 'Chọn ngày' });
const selectedTab = () => within(strip()).getAllByRole('tab').find((t) => t.getAttribute('aria-selected') === 'true')!;
/** Phần tên đọc ra sau chữ nhìn thấy: "Thứ Hai, 16/11: ôn 12 thẻ". */
const label = (t: HTMLElement) => t.textContent!.split(', ').slice(1).join(', ');

describe('Hôm nay: chuỗi 14 ngày làm trục', () => {
    it('chào theo giờ và ghi thứ, ngày', () => {
        open();
        const h1 = screen.getByRole('heading', { level: 1, name: 'Chào buổi sáng' });
        expect(h1.nextElementSibling?.textContent).toContain('Thứ Năm, 26/11');
    });

    it.each([
        [4, 'Chào buổi tối'],
        [11, 'Chào buổi trưa'],
        [13, 'Chào buổi chiều'],
        [18, 'Chào buổi tối'],
    ])('lúc %i giờ chào "%s"', (h, text) => {
        open(initialState(), new Date(2026, 10, 26, h, 0));
        expect(screen.getByRole('heading', { level: 1, name: text })).toBeTruthy();
    });

    it('14 ô ngày: 10 ngày qua, hôm nay đang chọn, 3 ngày tới', () => {
        open();
        const tabs = within(strip()).getAllByRole('tab');
        expect(tabs).toHaveLength(14);
        expect(selectedTab().textContent).toBe('Nay26, Thứ Năm, 26/11: ôn 8 thẻ');
        expect(tabs[0].textContent).toBe('T216, Thứ Hai, 16/11: ôn 12 thẻ');
        expect(label(tabs[1])).toBe('Thứ Ba, 17/11: không học');
        // Ngày tới: thẻ đến hạn sau 1, 2, 3 ngày
        expect(tabs.slice(11).map(label)).toEqual([
            'Thứ Sáu, 27/11: 2 thẻ sẽ đến hạn',
            'Thứ Bảy, 28/11: 2 thẻ sẽ đến hạn',
            'Chủ nhật, 29/11: 2 thẻ sẽ đến hạn',
        ]);
    });

    it('chuỗi ngày đếm từ hôm nay lùi về tới ngày nghỉ gần nhất; xu hiện số nguyên', () => {
        open();
        expect(screen.getByText('Chuỗi 5 ngày')).toBeTruthy();
        expect(screen.getByText('340 xu')).toBeTruthy();
    });

    it('chưa ôn hôm nay thì chuỗi chỉ tính các ngày trước', () => {
        const state = initialState();
        state.reviewedToday = 0;
        open(state);
        expect(screen.getByText('Chuỗi 4 ngày')).toBeTruthy();
    });

    it('Học ngay ghi số thẻ đến hạn và số từ mới sẽ học', () => {
        open();
        const go = screen.getByRole('link', { name: /Học ngay/ });
        expect(go.getAttribute('href')).toBe('/hoc');
        expect(go.textContent).toContain('12 thẻ đến hạn · 9 từ mới');
        expect(screen.getByRole('progressbar', { name: 'Mục tiêu ngày' }).getAttribute('aria-valuenow')).toBe('8');
        expect(screen.getByText('8/20 thẻ')).toBeTruthy();
    });

    it('chọn số từ mới mỗi lượt; Học ngay không ghi quá số từ mới còn lại', () => {
        open();
        const fifty = screen.getByRole('radio', { name: '50' }) as HTMLInputElement;
        fireEvent.click(fifty);
        expect(fifty.checked).toBe(true);
        // Chủ đề đang học chỉ còn 9 từ mới
        expect(screen.getByRole('link', { name: /Học ngay/ }).textContent).toContain('9 từ mới');
    });

    it('phím mũi tên chọn ngày trước, End sang ngày xa nhất, Home về ngày đầu', () => {
        open();
        fireEvent.keyDown(strip(), { key: 'ArrowLeft' });
        expect(label(selectedTab())).toBe('Thứ Tư, 25/11: ôn 20 thẻ');
        expect(document.activeElement).toBe(selectedTab());
        expect(screen.getByRole('tabpanel').textContent).toContain('Đã ôn 20 thẻ.');
        fireEvent.keyDown(strip(), { key: 'End' });
        expect(screen.getByRole('tabpanel').textContent).toContain('2 thẻ sẽ đến hạn');
        fireEvent.keyDown(strip(), { key: 'ArrowRight' }); // đã ở cuối thì đứng yên
        expect(label(selectedTab())).toBe('Chủ nhật, 29/11: 2 thẻ sẽ đến hạn');
        fireEvent.keyDown(strip(), { key: 'Home' });
        expect(screen.getByRole('tabpanel').textContent).toContain('Đã ôn 12 thẻ.');
    });

    it('ngày không học ghi rõ', () => {
        open();
        fireEvent.click(within(strip()).getAllByRole('tab')[1]);
        expect(screen.getByRole('tabpanel').textContent).toContain('Không học ngày này.');
    });

    it('hết thẻ đến hạn và hết từ mới thì không có nút Học ngay', () => {
        const state = initialState();
        state.progress = Object.fromEntries(words.map((w) => [w.id, { level: 3, dueInDays: 5, lapses: 0 }]));
        open(state);
        expect(screen.queryByRole('link', { name: /Học ngay/ })).toBeNull();
        expect(screen.getByText(/Hôm nay đã ôn hết thẻ đến hạn/)).toBeTruthy();
    });

    it('thẻ đến hạn chia theo chủ đề, có liên kết sang trang chủ đề', () => {
        open();
        const section = screen.getByRole('region', { name: 'Đến hạn theo chủ đề' });
        const links = within(section).getAllByRole('link');
        expect(links.map((l) => l.getAttribute('href'))).toEqual(['/chu-de/tiem-banh', '/chu-de/trai-cay']);
        expect(links[0].textContent).toContain('5 đến hạn · 9 từ mới');
        expect(links[1].textContent).toContain('7 đến hạn · 3 từ mới');
    });

    it('chế độ chưa làm ở G0 ghi rõ và không phải liên kết; Flashcard mở phiên học', () => {
        open();
        const modes = within(screen.getByRole('region', { name: 'Chế độ học' }));
        expect(modes.getAllByRole('link').map((l) => l.textContent)).toEqual(['Flashcard']);
        expect(modes.getAllByText('làm ở G1')).toHaveLength(5);
        expect(modes.getByRole('link', { name: 'Flashcard' }).getAttribute('href')).toBe('/hoc');
    });
});
