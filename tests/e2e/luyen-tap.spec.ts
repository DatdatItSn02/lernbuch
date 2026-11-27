import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

// Ngữ pháp, Bài tập, Động từ trên API thật với nội dung mẫu (NoiDungThuSeeder::taoLuyenTap): đọc bài, làm bài bằng bàn phím
// (chấm ngay và cả phiếu), Điền câu, Chia động từ; không tràn ngang, không lỗi, đạt WCAG 2.2 AA ở sáng và tối.
// Hai cỡ màn hình chạy song song trên cùng tài khoản nên mỗi cỡ làm một bộ riêng. Ảnh ghi vào test-results/anh.

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

async function mo(page: Page, duong: string, theme: 'light' | 'dark' = 'light') {
    await page.addInitScript((t) => {
        window.localStorage.setItem('lernbuch.caidat.v1', JSON.stringify({ theme: t }));
        window.localStorage.setItem('lernbuch.caidat.cho', JSON.stringify({ theme: t }));
    }, theme);
    await page.goto(duong);
    await expect(page.locator('h1').first()).toBeVisible();
    await expect(page.getByText('Đang tải…')).toHaveCount(0);
}

const tranNgang = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);

async function kiem(page: Page, ten: string, anh?: string) {
    expect(await tranNgang(page), `tràn ngang: ${ten}`).toBeLessThanOrEqual(0);
    expect(await axe(page), ten).toEqual([]);
    if (anh) {
        await page.evaluate(() => document.fonts.ready);
        await page.screenshot({ path: `test-results/anh/${anh}.png`, fullPage: true });
    }
}

for (const theme of ['light', 'dark'] as const) {
    test(`đọc ngữ pháp, xem bài tập, bảng động từ (${theme === 'light' ? 'sáng' : 'tối'}): không tràn, không lỗi, WCAG 2.2 AA`, async ({ page }, info) => {
        const errors = ghiLoi(page);
        const duoi = `${theme}-${info.project.name}`;

        await mo(page, '/app/ngu-phap', theme);
        await expect(page.getByRole('heading', { level: 1, name: 'Ngữ pháp' })).toBeVisible();
        await expect(page.getByText('1. Danh từ & mạo từ')).toBeVisible();
        await kiem(page, 'danh sách ngữ pháp', `app-ngu-phap-${duoi}`);

        await page.getByRole('link', { name: /Genus: der, die, das/ }).click();
        await expect(page).toHaveURL(/\/app\/ngu-phap\/g-A1-22$/);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Genus: der, die, das');
        await expect(page.getByRole('region', { name: 'Luyện tập bài này' }).getByRole('link')).toHaveCount(2);
        await expect(page.getByRole('table')).toBeVisible();
        await kiem(page, 'bài ngữ pháp', `app-bai-ngu-phap-${duoi}`);
        await page.getByRole('link', { name: /Bài sau/ }).click();
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Plural');

        await mo(page, '/app/bai-tap', theme);
        await expect(page.getByRole('navigation', { name: 'Cách luyện' }).getByRole('link')).not.toHaveCount(0);
        await page.getByText('1. Danh từ & mạo từ').click();
        await expect(page.getByRole('link', { name: /Giống danh từ & mạo từ/ })).toBeVisible();
        await kiem(page, 'bài tập theo cấp', `app-bai-tap-${duoi}`);
        await page.getByRole('button', { name: 'Ôn tập' }).click();
        await expect(page).toHaveURL(/tab=on-tap/);
        await expect(page.getByRole('radio', { name: 'Làm cả bộ rồi chấm' })).toBeAttached();
        await kiem(page, 'bài tập ôn tập', `app-on-tap-${duoi}`);

        await mo(page, '/app/so-nhieu', theme);
        await expect(page.getByRole('link', { name: /Thêm -e/ })).toBeVisible();
        await kiem(page, 'số nhiều', `app-so-nhieu-${duoi}`);

        await mo(page, '/app/dong-tu', theme);
        await expect(page.getByText('3 động từ')).toBeVisible();
        await page.getByRole('button', { name: /lesen/ }).click();
        await expect(page.getByRole('table', { name: 'Bảng chia của lesen' })).toBeVisible();
        await kiem(page, 'bảng động từ', `app-dong-tu-bang-${duoi}`);
        await page.getByRole('searchbox', { name: 'Tìm động từ hoặc nghĩa' }).fill('goi dien');
        await expect(page.getByText('1 động từ')).toBeVisible();
        await expect(page.getByRole('rowheader', { name: /anrufen/ })).toBeVisible();

        expect(errors).toEqual([]);
    });
}

