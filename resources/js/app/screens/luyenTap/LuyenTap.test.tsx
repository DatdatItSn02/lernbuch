import 'fake-indexeddb/auto';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { giaApi, type LanGoi } from '../../../test/fakeApi';
import { BAI_GIONG, CAU_DONG_TU, CAU_LB3, CAU_PHIEU, DONG_TU, DS_BAI_TAP, DS_NGU_PHAP, THE_DIEN_CAU, nhanKetQua } from '../../../test/luyenTapGia';
import { renderApp } from '../../../test/render';
import { CHO_KET_QUA, type KetQuaGui } from '../../lib/baiTap';
import { datLaiChoTest, type LuotCham } from '../../lib/hangCho';

const key = (k: string, doi: Partial<KeyboardEventInit> = {}) => fireEvent.keyDown(window, { key: k, ...doi });
const where = () => screen.getByTestId('duong-dan').textContent;
const lienKet = (ten: string | RegExp) => screen.getByRole('link', { name: ten }).getAttribute('href');
/** Chữ của các vùng thông báo (bỏ ô ghi đường dẫn của test, cũng có vai trò status). */
const bao = () =>
    screen
        .queryAllByRole('status')
        .filter((e) => e.tagName !== 'OUTPUT')
        .map((e) => e.textContent)
        .join(' ');
const ketQuaGui = (goi: LanGoi[]) => goi.filter((g) => g.method === 'POST' && g.url === '/api/v1/bai-tap/ket-qua').map((g) => g.body as KetQuaGui);

beforeEach(async () => {
    await datLaiChoTest();
});

afterEach(async () => {
    vi.restoreAllMocks();
    await datLaiChoTest();
});

