import { expect, test as setup } from '@playwright/test';
import { NGUOI_HOC_THU, NHAN_VIEN_THU, PHIEN_NGUOI_HOC, PHIEN_QUAN_TRI } from './phien';

setup('đăng nhập bằng tài khoản người học thử', async ({ page }) => {
    await page.goto('/app/hom-nay');
    // Chưa đăng nhập thì app học đưa về trang đăng nhập, đăng nhập xong quay lại đúng trang đang mở
    await expect(page).toHaveURL(/\/dang-nhap$/);
    await page.getByLabel('Email').fill(NGUOI_HOC_THU.email);
    await page.getByLabel('Mật khẩu').fill(NGUOI_HOC_THU.matKhau);
    await page.getByRole('button', { name: 'Đăng nhập' }).click();
    await expect(page).toHaveURL(/\/app\/hom-nay$/);
    await page.context().storageState({ path: PHIEN_NGUOI_HOC });
});

setup('đăng nhập trang quản trị bằng nhân viên thử', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin\/login$/);
    // Form đăng nhập là Livewire: đợi tải xong mới gõ, kẻo bấm khi script chưa chạy
    await page.waitForLoadState('networkidle');
    await page.locator('input[type="email"]').fill(NHAN_VIEN_THU);
    await page.locator('input[type="password"]').fill(NGUOI_HOC_THU.matKhau);
    await page.locator('form button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin$/, { timeout: 20_000 });
    await page.context().storageState({ path: PHIEN_QUAN_TRI });
});
