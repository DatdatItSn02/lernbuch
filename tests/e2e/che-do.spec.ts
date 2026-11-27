import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { dangNhap, emailHoc } from './phien';

// 7 chế độ học trên trình duyệt thật với nội dung mẫu (NoiDungThuSeeder), cỡ máy tính và điện thoại, đủ phím (mục 13 phần tự kiểm G1):
// - Flashcard: Space lật, 1–4 chấm, P, S, E nghe, ← → xem lại thẻ đã chấm, Esc thoát.
// - Quiz: 1–4 chọn, Enter hoặc Space đi tiếp, P, S nghe.
// - Listening: tự đọc khi câu hiện; đang gõ thì Alt+P, Alt+S nghe lại, P không có Alt là chữ p.
// - Typing: chưa trả lời thì không nghe được (khỏi lộ đáp án); nút chữ ä; thiếu mạo từ, gợi ý chữ đầu là Khó.
// - Ghép cặp: Enter chọn ô, P, S nghe từ vừa chọn; nối sai thì thẻ đó chỉ được Khó.
// - Hỗn hợp: thẻ mới có câu ví dụ thành Đặt câu, thẻ khác thành Quiz.
// - Đặt câu: P, S nghe câu, Backspace bỏ chữ cuối, Enter kiểm tra và đi tiếp.
// Chế độ nào cũng kiểm: câu sai quay lại (Ôn lại), bảng tổng kết, máy chủ nhận đúng số lượt và đúng mức, không tràn ngang, WCAG 2.2 AA.
// Máy CI không có giọng tiếng Đức: giọng đọc của trình duyệt được thay bằng giọng giả ghi lại chữ và tốc độ đã đọc.
// Mỗi chế độ một tài khoản riêng theo cỡ màn hình (tài khoản 4–10; hoc.spec.ts dùng 1–3), nên chạy song song không lẫn tiến độ.
// Ảnh màn hình ghi vào test-results/anh.

test.use({ storageState: { cookies: [], origins: [] } });
test.describe.configure({ timeout: 90_000 });

const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
/** Tốc độ giọng trình duyệt: nghe thường và nghe chậm (resources/js/app/lib/speech.ts). */
const THUONG = 0.95;
const CHAM = 0.6;

type The = { id: number; front: string; meanings: { vi?: string }; example: { sentence: string } | null; box: number };
type Phien = { id: number; mode: string; cards: The[] };
type CauDaDoc = { text: string; rate: number };

function ghiLoi(page: Page) {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(String(e)));
    page.on('console', (m) => {
        if (m.type() === 'error') errors.push(m.text());
    });
    return errors;
}

/** Giọng đọc giả: có một giọng de-DE; câu nào được đọc thì ghi chữ và tốc độ vào window.__daDoc, rồi báo đọc xong ngay. */
async function giaGiongDoc(page: Page) {
    await page.addInitScript(() => {
        const daDoc: { text: string; rate: number }[] = [];
        type Cau = { text: string; lang: string; voice: unknown; rate: number; onend: (() => void) | null; onerror: (() => void) | null };
        // Hàm dựng thường, không dùng class: hàm này được chép nguyên văn vào trang nên không được cần hàm phụ nào bên ngoài
        function CauDoc(this: Cau, text: string) {
            this.text = text;
            this.lang = '';
            this.voice = null;
            this.rate = 1;
            this.onend = null;
            this.onerror = null;
        }
        const giong = [{ lang: 'de-DE', name: 'Giọng thử', voiceURI: 'giong-thu', default: true, localService: true }];
        Object.defineProperty(window, 'SpeechSynthesisUtterance', { value: CauDoc, configurable: true, writable: true });
        Object.defineProperty(window, 'speechSynthesis', {
            configurable: true,
            value: {
                getVoices: () => giong,
                speak: (u: Cau) => {
                    daDoc.push({ text: u.text, rate: u.rate });
                    setTimeout(() => {
                        if (u.onend) u.onend();
                    }, 0);
                },
                cancel: () => {},
                addEventListener: () => {},
                removeEventListener: () => {},
            },
        });
        Object.defineProperty(window, '__daDoc', { value: daDoc });
    });
}

const daDoc = (page: Page) => page.evaluate(() => (window as unknown as { __daDoc: { text: string; rate: number }[] }).__daDoc.slice());