describe('Ngữ pháp', () => {
    it('danh sách theo cấp và chương; chương 9 chỉ có tên; giáo trình gốc và chuyên đề riêng; số câu bài tập', async () => {
        giaApi({ 'GET /api/v1/ngu-phap': () => ({ json: { data: DS_NGU_PHAP } }) });
        renderApp('/ngu-phap');

        expect(await screen.findByRole('heading', { level: 2, name: /A1/ })).toBeTruthy();
        expect(screen.getByRole('heading', { level: 2, name: /A1/ }).textContent).toBe('A13 điểm ngữ pháp');
        expect(screen.getByText('1. Danh từ & mạo từ')).toBeTruthy();
        expect(screen.getByText('Bổ trợ: phát âm, số, giờ')).toBeTruthy();
        expect(screen.getByText('Giáo trình gốc (tham khảo)')).toBeTruthy();
        expect(screen.getByRole('heading', { level: 2, name: 'Chuyên đề' })).toBeTruthy();
        expect(lienKet(/Genus: der, die, das/)).toBe('/ngu-phap/g-A1-22');
        expect(screen.getByRole('link', { name: /Genus: der, die, das/ }).textContent).toContain('7 câu');
        expect(within(screen.getByRole('link', { name: /Genus/ })).getByText('Genus: der, die, das').getAttribute('lang')).toBe('de');
        // Cấp có 3 chương trở xuống thì mở sẵn
        expect(screen.getByText('1. Danh từ & mạo từ').closest('details')?.open).toBe(true);
        expect(lienKet('Làm bài tập')).toBe('/bai-tap');
        expect(lienKet('Bảng động từ')).toBe('/dong-tu');
    });

    it('lọc một cấp (giữ trên đường dẫn); tìm theo tên bài không cần dấu; không khớp thì báo', async () => {
        giaApi({ 'GET /api/v1/ngu-phap': () => ({ json: { data: DS_NGU_PHAP } }) });
        renderApp('/ngu-phap');
        await screen.findByText('1. Danh từ & mạo từ');

        fireEvent.click(screen.getByRole('radio', { name: 'A2' }));
        expect(where()).toBe('/ngu-phap?cap=A2');
        expect(screen.queryByText('1. Danh từ & mạo từ')).toBeNull();
        expect(screen.getByText('3. Động từ')).toBeTruthy();
        expect(screen.queryByRole('heading', { name: 'Chuyên đề' })).toBeNull();

        fireEvent.click(screen.getByRole('radio', { name: 'Tất cả cấp' }));
        fireEvent.change(screen.getByRole('searchbox', { name: 'Tìm bài ngữ pháp' }), { target: { value: 'so nhieu' } });
        await waitFor(() => expect(screen.queryByText(/Genus/)).toBeNull());
        expect(screen.getByRole('link', { name: /Plural/ })).toBeTruthy();

        fireEvent.change(screen.getByRole('searchbox', { name: 'Tìm bài ngữ pháp' }), { target: { value: 'khong co bai nay' } });
        expect(await screen.findByText('Không có bài nào khớp.')).toBeTruthy();
    });

    it('không tải được thì báo và thử lại được', async () => {
        let lan = 0;
        giaApi({ 'GET /api/v1/ngu-phap': () => (++lan === 1 ? { status: 500, json: { message: 'x' } } : { json: { data: DS_NGU_PHAP } }) });
        renderApp('/ngu-phap');
        fireEvent.click(await screen.findByRole('button', { name: 'Thử lại' }));
        expect(await screen.findByText('1. Danh từ & mạo từ')).toBeTruthy();
    });

    it('trang bài: tiêu đề tiếng Đức, mục lục khi có hơn 2 phần, luyện tập bài này, các khối nội dung, bài sau', async () => {
        giaApi({ 'GET /api/v1/ngu-phap/g-A1-22': () => ({ json: { data: BAI_GIONG } }) });
        const cuon = vi.fn();
        Element.prototype.scrollIntoView = cuon;
        renderApp('/ngu-phap/g-A1-22');

        const h1 = await screen.findByRole('heading', { level: 1 });
        expect(h1.textContent).toBe('Genus: der, die, das');
        expect(h1.querySelector('[lang="de"]')).toBeTruthy();
        expect(document.title).toBe('Genus: der, die, das · Giống: der, die, das · Lernbuch');
        expect(screen.getByText('Giống: der, die, das · A1 · 1. Danh từ & mạo từ')).toBeTruthy();

        const luyen = screen.getByRole('region', { name: 'Luyện tập bài này' });
        expect(within(luyen).getByRole('link', { name: 'Giống danh từ & mạo từ (der / die / das) (4 câu)' }).getAttribute('href')).toBe(
            '/bai-tap/lam?bo=bt-LB3&n=20&ve=%2Fngu-phap%2Fg-A1-22',
        );

        const mucLuc = screen.getByRole('navigation', { name: 'Mục lục' });
        expect(within(mucLuc).getAllByRole('link').map((a) => a.textContent)).toEqual(['Ví dụ', 'Bảng mạo từ', 'Ghi nhớ']);
        fireEvent.click(within(mucLuc).getByRole('link', { name: 'Bảng mạo từ' }));
        expect(cuon).toHaveBeenCalled();
        // Số trang ở cuối tiêu đề phần bị bỏ; tiêu đề có mã neo cho mục lục
        expect(screen.getByRole('heading', { level: 2, name: 'Bảng mạo từ' }).id).toBe(within(mucLuc).getByRole('link', { name: 'Bảng mạo từ' }).getAttribute('href')?.slice(1));

        // Công thức, mẹo, ví dụ (câu tiếng Đức và bản dịch), tiêu đề nhỏ bỏ cấp, bảng, danh sách, cặp khoá, đoạn nhiều thứ tiếng
        expect(screen.getByText('der (m) · die (f) · das (n) · die (Plural)').getAttribute('lang')).toBe('de');
        expect(screen.getByText('LUÔN').tagName).toBe('STRONG');
        expect(screen.getAllByText('Mẹo')).toHaveLength(1);
        expect(screen.getByText('Bánh mì còn tươi.').getAttribute('lang')).toBe('vi');
        expect(screen.getByText('The bread is fresh.').getAttribute('lang')).toBe('en');
        expect(screen.getByRole('heading', { level: 3, name: 'Câu hỏi' })).toBeTruthy();
        expect(screen.getByText('Cái đèn có mới không?')).toBeTruthy();
        expect(screen.getByRole('columnheader', { name: 'giống đực' })).toBeTruthy();
        expect(screen.getByRole('rowheader', { name: 'Nominativ' })).toBeTruthy();
        expect(screen.getAllByRole('listitem').some((li) => li.textContent === 'Số nhiều luôn là die.')).toBe(true);
        expect(screen.getByText('-ung').tagName).toBe('DT');
        expect(screen.getByText('每个名词都有词性。').closest('[lang]')?.getAttribute('lang')).toBe('zh');
        expect(screen.getByText('Nhắc lại')).toBeTruthy();
        expect(screen.getByText('Lưu ý')).toBeTruthy();

        expect(screen.getByRole('link', { name: /Bài sau/ }).getAttribute('href')).toBe('/ngu-phap/g-A1-23');
        expect(screen.queryByRole('link', { name: /Bài trước/ })).toBeNull();
    });

    it('bài không có thì báo; bài không có bộ bài tập thì không có phần luyện tập', async () => {
        giaApi({
            'GET /api/v1/ngu-phap/khong-co': () => ({ status: 404, json: { code: 'khong_thay', message: 'Không tìm thấy.' } }),
            'GET /api/v1/ngu-phap/slang': () => ({ json: { data: { ...BAI_GIONG, slug: 'slang', title: 'Tiếng lóng', level: null, chapter: null, blocks: [], sets: [], prev: null, next: null } } }),
        });
        renderApp('/ngu-phap/khong-co');
        expect(await screen.findByText('Không có bài này.')).toBeTruthy();

        renderApp('/ngu-phap/slang');
        expect(await screen.findByRole('heading', { level: 1, name: 'Tiếng lóng' })).toBeTruthy();
        expect(screen.queryByRole('region', { name: 'Luyện tập bài này' })).toBeNull();
        expect(screen.queryByRole('navigation', { name: 'Mục lục' })).toBeNull();
    });
});

