import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { giaNoiDung, trangChuDe, tuyenChuDe } from '../../test/noiDungGia';
import { renderApp } from '../../test/render';

const bodyRows = () => {
    const table = screen.queryByRole('table');
    return table ? within(table).getAllByRole('row').slice(1) : [];
};
const words = () => bodyRows().map((r) => within(r).getAllByRole('cell')[0].querySelector('a')?.textContent);
const where = () => screen.getByTestId('duong-dan').textContent;

async function mo(duong: string) {
    const goi = giaNoiDung({ ...tuyenChuDe('banh-mi-tu-vung'), ...tuyenChuDe('so-dem-tu-vung'), ...tuyenChuDe('o-tiem-banh-giao-tiep') });
    renderApp(duong);
    await screen.findByRole('heading', { level: 1 });
    return goi;
}

describe('Trang chủ đề (API): sổ từ hai cột', () => {
    it('đầu trang: vị trí trong cây, tên hai thứ tiếng, loại thẻ và số từ, ghi chú phần học', async () => {
        await mo('/chu-de/banh-mi-tu-vung');
        expect(screen.getByRole('heading', { level: 1, name: 'Bánh mì · bánh ngọt' })).toBeTruthy();
        expect(screen.getByText('Bread · cake').getAttribute('lang')).toBe('en');
        const crumbs = within(screen.getByRole('navigation', { name: 'Vị trí' })).getAllByRole('listitem');
        expect(crumbs.map((c) => c.textContent)).toEqual(['Chủ đề', 'Ăn uống', 'Bánh và tiệm bánh']);
        expect(within(crumbs[0]).getByRole('link').getAttribute('href')).toBe('/chu-de');
        expect(screen.getByText('Từ vựng · 5 từ')).toBeTruthy();
        expect(screen.getByText(/Học và ôn theo chủ đề này có ở bản tiếp theo/)).toBeTruthy();
        // Tiêu đề trang đặt trong useEffect, có thể chạy sau lúc bảng đã hiện: chờ chứ không kiểm ngay
        await waitFor(() => expect(document.title).toBe('Bánh mì · bánh ngọt · Lernbuch'));
    });

    it('bảng: mạo từ tách riêng, link sang thẻ, dòng dạng từ và cấp; "–" là không có số nhiều; thẻ chưa xếp cấp không ghi cấp', async () => {
        await mo('/chu-de/banh-mi-tu-vung');
        expect(words()).toEqual(['das Brötchen', 'das Brot', 'das Mehl', 'backen', 'süß']);
        const [broetchen, , mehl, , suess] = bodyRows();
        expect(within(broetchen).getByRole('link').getAttribute('href')).toBe('/tu/1');
        expect(broetchen.textContent).toContain('die Brötchen · A1');
        expect(broetchen.textContent).toContain('bánh mì nhỏ (ổ tròn)');
        expect(mehl.textContent).toContain('thường chỉ dùng số ít · A2');
        expect(suess.textContent).toContain('süßer · am süßesten');
        expect(suess.textContent).not.toContain(' · A');
        // Mạo từ có chữ riêng (người mù màu vẫn đọc được) và lang="de"
        const link = within(broetchen).getByRole('link');
        expect(link.querySelector('[lang="de"]')?.textContent).toBe('das Brötchen');
        expect(link.querySelector('.text-das')?.textContent).toBe('das');
    });

    it('lọc theo cấp: bấm chọn, bấm lần nữa bỏ; đường dẫn và câu hỏi máy chủ đổi theo', async () => {
        const goi = await mo('/chu-de/banh-mi-tu-vung');
        const nhom = screen.getByRole('group', { name: 'Cấp của thẻ' });
        expect(within(nhom).getAllByRole('button').map((b) => b.textContent)).toEqual(['A12', 'A22', 'Chưa xếp cấp1']);
        fireEvent.click(within(nhom).getByRole('button', { name: /^A2/ }));
        await waitFor(() => expect(words()).toEqual(['das Mehl', 'backen']));
        expect(where()).toBe('/chu-de/banh-mi-tu-vung?cap=2');
        expect(goi.at(-1)?.url).toBe('/api/v1/chu-de/banh-mi-tu-vung?cap=2');
        expect(within(nhom).getByRole('button', { name: /^A2/ }).getAttribute('aria-pressed')).toBe('true');
        expect(screen.getByText('2/5 từ khớp bộ lọc')).toBeTruthy();

        fireEvent.click(within(nhom).getByRole('button', { name: /^Chưa xếp cấp/ }));
        await waitFor(() => expect(words()).toEqual(['das Mehl', 'backen', 'süß']));
        expect(where()).toBe('/chu-de/banh-mi-tu-vung?cap=2%2C0');

        fireEvent.click(screen.getByRole('button', { name: 'Bỏ lọc' }));
        await waitFor(() => expect(words()).toHaveLength(5));
        expect(where()).toBe('/chu-de/banh-mi-tu-vung');
    });

    it('lọc theo loại từ: chỉ có loại từ của chủ đề, kèm số thẻ', async () => {
        await mo('/chu-de/banh-mi-tu-vung');
        const select = screen.getByRole('combobox', { name: 'Loại từ' }) as HTMLSelectElement;
        expect([...select.options].map((o) => o.textContent)).toEqual(['Mọi loại từ', 'danh từ (3)', 'tính từ (1)', 'động từ (1)']);
        fireEvent.change(select, { target: { value: 'verb' } });
        await waitFor(() => expect(words()).toEqual(['backen']));
        expect(where()).toBe('/chu-de/banh-mi-tu-vung?loai=verb');
    });

    it('phím / vào ô tìm; ngừng gõ thì máy chủ lọc (không dấu vẫn ra); Esc xoá chữ', async () => {
        const goi = await mo('/chu-de/banh-mi-tu-vung');
        fireEvent.keyDown(window, { key: '/' });
        const box = screen.getByRole('searchbox', { name: 'Tìm trong chủ đề' });
        expect(document.activeElement).toBe(box);
        fireEvent.change(box, { target: { value: 'brotchen' } });
        await waitFor(() => expect(words()).toEqual(['das Brötchen']));
        expect(where()).toBe('/chu-de/banh-mi-tu-vung?tim=brotchen');
        expect(goi.filter((g) => g.url.includes('tim=')).map((g) => g.url)).toEqual(['/api/v1/chu-de/banh-mi-tu-vung?tim=brotchen']);

        fireEvent.keyDown(box, { key: 'Escape' });
        expect((box as HTMLInputElement).value).toBe('');
        await waitFor(() => expect(words()).toHaveLength(5));
    });

    it('không có từ nào khớp thì báo và cho bỏ lọc', async () => {
        await mo('/chu-de/banh-mi-tu-vung?tim=khongcotunay');
        expect(screen.getByText('Không có từ nào khớp bộ lọc.')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Bỏ lọc' }));
        await waitFor(() => expect(words()).toHaveLength(5));
        expect((screen.getByRole('searchbox') as HTMLInputElement).value).toBe('');
    });

    it('20 từ mỗi trang; sang trang 2 thì đường dẫn có ?trang=2 và tiêu điểm về đầu danh sách', async () => {
        await mo('/chu-de/so-dem-tu-vung');
        expect(bodyRows()).toHaveLength(20);
        expect(screen.getByText('Trang 1/2')).toBeTruthy();
        fireEvent.click(screen.getByRole('link', { name: 'Trang sau' }));
        await waitFor(() => expect(bodyRows()).toHaveLength(5));
        expect(where()).toBe('/chu-de/so-dem-tu-vung?trang=2');
        expect(words()).toEqual(['siebzehn', 'achtzehn', 'neunzehn', 'zwanzig', 'dreißig']);
        expect(screen.queryByRole('link', { name: 'Trang sau' })).toBeNull();
        // Tiêu điểm dời trong useEffect sau khi trang mới hiện
        await waitFor(() => expect(document.activeElement?.textContent).toContain('trang 2/2'));
    });

    it('số trang quá lớn thì hiện trang cuối (máy chủ trả trang cuối)', async () => {
        await mo('/chu-de/so-dem-tu-vung?trang=99');
        expect(screen.getAllByText('Trang 2/2').length).toBeGreaterThan(0);
        expect(bodyRows()).toHaveLength(5);
    });

    it('che cột tiếng Đức: chỉ còn nút Xem; chạm vào thì hiện từ của dòng đó; Hiện hết', async () => {
        await mo('/chu-de/banh-mi-tu-vung');
        fireEvent.click(screen.getByRole('radio', { name: 'Tiếng Đức' }));
        expect(screen.queryByRole('link', { name: /Brötchen/ })).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Xem tiếng Đức của “bánh mì nhỏ (ổ tròn)”' }));
        expect(screen.getByRole('link', { name: /das Brötchen/ })).toBeTruthy();
        expect(screen.queryByRole('link', { name: /das Brot$/ })).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Hiện hết' }));
        expect(words()).toHaveLength(5);
    });

    it('che cột tiếng Việt: nhãn nút đọc ra từ tiếng Đức của dòng', async () => {
        await mo('/chu-de/banh-mi-tu-vung');
        fireEvent.click(screen.getByRole('radio', { name: 'Tiếng Việt' }));
        expect(screen.queryByText('bánh mì')).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Xem tiếng Việt của “das Brot”' }));
        expect(screen.getByText('bánh mì')).toBeTruthy();
    });

    it('chủ đề Giao tiếp: đường dẫn đầu về trang Giao tiếp, đếm theo câu', async () => {
        await mo('/chu-de/o-tiem-banh-giao-tiep');
        const crumbs = within(screen.getByRole('navigation', { name: 'Vị trí' })).getAllByRole('link');
        expect(crumbs[0].textContent).toBe('Giao tiếp');
        expect(crumbs[0].getAttribute('href')).toBe('/giao-tiep');
        expect(screen.getByText('Giao tiếp · 1 câu')).toBeTruthy();
    });

    it('chủ đề không có thì báo không tìm thấy, có đường về danh sách', async () => {
        await mo('/chu-de/khong-co');
        expect(screen.getByRole('heading', { level: 1, name: 'Không có chủ đề này' })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Về danh sách chủ đề' }).getAttribute('href')).toBe('/chu-de');
    });

    it('mất mạng: báo rõ và có nút Thử lại; thử lại được thì hiện chủ đề', async () => {
        let lan = 0;
        vi.stubGlobal(
            'fetch',
            vi.fn(async (url: string) => {
                lan += 1;
                if (lan === 1) throw new TypeError('Failed to fetch');
                const kq = trangChuDe('banh-mi-tu-vung', new URL(url, 'http://lernbuch.test'));
                return new Response(JSON.stringify(kq), { status: 200, headers: { 'Content-Type': 'application/json' } });
            }),
        );
        renderApp('/chu-de/banh-mi-tu-vung');
        expect(await screen.findByText('Không có mạng. Kiểm tra kết nối rồi thử lại.')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Thử lại' }));
        expect(await screen.findByRole('heading', { level: 1, name: 'Bánh mì · bánh ngọt' })).toBeTruthy();
    });
});
