import { expect, type Page } from '@playwright/test';

// Phiên đăng nhập của người học thử (database/seeders/NguoiHocThuSeeder.php), lưu sau dự án dang-nhap.
export const PHIEN_NGUOI_HOC = 'tests/e2e/.auth/nguoi-hoc.json';
export const NGUOI_HOC_THU = { email: 'nguoi-hoc@lernbuch.test', matKhau: 'thu-lernbuch-2026' };

/**
 * Tài khoản riêng cho test có chấm thẻ (tiến độ không lẫn với test khác): theo cỡ màn hình (tên dự án Playwright)
 * và số thứ tự của test, 1–10 (NguoiHocThuSeeder::emailHoc; 1–3 cho hoc.spec.ts, 4–10 cho che-do.spec.ts).
 */
export const emailHoc = (duAn: string, so: number) => `hoc-${duAn}-${so}@lernbuch.test`;

/** Đăng nhập bằng trang đăng nhập (trang chưa có phiên), rồi về Hôm nay. */
export async function dangNhap(page: Page, email: string) {
    await page.goto('/app/hom-nay');
    await expect(page).toHaveURL(/\/dang-nhap$/);
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Mật khẩu').fill(NGUOI_HOC_THU.matKhau);
    await page.getByRole('button', { name: 'Đăng nhập' }).click();
    await expect(page).toHaveURL(/\/app\/hom-nay$/);
}
