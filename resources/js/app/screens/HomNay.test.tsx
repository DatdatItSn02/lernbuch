import 'fake-indexeddb/auto';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { giaApi, type LanGoi, type Tuyen } from '../../test/fakeApi';
import { homNayMau } from '../../test/noiDungGia';
import { renderWith } from '../../test/render';
import { datLaiChoTest, guiDi, themLuot } from '../lib/hangCho';
import type { HomNayData } from '../lib/hoc';
import { initialState } from '../state/store';
import { HomNay } from './HomNay';

// Hôm nay đọc /api/v1/hom-nay: thứ Sáu 27/11/2026 theo múi giờ người học (máy chủ tính ngày).
const mo = (doi: Partial<HomNayData> = {}, state = initialState(), them: Record<string, Tuyen> = {}): LanGoi[] => {
    const goi = giaApi({ 'GET /api/v1/hom-nay': () => ({ json: { data: homNayMau(doi) } }), ...them });
    renderWith(<HomNay />, '/hom-nay', state);
    return goi;
};
// Lúc đang tải đã có tiêu đề ẩn "Hôm nay": chờ tiêu đề có số liệu
const tieuDe = () => screen.findByRole('heading', { level: 1, name: /^Hôm nay (có|đã)/ });
const strip = () => screen.getByRole('tablist', { name: 'Chọn ngày' });
const selectedTab = () => within(strip()).getAllByRole('tab').find((t) => t.getAttribute('aria-selected') === 'true')!;
/** Phần tên đọc ra sau chữ nhìn thấy: "Thứ Ba, 17/11: ôn 12 thẻ". */
const label = (t: HTMLElement) => t.textContent!.split(', ').slice(1).join(', ');

beforeEach(async () => {
    await datLaiChoTest();
});

afterEach(async () => {
    await datLaiChoTest();
});

