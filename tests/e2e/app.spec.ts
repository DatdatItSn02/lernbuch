import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

// App học (dữ liệu mẫu) trên trình duyệt thật, cỡ máy tính và cỡ điện thoại:
// không tràn ngang, không lỗi console, đạt WCAG 2.2 AA theo axe ở cả giao diện sáng và tối, dùng được bằng bàn phím.

const SCREENS = [
    ['Hôm nay', '/app/hom-nay'],
    ['Học', '/app/hoc'],
    ['Thẻ từ', '/app/tu/broetchen'],
    ['Thẻ từ, động từ', '/app/tu/backen'],
    ['Trang chủ đề', '/app/chu-de/tiem-banh'],
    ['Danh sách chủ đề', '/app/chu-de'],
    ['Cài đặt', '/app/cai-dat'],
    ['Sắp có', '/app/ngu-phap'],
    ['Không tìm thấy', '/app/khong-co'],
] as const;

const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function open(page: Page, path: string, theme: 'light' | 'dark' = 'light') {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(String(e)));
    page.on('console', (m) => {
        if (m.type() === 'error') errors.push(m.text());
    });
    await page.addInitScript((t) => window.localStorage.setItem('lernbuch.caidat.v1', JSON.stringify({ theme: t })), theme);
    await page.goto(path);
    await expect(page.locator('#app')).not.toBeEmpty();
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
            expect(errors).toEqual([]);
        });
    }
}

test('phiên học bằng bàn phím: Space lật, 1–4 chấm, ← xem lại, Esc thoát', async ({ page }) => {
    await open(page, '/app/hoc');
    await expect(page.getByText('1/21')).toBeVisible();
    await expect(page.locator('h1')).toHaveText('die Orange');
    await page.keyboard.press('Space');
    await expect(page.getByText('quả cam')).toBeVisible();
    // Sau khi lật mới chấm WCAG: lúc này có thêm 4 nút chấm và phần đáp án
    expect(await axe(page)).toEqual([]);
    await page.keyboard.press('3');
    await expect(page.locator('h1')).toHaveText('der Teig');
    await expect(page.getByRole('status').first()).toContainText('Orange lên mức Khá');
    await page.keyboard.press('ArrowLeft');
    await expect(page.locator('h1')).toHaveText('die Orange');
    await page.keyboard.press('ArrowRight');
    await expect(page.locator('h1')).toHaveText('der Teig');
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/\/app\/hom-nay$/);
});

test('đi hết một phiên ôn và xem tổng kết', async ({ page }) => {
    await open(page, '/app/hoc?chu-de=trai-cay&on=1');
    for (let i = 0; i < 7; i++) {
        await page.keyboard.press('Space');
        await page.keyboard.press('3');
    }
    await expect(page.getByRole('heading', { name: 'Xong phiên' })).toBeVisible();
    expect(await axe(page)).toEqual([]);
    await page.getByRole('link', { name: /Học tiếp/ }).click();
    await expect(page.getByText('1/3')).toBeVisible();
});

test('trang chủ đề: phím / vào ô tìm, che cột rồi chạm để xem, sang trang 2', async ({ page }) => {
    await open(page, '/app/chu-de/tiem-banh');
    await page.keyboard.press('/');
    await expect(page.getByRole('searchbox', { name: 'Tìm trong chủ đề' })).toBeFocused();
    await page.keyboard.type('brotchen');
    await expect(page.locator('tbody tr')).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(page.locator('tbody tr')).toHaveCount(20);
    await page.getByText('Tiếng Đức', { exact: true }).first().click();
    await page.getByRole('button', { name: 'Xem tiếng Đức của “bánh mì”' }).click();
    await expect(page.getByRole('link', { name: 'das Brot' })).toBeVisible();
    expect(await axe(page)).toEqual([]);
    await page.getByRole('link', { name: 'Trang sau' }).click();
    await expect(page.locator('tbody tr')).toHaveCount(4);
    await expect(page).toHaveURL(/trang=2$/);
});

test('thẻ từ: Tab tới dải tab, mũi tên đổi tab', async ({ page }) => {
    await open(page, '/app/tu/backen');
    await page.getByRole('tab', { name: 'Nghĩa và ví dụ' }).focus();
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('tab', { name: 'Ngữ pháp' })).toBeFocused();
    await expect(page.getByRole('cell', { name: 'hat gebacken' })).toBeVisible();
    expect(await axe(page)).toEqual([]);
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