test('làm bài chấm ngay bằng bàn phím: chọn, gõ, câu sai quay lại; tổng kết; kết quả lên máy chủ', async ({ page }, info) => {
    const errors = ghiLoi(page);
    const dienThoai = info.project.name === 'dien-thoai';
    // Mỗi cỡ màn hình một bộ để không chạm tiến độ của nhau
    await mo(page, dienThoai ? '/app/bai-tap/lam?bo=pl-e&n=20&thu-tu=1&ve=/so-nhieu' : '/app/bai-tap/lam?bo=bt-LB3&n=20&thu-tu=1');
    const o = page.getByRole('textbox', { name: 'Câu trả lời' });
    const bao = page.locator('main [role="status"]');

    if (dienThoai) {
        for (const [i, dapAn] of ['Tische', 'hunde', 'Brote'].entries()) {
            await expect(page.getByText(`${i}/3`)).toBeVisible();
            await o.fill(dapAn);
            await o.press('Enter');
            await expect(bao).toContainText(i === 1 ? 'Đúng chữ' : 'Đúng');
            if (i === 0) await kiem(page, 'câu đã trả lời', 'app-lam-bai-dien-thoai');
            await page.keyboard.press('Enter');
        }
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Đúng ngay lần đầu 2/3 câu.');
    } else {
        // Câu 1, 2: trắc nghiệm (phím số); câu 3: phân biệt hoa thường; câu 4: sai, quay lại cuối lượt rồi đúng
        const chon = async (dapAn: string) => {
            const nut = page.getByRole('group', { name: 'Các đáp án' }).getByRole('button');
            const chu = await nut.allTextContents();
            await page.keyboard.press(String(chu.findIndex((c) => c.includes(dapAn)) + 1));
        };
        await chon('Der');
        await expect(bao).toContainText('Đúng');
        await kiem(page, 'câu đã trả lời', 'app-lam-bai-may-tinh');
        await page.keyboard.press('Enter');
        await chon('Das');
        await expect(bao).toContainText('Chưa đúng');
        await page.keyboard.press(' ');
        await o.fill('Das');
        await o.press('Enter');
        await expect(bao).toContainText('Đúng');
        await page.keyboard.press('Enter');
        await o.fill('der');
        await o.press('Enter');
        await expect(bao).toContainText('Chưa đúng');
        await page.keyboard.press('Enter');
        // Câu 2 sai lần đầu quay lại để luyện
        await expect(page.getByText('Ôn lại')).toBeVisible();
        await chon('Die');
        await page.keyboard.press('Enter');
        await expect(page.getByText('Ôn lại')).toBeVisible();
        await o.fill('die');
        await o.press('Enter');
        await page.keyboard.press('Enter');
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Đúng ngay lần đầu 2/4 câu.');
    }

    await expect(page.getByText(/^\+\d+ xu$/)).toBeVisible();
    await expect(page.getByRole('region', { name: 'Cần ôn lại' }).getByRole('listitem')).toHaveCount(dienThoai ? 1 : 2);
    await kiem(page, 'tổng kết', `app-tong-ket-bai-${info.project.name}`);

    // Máy chủ đã ghi: bộ vừa làm có câu đang sai
    await mo(page, dienThoai ? '/app/so-nhieu' : '/app/bai-tap');
    if (dienThoai) await expect(page.getByRole('link', { name: /Thêm -e/ })).toContainText('0/3 từ');
    else {
        await page.getByText('1. Danh từ & mạo từ').click();
        await expect(page.getByRole('link', { name: /Giống danh từ & mạo từ/ })).toContainText('2 sai');
    }
    expect(errors).toEqual([]);
});

