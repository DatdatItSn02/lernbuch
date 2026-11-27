import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { giaNoiDung, tuyenThe } from '../../test/noiDungGia';
import { renderApp } from '../../test/render';
import { initialState } from '../state/store';

const ok = (json: unknown) => () => ({ json });

async function mo(id: number, doi: Parameters<typeof giaNoiDung>[0] = {}, state = initialState()) {
    const goi = giaNoiDung({ ...tuyenThe(1), ...tuyenThe(2), ...tuyenThe(3), ...tuyenThe(4), ...tuyenThe(5), ...doi });
    renderApp(`/tu/${id}`, state);
    await screen.findByRole('heading', { level: 1 });
    return goi;
}
const tab = (name: string) => fireEvent.click(screen.getByRole('tab', { name }));

describe('Thẻ từ (API)', () => {
    it('đầu thẻ: mạo từ tô màu có chữ, số nhiều, loại thẻ, loại từ, cấp; đường về chủ đề; tiêu đề trang', async () => {
        await mo(1);
        const h1 = screen.getByRole('heading', { level: 1 });
        expect(h1.textContent).toBe('das Brötchen');
        expect(h1.querySelector('.text-das')?.textContent).toBe('das');
        expect(h1.querySelector('[lang="de"]')).toBeTruthy();
        expect(h1.nextElementSibling?.textContent).toBe('die Brötchen');
        expect(screen.getByText('Từ vựng · danh từ · cấp A1')).toBeTruthy();
        const ve = within(screen.getByRole('navigation', { name: 'Vị trí' })).getByRole('link');
        expect(ve.textContent).toBe('Bánh mì · bánh ngọt');
        expect(ve.getAttribute('href')).toBe('/chu-de/banh-mi-tu-vung');
        expect(screen.getByRole('button', { name: 'Nghe' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Nghe chậm' })).toBeTruthy();
        expect(document.title).toBe('das Brötchen · Lernbuch');
    });

    it('tab Nghĩa và ví dụ: ba thứ tiếng, câu ví dụ kèm bản dịch, mẹo viết tay', async () => {
        await mo(1);
        const panel = screen.getByRole('tabpanel');
        expect(within(panel).getByText('bánh mì nhỏ (ổ tròn)')).toBeTruthy();
        expect(within(panel).getByText('bread roll').getAttribute('lang')).toBe('en');
        expect(within(panel).getByText('小面包').getAttribute('lang')).toBe('zh');
        expect(within(panel).getByText('Bấm một chữ trong câu để tra nghĩa.')).toBeTruthy();
        expect(within(panel).getAllByRole('button', { name: 'Nghe câu ví dụ' })).toHaveLength(2);
        expect(within(panel).getByText('Cho tôi hai cái bánh mì nhỏ.')).toBeTruthy();
        expect(within(panel).getByText('Two bread rolls, please.')).toBeTruthy();
        expect(within(panel).getByText('Số nhiều giống số ít: ein Brötchen, zwei Brötchen.').className).toContain('font-hand');
    });

    it('tắt EN thì ẩn nghĩa và bản dịch tiếng Anh; tắt cả ba thì nhắc bật lại', async () => {
        await mo(1);
        fireEvent.click(screen.getByRole('button', { name: 'EN (tiếng Anh)' }));
        expect(screen.queryByText('bread roll')).toBeNull();
        expect(screen.queryByText('Two bread rolls, please.')).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'VI (tiếng Việt)' }));
        fireEvent.click(screen.getByRole('button', { name: '中 (tiếng Trung)' }));
        expect(screen.getByText('Đang tắt cả ba thứ tiếng. Bật một nút ở trên để xem nghĩa.')).toBeTruthy();
    });

    it('bấm một chữ trong câu ví dụ: tra đúng chữ, hiện nghĩa và link mở thẻ; chữ chưa có thì báo; đóng được', async () => {
        const goi = await mo(1);
        const cau = screen.getAllByRole('group', { name: 'Bấm một chữ trong câu để tra nghĩa.' })[0];
        expect(within(cau).getAllByRole('button').map((b) => b.textContent)).toEqual(['Ich', 'möchte', 'zwei', 'Brötchen', 'bitte']);
        fireEvent.click(within(cau).getByRole('button', { name: 'Brötchen' }));
        const bang = await screen.findByRole('region', { name: 'Nghĩa của “Brötchen”' });
        await waitFor(() => expect(within(bang).getByRole('link', { name: /das Brötchen/ }).getAttribute('href')).toBe('/tu/1'));
        expect(goi.at(-1)?.url).toBe('/api/v1/tra-chu?chu=Br%C3%B6tchen');
        expect(within(cau).getByRole('button', { name: 'Brötchen' }).getAttribute('aria-expanded')).toBe('true');

        fireEvent.click(within(cau).getByRole('button', { name: 'bitte' }));
        expect(await screen.findByText('Chưa có “bitte” trong từ điển.')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Đóng' }));
        expect(screen.queryByRole('region', { name: /Nghĩa của/ })).toBeNull();
    });

    it('câu ví dụ là một điểm dừng Tab; mũi tên trái/phải, Home, End đi giữa các chữ', async () => {
        await mo(1);
        const cau = screen.getAllByRole('group', { name: 'Bấm một chữ trong câu để tra nghĩa.' })[0];
        const chu = within(cau).getAllByRole('button');
        expect(chu.map((b) => b.tabIndex)).toEqual([0, -1, -1, -1, -1]);
        chu[0].focus();
        fireEvent.keyDown(chu[0], { key: 'ArrowRight' });
        expect(document.activeElement).toBe(chu[1]);
        fireEvent.keyDown(chu[1], { key: 'End' });
        expect(document.activeElement).toBe(chu[4]);
        fireEvent.keyDown(chu[4], { key: 'ArrowRight' });
        expect(document.activeElement).toBe(chu[4]);
        fireEvent.keyDown(chu[4], { key: 'Home' });
        expect(document.activeElement).toBe(chu[0]);
    });

    it('phím mũi tên, Home, End chuyển tab và dời tiêu điểm theo', async () => {
        await mo(1);
        const tabs = screen.getAllByRole('tab');
        tabs[0].focus();
        fireEvent.keyDown(tabs[0], { key: 'ArrowRight' });
        expect(document.activeElement?.textContent).toBe('Ngữ pháp');
        fireEvent.keyDown(document.activeElement!, { key: 'End' });
        expect(document.activeElement?.textContent).toBe('Ghi chú');
        fireEvent.keyDown(document.activeElement!, { key: 'Home' });
        expect(document.activeElement?.textContent).toBe('Nghĩa và ví dụ');
        fireEvent.keyDown(document.activeElement!, { key: 'ArrowLeft' });
        expect(document.activeElement?.textContent).toBe('Ghi chú');
    });

    it('Ngữ pháp của danh từ: giống, số ít, số nhiều; "–" là thường chỉ dùng số ít', async () => {
        await mo(1);
        tab('Ngữ pháp');
        const panel = screen.getByRole('tabpanel');
        expect(panel.textContent).toContain('Giốngdas (giống trung)');
        expect(panel.textContent).toContain('Số ítdas Brötchen');
        expect(panel.textContent).toContain('Số nhiềudie Brötchen');
    });

    it('Ngữ pháp của danh từ không có số nhiều', async () => {
        await mo(3);
        tab('Ngữ pháp');
        expect(screen.getByRole('tabpanel').textContent).toContain('Số nhiềuthường chỉ dùng số ít');
    });

    it('Ngữ pháp của động từ: ba dạng chính và bảng chia ba thì', async () => {
        await mo(4);
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('backen');
        tab('Ngữ pháp');
        const baDang = screen.getByRole('table', { name: 'Ba dạng chính' });
        expect(within(baDang).getAllByRole('cell').map((c) => c.textContent)).toEqual(['backt', 'backte', 'hat gebacken']);
        const presens = screen.getByRole('table', { name: 'Chia thì hiện tại (Präsens)' });
        expect(within(presens).getAllByRole('row').map((r) => r.textContent)).toEqual(['ichbacke', 'dubackst', 'er/sie/esbackt', 'wirbacken', 'ihrbackt', 'sie/Siebacken']);
        expect(screen.getByRole('table', { name: 'Chia thì quá khứ (Präteritum)' })).toBeTruthy();
        expect(screen.getByRole('table', { name: 'Chia thì hoàn thành (Perfekt)' })).toBeTruthy();
        expect(screen.getByText('haben').closest('p')?.textContent).toBe('Trợ động từ ở Perfekt: haben');
    });

    it('Ngữ pháp của tính từ: nguyên dạng, so sánh hơn, so sánh nhất lấy từ dòng dạng từ', async () => {
        await mo(5);
        tab('Ngữ pháp');
        expect(screen.getByRole('tabpanel').textContent).toBe('Nguyên dạngsüßSo sánh hơnsüßerSo sánh nhấtam süßesten');
    });

    it('nút sao: gửi lên tài khoản; máy chủ lỗi thì trả lại như cũ và báo', async () => {
        let lan = 0;
        const goi = await mo(1, {
            'PUT /api/v1/the/1/so-tay': (body) => {
                lan += 1;
                return lan === 1 ? { json: { data: { starred: (body as { starred: boolean }).starred, note: null } } } : { status: 500, json: null };
            },
        });
        const sao = screen.getByRole('button', { name: 'Đánh dấu sao' });
        expect(sao.getAttribute('aria-pressed')).toBe('false');
        fireEvent.click(sao);
        expect(sao.getAttribute('aria-pressed')).toBe('true');
        await waitFor(() => expect(goi.filter((g) => g.method === 'PUT')).toHaveLength(1));
        expect(goi.find((g) => g.method === 'PUT')?.body).toEqual({ starred: true });

        fireEvent.click(sao);
        await waitFor(() => expect(screen.getByRole('alert').textContent).toBe('Chưa lưu được.'));
        expect(sao.getAttribute('aria-pressed')).toBe('true');
    });

    it('ghi chú: ngừng gõ thì tự lưu; đếm chữ, tối đa 500; đổi tab rồi quay lại vẫn còn', async () => {
        const goi = await mo(1, { 'PUT /api/v1/the/1/so-tay': (body) => ({ json: { data: { starred: false, note: (body as { note: string }).note || null } } }) });
        tab('Ghi chú');
        const o = screen.getByRole('textbox', { name: 'Ghi chú riêng cho từ này' }) as HTMLTextAreaElement;
        expect(o.maxLength).toBe(500);
        expect(screen.getByText('Lưu theo tài khoản, chỉ mình bạn xem được.')).toBeTruthy();
        fireEvent.change(o, { target: { value: 'hay mua ở tiệm gần nhà' } });
        expect(screen.getByText('22/500')).toBeTruthy();
        await waitFor(() => expect(within(screen.getByRole('tabpanel')).getByRole('status').textContent).toBe('Đã lưu.'));
        expect(goi.filter((g) => g.method === 'PUT').map((g) => g.body)).toEqual([{ note: 'hay mua ở tiệm gần nhà' }]);

        tab('Nghĩa và ví dụ');
        tab('Ghi chú');
        expect((screen.getByRole('textbox', { name: 'Ghi chú riêng cho từ này' }) as HTMLTextAreaElement).value).toBe('hay mua ở tiệm gần nhà');
    });

    it('ghi chú lưu lỗi thì báo và có nút Lưu lại', async () => {
        let lan = 0;
        const goi = await mo(1, {
            'PUT /api/v1/the/1/so-tay': (body) => {
                lan += 1;
                return lan === 1 ? { status: 500, json: null } : { json: { data: { starred: false, note: (body as { note: string }).note } } };
            },
        });
        tab('Ghi chú');
        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'nhớ' } });
        const trangThai = () => within(screen.getByRole('tabpanel')).getByRole('status').textContent;
        await waitFor(() => expect(trangThai()).toContain('Chưa lưu được.'));
        fireEvent.click(screen.getByRole('button', { name: 'Lưu lại' }));
        await waitFor(() => expect(trangThai()).toBe('Đã lưu.'));
        expect(goi.filter((g) => g.method === 'PUT')).toHaveLength(2);
    });

    it('từ trước và từ sau trong chủ đề; từ đầu không có từ trước', async () => {
        await mo(1);
        const nav = screen.getByRole('navigation', { name: 'Từ khác trong chủ đề' });
        expect(within(nav).queryByRole('link', { name: /Từ trước/ })).toBeNull();
        expect(within(nav).getByRole('link', { name: 'Từ sau: das Brot' }).getAttribute('href')).toBe('/tu/2');
        expect(nav.textContent).toContain('1/5');
        fireEvent.click(within(nav).getByRole('link', { name: 'Từ sau: das Brot' }));
        await screen.findByRole('heading', { level: 1, name: 'das Brot' });
        expect(within(screen.getByRole('navigation', { name: 'Từ khác trong chủ đề' })).getByRole('link', { name: 'Từ trước: das Brötchen' })).toBeTruthy();
        // Đổi thẻ thì về tab đầu
        expect(screen.getByRole('tab', { name: 'Nghĩa và ví dụ' }).getAttribute('aria-selected')).toBe('true');
    });

    it('nghĩa ẩn theo cài đặt đã chọn từ trước', async () => {
        const state = initialState({ ...initialState().settings, langs: { vi: true, en: false, zh: false } });
        await mo(2, {}, state);
        expect(screen.getByText('bánh mì')).toBeTruthy();
        expect(screen.queryByText('bread')).toBeNull();
        expect(screen.queryByText('面包')).toBeNull();
    });

    it('thẻ không có (hay đã ẩn) thì báo không tìm thấy; mã không phải số thì không hỏi máy chủ', async () => {
        await mo(999);
        expect(screen.getByRole('heading', { level: 1, name: 'Không có từ này' })).toBeTruthy();

        const goi = giaNoiDung({ 'GET /api/v1/the/1': ok({}) });
        renderApp('/tu/broetchen');
        expect(screen.getAllByRole('heading', { level: 1, name: 'Không có từ này' }).length).toBeGreaterThan(0);
        expect(goi).toHaveLength(0);
    });
});
