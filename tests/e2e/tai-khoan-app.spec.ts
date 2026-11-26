import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { NGUOI_HOC_THU } from './phien';

// App học đã đăng nhập (người học thử): phần tài khoản trong Cài đặt đọc và ghi qua API /api/v1 bằng cookie phiên.
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test('Cài đặt có phần tài khoản từ API: email, máy này; chọn mục tiêu thì lưu lên tài khoản', async ({ page }) => {
    await page.goto('/app/cai-dat');
    await expect(page.getByRole('heading', { level: 2, name: 'Tài khoản' })).toBeVisible();
    await expect(page.getByText(`Email: ${NGUOI_HOC_THU.email}`)).toBeVisible();
    await expect(page.getByText('Máy này')).toBeVisible();

    await page.getByText('Du lịch', { exact: true }).click();
    await expect(page.getByText('Đã lưu.').first()).toBeVisible();
    await page.reload();
    await expect(page.getByRole('radio', { name: 'Du lịch' })).toBeChecked();

    const { violations } = await new AxeBuilder({ page }).withTags(WCAG).analyze();
    expect(violations.map((v) => v.id)).toEqual([]);
});

test('API trả 401 khi không có phiên đăng nhập, không lộ dữ liệu', async ({ playwright, baseURL }) => {
    const khach = await playwright.request.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
    const r = await khach.get('/api/v1/toi', { headers: { Accept: 'application/json' } });
    expect(r.status()).toBe(401);
    expect(await r.json()).toEqual({ code: 'chua_dang_nhap', message: 'Bạn cần đăng nhập lại.' });
    await khach.dispose();
});
