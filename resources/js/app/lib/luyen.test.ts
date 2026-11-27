import { describe, expect, it } from 'vitest';
import xuChung from '../../../../tests/chung/xu-che-do.json';
import { theHoc } from '../../test/noiDungGia';
import { CAC_CHE_DO, XU, laCheDo, type TheHoc } from './hoc';
import { chamGo, chiaVong, chonKieu, dapAnQuiz, fold, goiYChu, lev, nghiaChinh, tachCau, tachMaoTu, thuTuTron } from './luyen';

const LANGS = { vi: true, en: true, zh: true };
/** Số ngẫu nhiên lặp lại được (LCG) để test thứ tự trộn. */
const rnd = (seed = 7) => () => ((seed = (seed * 1103515245 + 12345) % 2147483648) / 2147483648);
const the = (front: string, article: TheHoc['article'], pos: string) => ({ front, article, pos });

describe('chấm chữ gõ như Lernbuch', () => {
    const brotchen = the('das Brötchen', 'das', 'noun');

    it('danh từ: đủ mạo từ được 2, thiếu mạo từ 1, sai mạo từ 0; gõ ae, oe, ue, ss thay ä, ö, ü, ß cũng đúng', () => {
        expect(chamGo(brotchen, 'das Brötchen')).toEqual({ diem: 2, lyDo: 'dung' });
        expect(chamGo(brotchen, '  Das   broetchen ')).toEqual({ diem: 2, lyDo: 'dung' });
        expect(chamGo(brotchen, 'Brötchen')).toEqual({ diem: 1, lyDo: 'thieuMaoTu' });
        expect(chamGo(brotchen, 'die Brötchen')).toEqual({ diem: 0, lyDo: 'saiMaoTu' });
        expect(chamGo(the('die Straße', 'die', 'noun'), 'die strasse')).toEqual({ diem: 2, lyDo: 'dung' });
    });

    it('gần đúng: từ 5 chữ cái sai 1 chữ, từ 9 chữ cái sai 2 chữ được 1; xa hơn hoặc từ ngắn thì 0', () => {
        expect(chamGo(brotchen, 'das Brotchen')).toEqual({ diem: 1, lyDo: 'ganDung' });
        expect(chamGo(brotchen, 'das Brot')).toEqual({ diem: 0, lyDo: 'chuaDung' });
        expect(chamGo(the('zählen', null, 'verb'), 'zahlen')).toEqual({ diem: 1, lyDo: 'ganDung' });
        expect(chamGo(the('eins', null, 'other'), 'ein')).toEqual({ diem: 0, lyDo: 'chuaDung' });
        expect(chamGo(the('eins', null, 'other'), '')).toEqual({ diem: 0, lyDo: 'chuaDung' });
    });

    it('bỏ phần trong ngoặc và "sich" đứng đầu; nhiều cách viết thì khớp cách nào cũng được', () => {
        expect(chamGo(the('die Eltern (Pl.)', 'die', 'noun'), 'die Eltern').diem).toBe(2);
        expect(chamGo(the('sich freuen', null, 'verb'), 'freuen').diem).toBe(2);
        expect(chamGo(the('Tschüss / Tschüs', null, 'other'), 'tschüs').diem).toBe(2);
        expect(chamGo(the('der/die Angestellte', null, 'noun'), 'die Angestellte').diem).toBe(2);
        expect(tachMaoTu(the('der/die Angestellte', null, 'noun'))).toEqual({ maoTu: ['der', 'die'], tu: 'Angestellte' });
    });

    it('câu bắt đầu bằng "Das" không phải mạo từ: phải gõ cả câu', () => {
        const cau = the('Das ist nett.', null, 'sentence');
        expect(chamGo(cau, 'das ist nett').diem).toBe(2);
        expect(chamGo(cau, 'ist nett').diem).toBe(0);
        expect(tachMaoTu(cau)).toEqual({ maoTu: [], tu: 'Das ist nett.' });
    });

    it('fold và khoảng cách chữ', () => {
        expect(fold('Brötchen!')).toBe('broetchen');
        expect(fold('Café  au lait')).toBe('cafe au lait');
        expect(lev('kitten', 'sitting')).toBe(3);
        expect(lev('', 'abc')).toBe(3);
    });

    it('gợi ý: chữ cái đầu, chữ khác thành gạch dưới, giữ khoảng trắng; danh từ bỏ mạo từ', () => {
        expect(goiYChu(the('die Straßenbahn', 'die', 'noun'))).toBe('S _ _ _ _ _ _ _ _ _ _');
        expect(goiYChu(the('Guten Morgen', null, 'phrase'))).toBe('G _ _ _ _   _ _ _ _ _ _');
        expect(goiYChu(the('die Eltern (Pl.)', 'die', 'noun'))).toBe('E _ _ _ _ _');
    });
});

