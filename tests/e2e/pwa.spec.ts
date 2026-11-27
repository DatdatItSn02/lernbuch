import AxeBuilder from '@axe-core/playwright';
import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { dangNhap, emailHoc } from './phien';

// PWA (mục 15 phần tự kiểm G1), cỡ máy tính và điện thoại:
// - Đủ điều kiện cài: manifest (tên, trang mở đầu, mở như app, biểu tượng 192 và 512 tải được), service worker điều khiển trang;
//   Chromium tự kiểm tiêu chí cài (Page.getInstallabilityErrors) không còn lỗi nào ngoài lỗi do trình duyệt test (ẩn danh, không giao diện).
// - Tải một chủ đề về máy, rồi mất mạng: tải lại trang vẫn mở được app (khung và dữ liệu đã lưu), có dòng báo không có mạng.
// - Mất mạng vẫn học chủ đề đã tải (phiên lập trên máy); có mạng lại thì lượt chấm tự gửi, máy chủ nhận đủ.
// Màn mới (ô tải chủ đề, dòng báo mất mạng, phiên học khi mất mạng, phần cài app) đạt WCAG 2.2 AA ở giao diện sáng và tối.
// Tài khoản học riêng số 11 theo cỡ màn hình (tests/e2e/phien.ts), nên không lẫn tiến độ với test khác.

test.describe.configure({ timeout: 120_000 });

const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const BAO_MAT_MANG = 'Không có mạng: vẫn học được chủ đề đã tải về máy; lượt chấm sẽ tự gửi khi có mạng.';
const DANG_OFFLINE = 'Đang học khi không có mạng: lượt chấm nằm trên máy, có mạng sẽ tự gửi.';
/** Lỗi tiêu chí cài chỉ có ở trình duyệt test (hồ sơ ẩn danh, chạy không giao diện), không phải lỗi của app. */
const LOI_DO_TRINH_DUYET_TEST = ['in-incognito', 'platform-not-supported-on-android', 'already-installed'];

async function datGiaoDien(page: Page, theme: 'light' | 'dark') {
    await page.addInitScript((t) => {
        window.localStorage.setItem('lernbuch.caidat.v1', JSON.stringify({ theme: t }));
        window.localStorage.setItem('lernbuch.caidat.cho', JSON.stringify({ theme: t }));
    }, theme);
}

