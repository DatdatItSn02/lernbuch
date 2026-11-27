import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

// Trang nội dung trên API thật với nội dung mẫu (NoiDungThuSeeder): thẻ từ, bấm chữ để tra, sao và ghi chú lưu theo
// tài khoản, tra từ (mục tự kiểm 16), Cấp độ đưa bộ lọc sang trang chủ đề. Hai cỡ màn hình chạy song song trên cùng tài khoản
// nên mỗi cỡ dùng một thẻ riêng khi ghi dữ liệu.

const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function axe(page: Page) {
    const { violations } = await new AxeBuilder({ page }).withTags(WCAG).analyze();
    return violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`);
}

function ghiLoi(page: Page) {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(String(e)));
    page.on('console', (m) => {
        if (m.type() === 'error') errors.push(m.text());
    });
    return errors;
}

/** Mở thẻ từ qua trang chủ đề (id thẻ tự tăng, không cố định). */
async function moThe(page: Page, chuDe: string, front: string, theme: 'light' | 'dark' = 'light') {
    await page.addInitScript((t) => {
        window.localStorage.setItem('lernbuch.caidat.v1', JSON.stringify({ theme: t }));
        window.localStorage.setItem('lernbuch.caidat.cho', JSON.stringify({ theme: t }));
    }, theme);
    await page.goto(`/app/chu-de/${chuDe}`);
    await page.getByRole('link', { name: front, exact: true }).click();
    await expect(page).toHaveURL(/\/app\/tu\/\d+$/);
    await expect(page.locator('h1')).toHaveText(front);
}

for (const theme of ['light', 'dark'] as const) {
    test(`thẻ từ danh từ và động từ (${theme === 'light' ? 'sáng' : 'tối'}): không tràn ngang, không lỗi, đạt WCAG 2.2 AA ở mọi tab`, async ({ page }) => {
        const errors = ghiLoi(page);
        for (const front of ['das Brötchen', 'backen']) {
            await moThe(page, 'banh-mi-tu-vung', front, theme);
            await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
            for (const tab of ['Nghĩa và ví dụ', 'Ngữ pháp', 'Ghi chú']) {
                await page.getByRole('tab', { name: tab }).click();
                expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth), `tràn ngang ở tab ${tab}`).toBeLessThanOrEqual(0);
                expect(await axe(page)).toEqual([]);
            }
        }
        expect(errors).toEqual([]);
    });
}

test('thẻ từ: Tab tới dải tab, mũi tên đổi tab; bảng chia động từ', async ({ page }) => {
    await moThe(page, 'banh-mi-tu-vung', 'backen');
    await page.getByRole('tab', { name: 'Nghĩa và ví dụ' }).focus();
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('tab', { name: 'Ngữ pháp' })).toBeFocused();
    await expect(page.getByRole('table', { name: 'Chia thì hiện tại (Präsens)' })).toContainText('backst');
    await expect(page.getByRole('table', { name: 'Ba dạng chính' })).toContainText('hat gebacken');
});

test('bấm một chữ trong câu ví dụ thì hiện nghĩa, có link mở thẻ; đạt WCAG khi bảng tra đang mở', async ({ page }) => {
    await moThe(page, 'banh-mi-tu-vung', 'das Brot');
    // Câu ví dụ của das Brot: "Das Brot ist noch frisch."
    const cau = page.getByRole('group', { name: 'Bấm một chữ trong câu để tra nghĩa.' }).first();
    await cau.getByRole('button', { name: 'Brot', exact: true }).click();
    const bang = page.getByRole('region', { name: 'Nghĩa của “Brot”' });
    await expect(bang.getByRole('link', { name: 'das Brot' })).toBeVisible();
    await expect(bang).toContainText('bánh mì');
    expect(await axe(page)).toEqual([]);
    // Bàn phím: mũi tên phải sang chữ kế, Enter để tra
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('Enter');
    await expect(page.getByRole('region', { name: 'Nghĩa của “ist”' })).toContainText('Chưa có “ist” trong từ điển.');
});

test('sao và ghi chú lưu theo tài khoản: tải lại trang vẫn còn, Sổ tay có thẻ; bỏ sao, xoá ghi chú thì hết', async ({ page }, testInfo) => {
    const front = testInfo.project.name === 'dien-thoai' ? 'das Mehl' : 'das Brot';
    await moThe(page, 'banh-mi-tu-vung', front);
    const sao = page.getByRole('button', { name: 'Đánh dấu sao' });
    // Lần chạy trước trên cùng database có thể đã để lại sao: đưa về chưa có sao
    if ((await sao.getAttribute('aria-pressed')) === 'true') {
        await Promise.all([page.waitForResponse((r) => r.url().includes('/so-tay') && r.request().method() === 'PUT'), sao.click()]);
    }
    const [phanHoi] = await Promise.all([page.waitForResponse((r) => r.url().includes('/so-tay') && r.request().method() === 'PUT'), sao.click()]);
    expect(phanHoi.status()).toBe(200);
    await expect(sao).toHaveAttribute('aria-pressed', 'true');

    await page.getByRole('tab', { name: 'Ghi chú' }).click();
    const o = page.getByRole('textbox', { name: 'Ghi chú riêng cho từ này' });
    await o.fill(`ghi chú thử ${testInfo.project.name}`);
    await expect(page.getByText('Đã lưu.')).toBeVisible();

    await page.reload();
    await expect(page.getByRole('button', { name: 'Đánh dấu sao' })).toHaveAttribute('aria-pressed', 'true');
    await page.getByRole('tab', { name: 'Ghi chú' }).click();
    await expect(page.getByRole('textbox', { name: 'Ghi chú riêng cho từ này' })).toHaveValue(`ghi chú thử ${testInfo.project.name}`);

    await page.goto('/app/so-tay');
    const danhSach = page.getByRole('region', { name: 'Thẻ trong sổ tay' });
    await expect(danhSach.getByRole('link', { name: front })).toBeVisible();
    await expect(danhSach).toContainText(`ghi chú thử ${testInfo.project.name}`);
    expect(await axe(page)).toEqual([]);

    // Dọn: xoá ghi chú, bỏ sao
    await danhSach.getByRole('link', { name: front }).click();
    await page.getByRole('tab', { name: 'Ghi chú' }).click();
    await page.getByRole('textbox', { name: 'Ghi chú riêng cho từ này' }).fill('');
    await expect(page.getByText('Đã lưu.')).toBeVisible();
    await Promise.all([page.waitForResponse((r) => r.url().includes('/so-tay') && r.request().method() === 'PUT'), page.getByRole('button', { name: 'Đánh dấu sao' }).click()]);
    await page.goto('/app/so-tay');
    await expect(page.getByRole('region', { name: 'Thẻ trong sổ tay' }).getByRole('link', { name: front })).toHaveCount(0);
});

test('tra từ: "zahlen" và "zählen" ra đúng thẻ đứng đầu; gõ không dấu tiếng Việt, tiếng Đức (mục tự kiểm 16)', async ({ page }) => {
    const errors = ghiLoi(page);
    await page.goto('/app/tra-tu');
    const o = page.getByRole('searchbox', { name: 'Tra từ tiếng Đức, Việt, Anh hoặc Trung' });
    const dau = page.getByRole('region', { name: 'Tra từ' }).getByRole('listitem').first().getByRole('link');
    // Mỗi lần tra mong một thẻ đầu khác lần trước, nên chờ chữ đổi là chờ đúng kết quả mới
    const tra = async (chu: string, mong: string) => {
        await o.fill(chu);
        await expect(dau).toHaveText(mong);
    };
    await tra('zahlen', 'zahlen');
    await tra('zählen', 'zählen');
    await tra('banh mi', 'das Brot');
    await tra('brotchen', 'das Brötchen');
    await tra('面包', 'das Brot');
    // Mở thẻ từ kết quả thì vào lịch sử tra
    await dau.click();
    await expect(page.locator('h1')).toHaveText('das Brot');
    await page.goto('/app/tra-tu');
    await expect(page.getByRole('region', { name: 'Tra gần đây' }).getByRole('link', { name: /^das Brot/ }).first()).toBeVisible();
    expect(errors).toEqual([]);
});

test('Cấp độ: chọn cấp thì chỉ còn chủ đề có thẻ khớp, link sang trang chủ đề giữ bộ lọc', async ({ page }) => {
    await page.goto('/app/cap-do');
    await page.getByRole('group', { name: 'Lọc theo cấp' }).getByRole('button', { name: /^A2/ }).click();
    await expect(page).toHaveURL(/\/app\/cap-do\?cap=2$/);
    await expect(page.getByRole('link', { name: /Số đếm/ })).toHaveCount(0);
    await page.getByRole('link', { name: /Bánh mì · bánh ngọt/ }).click();
    await expect(page).toHaveURL(/\/app\/chu-de\/banh-mi-tu-vung\?cap=2$/);
    await expect(page.getByRole('group', { name: 'Cấp của thẻ' }).getByRole('button', { name: /^A2/ })).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('tbody tr')).toHaveCount(2);
});

test('điều hướng: Chủ đề sáng cả khi đang ở Cấp độ, Giao tiếp, thẻ từ; có Ngữ pháp, Bài tập; Sổ tay trên thanh trên', async ({ page }, testInfo) => {
    await page.goto('/app/giao-tiep');
    await expect(page.locator('h1')).toHaveText('Giao tiếp');
    // Máy tính có thanh trên, điện thoại có thanh dưới: lấy thanh đang hiện
    const nav = page.locator('nav[aria-label="Điều hướng chính"]:visible');
    await expect(nav.getByRole('link', { name: 'Chủ đề' })).toHaveAttribute('aria-current', 'page');
    await expect(nav.getByRole('link', { name: 'Ngữ pháp' })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Bài tập' })).toBeVisible();
    // Máy tính: Sổ tay là một mục của thanh trên; điện thoại: nút hình sao trên thanh trên
    await page.getByRole('banner').getByRole('link', { name: 'Sổ tay' }).click();
    await expect(page).toHaveURL(/\/app\/so-tay$/);
    if (testInfo.project.name === 'dien-thoai') {
        // Điện thoại: Cài đặt ở nút bánh răng trên thanh trên
        await page.getByRole('link', { name: 'Cài đặt' }).click();
        await expect(page).toHaveURL(/\/app\/cai-dat$/);
    }
});
