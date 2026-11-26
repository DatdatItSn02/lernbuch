import { expect, test as setup } from '@playwright/test';
import { NGUOI_HOC_THU, PHIEN_NGUOI_HOC } from './phien';

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
