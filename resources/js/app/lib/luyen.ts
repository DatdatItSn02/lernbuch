// Chấm và dựng câu hỏi cho các chế độ luyện tập (Quiz, Listening, Typing, Ghép cặp, Hỗn hợp, Đặt câu), theo Lernbuch (modes.js).
// Điểm mỗi thẻ: 0 Quên, 1 Khó, 2 Tốt, gửi lên máy chủ như lượt chấm Flashcard.
import type { DapAnNhieu, TheHoc } from './hoc';
import type { NgonNguNghia } from './noiDung';

export type Diem = 0 | 1 | 2;
export type Kieu = 'quiz' | 'listening' | 'typing' | 'match' | 'sentence';
export type Rnd = () => number;

/** Lý do của điểm khi gõ (app hiện chữ theo lý do). */
export type LyDoGo = 'dung' | 'thieuMaoTu' | 'saiMaoTu' | 'ganDung' | 'chuaDung' | 'hetGio';
export type KetQuaGo = { diem: Diem; lyDo: LyDoGo };

/** Chữ để so: viết thường, ä ö ü ß thành ae oe ue ss, bỏ dấu và dấu câu, gộp khoảng trắng. */
export function fold(s: string): string {
    return s
        .normalize('NFC')
        .toLowerCase()
        .replace(/ß/g, 'ss')
        .replace(/ä/g, 'ae')
        .replace(/ö/g, 'oe')
        .replace(/ü/g, 'ue')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^\p{L}\p{N}\s]/gu, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/** Phần chính để so: bỏ phần trong ngoặc, bỏ "sich" đứng đầu. */
const core = (s: string) => fold(s.replace(/\([^)]*\)/g, ' ')).replace(/^sich /, '');

/** Khoảng cách Levenshtein. */
export function lev(a: string, b: string): number {
    const d = Array.from({ length: a.length + 1 }, (_, i) => [i, ...Array<number>(b.length).fill(0)]);
    for (let j = 1; j <= b.length; j++) d[0][j] = j;
    for (let i = 1; i <= a.length; i++) {
        for (let j = 1; j <= b.length; j++) d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
    }
    return d[a.length][b.length];
}

const MAO_TU_DAU = /^((?:der|die|das)(?:\s*\/\s*(?:der|die|das))*)\s+(.*)$/i;

/** Tách mạo từ đứng đầu của danh từ ("der/die Angestellte" → der, die + Angestellte); thẻ khác giữ nguyên. */
export function tachMaoTu(the: Pick<TheHoc, 'front' | 'article' | 'pos'>): { maoTu: string[]; tu: string } {
    const m = the.article !== null || the.pos === 'noun' ? the.front.match(MAO_TU_DAU) : null;
    return m ? { maoTu: m[1].toLowerCase().split('/').map((x) => x.trim()), tu: m[2] } : { maoTu: [], tu: the.front };
}

/**
 * Chấm chữ gõ như Lernbuch: đúng (kể cả gõ ae, oe, ue, ss thay ä, ö, ü, ß) được 2; danh từ thiếu mạo từ được 1, sai mạo từ 0;
 * gần đúng (từ 5 chữ cái, sai 1 chữ; từ 9 chữ cái sai 2) được 1. Thẻ có nhiều cách viết ("a / b", "a; b") khớp cách nào cũng được.
 */
export function chamGo(the: Pick<TheHoc, 'front' | 'article' | 'pos'>, go: string): KetQuaGo {
    const { maoTu, tu } = tachMaoTu(the);
    const cach = tu
        .replace(/\([^)]*\)/g, ' ')
        .split(/\s+\/\s+|;/)
        .map(core)
        .filter(Boolean);
    // Chỉ tách mạo từ ở chữ gõ khi thẻ là danh từ có mạo từ: câu "Das ist nett." phải gõ cả "Das"
    const m = maoTu.length ? go.trim().match(/^(der|die|das)\s+(.*)$/i) : null;
    const maoTuGo = m ? m[1].toLowerCase() : '';
    const goc = core(m ? m[2] : go);
    if (goc && cach.includes(goc)) {
        if (!maoTu.length) return { diem: 2, lyDo: 'dung' };
        if (!maoTuGo) return { diem: 1, lyDo: 'thieuMaoTu' };
        return maoTu.includes(maoTuGo) ? { diem: 2, lyDo: 'dung' } : { diem: 0, lyDo: 'saiMaoTu' };
    }
    const gan = cach.some((c) => {
        const n = Math.max(c.length, goc.length);
        return n >= 5 && lev(c, goc) <= (n >= 9 ? 2 : 1);
    });
    return gan ? { diem: 1, lyDo: 'ganDung' } : { diem: 0, lyDo: 'chuaDung' };
}

