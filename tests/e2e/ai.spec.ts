import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { chupDu, dangNhap, emailHoc } from './phien';

// Hỏi AI trên trình duyệt thật với AI giả (AI_DRIVER=fake): khai ngày sinh, đồng ý, Giải thích từ; người chưa đủ tuổi.
// Tài khoản 12 và 13 của mỗi cỡ màn hình nằm trong danh sách thử AI (NguoiHocThuSeeder). Mỗi bước: axe WCAG 2.2 AA ở
// giao diện sáng và tối, không tràn ngang, không lỗi trong console.

test.use({ storageState: { cookies: [], origins: [] } });

const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

function ghiLoi(page: Page) {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(String(e)));
    page.on('console', (m) => {
        if (m.type() === 'error') errors.push(m.text());
    });
    return errors;
}

/** Sáng rồi tối (đổi data-theme ngay trên trang, giữ nguyên trạng thái đang có): axe, tràn ngang; chụp ảnh khi chạy [anh]. */
async function kiem(page: Page, info: TestInfo, ten: string) {
    // Đổi giao diện ngay trên trang: tắt hiệu ứng chuyển màu, không thì axe đo màu đang chuyển dở (lỗi tương phản giả)
    await page.addStyleTag({ content: '*, *::before, *::after { transition: none !important; animation: none !important; }' });
    for (const theme of ['light', 'dark'] as const) {
        await page.evaluate((t) => (document.documentElement.dataset.theme = t), theme);
        const { violations } = await new AxeBuilder({ page }).withTags(WCAG).analyze();
        expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`), `${ten} (${theme})`).toEqual([]);
        expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth), `tràn ngang ở ${ten} (${theme})`).toBeLessThanOrEqual(0);
    }
    await page.evaluate(() => (document.documentElement.dataset.theme = 'light'));
    await chupDu(page, info, ten);
}

async function moTabAi(page: Page, front: string) {
    await page.goto('/app/chu-de/banh-mi-tu-vung');
    await page.getByRole('link', { name: front, exact: true }).click();
    await expect(page.locator('h1')).toHaveText(front);
    await page.getByRole('tab', { name: 'Hỏi AI' }).click();
}

async function chonNgaySinh(page: Page, ngay: string, thang: string, nam: string) {
    await page.getByLabel('Ngày', { exact: true }).selectOption(ngay);
    await page.getByLabel('Tháng', { exact: true }).selectOption(thang);
    await page.getByLabel('Năm', { exact: true }).selectOption(nam);
    await page.getByRole('button', { name: 'Tiếp tục' }).click();
}

test('khai ngày sinh, đồng ý, Giải thích từ; hỏi lại sau khi tải lại trang thì lấy từ cache, không trừ lượt', async ({ page }, info) => {
    const errors = ghiLoi(page);
    // Mỗi cỡ màn hình một từ, để hai cỡ chạy song song không lấy cache của nhau ở lần hỏi đầu
    const tu = info.project.name === 'may-tinh' ? 'das Brötchen' : 'das Brot';
    await dangNhap(page, emailHoc(info.project.name, 12));

    await moTabAi(page, tu);
    await expect(page.getByRole('heading', { name: 'Ngày sinh của bạn' })).toBeVisible();
    await kiem(page, info, 'ai-ngay-sinh');

    await chonNgaySinh(page, '15', '6', '1995');
    await expect(page.getByRole('heading', { name: 'Trước khi dùng AI' })).toBeFocused();
    await kiem(page, info, 'ai-dong-y');

    await page.getByRole('button', { name: 'Tôi đồng ý' }).click();
    await page.getByRole('button', { name: 'Giải thích từ này' }).click();
    await expect(page.getByText(`Nghĩa của ${tu} (AI giả).`)).toBeVisible();
    for (const phan of ['Nghĩa', 'Cách dùng', 'Ví dụ', 'Lỗi hay gặp']) {
        await expect(page.getByRole('heading', { level: 3, name: phan, exact: true })).toBeVisible();
    }
    const conLuot = (await page.getByText(/^Còn \d+ lượt AI hôm nay\.$/).textContent()) ?? '';
    expect(conLuot).toMatch(/^Còn \d+ lượt/);
    await kiem(page, info, 'ai-ket-qua');

    await page.reload();
    await page.getByRole('tab', { name: 'Hỏi AI' }).click();
    await expect(page.getByText(conLuot, { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Giải thích từ này' }).click();
    await expect(page.getByText(`Nghĩa của ${tu} (AI giả).`)).toBeVisible();
    await expect(page.getByText(conLuot, { exact: true })).toBeVisible();

    expect(errors).toEqual([]);
});

test('chưa đủ 18 tuổi: thấy câu giải thích; tải lại thì thẻ từ không còn tab Hỏi AI', async ({ page }, info) => {
    const errors = ghiLoi(page);
    await dangNhap(page, emailHoc(info.project.name, 13));

    await moTabAi(page, 'das Mehl');
    await chonNgaySinh(page, '1', '1', String(new Date().getFullYear() - 10));
    await expect(page.getByText('AI chỉ dành cho người từ 18 tuổi. Phần học còn lại bạn dùng bình thường.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Giải thích từ này' })).toHaveCount(0);
    await kiem(page, info, 'ai-chua-du-tuoi');

    await page.reload();
    await expect(page.locator('h1')).toHaveText('das Mehl');
    await expect(page.getByRole('tab', { name: 'Nghĩa và ví dụ' })).toBeVisible();
    await expect(page.getByRole('tab', { name: 'Hỏi AI' })).toHaveCount(0);

    expect(errors).toEqual([]);
});