describe('Bài tập', () => {
    it('ô luyện nhanh theo cấp đã chọn; Ôn câu sai khi có câu sai; bài theo cấp › chương › bài ngữ pháp › bộ', async () => {
        giaApi({ 'GET /api/v1/bai-tap': () => ({ json: { data: DS_BAI_TAP } }) });
        renderApp('/bai-tap');

        expect(await screen.findByText('2 / 34 câu đã thuộc')).toBeTruthy();
        const o = screen.getByRole('navigation', { name: 'Cách luyện' });
        expect(within(o).getAllByRole('link').map((a) => a.querySelector('span.font-bold')?.textContent)).toEqual(['Luyện nhanh', 'Điền câu', 'Chia động từ', 'Số nhiều', 'Ôn câu sai']);
        expect(within(o).getByRole('link', { name: /Ôn câu sai/ }).getAttribute('href')).toBe('/bai-tap/lam?on=1&n=15');
        expect(within(o).getByRole('link', { name: /Ôn câu sai/ }).textContent).toContain('2 câu cần ôn');

        fireEvent.click(screen.getByRole('button', { name: 'A1' }));
        expect(where()).toBe('/bai-tap?cap=1');
        expect(within(o).getByRole('link', { name: /Luyện nhanh/ }).getAttribute('href')).toBe('/bai-tap/lam?n=15&cap=1');
        expect(within(o).getByRole('link', { name: /Điền câu/ }).getAttribute('href')).toBe('/bai-tap/dien-cau?cap=1');
        expect(within(o).getByRole('link', { name: /Chia động từ/ }).getAttribute('href')).toBe('/bai-tap/chia-dong-tu?cap=1');

        // Chỉ còn A1: chương 1 gồm bài Giống (bộ theo cấp và bộ Ôn tập ngữ pháp gắn với bài) và bài Số nhiều
        expect(screen.queryByText('Dativ, Imperativ, sollen')).toBeNull();
        const chuong = screen.getByText('1. Danh từ & mạo từ').closest('details') as HTMLElement;
        expect(within(chuong).getByRole('link', { name: /Giống: der, die, das/ }).getAttribute('href')).toBe('/ngu-phap/g-A1-22');
        const dong = within(chuong).getByRole('link', { name: /Giống danh từ & mạo từ/ });
        expect(dong.getAttribute('href')).toBe('/bai-tap/lam?bo=bt-LB3&n=20&ve=%2Fbai-tap');
        expect(dong.textContent).toContain('1/4');
        expect(dong.textContent).toContain('2 sai');
        expect(within(dong).getByRole('img', { name: '1 / 4 câu đã thuộc' })).toBeTruthy();
        // Bộ có nhóm làm cả bộ theo thứ tự
        expect(within(chuong).getByRole('link', { name: /1A · Điền der/ }).getAttribute('href')).toBe('/bai-tap/lam?bo=dx-g1-1A&n=3&thu-tu=1&ve=%2Fbai-tap');
        expect(within(chuong).getByRole('link', { name: /Số nhiều: Thêm -e/ })).toBeTruthy();

        // Bỏ lọc: bộ không gắn bài ngữ pháp vào "Tổng hợp" ở cuối cấp của nó
        fireEvent.click(screen.getByRole('button', { name: 'A1' }));
        expect(screen.getByText('Tổng hợp')).toBeTruthy();
        expect(screen.getByText('3. Động từ')).toBeTruthy();
    });

    it('tab Ôn tập: Từ vựng và Ngữ pháp theo chương (bài ôn sau bài cuối nó ôn), trộn cả chương, chọn cách chấm nhớ trên máy', async () => {
        giaApi({ 'GET /api/v1/bai-tap': () => ({ json: { data: DS_BAI_TAP } }) });
        renderApp('/bai-tap');
        await screen.findByText('1. Danh từ & mạo từ');

        fireEvent.click(screen.getByRole('button', { name: 'Ôn tập' }));
        expect(where()).toBe('/bai-tap?tab=on-tap');
        expect(screen.queryByRole('navigation', { name: 'Cách luyện' })).toBeNull();
        expect(screen.getByText('Hai phần ôn tập riêng, không trộn với bài tập theo cấp độ.')).toBeTruthy();

        const tuVung = screen.getByRole('region', { name: 'Từ vựng' });
        expect([...tuVung.querySelectorAll('summary')].map((s) => s.querySelector('.flex-1')?.textContent)).toEqual([
            'Bài 1 · Person & Familie',
            'Bài 2 · Wohnen',
            'Ôn tập 1 · Bài 1 đến Bài 2',
        ]);
        const nguPhap = screen.getByRole('region', { name: 'Ngữ pháp' });
        expect(within(nguPhap).getByRole('link', { name: /1A · Điền der/ }).getAttribute('href')).toBe('/bai-tap/lam?bo=dx-g1-1A&n=3&thu-tu=1&ve=%2Fbai-tap%3Ftab%3Don-tap');
        const tron = within(nguPhap).getAllByRole('link', { name: 'Trộn cả chương' });
        expect(tron[0].getAttribute('href')).toBe('/bai-tap/lam?bo=dx-g1-1A&n=3&ve=%2Fbai-tap%3Ftab%3Don-tap');

        fireEvent.click(screen.getByRole('radio', { name: 'Làm cả bộ rồi chấm' }));
        expect(window.localStorage.getItem('lernbuch.baitap.cachcham')).toBe('sheet');
        expect(within(nguPhap).getByRole('link', { name: /1A · Điền der/ }).getAttribute('href')).toBe('/bai-tap/lam?bo=dx-g1-1A&n=3&thu-tu=1&cham=phieu&ve=%2Fbai-tap%3Ftab%3Don-tap');
    });

    it('chưa có bộ nào thì báo; không có câu sai thì không có ô Ôn câu sai', async () => {
        giaApi({ 'GET /api/v1/bai-tap': () => ({ json: { data: { sets: [], lessons: [], chapters: [] } } }) });
        renderApp('/bai-tap');
        expect(await screen.findByText('Chưa có bài tập nào. Nội dung có sau khi nhập dữ liệu Lernbuch.')).toBeTruthy();
        expect(screen.queryByRole('link', { name: /Ôn câu sai/ })).toBeNull();
    });

    it('Số nhiều: mỗi quy tắc một ô, kèm tiến độ; ôn trộn tất cả; bài lý thuyết', async () => {
        giaApi({ 'GET /api/v1/bai-tap': () => ({ json: { data: DS_BAI_TAP } }) });
        renderApp('/so-nhieu');

        expect(await screen.findByText('Viết dạng số nhiều của danh từ · 1 / 5 đã thuộc')).toBeTruthy();
        const o = screen.getByRole('link', { name: /Thêm -e/ });
        expect(o.getAttribute('href')).toBe('/bai-tap/lam?bo=pl-e&n=20&ve=%2Fso-nhieu');
        expect(o.textContent).toContain('Quy tắc: nhiều danh từ một âm tiết thêm -e.');
        expect(o.textContent).not.toContain('Việc cần làm');
        expect(o.textContent).toContain('1/3 từ');
        expect(screen.getByRole('link', { name: /Thêm -n/ })).toBeTruthy();
        expect(lienKet('Ôn trộn tất cả')).toBe('/bai-tap/lam?bo=pl-e%2Cpl-n&n=25&ve=%2Fso-nhieu');
        expect(lienKet('Bài lý thuyết')).toBe('/ngu-phap/g-A1-23');
    });
});