/** Gợi ý khi gõ: chữ cái đầu, các chữ khác thành gạch dưới (giữ khoảng trắng, gạch nối, dấu câu). */
export function goiYChu(the: Pick<TheHoc, 'front' | 'article' | 'pos'>): string {
    const tu = tachMaoTu(the).tu.replace(/\([^)]*\)/g, '').trim();
    return [...tu].map((ch, i) => (i === 0 || /[\s\-/.,!?']/.test(ch) ? ch : '_')).join(' ');
}

export function tron<T>(ds: readonly T[], r: Rnd = Math.random): T[] {
    const a = ds.slice();
    for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(r() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}

/** Nghĩa hiện cho người học: thứ tiếng bật đầu tiên (vi, en, zh); tắt hết thì lấy nghĩa đầu tiên có. */
export function nghiaChinh(the: { meanings: Partial<Record<NgonNguNghia, string>> }, langs: Record<NgonNguNghia, boolean>): string {
    const thu: NgonNguNghia[] = ['vi', 'en', 'zh'];
    return thu.filter((l) => langs[l]).map((l) => the.meanings[l]).find(Boolean) ?? thu.map((l) => the.meanings[l]).find(Boolean) ?? '';
}

/** Quiz: nghĩa đúng và 3 nghĩa nhiễu khác nhau (bỏ nghĩa trùng), trộn thứ tự. */
export function dapAnQuiz(the: TheHoc, langs: Record<NgonNguNghia, boolean>, r: Rnd = Math.random): { cacDapAn: string[]; dung: string } {
    const dung = nghiaChinh(the, langs);
    const da = new Set([dung]);
    const sai: string[] = [];
    for (const d of tron(the.options ?? [], r)) {
        const n = nghiaChinh(d, langs);
        if (n && !da.has(n)) {
            da.add(n);
            sai.push(n);
            if (sai.length === 3) break;
        }
    }
    return { cacDapAn: tron([dung, ...sai], r), dung };
}

/** Hỗn hợp: kiểu câu hỏi theo mức của thẻ như Lernbuch; Đặt câu chỉ khi thẻ có câu ví dụ. */
export function chonKieu(the: Pick<TheHoc, 'box' | 'example'>, r: Rnd = Math.random): Exclude<Kieu, 'match'> {
    const x = r();
    const coCau = !!the.example;
    if (the.box === 0) return coCau && x < 0.4 ? 'sentence' : 'quiz';
    if (the.box <= 2) return x < 0.4 ? 'quiz' : x < 0.7 ? 'listening' : 'typing';
    return x < 0.4 ? 'typing' : x < 0.7 || !coCau ? 'listening' : 'sentence';
}

/** Thẻ trong vòng Ghép cặp; cặp nhiễu (decoy) chỉ để đủ 3 cặp, không chấm, không có file audio (đọc bằng giọng trình duyệt). */
export type TheGhep = { id: number; front: string; article: TheHoc['article']; word: string; meanings: TheHoc['meanings']; audio: string | null; nhieu: boolean };
export type Vong = { cards: TheGhep[] };

/** Chia thẻ thành các vòng đều nhau, mỗi vòng tối đa 6 cặp; vòng ít hơn 3 cặp thì thêm cặp nhiễu từ đáp án nhiễu. */
export function chiaVong(cards: readonly TheHoc[], langs: Record<NgonNguNghia, boolean>, size = 6): Vong[] {
    if (cards.length === 0) return [];
    const k = Math.max(1, Math.ceil(cards.length / size));
    const moi = Math.ceil(cards.length / k);
    const vong: Vong[] = [];
    for (let i = 0; i < cards.length; i += moi) {
        const nhom: TheGhep[] = cards.slice(i, i + moi).map((c) => ({ id: c.id, front: c.front, article: c.article, word: c.word, meanings: c.meanings, audio: c.audio, nhieu: false }));
        const nhieu: DapAnNhieu[] = cards[i].options ?? [];
        for (let n = 0; nhom.length < 3 && n < nhieu.length; n++) {
            const d = nhieu[n];
            const nd = nghiaChinh(d, langs);
            if (nd && !nhom.some((g) => g.id === d.id || nghiaChinh(g, langs) === nd)) {
                nhom.push({ id: d.id, front: d.front, article: d.article, word: d.word, meanings: d.meanings, audio: null, nhieu: true });
            }
        }
        vong.push({ cards: nhom });
    }
    return vong;
}

/** Các chữ của câu ví dụ, theo khoảng trắng (dấu câu đi liền chữ như Lernbuch). */
export const tachCau = (cau: string) => cau.trim().split(/\s+/).filter(Boolean);

/** Thứ tự trộn của n chữ; thử lại vài lần để thứ tự khác thứ tự đúng (câu một chữ thì thôi). */
export function thuTuTron(n: number, r: Rnd = Math.random): number[] {
    const goc = Array.from({ length: n }, (_, i) => i);
    let ra = tron(goc, r);
    for (let k = 0; k < 5 && n > 1 && ra.every((v, i) => v === i); k++) ra = tron(goc, r);
    return ra;
}
