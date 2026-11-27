import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

// App học trên trình duyệt thật, cỡ máy tính và cỡ điện thoại: không tràn ngang, không lỗi console, đạt WCAG 2.2 AA
// theo axe ở cả giao diện sáng và tối, dùng được bằng bàn phím. Mọi trang đọc API với nội dung mẫu nhỏ (NoiDungThuSeeder).
// Thẻ từ có id tự tăng nên ở noi-dung.spec.ts, mở qua trang chủ đề. Phiên học có chấm thẻ ở hoc.spec.ts (tài khoản riêng).

const SCREENS = [
    ['Hôm nay', '/app/hom-nay'],
    ['Học', '/app/hoc'],
    ['Chủ đề', '/app/chu-de'],
    ['Cấp độ', '/app/cap-do'],
    ['Giao tiếp', '/app/giao-tiep'],
    ['Trang chủ đề', '/app/chu-de/banh-mi-tu-vung'],
    ['Tra từ', '/app/tra-tu?q=brot'],
    ['Tra từ, lịch sử', '/app/tra-tu'],
    ['Sổ tay', '/app/so-tay'],
    ['Cài đặt', '/app/cai-dat'],
    ['Ngữ pháp', '/app/ngu-phap'],
    ['Bài ngữ pháp', '/app/ngu-phap/g-A1-22'],
    ['Bài tập', '/app/bai-tap'],
    ['Bài tập, Ôn tập', '/app/bai-tap?tab=on-tap'],
    ['Số nhiều', '/app/so-nhieu'],
    ['Bảng động từ', '/app/dong-tu'],
    ['Không tìm thấy', '/app/khong-co'],
    ['Chủ đề không có', '/app/chu-de/khong-co'],
] as const;

const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function open(page: Page, path: string, theme: 'light' | 'dark' = 'light') {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(String(e)));
    page.on('console', (m) => {
        if (m.type() === 'error') errors.push(m.text());
    });
    // Cài đặt lưu theo tài khoản: đặt giao diện như một thay đổi đang chờ gửi (lernbuch.caidat.cho) để thắng bản trên máy chủ
    await page.addInitScript((t) => {
        window.localStorage.setItem('lernbuch.caidat.v1', JSON.stringify({ theme: t }));
        window.localStorage.setItem('lernbuch.caidat.cho', JSON.stringify({ theme: t }));
    }, theme);
    await page.goto(path);
    await expect(page.locator('#app')).not.toBeEmpty();
    // Trang đọc API: chờ có tiêu đề trang (h1) và hết chữ "Đang tải…"
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.getByText('Đang tải…')).toHaveCount(0);
    await page.evaluate(() => document.fonts.ready);
    return errors;
}

async function axe(page: Page) {
    const { violations } = await new AxeBuilder({ page }).withTags(WCAG).analyze();
    return violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`);
}

for (const theme of ['light', 'dark'] as const) {
    for (const [name, path] of SCREENS) {
        test(`${name} (${theme === 'light' ? 'sáng' : 'tối'}): không tràn ngang, không lỗi, đạt WCAG 2.2 AA`, async ({ page }) => {
            const errors = await open(page, path, theme);
            await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
            await expect(page.locator('h1')).toHaveCount(1);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
            expect(overflow, 'tràn ngang (px)').toBeLessThanOrEqual(0);
            expect(await axe(page)).toEqual([]);
            // Trang chủ đề không có: API trả 404 là đúng, trình duyệt ghi dòng "Failed to load resource" vào console
            const coY = path === '/app/chu-de/khong-co' ? /status of 404/ : null;
            expect(errors.filter((e) => !coY?.test(e))).toEqual([]);
        });
    }
}

test('trang chủ đề: phím / vào ô tìm, máy chủ lọc khi ngừng gõ (không dấu vẫn ra), che cột rồi chạm để xem, sang trang 2', async ({ page }) => {
    await open(page, '/app/chu-de/so-dem-tu-vung');
    await expect(page.locator('tbody tr')).toHaveCount(20);
    await page.keyboard.press('/');
    await expect(page.getByRole('searchbox', { name: 'Tìm trong chủ đề' })).toBeFocused();
    await page.keyboard.type('zwolf');
    await expect(page).toHaveURL(/tim=zwolf/);
    await expect(page.locator('tbody tr')).toHaveCount(1);
    await expect(page.getByRole('link', { name: 'zwölf' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('tbody tr')).toHaveCount(20);
    await page.getByText('Tiếng Đức', { exact: true }).first().click();
    await page.getByRole('button', { name: 'Xem tiếng Đức của “số hai”' }).click();
    await expect(page.getByRole('link', { name: 'zwei', exact: true })).toBeVisible();
    expect(await axe(page)).toEqual([]);
    await page.getByRole('link', { name: 'Trang sau' }).click();
    await expect(page.locator('tbody tr')).toHaveCount(5);
    await expect(page).toHaveURL(/trang=2$/);
});

test('font của app học đã nạp: Be Vietnam Pro cho tiêu đề và chữ', async ({ page }) => {
    await open(page, '/app/hom-nay');
    const fonts = await page.evaluate(() => ({
        loaded: [...document.fonts].filter((f) => f.status === 'loaded').map((f) => f.family.replace(/["']/g, '')),
        h1: getComputedStyle(document.querySelector('h1')!).fontFamily,
        body: getComputedStyle(document.body).fontFamily,
    }));
    expect(fonts.loaded).toContain('Be Vietnam Pro');
    expect(fonts.loaded).not.toContain('Nunito');
    expect(fonts.h1).toMatch(/^"?Be Vietnam Pro/);
    expect(fonts.body).toMatch(/^"?Be Vietnam Pro/);
});

test('giao diện tối lưu lại sau khi tải lại trang', async ({ page }) => {
    await page.goto('/app/cai-dat');
    await page.getByText('Tối', { exact: true }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
});
