import { expect, test, type Page } from '@playwright/test';

// Ảnh các màn nội dung của app học (đã đăng nhập, nội dung mẫu) ở cỡ máy tính và điện thoại, để duyệt giao diện.
// CI ghi vào test-results/anh rồi đưa lên nhánh ci-results (thư mục anh/), ghi đè bản cũ.

async function mo(page: Page, duong: string, theme: 'light' | 'dark') {
    await page.addInitScript((t) => {
        window.localStorage.setItem('lernbuch.caidat.v1', JSON.stringify({ theme: t }));
        window.localStorage.setItem('lernbuch.caidat.cho', JSON.stringify({ theme: t }));
    }, theme);
    await page.goto(duong);
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.getByText('Đang tải…')).toHaveCount(0);
    await page.evaluate(() => document.fonts.ready);
}

const MAN = [
    ['chu-de', '/app/chu-de'],
    ['cap-do', '/app/cap-do?cap=1'],
    ['giao-tiep', '/app/giao-tiep'],
    ['trang-chu-de', '/app/chu-de/banh-mi-tu-vung'],
    ['tra-tu', '/app/tra-tu?q=brot'],
    ['so-tay', '/app/so-tay'],
] as const;

for (const [ten, duong] of MAN) {
    test(`chụp ảnh app ${ten}`, async ({ page }, info) => {
        await mo(page, duong, 'light');
        await page.screenshot({ path: `test-results/anh/app-${ten}-${info.project.name}.png`, fullPage: true });
    });
}

for (const theme of ['light', 'dark'] as const) {
    test(`chụp ảnh app thẻ từ (${theme})`, async ({ page }, info) => {
        await mo(page, '/app/chu-de/banh-mi-tu-vung', theme);
        await page.getByRole('link', { name: 'das Brötchen', exact: true }).click();
        await expect(page.locator('h1')).toHaveText('das Brötchen');
        // Mở bảng tra của một chữ trong câu ví dụ
        await page.getByRole('group', { name: 'Bấm một chữ trong câu để tra nghĩa.' }).first().getByRole('button', { name: 'Brötchen', exact: true }).click();
        await expect(page.getByRole('region', { name: 'Nghĩa của “Brötchen”' }).getByRole('link')).toBeVisible();
        await page.screenshot({ path: `test-results/anh/app-the-tu-${theme}-${info.project.name}.png`, fullPage: true });
    });
}

test('chụp ảnh app thẻ từ động từ, tab Ngữ pháp', async ({ page }, info) => {
    await mo(page, '/app/chu-de/banh-mi-tu-vung', 'light');
    await page.getByRole('link', { name: 'backen', exact: true }).click();
    await page.getByRole('tab', { name: 'Ngữ pháp' }).click();
    await expect(page.getByRole('table', { name: 'Ba dạng chính' })).toBeVisible();
    await page.screenshot({ path: `test-results/anh/app-dong-tu-${info.project.name}.png`, fullPage: true });
});