test('làm cả phiếu rồi chấm: Enter sang câu sau, Ctrl+Enter chấm, ghi nhớ', async ({ page }, info) => {
    const errors = ghiLoi(page);
    const dienThoai = info.project.name === 'dien-thoai';
    await mo(page, dienThoai ? '/app/bai-tap/lam?bo=dx-v1-1A&n=2&thu-tu=1&cham=phieu' : '/app/bai-tap/lam?bo=dx-g1-1A&n=3&thu-tu=1&cham=phieu');
    await expect(page.getByText(dienThoai ? 'Đã làm 0 / 2' : 'Đã làm 0 / 3')).toBeVisible();
    await kiem(page, 'phiếu', `app-phieu-${info.project.name}`);

    if (dienThoai) {
        await page.getByRole('textbox', { name: 'Câu 1' }).fill('Familie');
        await page.getByRole('textbox', { name: 'Câu 1' }).press('Enter');
        await expect(page.getByRole('textbox', { name: 'Câu 2' })).toBeFocused();
        await page.getByRole('textbox', { name: 'Câu 2' }).fill('Onkel');
    } else {
        await page.getByRole('textbox', { name: 'Câu 1' }).fill('der');
        await page.getByRole('textbox', { name: 'Câu 1' }).press('Enter');
        await expect(page.getByRole('textbox', { name: 'Câu 2' })).toBeFocused();
        await page.getByRole('textbox', { name: 'Câu 2' }).fill('Die Frau heißt Anna.');
        // Câu 3 (sắp xếp) để trống: chấm thì hỏi lại
    }
    await page.keyboard.press('Control+Enter');
    if (!dienThoai) {
        await expect(page.getByRole('alert')).toHaveText('Còn 1 câu chưa làm. Vẫn chấm?');
        await page.getByRole('button', { name: 'Vẫn chấm' }).click();
    }
    await expect(page.getByText(dienThoai ? 'Đúng 1/2 câu.' : 'Đúng 2/3 câu.', { exact: true })).toBeVisible();
    await expect(page.getByText(/^\+\d+ xu$/)).toBeVisible();
    if (!dienThoai) await expect(page.getByText('Mạo từ xác định ở Nominativ: der, die, das.')).toBeVisible();
    await kiem(page, 'phiếu đã chấm', `app-phieu-da-cham-${info.project.name}`);
    expect(errors).toEqual([]);
});

test('Chia động từ và Điền câu: xem đáp án, câu quay lại, tổng kết', async ({ page }, info) => {
    const errors = ghiLoi(page);
    const o = page.getByRole('textbox', { name: 'Câu trả lời' });

    for (const [duong, so] of [
        ['/app/bai-tap/chia-dong-tu', 3],
        ['/app/bai-tap/dien-cau', 3],
    ] as const) {
        await mo(page, duong);
        // Lần đầu xem đáp án (sai), câu quay lại sau cùng thì gõ đúng đáp án đã thấy
        const dapAn: string[] = [];
        for (let i = 0; i < so; i++) {
            await page.getByRole('button', { name: 'Xem đáp án' }).click();
            dapAn.push(((await page.getByText(/^Đáp án:/).locator('..').textContent()) ?? '').replace(/^Đáp án:\s*/, '').trim());
            if (i === 0) await kiem(page, `${duong}: đã xem đáp án`, `app-${duong.split('/').pop()}-${info.project.name}`);
            await page.keyboard.press('Enter');
        }
        for (let i = 0; i < so; i++) {
            await expect(page.getByText('Ôn lại')).toBeVisible();
            const de = (await page.locator('main p[lang="de"]').first().textContent()) ?? '';
            // Câu quay lại theo đúng thứ tự đã sai
            await o.fill(dapAn[i]);
            await o.press('Enter');
            await expect(page.locator('main [role="status"]'), de).toContainText('Đúng');
            await page.keyboard.press('Enter');
        }
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Đúng ngay lần đầu 0/${so} câu.`);
    }
    expect(errors).toEqual([]);
});
