import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { chupDu, dangNhap, emailHoc } from './phien';

// Mục tự kiểm 10 trên trình duyệt thật, hai cỡ màn hình, AI giả (AI_DRIVER=fake): chín chức năng chữ. Tài khoản 14, 15
// của mỗi cỡ màn hình dùng được AI ngay (NguoiHocThuSeeder). Mỗi màn: axe WCAG 2.2 AA sáng và tối, không tràn ngang.

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

async function kiem(page: Page, info: TestInfo, ten: string) {
    // Lên đầu trang: nút nằm dưới thanh trên (sticky) thì axe báo target-size giả
    await page.evaluate(() => window.scrollTo(0, 0));
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

test('trang Hỏi AI: Dịch, Sửa ngữ pháp, Trò chuyện, Bài học AI', async ({ page }, info) => {
    const errors = ghiLoi(page);
    const may = info.project.name;
    await dangNhap(page, emailHoc(may, 14));

    // Vào bằng mục điều hướng (máy tính: thanh trên; điện thoại: nút ở đầu trang)
    await page.goto('/app/hom-nay');
    await page.locator('a[href="/app/hoi-ai"]:visible').first().click();
    await expect(page.getByRole('heading', { level: 1, name: 'Hỏi AI' })).toBeVisible();

    // Dịch; dán 10.000 ký tự thì nút tắt
    const o = page.getByLabel('Câu cần dịch');
    await o.fill('x'.repeat(10000));
    await expect(page.getByText('Dài quá: tối đa 500 ký tự.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Dịch', exact: true })).toBeDisabled();
    await o.fill(`Tôi muốn đặt bàn (${may}).`);
    await page.getByRole('button', { name: 'Dịch', exact: true }).click();
    await expect(page.getByText(`Bản dịch (AI giả): Tôi muốn đặt bàn (${may}).`)).toBeVisible();
    await kiem(page, info, 'hoi-ai-dich');

    // Sửa ngữ pháp
    await page.getByRole('tab', { name: 'Sửa ngữ pháp' }).click();
    await page.getByLabel('Đoạn tiếng Đức của bạn').fill('Ich bin gestern gegangt.');
    await page.getByRole('button', { name: 'Sửa ngữ pháp', exact: true }).click();
    await expect(page.getByText('Bản đã sửa (AI giả): Ich bin gestern gegangt.')).toBeVisible();
    await kiem(page, info, 'hoi-ai-sua');

    // Trò chuyện: mở cuộc, gửi hai tin, xoá
    await page.getByRole('tab', { name: 'Trò chuyện' }).click();
    await page.getByRole('radio', { name: 'Gọi món ở nhà hàng' }).check();
    await page.getByRole('button', { name: 'Bắt đầu trò chuyện' }).click();
    await expect(page.getByText('AI chỉ đọc 10 tin gần nhất.')).toBeVisible();
    for (const cau of ['Guten Tag, ich heißen Lan.', 'Ich möchte ein Wasser.']) {
        await page.getByLabel('Tin nhắn của bạn (tiếng Đức)').fill(cau);
        const [tl] = await Promise.all([
            page.waitForResponse((r) => r.url().endsWith('/tin') && r.request().method() === 'POST'),
            page.getByRole('button', { name: 'Gửi', exact: true }).click(),
        ]);
        expect(tl.status(), await tl.text()).toBe(200);
        await expect(page.getByText(cau, { exact: true })).toBeVisible();
    }
    await expect(page.getByText('Sehr gut! Und was möchten Sie trinken?')).toHaveCount(2);
    await page.getByRole('button', { name: 'Hiện nghĩa tiếng Việt' }).first().click();
    await kiem(page, info, 'hoi-ai-tro-chuyen');
    await page.getByRole('button', { name: 'Xoá cuộc trò chuyện' }).click();
    await page.getByRole('button', { name: 'Xoá hẳn' }).click();
    await expect(page.getByText('Chưa có cuộc trò chuyện nào.')).toBeVisible();

    // Bài học AI: soạn, làm, chấm, thấy trong danh sách
    await page.getByRole('tab', { name: 'Bài học AI' }).click();
    await page.getByLabel('Chủ đề', { exact: true }).fill(`Ăn uống ${may}`);
    await page.getByLabel(/Từ muốn luyện/).fill('der Kaffee, trinken');
    await page.getByRole('button', { name: 'Soạn bài' }).click();
    await expect(page.getByRole('heading', { level: 1, name: `Bài học AI giả: Ăn uống ${may}` })).toBeVisible();
    // Lựa chọn có chữ tiếng Đức nằm trong span: bấm vào chữ (span che ô radio ẩn)
    await page.getByText('Kaffee', { exact: true }).click();
    await expect(page.getByRole('radio', { name: 'Kaffee' })).toBeChecked();
    await page.getByLabel(/Ich ____ Kaffee/).fill('trinke');
    await page.getByRole('button', { name: 'Chấm bài' }).click();
    await expect(page.getByText('Bạn đúng 2/2 câu: 100 điểm.')).toBeVisible();
    await kiem(page, info, 'hoi-ai-bai-hoc');
    await page.getByRole('link', { name: /Về danh sách bài/ }).click();
    await expect(page.getByRole('link', { name: new RegExp(`Ăn uống ${may}.*100 điểm`) })).toBeVisible();

    expect(errors).toEqual([]);
});