describe('Bảng động từ', () => {
    it('danh sách; mở bảng chia; tìm, lọc động từ tách; hiện thêm trang sau', async () => {
        const goi = giaApi({
            'GET /api/v1/dong-tu': (_b, u) => {
                const trang = Number(u.searchParams.get('trang') ?? '1');
                const ds = u.searchParams.get('tach') === '1' ? [DONG_TU[0]] : trang === 2 ? [DONG_TU[1]] : [DONG_TU[0]];
                return { json: { data: ds, meta: { page: trang, per_page: 80, total: u.searchParams.get('tach') === '1' ? 1 : 2, pages: 2 } } };
            },
        });
        renderApp('/dong-tu');

        expect(await screen.findByText('2 động từ')).toBeTruthy();
        expect(screen.getByRole('rowheader', { name: /anrufen/ }).textContent).toContain('tách');
        expect(screen.getByText('rief an').getAttribute('lang')).toBe('de');
        fireEvent.click(screen.getByRole('button', { name: 'Hiện thêm (1)' }));
        const mo = await screen.findByRole('button', { name: /backen/ });
        expect(screen.queryByRole('button', { name: /Hiện thêm/ })).toBeNull();

        expect(mo.getAttribute('aria-expanded')).toBe('false');
        fireEvent.click(mo);
        expect(mo.getAttribute('aria-expanded')).toBe('true');
        const bang = screen.getByRole('table', { name: 'Bảng chia của backen' });
        expect(within(bang).getAllByRole('columnheader').map((c) => c.textContent)).toEqual(['Präsens', 'Präteritum', 'Perfekt']);
        expect(within(bang).getByRole('row', { name: /er \/ sie \/ es/ }).textContent).toBe('er / sie / esbacktbacktehat gebacken');
        expect(screen.getByText('back! · backt! · backen Sie!').getAttribute('lang')).toBe('de');
        expect(lienKet('Xem thẻ từ')).toBe('/tu/4');

        fireEvent.click(screen.getByRole('button', { name: 'Động từ tách' }));
        await waitFor(() => expect(where()).toBe('/dong-tu?tach=1'));
        expect(await screen.findByText('1 động từ')).toBeTruthy();

        fireEvent.change(screen.getByRole('searchbox', { name: 'Tìm động từ hoặc nghĩa' }), { target: { value: 'gọi' } });
        await waitFor(() => expect(goi.some((g) => g.url === `/api/v1/dong-tu?q=${encodeURIComponent('gọi')}&tach=1`)).toBe(true));
        expect(lienKet('Luyện chia động từ')).toBe('/bai-tap/chia-dong-tu?tach=1');
    });
});

