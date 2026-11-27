import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { dangNhap, emailHoc } from './phien';

// Phiên học trên trình duyệt thật với nội dung mẫu (NoiDungThuSeeder): người mới học từ chủ đề đầu tiên (Số đếm, 25 thẻ).
// Mỗi test một tài khoản riêng theo cỡ màn hình, nên tiến độ không lẫn nhau khi chạy song song.

test.use({ storageState: { cookies: [], origins: [] } });

const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const axe = async (page: Page) =>
    (await new AxeBuilder({ page }).withTags(WCAG).analyze()).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`);

/** Lật rồi chấm Tốt; chờ thẻ kế hiện (bộ đếm tăng). */
async function chamTot(page: Page, thuTu: number, tong: number) {
    await page.keyboard.press('Space');
    await expect(page.getByRole('group', { name: 'Tự chấm' })).toBeVisible();
    await page.keyboard.press('3');
    if (thuTu < tong) await expect(page.getByText(`${thuTu + 1}/${tong}`, { exact: true })).toBeVisible();
}

/** Số liệu Hôm nay đọc thẳng từ máy chủ (fetch trong trang, cùng cookie phiên). */
const homNay = (page: Page) =>
    page.evaluate(async () => {
        const r = await fetch('/api/v1/hom-nay', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        return ((await r.json()) as { data: { reviewed_today: number; levels: number[] } }).data;
    });

test('phiên học bằng bàn phím: Space lật, 1–4 chấm, ← xem lại, Esc thoát', async ({ page }, info) => {
    await dangNhap(page, emailHoc(info.project.name, 1));
    await page.goto('/app/hoc');
    await expect(page.getByText('1/10', { exact: true })).toBeVisible();
    await expect(page.locator('h1')).toHaveText('zahlen');
    await page.keyboard.press('Space');
    await expect(page.getByText('trả tiền')).toBeVisible();
    // Sau khi lật mới chấm WCAG: lúc này có thêm 4 nút chấm và phần đáp án
    expect(await axe(page)).toEqual([]);
    await page.keyboard.press('3');
    await expect(page.locator('h1')).toHaveText('zählen');
    await expect(page.getByRole('status').first()).toContainText('zahlen lên mức Quen');
    await page.keyboard.press('ArrowLeft');
    await expect(page.locator('h1')).toHaveText('zahlen');
    await page.keyboard.press('ArrowRight');
    await expect(page.locator('h1')).toHaveText('zählen');
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/\/app\/hom-nay$/);
    // Lượt vừa chấm tới máy chủ: Hôm nay ghi đã ôn 1 thẻ
    await expect(page.getByText('1/20 thẻ')).toBeVisible();
});

test('đi hết một phiên, xem tổng kết, Học tiếp mở phiên mới với từ mới kế tiếp', async ({ page }, info) => {
    await dangNhap(page, emailHoc(info.project.name, 2));
    await page.goto('/app/hoc');
    // Chờ phiên tải xong (đang tải thì chưa có thẻ để lật)
    await expect(page.getByText('1/10', { exact: true })).toBeVisible();
    for (let i = 1; i <= 10; i++) await chamTot(page, i, 10);
    await expect(page.getByRole('heading', { name: 'Xong phiên' })).toBeVisible();
    await expect(page.getByText('Đã học 10 từ mới. 10 thẻ lên mức.')).toBeVisible();
    expect(await axe(page)).toEqual([]);
    const tiep = page.getByRole('link', { name: /Học tiếp/ });
    await expect(tiep).toContainText('0 ôn · 10 mới');
    await tiep.click();
    await expect(page.getByText('1/10', { exact: true })).toBeVisible();
    // Phiên mới bắt đầu từ thẻ thứ 11 của chủ đề Số đếm (eins…sechs đã học: 10 thẻ đầu là zahlen, zählen, die Zahl, null…sechs)
    await expect(page.locator('h1')).toHaveText('sieben');
});

test('mục 5: mất mạng sau thẻ 3, chấm tiếp 5 thẻ, tải lại trang, có mạng lại: đủ 8 lượt tới máy chủ, mỗi lượt một lần', async ({ page, context }, info) => {
    await dangNhap(page, emailHoc(info.project.name, 3));
    await page.goto('/app/hoc');
    await expect(page.getByText('1/10', { exact: true })).toBeVisible();

    // 3 thẻ đầu khi có mạng: chờ lô tới máy chủ
    const lo = page.waitForResponse((r) => r.url().endsWith('/api/v1/hoc/luot-cham') && r.status() === 200);
    for (let i = 1; i <= 3; i++) await chamTot(page, i, 10);
    await lo;
    await expect(page.getByText(/lượt chờ gửi/)).toHaveCount(0);

    // Mất mạng: vẫn chấm tiếp, đầu trang ghi số lượt chờ gửi
    await context.setOffline(true);
    for (let i = 4; i <= 8; i++) await chamTot(page, i, 10);
    await expect(page.getByText('5 lượt chờ gửi')).toBeVisible();

    // Tải lại trang khi máy chủ còn chưa nhận được lượt nào (chặn riêng đường gửi lượt chấm): lượt vẫn nằm trong IndexedDB
    await page.route('**/api/v1/hoc/luot-cham', (r) => r.abort('internetdisconnected'));
    await context.setOffline(false);
    await page.reload();
    await expect(page.getByText('1/', { exact: false }).first()).toBeVisible();
    await expect(page.getByText('5 lượt chờ gửi')).toBeVisible();

    // Nối mạng lại: hàng chờ tự gửi
    await page.unroute('**/api/v1/hoc/luot-cham');
    const gui = page.waitForResponse((r) => r.url().endsWith('/api/v1/hoc/luot-cham') && r.status() === 200);
    await page.evaluate(() => window.dispatchEvent(new Event('online')));
    await gui;
    await expect(page.getByText(/lượt chờ gửi/)).toHaveCount(0);

    const d = await homNay(page);
    expect(d.reviewed_today).toBe(8);
    // 8 thẻ đã học (mức Quen), không thẻ nào bị tính hai lần
    expect(d.levels[2]).toBe(8);
});
