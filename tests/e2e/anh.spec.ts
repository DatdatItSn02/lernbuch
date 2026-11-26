import { expect, test } from '@playwright/test';

// Chụp khi chưa đăng nhập: trang quản trị không cho tài khoản người học vào
test.use({ storageState: { cookies: [], origins: [] } });

// Ảnh giao cùng báo cáo G0: trang chào và trang đăng nhập quản trị ở cỡ máy tính và điện thoại.
// CI ghi ảnh vào test-results/anh rồi đưa lên nhánh ci-results (thư mục anh/).
const PAGES = [
    ['trang-chao', '/', 'Học tiếng Đức cho người Việt'],
    ['quan-tri-dang-nhap', '/admin/login', 'Lernbuch'],
] as const;

for (const [name, path, text] of PAGES) {
    test(`chụp ảnh ${name}`, async ({ page }, info) => {
        await page.goto(path);
        await expect(page.getByText(text).first()).toBeVisible();
        await page.evaluate(() => document.fonts.ready);
        await page.screenshot({ path: `test-results/anh/${name}-${info.project.name}.png`, fullPage: true });
    });
}
