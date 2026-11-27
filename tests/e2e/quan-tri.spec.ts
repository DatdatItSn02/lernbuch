import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { NGUOI_HOC_THU } from './phien';

// Trang quản trị của G1 (mục 10 và 14 phần tự kiểm): người học, chủ đề, thẻ, ngữ pháp, bài tập, động từ, nguồn, nhật ký
// thao tác, job lỗi, vai trò, nhân viên, cài đặt học; ở cỡ máy tính và điện thoại, giao diện sáng và tối: đạt WCAG 2.2 AA,
// không tràn ngang, không lỗi trên trang. Nhân viên thử (NguoiHocThuSeeder::EMAIL_QUAN_TRI) sửa ghi chú một nguồn để có
// một dòng nhật ký, rồi xem dòng đó. Ảnh các màn chính cho báo cáo (test-results/anh).

test.use({ storageState: { cookies: [], origins: [] } });
test.describe.configure({ timeout: 240_000 });

const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const QUAN_TRI = 'quan-tri-thu@lernbuch.test';

/** Danh sách và trang đầu tiên mở được từ danh sách (sửa hay xem bản ghi đầu): [tên, đường dẫn, đuôi link của bản ghi]. */
const MAN: [string, string, RegExp | null][] = [
    ['tong-quan', '/admin', null],
    ['nguoi-hoc', '/admin/nguoi-hoc', /\/admin\/nguoi-hoc\/[0-9A-Z]{26}$/],
    ['chu-de', '/admin/chu-de', /\/admin\/chu-de\/\d+\/edit$/],
    ['the', '/admin/the', /\/admin\/the\/\d+\/edit$/],
    ['ngu-phap', '/admin/ngu-phap', /\/admin\/ngu-phap\/\d+\/edit$/],
    ['bai-tap', '/admin/bai-tap', /\/admin\/bai-tap\/\d+\/edit$/],
    ['dong-tu', '/admin/dong-tu', /\/admin\/dong-tu\/\d+\/edit$/],
    ['nguon', '/admin/nguon', /\/admin\/nguon\/\d+\/edit$/],
    ['nguon-them', '/admin/nguon/create', null],
    ['nhat-ky', '/admin/nhat-ky', /\/admin\/nhat-ky\/\d+$/],
    ['job-loi', '/admin/job-loi', null],
    ['vai-tro', '/admin/vai-tro', /\/admin\/vai-tro\/\d+\/edit$/],
    ['nhan-vien', '/admin/nhan-vien', /\/admin\/nhan-vien\/\d+\/edit$/],
    ['cai-dat-hoc', '/admin/cai-dat-hoc', null],
];
/** Màn chụp ảnh cho báo cáo (danh sách và trang mở từ danh sách). */
const CHUP = new Set(['nguoi-hoc', 'the', 'ngu-phap', 'bai-tap', 'nhat-ky', 'job-loi']);

async function dangNhapQuanTri(page: Page, theme: 'light' | 'dark') {
    // Filament đọc giao diện sáng/tối ở localStorage "theme"
    await page.addInitScript((t) => window.localStorage.setItem('theme', t), theme);
    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(QUAN_TRI);
    await page.locator('input[type="password"]').fill(NGUOI_HOC_THU.matKhau);
    await page.locator('form button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin$/);
    if (theme === 'dark') await expect(page.locator('html')).toHaveClass(/\bdark\b/);
    else await expect(page.locator('html')).not.toHaveClass(/\bdark\b/);
}

async function kiem(page: Page, ten: string, loi: string[]) {
    await page.waitForLoadState('networkidle');
    const { violations } = await new AxeBuilder({ page }).withTags(WCAG).analyze();
    expect(
        violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`),
        ten,
    ).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth), `tràn ngang: ${ten}`).toBeLessThanOrEqual(1);
    expect(loi, `lỗi trên trang: ${ten}`).toEqual([]);
}

/** Một dòng nhật ký: sửa ghi chú của nguồn đầu danh sách (chạy lại thì đổi lại chữ, vẫn ghi dòng mới). */
async function suaNguon(page: Page) {
    await page.goto('/admin/nguon');
    await page.locator('tbody a[href$="/edit"]').first().click();
    const ghiChu = page.getByLabel('Ghi chú');
    await expect(ghiChu).toBeVisible();
    const cu = await ghiChu.inputValue();
    const duoi = ' (đã soát)';
    await ghiChu.fill(cu.endsWith(duoi) ? cu.slice(0, -duoi.length) : `${cu}${duoi}`);
    await page.getByRole('button', { name: /^Lưu/ }).first().click();
    await page.waitForLoadState('networkidle');
}

/** Link mở bản ghi trên trang danh sách (sửa hay xem), theo mẫu đường dẫn của màn đó. */
async function linkBanGhi(page: Page, duong: string, mau: RegExp): Promise<string[]> {
    const hrefs = await page.locator(`a[href*="${duong}/"]`).evaluateAll((ds) => ds.map((a) => (a as HTMLAnchorElement).href));
    return hrefs.filter((h) => mau.test(h));
}

for (const theme of ['light', 'dark'] as const) {
    test(`trang quản trị (${theme === 'light' ? 'sáng' : 'tối'}): không lỗi, không tràn ngang, đạt WCAG 2.2 AA`, async ({ page }, info) => {
        const loi: string[] = [];
        page.on('pageerror', (e) => loi.push(String(e)));
        await dangNhapQuanTri(page, theme);
        await suaNguon(page);

        for (const [ten, duong, banGhi] of MAN) {
            await page.goto(duong);
            await expect(page.locator('h1').first()).toBeVisible();
            await kiem(page, `${ten} (${theme})`, loi);
            if (CHUP.has(ten)) await page.screenshot({ path: `test-results/anh/quan-tri-${ten}-${theme}-${info.project.name}.png`, fullPage: true });
            if (!banGhi) continue;

            const hrefs = await linkBanGhi(page, duong, banGhi);
            expect(hrefs.length, `có bản ghi để mở ở ${ten}`).toBeGreaterThan(0);
            await page.goto(hrefs[0]);
            await expect(page.locator('h1').first()).toBeVisible();
            await kiem(page, `${ten}: bản ghi đầu (${theme})`, loi);
            if (CHUP.has(ten)) await page.screenshot({ path: `test-results/anh/quan-tri-${ten}-mo-${theme}-${info.project.name}.png`, fullPage: true });
        }
    });
}
