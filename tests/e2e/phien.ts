import { createHash } from 'node:crypto';
import { mkdirSync, writeFileSync } from 'node:fs';
import { expect, type Page, type TestInfo } from '@playwright/test';

// Phiên đăng nhập của người học thử (database/seeders/NguoiHocThuSeeder.php), lưu sau dự án dang-nhap.
export const PHIEN_NGUOI_HOC = 'tests/e2e/.auth/nguoi-hoc.json';
export const NGUOI_HOC_THU = { email: 'nguoi-hoc@lernbuch.test', matKhau: 'thu-lernbuch-2026' };
// Phiên của nhân viên thử (NguoiHocThuSeeder::EMAIL_QUAN_TRI, cùng mật khẩu), lưu sau dự án dang-nhap: trang quản trị chỉ
// đăng nhập một lần cho mọi test (trang đăng nhập Filament chặn quá 5 lần mỗi phút từ một IP).
export const PHIEN_QUAN_TRI = 'tests/e2e/.auth/quan-tri.json';
export const NHAN_VIEN_THU = 'quan-tri-thu@lernbuch.test';

/**
 * Tài khoản riêng cho test có chấm thẻ (tiến độ không lẫn với test khác): theo cỡ màn hình (tên dự án Playwright)
 * và số thứ tự của test, 1–13 (NguoiHocThuSeeder::emailHoc; 1–3 cho hoc.spec.ts, 4–10 cho che-do.spec.ts, 11 cho pwa.spec.ts,
 * 12–13 cho ai.spec.ts).
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

/**
 * Lần chạy chụp đủ mọi màn (CI đặt ANH_DU=1 khi commit có "[anh]"): ảnh mọi màn, sáng và tối, hai cỡ màn hình, và DOM đã
 * vẽ để chạy bộ dò Impeccable (tools/tu-kiem). Lần chạy thường không chụp, cho nhánh ci-results khỏi phình.
 */
export const ANH_DU = process.env.ANH_DU === '1';

/** Khi ANH_DU: ảnh test-results/anh/<ten>-<cỡ màn hình>.png (cả trang) và, ở cỡ máy tính, DOM ở ket-qua/dom/<ten>.html. */
export async function chupDu(page: Page, info: TestInfo, ten: string, caTrang = true) {
    if (!ANH_DU) return;
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: `test-results/anh/${ten}-${info.project.name}.png`, fullPage: caTrang });
    if (info.project.name === 'may-tinh') await luuDom(page, ten);
}

/** DOM đang thấy (bỏ script) kèm toàn bộ CSS trang đang dùng, gom vào một file CSS chung theo mã băm nội dung. */
async function luuDom(page: Page, ten: string) {
    const { html, css } = await page.evaluate(() => {
        const css = [...document.styleSheets]
            .map((s) => {
                try {
                    return [...s.cssRules].map((r) => r.cssText).join('\n');
                } catch {
                    return '';
                }
            })
            .join('\n');
        const goc = document.documentElement.cloneNode(true) as HTMLElement;
        goc.querySelectorAll('script, style, link[rel="stylesheet"], link[rel="modulepreload"], link[rel="preload"]').forEach((e) => e.remove());
        return { html: goc.outerHTML, css };
    });
    const ma = createHash('sha256').update(css).digest('hex').slice(0, 12);
    mkdirSync('ket-qua/dom', { recursive: true });
    try {
        writeFileSync(`ket-qua/dom/css-${ma}.css`, css, { flag: 'wx' });
    } catch {
        // Tiến trình khác đã ghi cùng file CSS
    }
    writeFileSync(`ket-qua/dom/${ten}.html`, `<!DOCTYPE html>\n${html.replace('</head>', `<link rel="stylesheet" href="css-${ma}.css"></head>`)}\n`);
}
