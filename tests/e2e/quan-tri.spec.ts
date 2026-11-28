import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { chupDu, PHIEN_QUAN_TRI } from './phien';

// Trang quản trị của G1 (mục 10 và 14 phần tự kiểm): người học, chủ đề, thẻ, ngữ pháp, bài tập, động từ, nguồn, nhật ký
// thao tác, job lỗi, vai trò, nhân viên, cài đặt học; ở cỡ máy tính và điện thoại, giao diện sáng và tối: đạt WCAG 2.2 AA,
// không tràn ngang, không lỗi trên trang. Nhân viên thử (NguoiHocThuSeeder::EMAIL_QUAN_TRI) sửa ghi chú một nguồn để có
// một dòng nhật ký, rồi xem dòng đó. Lần chạy chụp đủ (ANH_DU) chụp ảnh mọi màn. Lỗi từng màn ghi bằng expect.soft:
// một lần chạy liệt kê đủ mọi màn chưa đạt.

test.use({ storageState: PHIEN_QUAN_TRI });
test.describe.configure({ timeout: 240_000 });

const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

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
    ['cai-dat-ai', '/admin/cai-dat-ai', null],
];

/** Mở trang quản trị bằng phiên của nhân viên thử, ở giao diện sáng hay tối (Filament đọc localStorage "filament:theme:mode"). */
async function moQuanTri(page: Page, theme: 'light' | 'dark') {
    await page.addInitScript((t) => window.localStorage.setItem('filament:theme:mode', t), theme);
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin$/);
    if (theme === 'dark') await expect(page.locator('html')).toHaveClass(/\bdark\b/);
    else await expect(page.locator('html')).not.toHaveClass(/\bdark\b/);
}

async function kiem(page: Page, ten: string, loi: string[]) {
    await page.waitForLoadState('networkidle');
    const { violations } = await new AxeBuilder({ page }).withTags(WCAG).analyze();
    expect
        .soft(
            violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`),
            ten,
        )
        .toEqual([]);
    expect.soft(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth), `tràn ngang: ${ten}`).toBeLessThanOrEqual(1);
    expect.soft(loi.splice(0), `lỗi trên trang: ${ten}`).toEqual([]);
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
        await moQuanTri(page, theme);
        await suaNguon(page);

        for (const [ten, duong, banGhi] of MAN) {
            await page.goto(duong);
            await expect(page.locator('h1').first()).toBeVisible();
            await kiem(page, `${ten} (${theme})`, loi);
            await chupDu(page, info, `quan-tri-${ten}-${theme}`);
            if (!banGhi) continue;

            const hrefs = await linkBanGhi(page, duong, banGhi);
            expect.soft(hrefs.length, `có bản ghi để mở ở ${ten}`).toBeGreaterThan(0);
            if (!hrefs.length) continue;
            await page.goto(hrefs[0]);
            await expect(page.locator('h1').first()).toBeVisible();
            await kiem(page, `${ten}: bản ghi đầu (${theme})`, loi);
            await chupDu(page, info, `quan-tri-${ten}-mo-${theme}`);
        }
    });
}
