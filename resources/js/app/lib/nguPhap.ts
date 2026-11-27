// Chữ trong bài ngữ pháp theo Lernbuch (exercises.js: rich, sentences, lines, split, cleanH2): tách câu dài thành dòng,
// tô đậm điều cần nhớ (thuật ngữ, chữ trong ngoặc kép, chữ viết hoa hết), tách dòng nhiều thứ tiếng (EN · 中 · VI).
import type { Nghia } from './baiTap';
import { fold } from './luyen';

const CJK = /[\u3000-\u9fff\uff00-\uffef]/;
const CJK_DOAN = /[\u3000-\u9fff\uff00-\uffef][\u3000-\u9fff\uff00-\uffef\s\u00b7\uff0c\u3002\uff1b\uff1a\u3001\uff08\uff09()0-9-]*/g;
const VI = /[ăâđêôơưạảấầẩẫậắằẳẵặẹẻẽếềểễệỉịọỏốồổỗộớờởỡợụủứừửữựỳỵỷỹ]/i;
const VIET_TAT = /(?:z\.B|usw|bzw|vgl|Nr|ca|d\.h|S|Hr|Fr|u\.a|etc|Dr|Prof|u\.Ä|sog)\.$/;
const CAN_NHO =
    /(„[^“”]{1,80}[“”]|"[^"]{1,80}"|\b(?:Nominativ|Akkusativ|Dativ|Genitiv|Perfekt|Präteritum|Plusquamperfekt|Futur(?: I)?|Konjunktiv (?:II|I)|Passiv|Partizip (?:II|I)|Infinitiv|Imperativ|Satzklammer|Hauptsatz|Nebensatz|Position [0-9])\b|\b[A-ZĐƠƯÀ-Ỹ]{3,}(?:\s[A-ZĐƠƯÀ-Ỹ]{3,})*\b|⚠️?|📌|✅|❌)/g;
const HET_CAU = /[.!?…]\s+(?=[A-ZĐƠƯÀ-Ỹ„“📌⚠✅❌])/gu;

/** Một mảnh chữ: thường hay cần nhớ (vẽ đậm). */
export type Manh = { chu: string; dam: boolean };

/** Tô đậm thuật ngữ ngữ pháp, chữ trong ngoặc kép, chữ viết hoa hết và dấu ⚠ 📌 ✅ ❌. */
export function canNho(chu: string): Manh[] {
    const ra: Manh[] = [];
    let cuoi = 0;
    for (const m of chu.matchAll(CAN_NHO)) {
        const i = m.index;
        if (i > cuoi) ra.push({ chu: chu.slice(cuoi, i), dam: false });
        ra.push({ chu: m[0], dam: true });
        cuoi = i + m[0].length;
    }
    if (cuoi < chu.length) ra.push({ chu: chu.slice(cuoi), dam: false });
    return ra;
}

/** Tách đoạn dài thành câu (mỗi câu một dòng); câu ngắn dưới 28 chữ hay kết thúc bằng chữ viết tắt (z.B.) thì giữ liền. */
export function tachCau(chu: string): string[] {
    const ra: string[] = [];
    let dau = 0;
    for (const m of chu.matchAll(HET_CAU)) {
        const doan = chu.slice(dau, m.index + 1);
        if (doan.length >= 28 && !VIET_TAT.test(doan)) {
            ra.push(doan.trim());
            dau = m.index + m[0].length;
        }
    }
    ra.push(chu.slice(dau).trim());
    return ra.filter(Boolean);
}

/** Các dòng của một đoạn: theo xuống dòng, đoạn dài có " · " thì tách ở đó, rồi tách câu. */
export function cacDong(chu: string): string[] {
    return chu.split('\n').flatMap((phan) => (phan.includes(' · ') && phan.length > 70 ? phan.split(' · ') : [phan]).flatMap(tachCau));
}

/** Dòng "Nhãn: nội dung" (nhãn ngắn, không có dấu câu) ở đầu đoạn thì tách nhãn ra. */
export function tachNhan(dong: string): { nhan: string | null; chu: string } {
    const m = dong.match(/^([^:.!?()]{2,30}):\s+(\S.*)$/);
    return m ? { nhan: m[1], chu: m[2] } : { nhan: null, chu: dong };
}

/**
 * Một chuỗi nhiều thứ tiếng thành từng thứ tiếng: nhiều dòng có chữ Hán thì dòng có chữ Hán là 中, dòng có dấu tiếng Việt
 * là VI, còn lại là EN; một dòng có chữ Hán xen chữ Latin thì tách phần Hán. Chỉ một thứ tiếng thì trả { main }.
 */
export function tachTieng(chu: string): Nghia & { main?: string } {
    const dong = chu
        .split('\n')
        .map((x) => x.trim())
        .filter(Boolean);
    if (dong.length > 1 && dong.some((d) => CJK.test(d))) {
        const ra: Nghia = {};
        for (const d of dong) {
            const k = CJK.test(d) ? 'zh' : VI.test(d) ? 'vi' : 'en';
            ra[k] = ra[k] ? `${ra[k]} ${d}` : d;
        }
        return ra;
    }
    if (CJK.test(chu)) {
        const zh = (chu.match(CJK_DOAN) ?? []).join(' ').trim();
        const latin = chu
            .replace(CJK_DOAN, ' ')
            .replace(/\(\s*\)/g, '')
            .replace(/\s{2,}/g, ' ')
            .replace(/[\s+·-]+$/, '')
            .trim();
        return latin ? { en: latin, zh } : { zh };
    }
    return { main: chu };
}

/** Tiêu đề phần: bỏ số trang ("– S. 12") và chỗ dẫn sang bài khác ("(→ …)"). */
export const tieuDePhan = (chu: string) =>
    chu
        .replace(/\s*[–-]\s*S\.\s*[\d, –-]+\s*$/, '')
        .replace(/\s*\(→[^)]*\)\s*/g, ' ')
        .replace(/\s{2,}/g, ' ')
        .trim();

/** Tiêu đề nhỏ: bỏ cấp ở cuối ("(A1)"). */
export const tieuDeNho = (chu: string) => chu.replace(/\s*\((?:A1|A2|B1|B2)\)\s*$/, '');

/** Chữ ngắn của mục lục: bỏ số thứ tự đầu, phần sau gạch hay ngoặc, tối đa 42 chữ. */
export const chuMucLuc = (chu: string) =>
    chu
        .replace(/^\d+\.\s*/, '')
        .replace(/\s*[–(].*$/, '')
        .slice(0, 42);

/** Mã neo của một phần (để mục lục nhảy tới). */
export const maPhan = (chu: string, thuTu: number) => `p-${fold(chu).replace(/\s+/g, '-').slice(0, 40)}-${thuTu}`;

/** Tên bài theo dàn ý "Genus: der, die, das  ·  Giống: der, die, das": phần tiếng Đức và phần tiếng Việt. */
export function tachTenBai(ten: string): { duc: string; viet: string | null } {
    const [duc, ...con] = ten.split('  ·  ');
    return { duc, viet: con.length > 0 ? con.join('  ·  ') : null };
}
