import { expect, test, type Page } from '@playwright/test';

// Kiểm font bằng trình duyệt thật: Chromium cho biết từng ký tự được vẽ bằng font nào
// (DevTools: CSS.getPlatformFontsForNode), nên bắt được cả trường hợp chữ lặng lẽ rơi về font của máy.

type PlatformFont = { familyName: string; isCustomFont: boolean; glyphCount: number };
type Sample = { index: number; font: string; fallbackFont: string | null; fallbackChars: string; text: string };

async function renderedFonts(page: Page, selector: string): Promise<PlatformFont[]> {
    const cdp = await page.context().newCDPSession(page);
    try {
        await cdp.send('DOM.enable');
        await cdp.send('CSS.enable');
        const { root } = await cdp.send('DOM.getDocument', { depth: -1 });
        const { nodeId } = await cdp.send('DOM.querySelector', { nodeId: root.nodeId, selector });
        const { fonts } = await cdp.send('CSS.getPlatformFontsForNode', { nodeId });
        return fonts as PlatformFont[];
    } finally {
        await cdp.detach();
    }
}

const isFamily = (font: PlatformFont, family: string) => font.isCustomFont && font.familyName.startsWith(family);
const sampleSelector = (index: number) => `[data-sample-index="${index}"]`;

async function open(page: Page, path: string) {
    await page.goto(path);
    await page.evaluate(async () => {
        document.querySelectorAll('[data-font-sample]').forEach((n, i) => n.setAttribute('data-sample-index', String(i)));
        await document.fonts.ready;
    });
}

async function samples(page: Page): Promise<Sample[]> {
    return page.locator('[data-font-sample]').evaluateAll((nodes) =>
        nodes.map((n, index) => ({
            index,
            font: n.getAttribute('data-font') ?? '',
            fallbackFont: n.getAttribute('data-fallback-font'),
            fallbackChars: n.getAttribute('data-fallback-chars') ?? '',
            text: n.textContent ?? '',
        })),
    );
}

// Trả về mô tả lỗi, hoặc null nếu mọi ký tự vẽ bằng font dự kiến (và font dự phòng khai sẵn cho đúng các ký tự đã ghi).
function sampleProblem(s: Sample, fonts: PlatformFont[]): string | null {
    const wrong = fonts.filter((f) => !isFamily(f, s.font) && !(s.fallbackFont !== null && isFamily(f, s.fallbackFont)));
    if (wrong.length > 0) {
        return wrong
            .map((f) => `${f.glyphCount} ký tự vẽ bằng ${f.familyName} (${f.isCustomFont ? 'font web khác' : 'font của máy'})`)
            .join(', ');
    }
    if (!fonts.some((f) => isFamily(f, s.font))) return `không ký tự nào vẽ bằng ${s.font}`;
    const expectedFallback = [...s.text].filter((ch) => s.fallbackChars.includes(ch)).length;
    const fallbackGlyphs = fonts
        .filter((f) => s.fallbackFont !== null && isFamily(f, s.fallbackFont))
        .reduce((n, f) => n + f.glyphCount, 0);
    if (fallbackGlyphs !== expectedFallback) {
        return `${fallbackGlyphs} ký tự vẽ bằng ${s.fallbackFont ?? 'font dự phòng'}, dự kiến ${expectedFallback}`;
    }
    return null;
}

async function problems(page: Page): Promise<string> {
    const out: string[] = [];
    for (const s of await samples(page)) {
        const problem = sampleProblem(s, await renderedFonts(page, sampleSelector(s.index)));
        if (problem) out.push(`mẫu ${s.index + 1} (${s.font}) "${s.text.slice(0, 30)}": ${problem}`);
    }
    return out.join('\n');
}

test('mọi mẫu chữ hiện đúng font, đủ dấu tiếng Việt và ä ö ü ß ẞ', async ({ page }) => {
    await open(page, '/kiem-chu');
    expect((await samples(page)).length).toBe(20);
    await expect.poll(() => problems(page), { timeout: 15_000 }).toBe('');
});

test('trang chào dùng Baloo 2 cho tên và Nunito cho chữ', async ({ page }) => {
    await open(page, '/');
    await expect.poll(async () => (await renderedFonts(page, 'h1')).every((f) => isFamily(f, 'Baloo 2'))).toBe(true);
    await expect.poll(async () => (await renderedFonts(page, 'main p')).every((f) => isFamily(f, 'Nunito'))).toBe(true);
});

test('trang chào và trang kiểm chữ không tràn ngang', async ({ page }) => {
    for (const path of ['/', '/kiem-chu']) {
        await open(page, path);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(overflow, `${path} tràn ngang ${overflow}px`).toBeLessThanOrEqual(0);
    }
});

test('thiếu file font: phép kiểm báo lỗi, chữ vẫn đọc được bằng font dự phòng', async ({ page }) => {
    // Giả lập mất file Nunito bộ chữ tiếng Việt.
    await page.route(/nunito-vietnamese-wght-normal.*\.woff2/, (route) => route.abort());
    await open(page, '/kiem-chu');

    const sample = (await samples(page)).find((s) => s.font === 'Nunito' && s.text.startsWith('Học tiếng Đức'));
    expect(sample).toBeDefined();
    const s = sample as Sample;
    await expect
        .poll(async () => sampleProblem(s, await renderedFonts(page, sampleSelector(s.index))) ?? '', { timeout: 10_000 })
        .toMatch(/font của máy/);

    const line = page.locator(sampleSelector(s.index));
    await expect(line).toHaveText('Học tiếng Đức mỗi ngày một chút, ôn đều thì nhớ lâu.');
    expect((await line.boundingBox())?.height ?? 0).toBeGreaterThan(10);
});