describe('Làm bài: chấm ngay từng câu', () => {
    function moBo(path = '/bai-tap/lam?bo=bt-LB3&n=20', cau = CAU_LB3, ketQua = () => nhanKetQua()) {
        const goi = giaApi({
            'GET /api/v1/bai-tap/phien': () => ({ json: { data: cau } }),
            'POST /api/v1/bai-tap/ket-qua': ketQua,
            'GET /api/v1/bai-tap': () => ({ json: { data: DS_BAI_TAP } }),
        });
        renderApp(path);
        return goi;
    }

    it('4 dạng câu, câu sai quay lại để luyện, tổng kết đúng số; gửi lần trả lời đầu của từng câu; làm lại câu sai', async () => {
        const goi = moBo();

        // Trắc nghiệm: phím 2 chọn "Der" (đúng)
        expect(await screen.findByRole('group', { name: 'Các đáp án' })).toBeTruthy();
        expect(screen.getByText('Chọn đáp án đúng')).toBeTruthy();
        expect(goi.find((g) => g.url.startsWith('/api/v1/bai-tap/phien'))?.url).toBe('/api/v1/bai-tap/phien?bo=bt-LB3&n=20');
        key('2');
        expect(bao()).toContain('Đúng');
        expect(screen.getByText('Tisch là giống đực: der Tisch.')).toBeTruthy();
        key('Enter');

        // Điền, phân biệt hoa thường: "das" chỉ đúng chữ
        const o = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        fireEvent.change(o, { target: { value: 'das' } });
        fireEvent.keyDown(o, { key: 'Enter' });
        expect(bao()).toContain('Đúng chữ, chú ý viết hoa và ä, ö, ü, ß');
        key('Enter');

        // Sắp xếp: bấm từ theo thứ tự; bấm lại để bỏ ra
        fireEvent.click(await screen.findByRole('button', { name: 'Thêm “spielt” vào câu' }));
        fireEvent.click(screen.getByRole('button', { name: 'Bỏ “spielt” khỏi câu' }));
        for (const tu of ['Das Kind', 'spielt', 'im Garten']) fireEvent.click(screen.getByRole('button', { name: `Thêm “${tu}” vào câu` }));
        fireEvent.click(screen.getByRole('button', { name: /Kiểm tra/ }));
        expect(bao()).toContain('Đúng');
        key('Enter');

        // Sửa lỗi: ô có sẵn câu sai; để nguyên là sai, câu quay lại sau (ôn lại) rồi sửa đúng
        const sua = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        expect((sua as HTMLInputElement).value).toBe('Das Frau heißt Anna.');
        fireEvent.keyDown(sua, { key: 'Enter' });
        expect(bao()).toContain('Chưa đúng');
        expect(screen.getByText('Die Frau heißt Anna')).toBeTruthy();
        key('Enter');
        expect(await screen.findByText('Ôn lại')).toBeTruthy();
        const lai = screen.getByRole('textbox', { name: 'Câu trả lời' });
        fireEvent.change(lai, { target: { value: 'Die Frau heißt Anna.' } });
        fireEvent.keyDown(lai, { key: 'Enter' });
        key('Enter');

        expect(await screen.findByRole('heading', { level: 1, name: 'Đúng ngay lần đầu 2/4 câu.' })).toBeTruthy();
        expect(await screen.findByText('+10 xu')).toBeTruthy();
        const canOn = screen.getByRole('region', { name: 'Cần ôn lại' });
        expect(within(canOn).getAllByRole('listitem')).toHaveLength(2);
        const gui = ketQuaGui(goi);
        expect(gui).toHaveLength(1);
        expect(gui[0]).toMatchObject({ mode: 'set', grading: 'instant', set: 'bt-LB3' });
        expect(gui[0].items).toEqual([
            { id: 101, answer: 'Der' },
            { id: 102, answer: 'das' },
            { id: 103, answer: 'Das Kind spielt im Garten' },
            { id: 104, answer: 'Das Frau heißt Anna.' },
        ]);
        expect(gui[0].uid).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/);
        expect(gui[0].finished_at_ms).toBeGreaterThanOrEqual(gui[0].started_at_ms);

        // Làm lại 2 câu sai: lượt mới, mã mới
        fireEvent.click(screen.getByRole('button', { name: 'Làm lại 2 câu sai' }));
        expect(await screen.findByText('0/2')).toBeTruthy();
    });

    it('Xem đáp án là sai; Esc thoát về trang trước; mất mạng thì kết quả nằm chờ trên máy', async () => {
        const goi = giaApi({ 'GET /api/v1/bai-tap/phien': () => ({ json: { data: [CAU_LB3[1]] } }), 'GET /api/v1/bai-tap': () => ({ json: { data: DS_BAI_TAP } }) });
        renderApp('/bai-tap/lam?bo=bt-LB3&n=3&ve=/ngu-phap/g-A1-22');
        fireEvent.click(await screen.findByRole('button', { name: 'Xem đáp án' }));
        expect(bao()).toContain('Chưa đúng');
        key('Enter');
        // Câu sai quay lại một lần
        fireEvent.click(await screen.findByRole('button', { name: 'Xem đáp án' }));
        globalThis.fetch = (() => Promise.reject(new TypeError('Failed to fetch'))) as typeof fetch;
        key('Enter');

        expect(await screen.findByText('Chưa gửi được kết quả (mất mạng); có mạng sẽ tự gửi.')).toBeTruthy();
        expect(JSON.parse(window.localStorage.getItem(CHO_KET_QUA) ?? '[]')[0].items).toEqual([{ id: 102, answer: '' }]);
        expect(goi.filter((g) => g.method === 'POST')).toHaveLength(0);

        fireEvent.click(screen.getByRole('link', { name: 'Quay lại' }));
        expect(where()).toBe('/ngu-phap/g-A1-22');
    });

    it('Esc thoát; đường quay lại ngoài app thì về Bài tập; không có câu thì báo', async () => {
        moBo('/bai-tap/lam?n=15&ve=//evil.example');
        await screen.findByRole('group', { name: 'Các đáp án' });
        key('Escape');
        expect(where()).toBe('/bai-tap');

        giaApi({ 'GET /api/v1/bai-tap/phien': () => ({ json: { data: [] } }) });
        renderApp('/bai-tap/lam?on=1&n=15');
        expect(await screen.findByRole('heading', { level: 1, name: 'Chưa có câu nào cần ôn lại.' })).toBeTruthy();
    });

    it('Luyện nhanh và Ôn câu sai gửi đúng loại lượt; Số nhiều là loại riêng', async () => {
        const goi = moBo('/bai-tap/lam?n=15&cap=1', [CAU_LB3[0]]);
        await screen.findByRole('group', { name: 'Các đáp án' });
        expect(goi[0].url).toBe('/api/v1/bai-tap/phien?cap=1&n=15');
        key('2');
        key('Enter');
        await waitFor(() => expect(ketQuaGui(goi)).toHaveLength(1));
        expect(ketQuaGui(goi)[0]).toMatchObject({ mode: 'quick', set: null });

        const goi2 = moBo('/bai-tap/lam?bo=pl-e%2Cpl-n&n=25', [CAU_LB3[0]]);
        await screen.findAllByRole('group', { name: 'Các đáp án' });
        key('2');
        key('Enter');
        await waitFor(() => expect(ketQuaGui(goi2)).toHaveLength(1));
        expect(ketQuaGui(goi2)[0]).toMatchObject({ mode: 'plural', set: null });
    });
});

