import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { NGUOI_HOC_THU } from './phien';

// App học đã đăng nhập (người học thử): phần tài khoản trong Cài đặt đọc và ghi qua API /api/v1 bằng cookie phiên.
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test('Cài đặt có phần tài khoản từ API: email, máy này; chọn mục tiêu thì gửi lên tài khoản', async ({ page }) => {
    await page.goto('/app/cai-dat');
    await expect(page.getByRole('heading', { level: 2, name: 'Tài khoản' })).toBeVisible();
    await expect(page.getByText(`Email: ${NGUOI_HOC_THU.email}`)).toBeVisible();
    // Danh sách máy đọc từ bảng sessions: phải có đúng phiên của trình duyệt này
    const phien = await page.evaluate(async () => (await fetch('/api/v1/toi/phien', { headers: { Accept: 'application/json' } })).json());
    expect(phien.supported).toBe(true);
    expect(phien.data.filter((p: { is_current: boolean }) => p.is_current), JSON.stringify(phien)).toHaveLength(1);
    await expect(page.getByText('Máy này')).toBeVisible();

    // Hai dự án (máy tính, điện thoại) chạy song song trên cùng tài khoản: chọn mục tiêu khác cái đang chọn,
    // rồi kiểm bằng phản hồi của máy chủ (lưu bền đã có test Pest)
    const muc = (await page.getByRole('radio', { name: 'Du lịch' }).isChecked()) ? 'Đi làm' : 'Du lịch';
    const [phanHoi] = await Promise.all([
        page.waitForResponse((r) => r.url().endsWith('/api/v1/toi/ho-so') && r.request().method() === 'PUT'),
        page.getByText(muc, { exact: true }).click(),
    ]);
    expect(phanHoi.status()).toBe(200);
    await expect(page.getByText('Đã lưu.').first()).toBeVisible();
    await expect(page.getByRole('radio', { name: muc })).toBeChecked();

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