describe('Hôm nay: số liệu thật từ máy chủ', () => {
    it('tiêu đề là một câu nói việc hôm nay; dòng trên ghi thứ, ngày, chuỗi ngày, xu', async () => {
        mo();
        const h1 = await tieuDe();
        expect(h1.textContent).toBe('Hôm nay có 12 thẻ cần ôn và 9 từ mới.');
        expect(h1.previousElementSibling?.textContent).toContain('Thứ Sáu, 27/11');
        expect(screen.getByText('Chuỗi 5 ngày')).toBeTruthy();
        expect(screen.getByText('340 xu')).toBeTruthy();
    });

    it('hộp thẻ đọc ra số thẻ từng ngăn, số đến hạn và số từ mới sắp học', async () => {
        mo();
        await tieuDe();
        const name = screen.getByRole('img', { name: /^Hộp thẻ theo mức thuộc/ }).getAttribute('aria-label')!;
        expect(name).toContain('Hạt (Mới) 12 thẻ, 9 sắp học');
        expect(name).toContain('Mầm (Đang học) 7 thẻ, 4 đến hạn');
        expect(name).toContain('Quả (Thành thạo) 2 thẻ');
        expect(name).not.toContain('Quả (Thành thạo) 2 thẻ,');
    });

    it('14 ô ngày: 10 ngày qua, hôm nay đang chọn, 3 ngày tới, thứ theo ngày của máy chủ', async () => {
        mo();
        await tieuDe();
        const tabs = within(strip()).getAllByRole('tab');
        expect(tabs).toHaveLength(14);
        expect(selectedTab().textContent).toBe('Nay27, Thứ Sáu, 27/11: ôn 8 thẻ');
        expect(tabs[0].textContent).toBe('T317, Thứ Ba, 17/11: ôn 12 thẻ');
        expect(label(tabs[1])).toBe('Thứ Tư, 18/11: không học');
        expect(tabs.slice(11).map(label)).toEqual(['Thứ Bảy, 28/11: 2 thẻ sẽ đến hạn', 'Chủ nhật, 29/11: 0 thẻ sẽ đến hạn', 'Thứ Hai, 30/11: 3 thẻ sẽ đến hạn']);
    });

    it('Học ngay ghi số thẻ đến hạn và số từ mới; mục tiêu ngày theo số đã ôn', async () => {
        mo();
        const go = await screen.findByRole('link', { name: /Học ngay/ });
        expect(go.getAttribute('href')).toBe('/hoc');
        expect(go.textContent).toContain('12 thẻ đến hạn · 9 từ mới');
        expect(screen.getByRole('progressbar', { name: 'Mục tiêu ngày' }).getAttribute('aria-valuenow')).toBe('8');
        expect(screen.getByText('8/20 thẻ')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Bánh mì · bánh ngọt' }).getAttribute('href')).toBe('/chu-de/banh-mi-tu-vung');
    });

    it('đổi số từ mới mỗi lượt thì số từ mới tính lại ngay (không quá số từ chưa học còn lại)', async () => {
        mo({ new_available: 40, new_topic: { slug: 'banh-mi-tu-vung', name: 'Bánh mì · bánh ngọt', new: 9 } });
        const go = await screen.findByRole('link', { name: /Học ngay/ });
        expect(go.textContent).toContain('10 từ mới');
        fireEvent.click(screen.getByRole('radio', { name: '20' }));
        expect(go.textContent).toContain('20 từ mới');
        fireEvent.click(screen.getByRole('radio', { name: '50' }));
        expect(go.textContent).toContain('40 từ mới');
        // Tất cả: hết từ mới của chủ đề đang học
        fireEvent.click(screen.getByRole('radio', { name: 'Tất cả' }));
        expect(go.textContent).toContain('9 từ mới');
        expect(screen.getByRole('img', { name: /^Hộp thẻ/ }).getAttribute('aria-label')).toContain('9 sắp học');
    });

    it('phím mũi tên chọn ngày trước, End sang ngày xa nhất, Home về ngày đầu', async () => {
        mo();
        await tieuDe();
        fireEvent.keyDown(strip(), { key: 'ArrowLeft' });
        expect(label(selectedTab())).toBe('Thứ Năm, 26/11: ôn 20 thẻ');
        expect(document.activeElement).toBe(selectedTab());
        expect(screen.getByRole('tabpanel').textContent).toContain('Đã ôn 20 thẻ.');
        fireEvent.keyDown(strip(), { key: 'End' });
        expect(screen.getByRole('tabpanel').textContent).toContain('3 thẻ sẽ đến hạn');
        fireEvent.keyDown(strip(), { key: 'ArrowRight' }); // đã ở cuối thì đứng yên
        expect(label(selectedTab())).toBe('Thứ Hai, 30/11: 3 thẻ sẽ đến hạn');
        fireEvent.keyDown(strip(), { key: 'Home' });
        expect(screen.getByRole('tabpanel').textContent).toContain('Đã ôn 12 thẻ.');
        fireEvent.click(within(strip()).getAllByRole('tab')[1]);
        expect(screen.getByRole('tabpanel').textContent).toContain('Không học ngày này.');
        fireEvent.click(within(strip()).getAllByRole('tab')[12]);
        expect(screen.getByRole('tabpanel').textContent).toContain('Chưa có thẻ nào đến hạn ngày này.');
    });

    it('hết thẻ đến hạn và hết từ mới thì không có nút Học ngay, chỉ đường sang Chủ đề', async () => {
        mo({ due: 0, due_by_level: [0, 0, 0, 0, 0, 0], new_available: 0, new_topic: { slug: 'banh-mi-tu-vung', name: 'Bánh mì · bánh ngọt', new: 0 }, due_topics: [] });
        expect((await tieuDe()).textContent).toBe('Hôm nay đã ôn hết thẻ đến hạn.');
        expect(screen.queryByRole('link', { name: /Học ngay/ })).toBeNull();
        expect(screen.getByRole('link', { name: 'Chủ đề' }).getAttribute('href')).toBe('/chu-de');
        expect(screen.getByRole('img', { name: /^Hộp thẻ/ }).getAttribute('aria-label')).not.toMatch(/đến hạn|sắp học/);
        expect(within(screen.getByRole('region', { name: 'Đến hạn theo chủ đề' })).getByText(/Chưa có thẻ nào đến hạn/)).toBeTruthy();
    });

    it('thẻ đến hạn chia theo chủ đề nhỏ, bấm vào thì học chủ đề đó', async () => {
        mo();
        await tieuDe();
        const links = within(screen.getByRole('region', { name: 'Đến hạn theo chủ đề' })).getAllByRole('link');
        expect(links.map((l) => l.getAttribute('href'))).toEqual(['/hoc?chu-de=banh-mi-tu-vung', '/hoc?chu-de=so-dem-tu-vung']);
        expect(links[0].textContent).toContain('5 đến hạn · 9 từ mới · 0 đã thành quả');
        expect(links[1].textContent).toContain('7 đến hạn · 3 từ mới · 2 đã thành quả');
    });

    it('chế độ học: đủ 7 chế độ như Lernbuch, mỗi ô ghi xu mỗi thẻ và mở phiên bằng chế độ đó', async () => {
        mo();
        await tieuDe();
        const modes = within(screen.getByRole('navigation', { name: 'Chế độ học' }));
        const links = modes.getAllByRole('link');
        expect(links.map((l) => l.querySelector('span')?.textContent)).toEqual(['Flashcard', 'Quiz', 'Listening', 'Typing', 'Ghép cặp', 'Hỗn hợp', 'Đặt câu']);
        expect(links.map((l) => l.getAttribute('href'))).toEqual([
            '/hoc',
            '/hoc?che-do=quiz',
            '/hoc?che-do=listening',
            '/hoc?che-do=typing',
            '/hoc?che-do=match',
            '/hoc?che-do=mixed',
            '/hoc?che-do=sentence',
        ]);
        expect(links[5].textContent).toContain('+20 xu');
        expect(links[0].textContent).toContain('+5 xu');
    });

    it('không tải được thì báo và cho thử lại', async () => {
        let lan = 0;
        giaApi({ 'GET /api/v1/hom-nay': () => (lan++ === 0 ? { status: 500, json: {} } : { json: { data: homNayMau() } }) });
        renderWith(<HomNay />, '/hom-nay');
        expect((await screen.findByRole('alert')).textContent).toContain('Không tải được nội dung.');
        fireEvent.click(screen.getByRole('button', { name: 'Thử lại' }));
        expect((await tieuDe()).textContent).toBe('Hôm nay có 12 thẻ cần ôn và 9 từ mới.');
    });

    it('máy chủ vừa nhận lô lượt chấm (từ hàng chờ) thì tải lại số liệu', async () => {
        let daOn = 8;
        const goi = mo({}, initialState(), {
            'POST /api/v1/hoc/luot-cham': () => {
                daOn += 1;
                return { json: { data: { received: 1, duplicates: 0, skipped: [], coins: 5, too_fast: 0, cards: [], today: { reviewed: daOn, goal: 20, coins: 345, streak: 5 } } } };
            },
            'GET /api/v1/hom-nay': () => ({ json: { data: homNayMau({ reviewed_today: daOn }) } }),
        });
        await tieuDe();
        expect(screen.getByText('8/20 thẻ')).toBeTruthy();
        await themLuot({ uid: '01KB0000000000000000000AAA', card_id: 1, session_id: null, mode: 'flashcard', rating: 2, reviewed_at_ms: 1_764_208_800_000, duration_ms: 3000 });
        await guiDi();
        await waitFor(() => expect(screen.getByText('9/20 thẻ')).toBeTruthy());
        expect(goi.filter((g) => g.url === '/api/v1/hom-nay')).toHaveLength(2);
    });
});
