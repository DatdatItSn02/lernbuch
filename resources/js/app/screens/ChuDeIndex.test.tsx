import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { giaApi } from '../../test/fakeApi';
import { giaNoiDung } from '../../test/noiDungGia';
import { renderApp } from '../../test/render';

const where = () => screen.getByTestId('duong-dan').textContent;

describe('Chủ đề, Cấp độ, Giao tiếp: cây chủ đề từ API', () => {
    it('Chủ đề: nhóm › chủ đề vừa › chủ đề nhỏ theo thứ tự của máy chủ, số chủ đề và số từ', async () => {
        const goi = giaNoiDung();
        renderApp('/chu-de');
        expect(await screen.findByText('2 chủ đề · 30 từ')).toBeTruthy();
        expect(goi[0].url).toBe('/api/v1/chu-de?bo=vocab');
        expect(screen.getAllByRole('heading', { level: 2 }).map((h) => h.textContent)).toEqual(['Con số', 'Ăn uống']);
        expect(screen.getAllByRole('heading', { level: 3 }).map((h) => h.textContent)).toEqual(['Đếm số', 'Bánh và tiệm bánh']);
        const link = screen.getByRole('link', { name: /Bánh mì · bánh ngọt/ });
        expect(link.getAttribute('href')).toBe('/chu-de/banh-mi-tu-vung');
        expect(link.textContent).toBe('Bánh mì · bánh ngọt5 từ');
        expect(document.title).toBe('Chủ đề · Lernbuch');
    });

    it('dải "Đến nhóm" nhảy tới từng nhóm; dải cách xem đánh dấu trang đang mở', async () => {
        giaNoiDung();
        renderApp('/chu-de');
        const den = await screen.findByRole('navigation', { name: 'Đến nhóm' });
        expect(within(den).getAllByRole('link').map((a) => a.getAttribute('href'))).toEqual(['#nhom-con-so', '#nhom-an-uong']);
        expect(document.getElementById('nhom-an-uong')?.textContent).toBe('Ăn uống');
        const cach = screen.getByRole('navigation', { name: 'Xem nội dung theo' });
        expect(within(cach).getAllByRole('link').map((a) => [a.textContent, a.getAttribute('aria-current')])).toEqual([
            ['Chủ đề', 'page'],
            ['Cấp độ', null],
            ['Giao tiếp', null],
        ]);
    });

    it('Giao tiếp: cây của loại thẻ Giao tiếp, đếm theo câu; một nhóm thì không có dải Đến nhóm', async () => {
        const goi = giaNoiDung();
        renderApp('/giao-tiep');
        expect((await screen.findAllByText('1 chủ đề · 1 câu')).length).toBeGreaterThan(0);
        expect(goi[0].url).toBe('/api/v1/chu-de?bo=comm');
        expect(screen.getByRole('heading', { level: 1, name: 'Giao tiếp' })).toBeTruthy();
        expect(screen.getByRole('link', { name: /Ở tiệm bánh/ }).getAttribute('href')).toBe('/chu-de/o-tiem-banh-giao-tiep');
        expect(screen.queryByRole('navigation', { name: 'Đến nhóm' })).toBeNull();
    });

    it('chuyển từ Chủ đề sang Giao tiếp không hiện lẫn cây cũ', async () => {
        giaNoiDung();
        renderApp('/chu-de');
        await screen.findByText('2 chủ đề · 30 từ');
        fireEvent.click(within(screen.getByRole('navigation', { name: 'Xem nội dung theo' })).getByRole('link', { name: 'Giao tiếp' }));
        expect(screen.queryByText('Con số')).toBeNull();
        expect((await screen.findAllByText('1 chủ đề · 1 câu')).length).toBeGreaterThan(0);
    });

    it('máy chủ lỗi: báo không tải được, Thử lại tải lại', async () => {
        let lan = 0;
        giaApi({ 'GET /api/v1/chu-de?bo=vocab': () => (++lan === 1 ? { status: 500, json: null } : { json: { data: { collection: { code: 'vocab', name: 'Từ vựng' }, topic_count: 0, card_count: 0, levels: [], pos: [], groups: [] } } }) });
        renderApp('/chu-de');
        expect(await screen.findByText('Không tải được nội dung.')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Thử lại' }));
        expect(await screen.findByText('Chưa có chủ đề nào. Nội dung có sau khi nhập dữ liệu Lernbuch.')).toBeTruthy();
    });

    it('Cấp độ: tab loại thẻ (không có Giao tiếp), chọn cấp và loại từ thì đường dẫn và câu hỏi máy chủ đổi theo', async () => {
        const goi = giaNoiDung();
        renderApp('/cap-do');
        const tabs = await screen.findByRole('navigation', { name: 'Loại thẻ' });
        expect(within(tabs).getAllByRole('link').map((a) => a.textContent)).toEqual(['Từ vựng30', 'Cụm câu12']);
        expect(within(tabs).getByRole('link', { name: /Từ vựng/ }).getAttribute('aria-current')).toBe('page');
        await screen.findByText('2 chủ đề · 30 từ');

        const cap = screen.getByRole('group', { name: 'Lọc theo cấp' });
        expect(within(cap).getAllByRole('button').map((b) => b.textContent)).toEqual(['A127', 'A22', 'Chưa xếp cấp1']);
        fireEvent.click(within(cap).getByRole('button', { name: /^A2/ }));
        expect((await screen.findAllByText('1 chủ đề · 2 từ')).length).toBeGreaterThan(0);
        expect(where()).toBe('/cap-do?cap=2');
        expect(goi.at(-1)?.url).toBe('/api/v1/chu-de?bo=vocab&cap=2');
        // Link sang trang chủ đề mang theo bộ lọc
        expect(screen.getByRole('link', { name: /Bánh mì · bánh ngọt/ }).getAttribute('href')).toBe('/chu-de/banh-mi-tu-vung?cap=2');

        fireEvent.change(screen.getByRole('combobox', { name: 'Loại từ' }), { target: { value: 'verb' } });
        await waitFor(() => expect(where()).toBe('/cap-do?cap=2&loai=verb'));
        expect((await screen.findAllByText('1 chủ đề · 1 từ')).length).toBeGreaterThan(0);
        expect(screen.getByRole('link', { name: /Bánh mì · bánh ngọt/ }).getAttribute('href')).toBe('/chu-de/banh-mi-tu-vung?cap=2&loai=verb');

        fireEvent.click(screen.getByRole('button', { name: 'Bỏ lọc' }));
        expect(await screen.findByText('2 chủ đề · 30 từ')).toBeTruthy();
        expect(where()).toBe('/cap-do');
    });

    it('Cấp độ: bộ lọc không khớp chủ đề nào thì báo và gợi ý bỏ bớt', async () => {
        giaNoiDung();
        renderApp('/cap-do?cap=2&loai=other');
        expect(await screen.findByText('Không có chủ đề nào khớp bộ lọc.')).toBeTruthy();
        expect(screen.getByText('Bỏ bớt cấp hoặc loại từ để xem thêm.')).toBeTruthy();
    });

    it('Cấp độ: đổi loại thẻ thì bỏ bộ lọc cũ', async () => {
        const goi = giaNoiDung();
        renderApp('/cap-do?cap=2');
        fireEvent.click(await screen.findByRole('link', { name: /Cụm câu/ }));
        await waitFor(() => expect(where()).toBe('/cap-do?bo=phrase'));
        await waitFor(() => expect(goi.at(-1)?.url).toBe('/api/v1/chu-de?bo=phrase'));
    });
});