describe('Làm cả phiếu rồi chấm', () => {
    it('Enter sang câu sau, từ cho sẵn, Ctrl+Enter hỏi lại khi còn câu chưa làm, chấm và gửi, ghi nhớ, làm lại câu sai', async () => {
        const goi = giaApi({
            'GET /api/v1/bai-tap/phien': () => ({ json: { data: CAU_PHIEU } }),
            'POST /api/v1/bai-tap/ket-qua': () => nhanKetQua({ coins: 5, correct: 1, total: 3 }),
        });
        renderApp('/bai-tap/lam?bo=dx-g1-1A&n=3&thu-tu=1&cham=phieu');

        expect(await screen.findByRole('heading', { level: 1, name: 'Điền der / die / das' })).toBeTruthy();
        expect(goi[0].url).toBe('/api/v1/bai-tap/phien?bo=dx-g1-1A&n=3&thu_tu=1');
        expect(screen.getByText('Chương 1 · Artikel & Genus · 1A')).toBeTruthy();
        expect(screen.getByText('Điền der, die hoặc das.')).toBeTruthy();
        expect(screen.getByText('Đã làm 0 / 3')).toBeTruthy();

        // Một chỗ trống: ô nằm ngay trong câu; Enter sang ô sau
        const o1 = screen.getByRole('textbox', { name: 'Câu 1' });
        expect(o1.closest('p')?.textContent).toContain('Hier ist');
        fireEvent.change(o1, { target: { value: 'die' } });
        fireEvent.keyDown(o1, { key: 'Enter' });
        expect(document.activeElement).toBe(screen.getByRole('textbox', { name: 'Câu 2' }));
        fireEvent.click(within(screen.getByRole('group', { name: 'Từ cho sẵn' })).getByRole('button', { name: 'Familie' }));
        expect(screen.getByText('Đã làm 2 / 3')).toBeTruthy();

        key('Enter', { ctrlKey: true });
        expect(screen.getByRole('alert').textContent).toBe('Còn 1 câu chưa làm. Vẫn chấm?');
        fireEvent.click(screen.getByRole('button', { name: 'Làm tiếp' }));
        fireEvent.click(within(screen.getByRole('group', { name: 'Câu 3' })).getByRole('button', { name: 'Die' }));
        fireEvent.click(within(screen.getByRole('group', { name: 'Câu 3' })).getByRole('button', { name: 'Die' }));
        expect(screen.getByText('Đã làm 2 / 3')).toBeTruthy();
        key('Enter', { ctrlKey: true });
        fireEvent.click(screen.getByRole('button', { name: 'Vẫn chấm' }));

        expect(await screen.findByText('Đúng 1/3 câu.', { selector: 'p' })).toBeTruthy();
        expect(screen.getByText('2 câu cần xem lại.')).toBeTruthy();
        expect(screen.getByText('Chưa làm')).toBeTruthy();
        expect(screen.getByText('Mạo từ xác định ở Nominativ: der, die, das.')).toBeTruthy();
        expect(await screen.findByText('+5 xu')).toBeTruthy();
        const gui = ketQuaGui(goi);
        expect(gui[0]).toMatchObject({ mode: 'set', grading: 'sheet', set: 'dx-g1-1A' });
        expect(gui[0].items).toEqual([
            { id: 201, answer: 'die' },
            { id: 202, answer: 'Familie' },
            { id: 203, answer: '' },
        ]);

        fireEvent.click(screen.getByRole('button', { name: 'Làm lại 2 câu sai' }));
        expect(await screen.findByText('Đã làm 0 / 2')).toBeTruthy();
    });
});