test('AI ở các màn đang có: Tra từ, bài ngữ pháp, Kiểm đáp án, Viết câu, Giải thích từ trong phiên học', async ({ page }, info) => {
    const errors = ghiLoi(page);
    const may = info.project.name;
    await dangNhap(page, emailHoc(may, 15));

    // Tra từ AI khi từ điển không có từ
    await page.goto('/app/tra-tu?q=Fernweh');
    await page.getByRole('button', { name: 'Hỏi AI về từ này' }).click();
    await expect(page.getByText('das Fernweh')).toBeVisible();
    await kiem(page, info, 'tra-tu-ai');

    // Bài ngữ pháp: giải thích một phần, hỏi một câu
    await page.goto('/app/ngu-phap/g-A1-22');
    await page.getByRole('button', { name: /^Hỏi AI về phần/ }).first().click();
    await page.getByRole('button', { name: 'Giải thích phần này' }).click();
    await expect(page.getByText('Tóm tắt đoạn ngữ pháp (AI giả).')).toBeVisible();
    await page.getByLabel('Câu hỏi của bạn').fill(`Vì sao là das Brot? (${may})`);
    await page.getByRole('button', { name: 'Hỏi', exact: true }).click();
    await expect(page.getByText('Câu trả lời mẫu của AI giả.')).toBeVisible();
    await kiem(page, info, 'ngu-phap-ai');

    // Kiểm đáp án: bộ Viết số nhiều (toàn câu điền), trả lời khác mẫu
    await page.goto('/app/bai-tap/lam?bo=pl-e&n=20&thu-tu=1');
    const tl = page.getByRole('textbox', { name: 'Câu trả lời' });
    await tl.fill('Ein');
    await tl.press('Enter');
    await page.getByRole('button', { name: 'Hỏi AI: câu của tôi có đúng không?' }).click();
    await expect(page.getByText('Điểm của câu này không đổi.')).toBeVisible();
    await kiem(page, info, 'kiem-dap-an');

    // Viết câu
    await page.goto('/app/bai-tap');
    await page.getByRole('link', { name: /Viết câu/ }).click();
    await page.getByLabel(/^Câu của bạn có dùng/).fill(`Ich lerne heute (${may}).`);
    await page.getByRole('button', { name: 'Chấm câu' }).click();
    await expect(page.getByText('Gần đúng', { exact: true })).toBeVisible();
    await kiem(page, info, 'viet-cau');

    // Giải thích từ trong phiên luyện gõ
    await page.goto('/app/hoc?che-do=typing');
    const go = page.getByRole('textbox', { name: 'Câu trả lời' });
    await go.fill('khong-phai');
    await go.press('Enter');
    await page.getByRole('button', { name: 'Giải thích từ này' }).click();
    await expect(page.getByRole('heading', { level: 3, name: 'Nghĩa', exact: true })).toBeVisible();
    await kiem(page, info, 'phien-giai-thich');

    expect(errors).toEqual([]);
});