async function wcag(page: Page, ten: string) {
    const { violations } = await new AxeBuilder({ page }).withTags(WCAG).analyze();
    expect(
        violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`),
        ten,
    ).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth), `tràn ngang: ${ten}`).toBeLessThanOrEqual(0);
}

/** Mất mạng thật: trang thấy offline (navigator.onLine, sự kiện offline), mọi yêu cầu ra mạng (cả của service worker) đều hỏng. */
async function matMang(context: BrowserContext, mat: boolean) {
    if (mat) await context.route('**/*', (r) => r.abort('internetdisconnected'));
    else await context.unroute('**/*');
    await context.setOffline(mat);
}

const homNay = (page: Page) =>
    page.evaluate(async () => {
        const r = await fetch('/api/v1/hom-nay', { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
        return ((await r.json()) as { data: { reviewed_today: number } }).data;
    });

async function taiChuDe(page: Page) {
    await page.goto('/app/chu-de/so-dem-tu-vung');
    await page.getByRole('button', { name: 'Tải để học khi không có mạng' }).click();
    await expect(page.getByText(/^Đã tải về máy 25 từ lúc/)).toBeVisible({ timeout: 30_000 });
}

test.describe('cài app và học khi mất mạng', () => {
    test.use({ storageState: { cookies: [], origins: [] }, serviceWorkers: 'allow' });

    test('đủ điều kiện cài; mất mạng vẫn mở app và học tiếp chủ đề đã tải; có mạng lại thì gửi lượt chấm', async ({ page, context }, info) => {
        await datGiaoDien(page, 'light');
        await dangNhap(page, emailHoc(info.project.name, 11));

        // Manifest và biểu tượng
        await expect(page.locator('link[rel="manifest"]')).toHaveAttribute('href', '/manifest.webmanifest');
        const manifest = (await (await page.request.get('/manifest.webmanifest')).json()) as { name: string; start_url: string; display: string; icons: { src: string }[] };
        expect(manifest).toMatchObject({ name: 'Lernbuch', start_url: '/app', display: 'standalone' });
        for (const icon of manifest.icons) expect((await page.request.get(icon.src)).ok(), icon.src).toBe(true);

        // Service worker cài xong, điều khiển trang, đã lưu khung app (để mở lại khi mất mạng)
        await page.evaluate(() => navigator.serviceWorker.ready.then(() => true));
        await expect.poll(() => page.evaluate(() => !!navigator.serviceWorker.controller), { timeout: 30_000 }).toBe(true);
        await expect
            .poll(() => page.evaluate(async () => (await caches.keys()).some((k) => k.startsWith('lernbuch-khung-')) && !!(await caches.match('/app'))), { timeout: 30_000 })
            .toBe(true);

        // Tiêu chí cài của Chromium
        const cdp = await context.newCDPSession(page);
        const { installabilityErrors } = (await cdp.send('Page.getInstallabilityErrors')) as { installabilityErrors: { errorId: string }[] };
        expect(installabilityErrors.map((e) => e.errorId).filter((id) => !LOI_DO_TRINH_DUYET_TEST.includes(id))).toEqual([]);

        // Tải chủ đề Số đếm về máy (ô tải đạt WCAG)
        await taiChuDe(page);
        await wcag(page, 'trang chủ đề đã tải về máy');

        // Mất mạng: tải lại trang vẫn mở được (service worker trả khung và dữ liệu đã lưu), có dòng báo không có mạng
        await matMang(context, true);
        expect((await page.reload())?.fromServiceWorker()).toBe(true);
        await expect(page.getByRole('heading', { level: 1, name: 'Số đếm' })).toBeVisible();
        // Mất mạng thật cả với service worker: API trả bản đã lưu (có đầu báo), API không lưu thì hỏng
        const thu = await page.evaluate(async () => {
            const lay = (u: string) => fetch(u, { headers: { Accept: 'application/json' } }).then((r) => r.headers.get('X-Lernbuch-Ban-Luu') ?? String(r.status), () => 'hong');
            return { homNay: await lay('/api/v1/hom-nay'), conLai: await lay('/api/v1/hoc/con-lai') };
        });
        expect(thu).toEqual({ homNay: '1', conLai: 'hong' });
        await expect(page.getByText(BAO_MAT_MANG)).toBeVisible();
        await wcag(page, 'trang chủ đề khi mất mạng');
        await page.screenshot({ path: `test-results/anh/pwa-mat-mang-chu-de-${info.project.name}.png` });

        // Học chủ đề đã tải khi mất mạng: phiên lập trên máy, chấm 3 thẻ, lượt chấm nằm chờ
        await page.goto('/app/hoc?chu-de=so-dem-tu-vung');
        await expect(page.getByText(DANG_OFFLINE)).toBeVisible();
        await expect(page.getByText(/^1\/\d+$/)).toBeVisible();
        await wcag(page, 'phiên học khi mất mạng');
        for (let i = 1; i <= 3; i++) {
            await page.keyboard.press(' ');
            await expect(page.getByRole('group', { name: 'Tự chấm' })).toBeVisible();
            await page.keyboard.press('3');
            await expect(page.getByText(new RegExp(`^${i + 1}/\\d+$`))).toBeVisible();
        }
        await expect(page.getByText('3 lượt chờ gửi')).toBeVisible({ timeout: 15_000 });
        await page.screenshot({ path: `test-results/anh/pwa-hoc-mat-mang-${info.project.name}.png` });

        // Có mạng lại: lượt chấm tự gửi, máy chủ nhận đủ 3 lượt; dòng báo mất mạng không còn
        await matMang(context, false);
        await expect.poll(async () => (await homNay(page)).reviewed_today, { timeout: 30_000 }).toBe(3);
        await expect(page.getByText('3 lượt chờ gửi')).toHaveCount(0);
    });
});

test.describe('màn mới của PWA ở giao diện tối', () => {
    test('ô tải chủ đề, dòng báo mất mạng, phiên học từ chủ đề đã tải, phần cài app: đạt WCAG 2.2 AA', async ({ page, context }, info) => {
        await datGiaoDien(page, 'dark');
        await taiChuDe(page);
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await wcag(page, 'trang chủ đề đã tải (tối)');

        // Không gọi được máy chủ lúc mở phiên: lập phiên từ chủ đề đã tải
        await page.route('**/api/v1/hoc/phien', (r) => r.abort('internetdisconnected'));
        await page.goto('/app/hoc?chu-de=so-dem-tu-vung');
        await expect(page.getByText(DANG_OFFLINE)).toBeVisible();
        await wcag(page, 'phiên học khi mất mạng (tối)');
        await page.unroute('**/api/v1/hoc/phien');

        await page.goto('/app/cai-dat');
        await expect(page.getByRole('heading', { name: 'Cài app lên máy' })).toBeVisible();
        await context.setOffline(true);
        await expect(page.getByText(BAO_MAT_MANG)).toBeVisible();
        await wcag(page, 'cài đặt, phần cài app, khi mất mạng (tối)');
        await page.screenshot({ path: `test-results/anh/pwa-cai-dat-toi-${info.project.name}.png`, fullPage: true });
        await context.setOffline(false);
    });
});
