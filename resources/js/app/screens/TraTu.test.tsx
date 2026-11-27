import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { giaNoiDung } from '../../test/noiDungGia';
import { renderApp } from '../../test/render';

const where = () => screen.getByTestId('duong-dan').textContent;
const o = () => screen.getByRole('searchbox', { name: 'Tra từ tiếng Đức, Việt, Anh hoặc Trung' }) as HTMLInputElement;
const ketQua = () => within(screen.getByRole('region', { name: 'Tra từ' })).getAllByRole('listitem');

describe('Tra từ', () => {
    it('ô trống thì hiện tra gần đây: mở lại thẻ đã xem, bấm chữ cũ để tra lại', async () => {
        giaNoiDung();
        renderApp('/tra-tu');
        const ganDay = await screen.findByRole('region', { name: 'Tra gần đây' });
        await waitFor(() => expect(within(ganDay).getAllByRole('listitem')).toHaveLength(2));
        expect(within(ganDay).getByRole('link', { name: /^das Brot\s?· brot$/ }).getAttribute('href')).toBe('/tu/2');
        fireEvent.click(within(ganDay).getByRole('button', { name: 'zahlen' }));
        expect(o().value).toBe('zahlen');
        await waitFor(() => expect(where()).toBe('/tra-tu?q=zahlen'));
    });

    it('gõ rồi ngừng thì tra; từ khớp đúng đứng đầu; đếm kết quả; chữ tra nằm trên đường dẫn', async () => {
        const goi = giaNoiDung();
        renderApp('/tra-tu');
        fireEvent.change(o(), { target: { value: 'zahlen' } });
        await waitFor(() => expect(where()).toBe('/tra-tu?q=zahlen'));
        await waitFor(() => expect(ketQua().length).toBeGreaterThan(0));
        expect(goi.filter((g) => g.url.startsWith('/api/v1/tra-tu?')).map((g) => g.url)).toEqual(['/api/v1/tra-tu?q=zahlen']);
        expect(ketQua()[0].querySelector('a')?.textContent).toBe('zahlen');
        expect(ketQua()[0].textContent).toContain('trả tiền');
        expect(screen.getByText('3 kết quả')).toBeTruthy();
        expect(screen.queryByRole('region', { name: 'Tra gần đây' })).toBeNull();
    });

    it('mở đường dẫn có ?q= thì tra ngay; một chữ cái thì nhắc gõ thêm, không hỏi máy chủ', async () => {
        const goi = giaNoiDung();
        renderApp('/tra-tu?q=brot');
        await waitFor(() => expect(ketQua()[0].querySelector('a')?.textContent).toBe('das Brot'));
        fireEvent.change(o(), { target: { value: 'b' } });
        await waitFor(() => expect(screen.getByText('Gõ ít nhất 2 chữ cái.')).toBeTruthy());
        expect(goi.filter((g) => g.url === '/api/v1/tra-tu?q=b')).toHaveLength(0);
    });

    it('không có kết quả thì gợi ý bỏ dấu hoặc gõ ngắn hơn', async () => {
        giaNoiDung();
        renderApp('/tra-tu?q=khongcotunay');
        expect(await screen.findByText('Không có kết quả. Thử bỏ dấu hoặc gõ ngắn hơn.')).toBeTruthy();
    });

    it('mở thẻ từ kết quả thì ghi lịch sử (chữ đã tra và thẻ); Enter thì ghi chữ đã tra', async () => {
        const goi = giaNoiDung();
        renderApp('/tra-tu?q=brot');
        await waitFor(() => expect(ketQua().length).toBeGreaterThan(0));
        fireEvent.submit(o().closest('form')!);
        fireEvent.click(within(ketQua()[0]).getByRole('link'));
        await waitFor(() => expect(goi.filter((g) => g.method === 'POST')).toHaveLength(2));
        expect(goi.filter((g) => g.method === 'POST').map((g) => g.body)).toEqual([{ term: 'brot' }, { term: 'brot', card_id: 2 }]);
        expect(where()).toBe('/tu/2');
    });

    it('nút sao trên kết quả thêm thẻ vào sổ tay; lỗi thì trả lại như cũ', async () => {
        let lan = 0;
        const goi = giaNoiDung({
            'PUT /api/v1/the/2/so-tay': (body) => (++lan === 1 ? { json: { data: { starred: (body as { starred: boolean }).starred, note: null } } } : { status: 500, json: null }),
        });
        renderApp('/tra-tu?q=brot');
        const sao = await screen.findByRole('button', { name: 'Thêm “das Brot” vào sổ tay' });
        fireEvent.click(sao);
        await waitFor(() => expect(sao.getAttribute('aria-pressed')).toBe('true'));
        expect(goi.find((g) => g.method === 'PUT')?.body).toEqual({ starred: true });
        fireEvent.click(sao);
        await waitFor(() => expect(goi.filter((g) => g.method === 'PUT')).toHaveLength(2));
        await waitFor(() => expect(sao.getAttribute('aria-pressed')).toBe('true'));
    });

    it('xoá một mục lịch sử và xoá hết', async () => {
        const goi = giaNoiDung({ 'DELETE /api/v1/tra-tu/lich-su/31': () => ({ status: 204 }), 'DELETE /api/v1/tra-tu/lich-su': () => ({ status: 204 }) });
        renderApp('/tra-tu');
        const ganDay = await screen.findByRole('region', { name: 'Tra gần đây' });
        fireEvent.click(await within(ganDay).findByRole('button', { name: 'Xoá “das Brot” khỏi lịch sử' }));
        expect(within(ganDay).getAllByRole('listitem')).toHaveLength(1);
        fireEvent.click(within(ganDay).getByRole('button', { name: 'Xoá hết' }));
        expect(within(ganDay).getByText('Từ bạn mở từ kết quả tra sẽ hiện ở đây.')).toBeTruthy();
        await waitFor(() => expect(goi.filter((g) => g.method === 'DELETE').map((g) => g.url)).toEqual(['/api/v1/tra-tu/lich-su/31', '/api/v1/tra-tu/lich-su']));
    });

    it('phím / đưa tiêu điểm vào ô tra; Esc xoá chữ', async () => {
        giaNoiDung();
        renderApp('/tra-tu?q=brot');
        fireEvent.keyDown(window, { key: '/' });
        expect(document.activeElement).toBe(o());
        fireEvent.keyDown(o(), { key: 'Escape' });
        expect(o().value).toBe('');
        await waitFor(() => expect(where()).toBe('/tra-tu'));
    });
});