/** Mở phiên học; trả về phiên máy chủ lập (thẻ theo đúng thứ tự sẽ hỏi). */
async function moPhien(page: Page, duong: string): Promise<Phien> {
    const tra = page.waitForResponse((r) => r.url().endsWith('/api/v1/hoc/phien') && r.request().method() === 'POST');
    await page.goto(duong);
    const phien = ((await (await tra).json()) as { data: Phien }).data;
    await expect(page.getByText(`1/${phien.cards.length}`, { exact: true })).toBeVisible();
    return phien;
}

/** Số liệu Hôm nay đọc thẳng từ máy chủ (fetch trong trang, cùng cookie phiên). */
const homNay = (page: Page) =>
    page.evaluate(async () => {
        const r = await fetch('/api/v1/hom-nay', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        return ((await r.json()) as { data: { reviewed_today: number; levels: number[] } }).data;
    });

/** Máy chủ đã nhận đủ lượt chấm của phiên (lượt ôn lại trong phiên không tính) và thẻ ở đúng mức 1–5. */
async function mayChuDaNhan(page: Page, soLuot: number, theoMuc: number[]) {
    await expect.poll(async () => (await homNay(page)).reviewed_today, { timeout: 15_000 }).toBe(soLuot);
    expect((await homNay(page)).levels.slice(1)).toEqual(theoMuc);
}

const tranNgang = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);