describe('dựng câu hỏi', () => {
    const zahl = theHoc(10, 1);

    it('Hỗn hợp chọn kiểu theo mức như Lernbuch; Đặt câu chỉ khi có câu ví dụ', () => {
        const co = { example: { sentence: 'Ich zähle.', translations: {} } };
        const so = (x: number) => () => x;
        expect(chonKieu({ box: 0, ...co }, so(0.3))).toBe('sentence');
        expect(chonKieu({ box: 0, ...co }, so(0.5))).toBe('quiz');
        expect(chonKieu({ box: 0, example: null }, so(0.3))).toBe('quiz');
        expect([0.3, 0.5, 0.8].map((x) => chonKieu({ box: 2, ...co }, so(x)))).toEqual(['quiz', 'listening', 'typing']);
        expect([0.3, 0.5, 0.8].map((x) => chonKieu({ box: 4, ...co }, so(x)))).toEqual(['typing', 'listening', 'sentence']);
        expect(chonKieu({ box: 4, example: null }, so(0.8))).toBe('listening');
    });

    it('Quiz: 4 đáp án gồm nghĩa đúng và 3 nghĩa nhiễu khác nhau, bỏ nghĩa trùng với nghĩa đúng', () => {
        const c = {
            ...zahl,
            options: [
                { id: 1, front: 'zählen', article: null, word: 'zählen', gender: null, meanings: { vi: 'đếm' } },
                { id: 2, front: 'die Nummer', article: 'die', word: 'Nummer', gender: 'f', meanings: { vi: zahl.meanings.vi } },
                { id: 3, front: 'eins', article: null, word: 'eins', gender: null, meanings: { vi: 'số một' } },
                { id: 4, front: 'zwei', article: null, word: 'zwei', gender: null, meanings: { vi: 'số hai' } },
                { id: 5, front: 'drei', article: null, word: 'drei', gender: null, meanings: { vi: 'số một' } },
            ],
        } satisfies TheHoc;
        const q = dapAnQuiz(c, LANGS, rnd());
        expect(q.dung).toBe(zahl.meanings.vi);
        expect(q.cacDapAn).toHaveLength(4);
        expect(new Set(q.cacDapAn).size).toBe(4);
        expect([...q.cacDapAn].sort()).toEqual([zahl.meanings.vi, 'đếm', 'số hai', 'số một'].sort());
        // Tắt tiếng Việt: hỏi bằng nghĩa tiếng Anh
        expect(nghiaChinh(zahl, { vi: false, en: true, zh: false })).toBe(zahl.meanings.en);
        expect(nghiaChinh(zahl, { vi: false, en: false, zh: false })).toBe(zahl.meanings.vi);
    });

    it('Ghép cặp: vòng đều nhau tối đa 6 cặp; vòng dưới 3 cặp thì thêm cặp nhiễu (không chấm) từ đáp án nhiễu', () => {
        const nhieu = [
            { id: 90, front: 'eins', article: null, word: 'eins', gender: null, meanings: { vi: 'số một' } },
            { id: 91, front: 'die Nummer', article: 'die', word: 'Nummer', gender: 'f', meanings: { vi: 'số hiệu' } },
        ] satisfies TheHoc['options'];
        // 13 số đếm mẫu (id 11–23, nghĩa khác nhau)
        const cards = Array.from({ length: 13 }, (_, i) => ({ ...theHoc(11 + i), options: nhieu, audio: i === 0 ? '/audio/null.mp3' : null }));
        expect(chiaVong(cards, LANGS).map((v) => v.cards.length)).toEqual([5, 5, 3]);
        // Hai thẻ: thêm một cặp nhiễu; bỏ "eins" vì trùng nghĩa với thẻ id 12 (số một). Cặp nhiễu không có file audio
        const hai = chiaVong(cards.slice(0, 2), LANGS);
        expect(hai).toHaveLength(1);
        expect(hai[0].cards.map((c) => [c.id, c.nhieu, c.audio])).toEqual([
            [11, false, '/audio/null.mp3'],
            [12, false, null],
            [91, true, null],
        ]);
        expect(chiaVong([], LANGS)).toEqual([]);
    });

    it('Đặt câu: tách chữ theo khoảng trắng; thứ tự trộn khác thứ tự đúng (trừ câu một chữ)', () => {
        expect(tachCau('  Ich möchte zwei Brötchen, bitte. ')).toEqual(['Ich', 'möchte', 'zwei', 'Brötchen,', 'bitte.']);
        for (let seed = 1; seed < 30; seed++) {
            const t = thuTuTron(4, rnd(seed));
            expect([...t].sort()).toEqual([0, 1, 2, 3]);
            expect(t).not.toEqual([0, 1, 2, 3]);
        }
        expect(thuTuTron(1)).toEqual([0]);
    });
});

describe('chế độ học và xu', () => {
    it('7 chế độ, xu mỗi chế độ khớp bảng dùng chung với máy chủ', () => {
        expect(XU).toEqual(xuChung.xu);
        expect(Object.keys(XU)).toEqual([...CAC_CHE_DO, 'cloze']);
        expect(laCheDo('quiz')).toBe(true);
        expect(laCheDo('hack')).toBe(false);
        expect(laCheDo('cloze')).toBe(false);
        expect(laCheDo(null)).toBe(false);
    });
});
