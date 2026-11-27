import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { chupDu, NGUOI_HOC_THU } from './phien';

// Trang tài khoản (Fortify, Blade) khi chưa đăng nhập: dẫn về đúng trang, báo lỗi rõ, đạt WCAG 2.2 AA, không tràn ngang.
test.use({ storageState: { cookies: [], origins: [] } });

const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
// Trang tài khoản chỉ có giao diện sáng (giao diện tối là lựa chọn trong app, sau khi đăng nhập)
const PAGES = [
    ['Đăng nhập', '/dang-nhap', 'dang-nhap'],
    ['Tạo tài khoản', '/dang-ky', 'dang-ky'],
    ['Quên mật khẩu', '/quen-mat-khau', 'quen-mat-khau'],
] as const;

for (const [heading, path, anh] of PAGES) {
    test(`trang ${heading}: đạt WCAG 2.2 AA, không tràn ngang`, async ({ page }, info) => {
        await page.goto(path);
        await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible();
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(overflow, 'tràn ngang (px)').toBeLessThanOrEqual(0);
        const { violations } = await new AxeBuilder({ page }).withTags(WCAG).analyze();
        expect(violations.map((v) => v.id)).toEqual([]);
        await chupDu(page, info, `tai-khoan-${anh}`);
    });
}

test('đăng ký xong thì tới trang Xác minh email: đạt WCAG 2.2 AA, không tràn ngang', async ({ page }, info) => {
    await page.goto('/dang-ky');
    await page.getByLabel('Tên của bạn').fill('Người thử');
    await page.getByLabel('Email').fill(`dang-ky-${info.project.name}-${Date.now()}@lernbuch.test`);
    await page.getByLabel('Mật khẩu', { exact: true }).fill(NGUOI_HOC_THU.matKhau);
    await page.getByLabel('Nhập lại mật khẩu').fill(NGUOI_HOC_THU.matKhau);
    await page.getByRole('button', { name: 'Tạo tài khoản' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Xác minh email' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth), 'tràn ngang (px)').toBeLessThanOrEqual(0);
    const { violations } = await new AxeBuilder({ page }).withTags(WCAG).analyze();
    expect(violations.map((v) => v.id)).toEqual([]);
    await chupDu(page, info, 'tai-khoan-xac-minh-email');
});

test('chưa đăng nhập mà mở app học thì về trang đăng nhập', async ({ page }) => {
    await page.goto('/app/cai-dat');
    await expect(page).toHaveURL(/\/dang-nhap$/);
});

test('sai mật khẩu thì báo lỗi ngay dưới ô email, giữ email đã gõ', async ({ page }) => {
    await page.goto('/dang-nhap');
    await page.getByLabel('Email').fill(NGUOI_HOC_THU.email);
    await page.getByLabel('Mật khẩu').fill('sai-mat-khau-1');
    await page.getByRole('button', { name: 'Đăng nhập' }).click();
    await expect(page.getByText('Email hoặc mật khẩu không đúng.')).toBeVisible();
    await expect(page.getByLabel('Email')).toHaveValue(NGUOI_HOC_THU.email);
    await expect(page.getByLabel('Email')).toHaveAttribute('aria-invalid', 'true');
});
