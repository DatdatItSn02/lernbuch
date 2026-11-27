import { fireEvent, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { words } from '../data/demo';
import { initialState } from '../state/store';
import { renderApp } from '../../test/render';
import { sessionLine } from './Hoc';

const key = (k: string) => fireEvent.keyDown(window, { key: k });
const word = () => screen.getByRole('heading', { level: 1 }).textContent;
const counter = () => screen.getByText(/^\d+\/\d+$/).textContent;
const status = () => screen.getAllByRole('status')[0].textContent;
const where = () => screen.getByTestId('duong-dan').textContent;

describe('Học: phiên flashcard', () => {
    it('ra thẻ quá hạn lâu nhất trước, chỉ có từ cho tới khi lật', () => {
        renderApp('/hoc');
        // 12 thẻ đến hạn + 9 từ mới của chủ đề đang học
        expect(counter()).toBe('1/21');
        expect(word()).toBe('die Orange');
        expect(screen.queryByText('quả cam')).toBeNull();
        expect(screen.queryByRole('group', { name: 'Tự chấm' })).toBeNull();
    });

    it('bấm chuột: lật thì tiêu điểm vào đáp án, chấm xong thì vào nút Lật thẻ của thẻ mới', () => {
        renderApp('/hoc');
        fireEvent.click(screen.getByRole('button', { name: /Lật thẻ/ }));
        expect(document.activeElement?.getAttribute('aria-label')).toBe('Đáp án');
        fireEvent.click(screen.getByRole('button', { name: /^Tốt/ }));
        expect(word()).toBe('der Teig');
        expect(document.activeElement?.textContent).toContain('Lật thẻ');
    });

    it('Space lật thẻ; nút chấm ghi hạn ôn mới theo luật 6 mức', () => {
        renderApp('/hoc');
        key(' ');
        expect(screen.getByText('quả cam')).toBeTruthy();
        expect(screen.getByText('orange')).toBeTruthy();
        expect(screen.getByText('橙子')).toBeTruthy();
        const grades = within(screen.getByRole('group', { name: 'Tự chấm' })).getAllByRole('button');
        // Orange đang ở mức 2 (Quen)
        expect(grades.map((b) => b.textContent)).toEqual(['Quên10 phút 1', 'Khó1,5 ngày 2', 'Tốt7 ngày 3', 'Dễ14 ngày 4']);
        expect(document.activeElement?.getAttribute('aria-label')).toBe('Đáp án');
    });

    it('chưa lật thì phím 1–4 không chấm', () => {
        renderApp('/hoc');
        key('3');
        expect(counter()).toBe('1/21');
        expect(word()).toBe('die Orange');
    });

    it('chấm Tốt thì sang thẻ kế và báo thẻ vừa lên mức', () => {
        renderApp('/hoc');
        key(' ');
        key('3');
        expect(counter()).toBe('2/21');
        expect(word()).toBe('der Teig');
        expect(status()).toContain('Orange lên mức Khá');
    });

    it('Quên thì thẻ quay lại trong phiên (phiên dài thêm một thẻ)', () => {
        renderApp('/hoc');
        key(' ');
        key('1');
        expect(counter()).toBe('2/22');
        expect(status()).toContain('Orange sẽ quay lại sau vài thẻ');
        // Đi qua 3 thẻ nữa thì gặp lại Orange
        for (let i = 0; i < 3; i++) {
            key(' ');
            key('3');
        }
        expect(word()).toBe('die Orange');
    });

    it('← xem lại thẻ vừa chấm, → về thẻ đang học', () => {
        renderApp('/hoc');
        key(' ');
        key('4');
        key('ArrowLeft');
        expect(word()).toBe('die Orange');
        expect(status()).toContain('Đang xem lại thẻ đã chấm: Dễ');
        key('3'); // đang xem lại thì không chấm
        key('ArrowRight');
        expect(word()).toBe('der Teig');
        expect(counter()).toBe('2/21');
    });

    it('Esc thoát về Hôm nay; học theo chủ đề thì thoát về chủ đề đó', () => {
        renderApp('/hoc');
        key('Escape');
        expect(where()).toBe('/hom-nay');
    });

    it('Esc trong phiên của một chủ đề mẫu thì về Hôm nay (chủ đề mẫu không có trang riêng)', () => {
        renderApp('/hoc?chu-de=trai-cay');
        key('Escape');
        expect(where()).toBe('/hom-nay');
    });

    it('chỉ ôn một chủ đề: học hết rồi xem bảng tổng kết', () => {
        renderApp('/hoc?chu-de=trai-cay&on=1');
        expect(counter()).toBe('1/7');
        for (let i = 0; i < 7; i++) {
            key(' ');
            key('3');
        }
        expect(screen.getByRole('heading', { level: 1, name: 'Xong phiên' })).toBeTruthy();
        expect(screen.getByText('Táo · chuối · cam')).toBeTruthy();
        expect(screen.getByText('Đã học 7 thẻ ôn. 7 thẻ lên mức.')).toBeTruthy();
        expect(screen.getByText('Quên 0 · Khó 0 · Tốt 7 · Dễ 0')).toBeTruthy();
        const grown = within(screen.getByRole('region', { name: 'Cây lớn lên' })).getAllByRole('listitem');
        expect(grown).toHaveLength(7);
        // Orange: Quen (mức 2) lên Khá (mức 3)
        expect(grown[0].textContent).toBe('die Orangetừ Quen lênKhá');
        // Hết thẻ ôn, còn 3 từ mới: Học tiếp mở phiên mới của chủ đề (có từ mới)
        expect(screen.getByRole('link', { name: 'Về Hôm nay' }).getAttribute('href')).toBe('/hom-nay');
        const next = screen.getByRole('link', { name: /Học tiếp/ });
        expect(next.textContent).toContain('0 ôn · 3 mới');
        fireEvent.click(next);
        expect(where()).toBe('/hoc?chu-de=trai-cay');
        expect(counter()).toBe('1/3');
        expect(word()).toBe('die Pflaume');
    });

    it('học hết cả thẻ ôn lẫn từ mới thì chỉ còn đường về', () => {
        renderApp('/hoc?chu-de=trai-cay');
        expect(counter()).toBe('1/10');
        for (let i = 0; i < 10; i++) {
            key(' ');
            key('4');
        }
        expect(screen.getByText('Đã học hết thẻ đến hạn và từ mới ở đây.')).toBeTruthy();
        expect(screen.queryByRole('link', { name: /Học tiếp|Ôn tiếp/ })).toBeNull();
        expect(screen.getByRole('link', { name: 'Về Hôm nay' })).toBeTruthy();
    });

    it('có thẻ Quên thì cuối phiên mời Ôn tiếp', () => {
        renderApp('/hoc?chu-de=trai-cay&on=1');
        // Quên cả 7 thẻ ba lần (mỗi thẻ chỉ chèn lại tối đa 2 lần), rồi xong phiên
        for (let i = 0; i < 21; i++) {
            key(' ');
            key('1');
        }
        expect(screen.getByRole('heading', { level: 1, name: 'Xong phiên' })).toBeTruthy();
        expect(screen.getByText('Quên 21 · Khó 0 · Tốt 0 · Dễ 0')).toBeTruthy();
        expect(screen.getByText('Đã học 7 thẻ ôn. Chưa có thẻ nào lên mức.')).toBeTruthy();
        expect(screen.queryByRole('region', { name: 'Cây lớn lên' })).toBeNull();
        expect(screen.getByRole('link', { name: /Học tiếp/ }).textContent).toContain('7 ôn · 3 mới');
    });

    it('không còn thẻ nào thì báo rõ, không hiện thẻ trống', () => {
        const state = initialState();
        state.progress = Object.fromEntries(words.map((w) => [w.id, { level: 5, dueInDays: 30, lapses: 0 }]));
        renderApp('/hoc', state);
        expect(screen.getByRole('heading', { level: 1, name: 'Chưa có thẻ nào để học' })).toBeTruthy();
        expect(counter()).toBe('0/0');
        fireEvent.click(screen.getByRole('button', { name: 'Quay lại' }));
        expect(where()).toBe('/hom-nay');
    });

    it('tắt cả ba thứ tiếng thì báo cách bật lại', () => {
        const state = initialState();
        state.settings = { ...state.settings, langs: { vi: false, en: false, zh: false } };
        renderApp('/hoc', state);
        key(' ');
        expect(screen.getByText(/Đang tắt cả ba thứ tiếng/)).toBeTruthy();
    });

    it('trình duyệt không đọc được thành tiếng thì báo lỗi ngay dưới nút nghe', async () => {
        renderApp('/hoc');
        fireEvent.click(screen.getByRole('button', { name: 'Nghe (phím P)' }));
        expect(await screen.findByText('Trình duyệt này không đọc được thành tiếng.')).toBeTruthy();
    });
});

describe('sessionLine: câu tổng kết phiên', () => {
    it('bỏ phần bằng 0', () => {
        expect(sessionLine(7, 0)).toBe('Đã học 7 thẻ ôn.');
        expect(sessionLine(3, 3)).toBe('Đã học 3 từ mới.');
        expect(sessionLine(21, 9)).toBe('Đã học 21 thẻ: 9 từ mới, 12 thẻ ôn.');
    });
});