/** Không tràn ngang, đạt WCAG 2.2 AA; có tên ảnh thì chụp khung đang thấy. */
async function kiem(page: Page, ten: string, anh: string) {
    expect(await tranNgang(page), `tràn ngang: ${ten}`).toBeLessThanOrEqual(0);
    const { violations } = await new AxeBuilder({ page }).withTags(WCAG).analyze();
    expect(
        violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`),
        ten,
    ).toEqual([]);
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: `test-results/anh/${anh}.png` });
}

/** Dòng báo đúng sai của câu đang hỏi (câu mới chưa có). */
const bao = (page: Page) => page.locator('main [role="status"]');
/** Từ tiếng Đức của câu Quiz (đã hiện sẵn). */
const tuQuiz = (page: Page) => page.locator('main article [lang="de"]').first();
/** Nghĩa chính (tiếng Việt) của câu Typing. */
const nghiaHoi = (page: Page) => page.locator('main article p[lang="vi"]').first();
const onLai = (page: Page) => page.getByText('Ôn lại', { exact: true });

/** Quiz: bấm phím số của đáp án đúng bằng nghĩa cho trước (dung = false: của một đáp án khác). */
async function chonBangPhim(page: Page, nghia: string, dung = true) {
    const nut = page.getByRole('group', { name: 'Các đáp án' }).getByRole('button');
    await expect(nut.first()).toBeEnabled();
    // Chữ của nút: phím số rồi tới đáp án
    const chu = (await nut.allTextContents()).map((c) => c.trim().replace(/^[1-4]/, '').trim());
    const i = dung ? chu.indexOf(nghia) : chu.findIndex((c) => c !== nghia);
    expect(i, `các đáp án: ${chu.join(' | ')}`).toBeGreaterThanOrEqual(0);
    await page.keyboard.press(String(i + 1));
}

/** Đặt câu: bấm các chữ theo thứ tự cho trước. */
async function xepChu(page: Page, chu: string[]) {
    for (const c of chu) await page.getByRole('button', { name: `Thêm “${c}” vào câu`, exact: true }).click();
}

const chuCua = (the: The) => (the.example?.sentence ?? '').split(' ');

/** Ghép cặp: chọn từ rồi chọn nghĩa của nó. Vừa nối sai thì hai ô rung nửa giây, chưa bấm được: bấm lại tới khi chọn được. */
async function noi(page: Page, the: The) {
    const tu = page.getByRole('group', { name: 'Từ tiếng Đức' }).getByRole('button', { name: the.front, exact: true });
    await expect(async () => {
        if ((await tu.getAttribute('aria-pressed')) !== 'true') await tu.click();
        await expect(tu).toHaveAttribute('aria-pressed', 'true', { timeout: 300 });
    }).toPass();
    await page.getByRole('group', { name: 'Nghĩa' }).getByRole('button', { name: the.meanings.vi, exact: true }).click();
    await expect(tu).toBeDisabled();
}

/** Flashcard: Space lật, chờ hàng nút chấm hiện rồi bấm phím chấm. */
async function latVaCham(page: Page, phim: '1' | '2' | '3' | '4') {
    await page.keyboard.press('Space');
    await expect(page.getByRole('group', { name: 'Tự chấm' })).toBeVisible();
    await page.keyboard.press(phim);
}

async function xongPhien(page: Page, tongKet: string, theoCach: string) {
    await expect(page.getByRole('heading', { name: 'Xong phiên' })).toBeVisible();
    await expect(page.getByText(tongKet, { exact: true })).toBeVisible();
    await expect(page.getByText(theoCach, { exact: true })).toBeVisible();
}

test('Flashcard: Space lật, 1–4 chấm, P, S, E nghe, ← → xem lại, Esc thoát', async ({ page }, info) => {
    const errors = ghiLoi(page);
    await giaGiongDoc(page);
    await dangNhap(page, emailHoc(info.project.name, 4));
    const phien = await moPhien(page, '/app/hoc?chu-de=banh-mi-tu-vung');
    expect(phien.cards.map((c) => c.front)).toEqual(['das Brötchen', 'das Brot', 'das Mehl', 'backen', 'süß']);
    const tu = page.getByRole('heading', { level: 1 });
    const trangThai = page.getByRole('status').first();
    await expect(tu).toHaveText('das Brötchen');

    // P từ, S từ chậm, E câu ví dụ (được cả trước khi lật)
    await page.keyboard.press('p');
    await page.keyboard.press('s');
    await page.keyboard.press('e');
    await expect
        .poll(() => daDoc(page))
        .toEqual([
            { text: 'das Brötchen', rate: THUONG },
            { text: 'das Brötchen', rate: CHAM },
            { text: 'Ich möchte zwei Brötchen, bitte.', rate: THUONG },
        ]);

    // Space lật; 1 Quên: thẻ quay lại sau vài thẻ
    await page.keyboard.press('Space');
    await expect(page.getByRole('group', { name: 'Tự chấm' })).toBeVisible();
    await expect(page.getByText('Cho tôi hai cái bánh mì nhỏ.')).toBeVisible();
    await kiem(page, 'Flashcard đã lật', `che-do-flashcard-${info.project.name}`);
    await page.keyboard.press('1');
    await expect(tu).toHaveText('das Brot');
    await expect(trangThai).toContainText('das Brötchen sẽ quay lại sau vài thẻ');
    // 2 Khó, 4 Dễ
    await latVaCham(page, '2');
    await expect(tu).toHaveText('das Mehl');
    await expect(trangThai).toContainText('das Brot lên mức Đang học');
    await latVaCham(page, '4');
    await expect(tu).toHaveText('backen');
    await expect(trangThai).toContainText('das Mehl lên mức Khá');
    // Thẻ không có câu ví dụ: E không đọc gì
    await page.keyboard.press('e');

    // ← xem lại hai thẻ vừa chấm (đang xem lại thì phím chấm không có tác dụng), → về thẻ đang học
    await page.keyboard.press('ArrowLeft');
    await expect(tu).toHaveText('das Mehl');
    await expect(trangThai).toContainText('Đang xem lại thẻ đã chấm: Dễ');
    await page.keyboard.press('ArrowLeft');
    await expect(tu).toHaveText('das Brot');
    await expect(trangThai).toContainText('Đang xem lại thẻ đã chấm: Khó');
    await page.keyboard.press('3');
    await page.keyboard.press('ArrowRight');
    await expect(tu).toHaveText('das Mehl');
    await page.keyboard.press('ArrowRight');
    await expect(tu).toHaveText('backen');

    // 3 Tốt; thẻ Quên quay lại (Ôn lại, chỉ để luyện); thẻ cuối
    await latVaCham(page, '3');
    await expect(tu).toHaveText('das Brötchen');
    await expect(onLai(page)).toBeVisible();
    await latVaCham(page, '3');
    await expect(tu).toHaveText('süß');
    await latVaCham(page, '3');

    await xongPhien(page, 'Đã học 5 từ mới. 5 thẻ lên mức.', 'Quên 1 · Khó 1 · Tốt 2 · Dễ 1');
    expect(await daDoc(page)).toHaveLength(3);
    // Quên và Khó: Đang học (mức 1); Tốt: Quen (2); Dễ: Khá (3)
    await mayChuDaNhan(page, 5, [2, 2, 1, 0, 0]);
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/\/app\/chu-de\/banh-mi-tu-vung$/);
    expect(errors).toEqual([]);
});

test('Quiz: phím 1–4 chọn, Enter và Space đi tiếp, P và S nghe; chọn sai thì câu quay lại sau 3 câu', async ({ page }, info) => {
    const errors = ghiLoi(page);
    await giaGiongDoc(page);
    await dangNhap(page, emailHoc(info.project.name, 5));
    const phien = await moPhien(page, '/app/hoc?che-do=quiz');
    expect(phien.mode).toBe('quiz');
    expect(phien.cards).toHaveLength(10);

    // Câu đầu chọn sai: gặp lại sau 3 câu nữa (Ôn lại); lượt ôn lại không tính
    const thuTu = [0, 1, 2, 3, 0, 4, 5, 6, 7, 8, 9].map((i) => phien.cards[i]);
    for (const [i, the] of thuTu.entries()) {
        await expect(bao(page)).toHaveCount(0);
        await expect(tuQuiz(page)).toHaveText(the.front);
        await expect(onLai(page)).toHaveCount(i === 4 ? 1 : 0);
        if (i === 0) {
            await page.keyboard.press('p');
            await page.keyboard.press('s');
            await expect
                .poll(() => daDoc(page))
                .toEqual([
                    { text: the.front, rate: THUONG },
                    { text: the.front, rate: CHAM },
                ]);
            await chonBangPhim(page, the.meanings.vi ?? '', false);
            await expect(bao(page)).toContainText('Chưa đúng');
            await kiem(page, 'Quiz chọn sai', `che-do-quiz-${info.project.name}`);
        } else {
            await chonBangPhim(page, the.meanings.vi ?? '');
            await expect(bao(page)).toContainText('Đúng');
        }
        await page.keyboard.press(i % 2 === 0 ? 'Enter' : 'Space');
    }

    await xongPhien(page, 'Đã học 10 từ mới. 10 thẻ lên mức.', 'Quên 1 · Khó 0 · Tốt 9 · Dễ 0');
    await mayChuDaNhan(page, 10, [1, 9, 0, 0, 0]);
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/\/app\/hom-nay$/);
    await expect(page.getByText('10/20 thẻ')).toBeVisible();
    expect(errors).toEqual([]);
});

test('Listening: đọc từ khi câu hiện; đang gõ thì Alt+P, Alt+S nghe lại, P là chữ p; gợi ý nghĩa là Khó; gõ sai thì câu quay lại', async ({ page }, info) => {
    const errors = ghiLoi(page);
    await giaGiongDoc(page);
    await dangNhap(page, emailHoc(info.project.name, 6));
    const phien = await moPhien(page, '/app/hoc?che-do=listening');
    expect(phien.cards).toHaveLength(10);
    const o = page.getByRole('textbox', { name: 'Câu trả lời' });

    // Câu thứ ba gõ sai: gặp lại sau 3 câu nữa
    const thuTu = [0, 1, 2, 3, 4, 5, 2, 6, 7, 8, 9].map((i) => phien.cards[i]);
    const doc: CauDaDoc[] = [];
    for (const [i, the] of thuTu.entries()) {
        await expect(bao(page)).toHaveCount(0);
        // Từ được đọc ngay khi câu hiện, chữ không hiện; đồng hồ 30 giây chạy
        doc.push({ text: the.front, rate: THUONG });
        await expect.poll(() => daDoc(page)).toEqual(doc);
        await expect(page.getByRole('timer', { name: 'Thời gian còn lại' })).toBeVisible();
        await expect(onLai(page)).toHaveCount(i === 6 ? 1 : 0);
        await expect(o).toBeFocused();
        if (i === 0) {
            await page.keyboard.press('p');
            await expect(o).toHaveValue('p');
            await page.keyboard.press('Alt+p');
            await page.keyboard.press('Alt+s');
            doc.push({ text: the.front, rate: THUONG }, { text: the.front, rate: CHAM });
            await expect.poll(() => daDoc(page)).toEqual(doc);
            await expect(o).toHaveValue('p');
        } else if (i === 1) {
            await page.getByRole('button', { name: 'Gợi ý nghĩa' }).click();
            await expect(nghiaHoi(page)).toHaveText(the.meanings.vi ?? '');
        }
        await o.fill(i === 2 ? 'xyz' : the.front);
        await o.press('Enter');
        if (i === 2) {
            await expect(bao(page)).toContainText('Chưa đúng');
            await expect(bao(page)).toContainText('Bạn gõ: xyz');
            await kiem(page, 'Listening gõ sai', `che-do-listening-${info.project.name}`);
        } else {
            await expect(bao(page)).toContainText('Đúng');
            if (i === 1) await expect(bao(page)).toContainText('có gợi ý');
        }
        await page.keyboard.press(i % 2 === 0 ? 'Enter' : 'Space');
    }

    await xongPhien(page, 'Đã học 10 từ mới. 10 thẻ lên mức.', 'Quên 1 · Khó 1 · Tốt 8 · Dễ 0');
    expect(await daDoc(page)).toHaveLength(doc.length);
    await mayChuDaNhan(page, 10, [2, 8, 0, 0, 0]);
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/\/app\/hom-nay$/);
    expect(errors).toEqual([]);
});

test('Typing: chưa trả lời thì không nghe được, trả lời rồi P, S nghe; nút ä; thiếu mạo từ, gợi ý chữ đầu là Khó; gõ sai thì câu quay lại', async ({ page }, info) => {
    const errors = ghiLoi(page);
    await giaGiongDoc(page);
    await dangNhap(page, emailHoc(info.project.name, 7));
    const phien = await moPhien(page, '/app/hoc?che-do=typing');
    expect(phien.cards.slice(0, 5).map((c) => c.front)).toEqual(['zahlen', 'zählen', 'die Zahl', 'null', 'eins']);
    const o = page.getByRole('textbox', { name: 'Câu trả lời' });
    const tiep = page.getByRole('button', { name: /Tiếp/ });

    // Câu thứ tư (null) gõ thiếu chữ: gặp lại sau 3 câu nữa
    const thuTu = [0, 1, 2, 3, 4, 5, 6, 3, 7, 8, 9].map((i) => phien.cards[i]);
    const ketQua = ['Đúng', 'Đúng', 'Đúng, nhưng thiếu mạo từ', 'Chưa đúng', 'Đúng'];
    for (const [i, the] of thuTu.entries()) {
        await expect(bao(page)).toHaveCount(0);
        await expect(nghiaHoi(page)).toHaveText(the.meanings.vi ?? '');
        await expect(onLai(page)).toHaveCount(i === 7 ? 1 : 0);
        await expect(o).toBeFocused();
        if (i === 0) {
            // Chưa trả lời: Alt+P, Alt+S không đọc (nghe là lộ đáp án)
            await page.keyboard.press('Alt+p');
            await page.keyboard.press('Alt+s');
            await o.fill(the.front);
        } else if (i === 1) {
            await o.fill('z');
            await page.getByRole('button', { name: 'Gõ ä' }).click();
            await expect(o).toHaveValue('zä');
            await expect(o).toBeFocused();
            await page.keyboard.type('hlen');
            await expect(o).toHaveValue('zählen');
        } else if (i === 2) {
            await o.fill('Zahl');
        } else if (i === 3) {
            await o.fill('nul');
        } else if (i === 4) {
            await page.getByRole('button', { name: 'Gợi ý chữ đầu' }).click();
            await expect(page.getByText('e _ _ _', { exact: true })).toBeVisible();
            await o.fill('eins');
        } else {
            await o.fill(the.front);
        }
        await o.press('Enter');
        await expect(bao(page)).toContainText(ketQua[i] ?? 'Đúng');
        if (i === 0) {
            // Trả lời rồi (tiêu điểm ở nút Tiếp): P, S đọc từ
            await expect(tiep).toBeFocused();
            expect(await daDoc(page)).toEqual([]);
            await page.keyboard.press('p');
            await page.keyboard.press('s');
            await expect
                .poll(() => daDoc(page))
                .toEqual([
                    { text: the.front, rate: THUONG },
                    { text: the.front, rate: CHAM },
                ]);
        } else if (i === 2) {
            await kiem(page, 'Typing thiếu mạo từ', `che-do-typing-${info.project.name}`);
        } else if (i === 4) {
            await expect(bao(page)).toContainText('có gợi ý');
        }
        await page.keyboard.press(i % 2 === 0 ? 'Enter' : 'Space');
    }

    await xongPhien(page, 'Đã học 10 từ mới. 10 thẻ lên mức.', 'Quên 1 · Khó 2 · Tốt 7 · Dễ 0');
    await mayChuDaNhan(page, 10, [3, 7, 0, 0, 0]);
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/\/app\/hom-nay$/);
    expect(errors).toEqual([]);
});

test('Ghép cặp: Enter chọn ô, P và S nghe từ vừa chọn; nối sai thì báo, thẻ đó chỉ được Khó; 10 thẻ thành 2 vòng 5 cặp', async ({ page }, info) => {
    const errors = ghiLoi(page);
    await giaGiongDoc(page);
    await dangNhap(page, emailHoc(info.project.name, 8));
    const phien = await moPhien(page, '/app/hoc?che-do=match');
    expect(phien.cards).toHaveLength(10);
    const cotTu = page.getByRole('group', { name: 'Từ tiếng Đức' });
    const cotNghia = page.getByRole('group', { name: 'Nghĩa' });
    const [dau, sau] = phien.cards;
    await expect(cotTu.getByRole('button')).toHaveCount(5);
    await expect(cotNghia.getByRole('button')).toHaveCount(5);

    // Chưa chọn từ nào thì P không đọc; chọn bằng bàn phím (tiêu điểm vào ô, Enter) rồi P, S đọc từ đó
    await page.keyboard.press('p');
    const o = cotTu.getByRole('button', { name: dau.front, exact: true });
    await o.focus();
    await page.keyboard.press('Enter');
    await expect(o).toHaveAttribute('aria-pressed', 'true');
    await page.keyboard.press('p');
    await page.keyboard.press('s');
    await expect
        .poll(() => daDoc(page))
        .toEqual([
            { text: dau.front, rate: THUONG },
            { text: dau.front, rate: CHAM },
        ]);

    // Nối sai: báo rõ; thẻ đầu chỉ còn được Khó
    await cotNghia.getByRole('button', { name: sau.meanings.vi, exact: true }).click();
    await expect(page.locator('main [role="status"]')).toHaveText(`Chưa khớp: ${dau.front} không phải “${sau.meanings.vi}”`);
    await kiem(page, 'Ghép cặp nối sai', `che-do-ghep-cap-${info.project.name}`);

    for (const the of phien.cards.slice(0, 5)) await noi(page, the);
    // Xong vòng 1: sang vòng 2 với 5 thẻ còn lại
    await expect(page.getByText('6/10', { exact: true })).toBeVisible();
    await expect(cotTu.getByRole('button', { name: phien.cards[5].front, exact: true })).toBeEnabled();
    for (const the of phien.cards.slice(5)) await noi(page, the);

    await xongPhien(page, 'Đã học 10 từ mới. 10 thẻ lên mức.', 'Quên 0 · Khó 1 · Tốt 9 · Dễ 0');
    await mayChuDaNhan(page, 10, [1, 9, 0, 0, 0]);
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/\/app\/hom-nay$/);
    expect(errors).toEqual([]);
});

test('Hỗn hợp: thẻ mới có câu ví dụ thành Đặt câu, thẻ khác thành Quiz; xem đáp án là sai, câu quay lại', async ({ page }, info) => {
    const errors = ghiLoi(page);
    await giaGiongDoc(page);
    // Số ngẫu nhiên cố định 0,1: thẻ mới có câu ví dụ chọn Đặt câu (dưới 0,4), thẻ mới không có câu chọn Quiz
    await page.addInitScript(() => {
        Math.random = () => 0.1;
    });
    await dangNhap(page, emailHoc(info.project.name, 9));
    const phien = await moPhien(page, '/app/hoc?che-do=mixed&chu-de=banh-mi-tu-vung');
    expect(phien.cards.map((c) => [c.front, c.example !== null, c.box])).toEqual([
        ['das Brötchen', true, 0],
        ['das Brot', true, 0],
        ['das Mehl', false, 0],
        ['backen', false, 0],
        ['süß', false, 0],
    ]);
    const [brotchen, brot, ...conLai] = phien.cards;
    const hoi = page.locator('main h1');

    await expect(hoi).toHaveText('Sắp xếp thành câu');
    await xepChu(page, chuCua(brotchen));
    await page.keyboard.press('Enter');
    await expect(bao(page)).toHaveText('Đúng');
    await page.keyboard.press('Enter');

    // Xem đáp án là sai: câu quay lại sau 3 câu nữa, vẫn là Đặt câu
    await expect(bao(page)).toHaveCount(0);
    await expect(hoi).toHaveText('Sắp xếp thành câu');
    await page.getByRole('button', { name: 'Xem đáp án' }).click();
    await expect(bao(page)).toHaveText('Đáp án');
    await kiem(page, 'Hỗn hợp: Đặt câu, xem đáp án', `che-do-hon-hop-${info.project.name}`);
    await page.keyboard.press('Enter');

    for (const the of conLai) {
        await expect(bao(page)).toHaveCount(0);
        await expect(hoi).toHaveText('Chọn nghĩa đúng');
        await expect(tuQuiz(page)).toHaveText(the.front);
        await chonBangPhim(page, the.meanings.vi ?? '');
        await expect(bao(page)).toContainText('Đúng');
        await page.keyboard.press('Space');
    }
    await expect(bao(page)).toHaveCount(0);
    await expect(hoi).toHaveText('Sắp xếp thành câu');
    await expect(onLai(page)).toBeVisible();
    await xepChu(page, chuCua(brot));
    await page.keyboard.press('Enter');
    await expect(bao(page)).toHaveText('Đúng');
    await page.keyboard.press('Enter');

    await xongPhien(page, 'Đã học 5 từ mới. 5 thẻ lên mức.', 'Quên 1 · Khó 0 · Tốt 4 · Dễ 0');
    await mayChuDaNhan(page, 5, [1, 4, 0, 0, 0]);
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/\/app\/chu-de\/banh-mi-tu-vung$/);
    expect(errors).toEqual([]);
});

test('Đặt câu: P và S nghe câu; sai thứ tự thì thử lại, Backspace bỏ chữ cuối, sửa rồi đúng là Khó; Enter kiểm tra và đi tiếp', async ({ page }, info) => {
    const errors = ghiLoi(page);
    await giaGiongDoc(page);
    await dangNhap(page, emailHoc(info.project.name, 10));
    const phien = await moPhien(page, '/app/hoc?che-do=sentence');
    // Chủ đề đang học (Số đếm) không có câu ví dụ: phiên lấy thẻ có câu ở chủ đề kế (Bánh mì · bánh ngọt)
    expect(phien.cards.map((c) => c.front)).toEqual(['das Brötchen', 'das Brot']);
    const [brotchen, brot] = phien.cards;
    const cauCuaBan = page.getByRole('group', { name: 'Câu của bạn' });

    await page.keyboard.press('p');
    await page.keyboard.press('s');
    await expect
        .poll(() => daDoc(page))
        .toEqual([
            { text: 'Ich möchte zwei Brötchen, bitte.', rate: THUONG },
            { text: 'Ich möchte zwei Brötchen, bitte.', rate: CHAM },
        ]);

    // Sai thứ tự: thử lại; Backspace bỏ từng chữ cuối; xếp lại đúng thì được Khó
    await xepChu(page, [...chuCua(brotchen)].reverse());
    await expect(cauCuaBan.getByRole('button')).toHaveCount(5);
    await page.keyboard.press('Enter');
    await expect(bao(page)).toHaveText('Chưa đúng, thử lại');
    for (let i = 0; i < 5; i++) await page.keyboard.press('Backspace');
    await expect(cauCuaBan.getByRole('button')).toHaveCount(0);
    await xepChu(page, chuCua(brotchen));
    await page.keyboard.press('Enter');
    await expect(bao(page)).toHaveText('Đúng sau khi sửa');
    await kiem(page, 'Đặt câu đúng sau khi sửa', `che-do-dat-cau-${info.project.name}`);
    await page.keyboard.press('Enter');

    await expect(bao(page)).toHaveCount(0);
    await xepChu(page, chuCua(brot));
    await page.keyboard.press('Enter');
    await expect(bao(page)).toHaveText('Đúng');
    await page.keyboard.press('Space');

    await xongPhien(page, 'Đã học 2 từ mới. 2 thẻ lên mức.', 'Quên 0 · Khó 1 · Tốt 1 · Dễ 0');
    await mayChuDaNhan(page, 2, [1, 1, 0, 0, 0]);
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/\/app\/hom-nay$/);
    expect(errors).toEqual([]);
});