describe('Điền câu và Chia động từ', () => {
    it('Điền câu: mỗi câu trả lời lần đầu là một lượt chấm thẻ chế độ cloze (Tốt, Khó hay Quên); câu đầy đủ kèm bản dịch', async () => {
        const goi = giaApi({
            'GET /api/v1/bai-tap/dien-cau': () => ({ json: { data: THE_DIEN_CAU } }),
            'POST /api/v1/hoc/luot-cham': (body) => {
                const n = (body as { reviews: unknown[] }).reviews.length;
                return { json: { data: { received: n, duplicates: 0, skipped: [], coins: 10 * n, too_fast: 0, cards: [], today: { reviewed: n, goal: 20, coins: 0, streak: 1 } } } };
            },
        });
        renderApp('/bai-tap/dien-cau?cap=1');
        const o = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        expect(goi[0].url).toBe('/api/v1/bai-tap/dien-cau?cap=1');
        expect(screen.getByText('Bánh mì · bánh ngọt')).toBeTruthy();
        expect(screen.getByText('bánh mì')).toBeTruthy();

        // Danh từ viết thường: đúng chữ (Khó); hiện câu đầy đủ và bản dịch
        fireEvent.change(o, { target: { value: 'brot' } });
        fireEvent.keyDown(o, { key: 'Enter' });
        expect(bao()).toContain('Đúng chữ');
        expect(screen.getByText('Das Brot ist noch frisch.')).toBeTruthy();
        expect(screen.getByText('Bánh mì vẫn còn tươi.')).toBeTruthy();
        key('Enter');

        const o2 = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        fireEvent.change(o2, { target: { value: 'zählt' } });
        fireEvent.keyDown(o2, { key: 'Enter' });
        expect(bao()).toContain('Đúng');
        key('Enter');

        expect(await screen.findByRole('heading', { level: 1, name: 'Đúng ngay lần đầu 1/2 câu.' })).toBeTruthy();
        expect(screen.getByText('Kết quả ghi vào mức thuộc của từng thẻ.')).toBeTruthy();
        expect(await screen.findByText('+20 xu')).toBeTruthy();
        const luot = goi.filter((g) => g.url === '/api/v1/hoc/luot-cham').flatMap((g) => (g.body as { reviews: LuotCham[] }).reviews);
        expect(luot.map((l) => [l.card_id, l.rating, l.mode, l.session_id])).toEqual([
            [2, 1, 'cloze', null],
            [9, 2, 'cloze', null],
        ]);
        expect(goi.some((g) => g.url === '/api/v1/bai-tap/ket-qua')).toBe(false);
    });

    it('Chia động từ: hỏi một dạng, nghe động từ sau khi trả lời; gửi theo mã câu, loại lượt chia động từ', async () => {
        const goi = giaApi({
            'GET /api/v1/dong-tu/luyen': () => ({ json: { data: CAU_DONG_TU } }),
            'POST /api/v1/bai-tap/ket-qua': () => nhanKetQua({ coins: 10, correct: 1, total: 2 }),
        });
        renderApp('/bai-tap/chia-dong-tu?tach=1');
        const o = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        expect(goi[0].url).toBe('/api/v1/dong-tu/luyen?tach=1');
        expect(screen.getByText('lesen → du')).toBeTruthy();
        expect(screen.getByText('đọc')).toBeTruthy();

        fireEvent.change(o, { target: { value: 'liest' } });
        fireEvent.keyDown(o, { key: 'Enter' });
        expect(bao()).toContain('Đúng');
        expect(screen.getByRole('button', { name: /Phát âm/ })).toBeTruthy();
        expect(screen.getByText('liest · liest · las · hat gelesen')).toBeTruthy();
        key('Enter');

        const o2 = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        fireEvent.change(o2, { target: { value: 'rief' } });
        fireEvent.keyDown(o2, { key: 'Enter' });
        expect(bao()).toContain('Chưa đúng');
        key('Enter');
        const o3 = await screen.findByRole('textbox', { name: 'Câu trả lời' });
        expect(screen.getByText('Ôn lại')).toBeTruthy();
        fireEvent.change(o3, { target: { value: 'rief an' } });
        fireEvent.keyDown(o3, { key: 'Enter' });
        key('Enter');

        expect(await screen.findByRole('heading', { level: 1, name: 'Đúng ngay lần đầu 1/2 câu.' })).toBeTruthy();
        const gui = ketQuaGui(goi);
        expect(gui).toHaveLength(1);
        expect(gui[0]).toMatchObject({ mode: 'conjugate', grading: 'instant', set: null });
        expect(gui[0].items).toEqual([
            { sig: 'v:lesen:du', answer: 'liest' },
            { sig: 'v:anrufen:prat', answer: 'rief' },
        ]);
    });
});
