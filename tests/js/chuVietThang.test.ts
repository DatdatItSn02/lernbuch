// @vitest-environment node
// Chuẩn bị đa ngôn ngữ (mục tự kiểm 19 của G1): chữ trên app học lấy từ từ điển (resources/js/app/i18n), không viết thẳng trong code.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { timChuVietThang } from './chuVietThang';

const GOC = fileURLToPath(new URL('../../resources/js/app', import.meta.url));
/** Không quét: từ điển, dữ liệu mẫu (nội dung bài học, sau này lấy từ API), test. */
const BO_QUA = [/^i18n\//, /^data\//, /\.test\.tsx?$/];

function cacFile(thuMuc: string): string[] {
    return readdirSync(thuMuc).flatMap((ten) => {
        const duong = join(thuMuc, ten);
        return statSync(duong).isDirectory() ? cacFile(duong) : /\.tsx?$/.test(ten) ? [duong] : [];
    });
}

describe('bộ dò chữ viết thẳng', () => {
    it('bắt chữ trong JSX, chuỗi có dấu và thuộc tính hiện chữ', () => {
        const ma = [
            'export function A() {',
            '    const x = `Còn ${n} thẻ`;',
            '    return <p title="Help" aria-label={`Mở ${ten}`}>Xin chào {x}<b>OK</b></p>;',
            '}',
        ].join('\n');
        const chu = timChuVietThang('a.tsx', ma).map((c) => c.chu);
        expect(chu).toEqual(['Còn', 'thẻ', 'Help', 'Mở', 'Xin chào', 'OK']);
    });

    it('không báo nhầm khoá từ điển, lớp CSS, đường dẫn, lỗi cho lập trình viên', () => {
        const ma = [
            "import { t } from '../i18n';",
            'export function B() {',
            "    if (!ok) throw new Error('Thiếu StoreProvider');",
            "    console.warn('Không đăng ký được', e);",
            "    return <a href=\"/hom-nay\" className=\"text-tim\" title={t('khung.homNay')} lang=\"de\">{t('chung.ve')} {n}</a>;",
            '}',
        ].join('\n');
        expect(timChuVietThang('b.tsx', ma)).toEqual([]);
    });
});

describe('app học không còn chữ viết thẳng', () => {
    it('mọi file trong resources/js/app', () => {
        const loi = cacFile(GOC)
            .map((f) => relative(GOC, f).replaceAll('\\', '/'))
            .filter((f) => !BO_QUA.some((r) => r.test(f)))
            .flatMap((f) => timChuVietThang(f, readFileSync(join(GOC, f), 'utf8')).map((c) => `${f}:${c.dong}: ${c.chu}`));
        expect(loi, 'Đưa các chữ này vào resources/js/app/i18n/vi.ts rồi gọi t()').toEqual([]);
    });
});
