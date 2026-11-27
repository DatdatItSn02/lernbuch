import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { giaNoiDung, soTayMau } from '../../test/noiDungGia';
import { renderApp } from '../../test/render';

const where = () => screen.getByTestId('duong-dan').textContent;
const dem = { all: 3, starred: 2, noted: 2 };
const meta = { page: 1, per_page: 20, total: 3, pages: 1, counts: dem };

describe('Sổ tay', () => {
    it('danh sách thẻ có sao và ghi chú riêng; lọc theo loại kèm số đếm', async () => {
        const goi = giaNoiDung({
            'GET /api/v1/so-tay': (_b, u) => {
                const loc = u.searchParams.get('loc');
                const ds = soTayMau().filter((x) => (loc === 'sao' ? x.bookmark.starred : loc === 'ghi-chu' ? !!x.bookmark.note : true));
                return { json: { data: ds, meta: { ...meta, total: ds.length } } };
            },
        });
        renderApp('/so-tay');
        const ds = await screen.findByRole('region', { name: 'Thẻ trong sổ tay' });
        await waitFor(() => expect(within(ds).getAllByRole('listitem')).toHaveLength(3));
        expect(within(ds).getAllByRole('link').map((a) => [a.textContent, a.getAttribute('href')])).toEqual([
            ['das Mehl', '/tu/3'],
            ['zahlen', '/tu/8'],
            ['das Brot', '/tu/2'],
        ]);
        expect(within(ds).getByText('không có số nhiều').className).toContain('font-hand');

        const loc = screen.getByRole('navigation', { name: 'Lọc sổ tay' });
        expect(within(loc).getAllByRole('link').map((a) => a.textContent)).toEqual(['Tất cả (3)', 'Có sao (2)', 'Có ghi chú (2)']);
        fireEvent.click(within(loc).getByRole('link', { name: 'Có sao (2)' }));
        await waitFor(() => expect(within(ds).getAllByRole('listitem')).toHaveLength(2));
        expect(where()).toBe('/so-tay?loc=sao');
        expect(goi.at(-1)?.url).toBe('/api/v1/so-tay?loc=sao');
        expect(within(loc).getByRole('link', { name: 'Có sao (2)' }).getAttribute('aria-current')).toBe('page');
    });

    it('bỏ sao thì gửi lên tài khoản rồi tải lại danh sách', async () => {
        let lan = 0;
        const goi = giaNoiDung({
            'GET /api/v1/so-tay': () => {
                lan += 1;
                const ds = lan === 1 ? soTayMau() : soTayMau().slice(0, 2);
                return { json: { data: ds, meta: { ...meta, total: ds.length, counts: lan === 1 ? dem : { all: 2, starred: 1, noted: 2 } } } };
            },
            'PUT /api/v1/the/2/so-tay': () => ({ json: { data: { starred: false, note: null } } }),
        });
        renderApp('/so-tay');
        fireEvent.click(await screen.findByRole('button', { name: 'Bỏ sao “das Brot”' }));
        await waitFor(() => expect(screen.getByRole('link', { name: 'Tất cả (2)' })).toBeTruthy());
        expect(goi.find((g) => g.method === 'PUT')).toMatchObject({ url: '/api/v1/the/2/so-tay', body: { starred: false } });
        expect(screen.queryByRole('link', { name: 'das Brot' })).toBeNull();
    });

    it('sổ tay trống thì chỉ cách thêm thẻ; không có ô lọc', async () => {
        giaNoiDung({ 'GET /api/v1/so-tay': () => ({ json: { data: [], meta: { page: 1, per_page: 20, total: 0, pages: 1, counts: { all: 0, starred: 0, noted: 0 } } } }) });
        renderApp('/so-tay');
        expect(await screen.findByText(/Sổ tay còn trống/)).toBeTruthy();
        expect(screen.queryByRole('navigation', { name: 'Lọc sổ tay' })).toBeNull();
        const ds = screen.getByRole('region', { name: 'Thẻ trong sổ tay' });
        expect(within(ds).getAllByRole('link').map((a) => a.getAttribute('href'))).toEqual(['/chu-de', '/tra-tu']);
    });

    it('tìm trong sổ tay: ngừng gõ thì hỏi máy chủ; không khớp thì báo', async () => {
        const goi = giaNoiDung({
            'GET /api/v1/so-tay': (_b, u) => {
                const tim = u.searchParams.get('tim');
                const ds = tim ? [] : soTayMau();
                return { json: { data: ds, meta: { ...meta, total: ds.length } } };
            },
        });
        renderApp('/so-tay');
        fireEvent.change(await screen.findByRole('searchbox', { name: 'Tìm trong sổ tay' }), { target: { value: 'xyz' } });
        expect(await screen.findByText('Không có thẻ nào khớp.')).toBeTruthy();
        expect(where()).toBe('/so-tay?tim=xyz');
        expect(goi.at(-1)?.url).toBe('/api/v1/so-tay?tim=xyz');
    });
});
