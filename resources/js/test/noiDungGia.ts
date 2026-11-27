// Nội dung giả cho test màn hình nội dung: cùng dạng với API /api/v1 (và cùng số liệu với NoiDungThuSeeder phía PHP).
import type { CayChuDe, DemCap, KetQuaTra, MucLichSu, TheChiTiet, TheSoTay, TheTomTat, TrangChuDe } from '../app/lib/noiDung';
import { giaApi, type LanGoi, type Tuyen } from './fakeApi';

type TheMau = TheTomTat & { capThap: number; capCao: number; chuDe: string };

const the = (id: number, chuDe: string, front: string, doi: Partial<TheMau>): TheMau => {
    const article = (['der', 'die', 'das'] as const).find((a) => front.startsWith(`${a} `)) ?? null;
    return {
        id,
        collection: 'vocab',
        front,
        article,
        word: article ? front.slice(article.length + 1) : front,
        gender: article ? ({ der: 'm', die: 'f', das: 'n' } as const)[article] : null,
        pos: 'noun',
        forms: null,
        level: 'A1',
        image: null,
        meanings: {},
        starred: false,
        capThap: 1,
        capCao: 1,
        chuDe,
        ...doi,
    };
};

const SO_DEM: [string, string][] = [
    ['null', 'số không'], ['eins', 'số một'], ['zwei', 'số hai'], ['drei', 'số ba'], ['vier', 'số bốn'], ['fünf', 'số năm'],
    ['sechs', 'số sáu'], ['sieben', 'số bảy'], ['acht', 'số tám'], ['neun', 'số chín'], ['zehn', 'số mười'], ['elf', 'số mười một'],
    ['zwölf', 'số mười hai'], ['dreizehn', 'số mười ba'], ['vierzehn', 'số mười bốn'], ['fünfzehn', 'số mười lăm'],
    ['sechzehn', 'số mười sáu'], ['siebzehn', 'số mười bảy'], ['achtzehn', 'số mười tám'], ['neunzehn', 'số mười chín'],
    ['zwanzig', 'số hai mươi'], ['dreißig', 'số ba mươi'],
];

export const CAC_THE: TheMau[] = [
    the(1, 'banh-mi-tu-vung', 'das Brötchen', { forms: 'die Brötchen', meanings: { vi: 'bánh mì nhỏ (ổ tròn)', en: 'bread roll', zh: '小面包' } }),
    the(2, 'banh-mi-tu-vung', 'das Brot', { forms: 'die Brote', meanings: { vi: 'bánh mì', en: 'bread', zh: '面包' } }),
    the(3, 'banh-mi-tu-vung', 'das Mehl', { forms: '–', level: 'A2', capThap: 2, capCao: 2, meanings: { vi: 'bột mì', en: 'flour', zh: '面粉' } }),
    the(4, 'banh-mi-tu-vung', 'backen', { pos: 'verb', forms: 'er backt · hat gebacken', level: 'A2', capThap: 2, capCao: 2, meanings: { vi: 'nướng (bánh)', en: 'to bake', zh: '烤' } }),
    the(5, 'banh-mi-tu-vung', 'süß', { pos: 'adj', forms: 'süßer · am süßesten', level: null, capThap: 1, capCao: 6, meanings: { vi: 'ngọt', en: 'sweet', zh: '甜' } }),
    the(7, 'o-tiem-banh-giao-tiep', 'Ich hätte gern zwei Brötchen.', { collection: 'comm', pos: 'sentence', meanings: { vi: 'Cho tôi hai cái bánh mì nhỏ.', en: "I'd like two bread rolls." } }),
    the(8, 'so-dem-tu-vung', 'zahlen', { pos: 'verb', forms: 'er zahlt · hat gezahlt', meanings: { vi: 'trả tiền', en: 'to pay', zh: '付钱' } }),
    the(9, 'so-dem-tu-vung', 'zählen', { pos: 'verb', forms: 'er zählt · hat gezählt', meanings: { vi: 'đếm', en: 'to count', zh: '数' } }),
    the(10, 'so-dem-tu-vung', 'die Zahl', { forms: 'die Zahlen', meanings: { vi: 'con số', en: 'number', zh: '数字' } }),
    ...SO_DEM.map(([de, vi], i) => the(11 + i, 'so-dem-tu-vung', de, { pos: 'other', meanings: { vi } })),
];

const CHU_DE = {
    'banh-mi-tu-vung': { name: 'Bánh mì · bánh ngọt', name_en: 'Bread · cake', bo: 'vocab', boTen: 'Từ vựng', parent: ['banh-va-tiem-banh', 'Bánh và tiệm bánh'], group: ['an-uong', 'Ăn uống'] },
    'o-tiem-banh-giao-tiep': { name: 'Ở tiệm bánh', name_en: 'At the bakery', bo: 'comm', boTen: 'Giao tiếp', parent: ['banh-va-tiem-banh', 'Bánh và tiệm bánh'], group: ['an-uong', 'Ăn uống'] },
    'so-dem-tu-vung': { name: 'Số đếm', name_en: 'Numbers', bo: 'vocab', boTen: 'Từ vựng', parent: ['dem-so', 'Đếm số'], group: ['con-so', 'Con số'] },
} as const;

/** Bỏ dấu và viết thường như search_text của máy chủ. */
const gon = (s: string) => s.toLowerCase().replace(/ß/g, 'ss').replace(/đ/g, 'd').normalize('NFD').replace(/\p{M}/gu, '');
const khopChu = (th: TheMau, q: string) => {
    const chu = gon([th.front, th.forms ?? '', ...Object.values(th.meanings)].join(' '));
    return gon(q).split(/\s+/).filter(Boolean).every((tu) => chu.includes(tu));
};
function tomTat(th: TheMau): TheTomTat {
    const x: Partial<TheMau> = { ...th };
    delete x.capThap;
    delete x.capCao;
    delete x.chuDe;
    return x as TheTomTat;
}
const khopCap = (th: TheMau, cap: number[]) => cap.length === 0 || cap.some((c) => (c === 0 ? th.level === null : th.level !== null && th.capThap <= c && th.capCao >= c));

function demCap(ds: TheMau[]): DemCap[] {
    const ra: DemCap[] = [];
    for (const c of [1, 2, 3, 4, 5, 6, 0]) {
        const n = ds.filter((th) => khopCap(th, [c])).length;
        if (n > 0) ra.push({ level: c, count: n });
    }
    return ra;
}
function demLoaiTu(ds: TheMau[]) {
    const dem = new Map<string, number>();
    for (const th of ds) if (th.pos) dem.set(th.pos, (dem.get(th.pos) ?? 0) + 1);
    return [...dem].map(([pos, count]) => ({ pos, count })).sort((a, b) => b.count - a.count || a.pos.localeCompare(b.pos));
}
const docCap = (u: URL) => (u.searchParams.get('cap') ?? '').split(',').filter(Boolean).map(Number);

/** Trả lời GET /chu-de/{slug} như máy chủ: lọc cấp, loại từ, chữ; 20 thẻ mỗi trang, trang quá lớn thì về trang cuối. */
export function trangChuDe(slug: string, u: URL): TrangChuDe | null {
    const cd = CHU_DE[slug as keyof typeof CHU_DE];
    if (!cd) return null;
    const goc = CAC_THE.filter((th) => th.chuDe === slug);
    const loai = u.searchParams.get('loai_tu');
    const q = u.searchParams.get('tim') ?? '';
    const loc = goc.filter((th) => khopCap(th, docCap(u)) && (!loai || th.pos === loai) && (!q || khopChu(th, q)));
    const pages = Math.max(1, Math.ceil(loc.length / 20));
    const page = Math.min(Number(u.searchParams.get('trang') ?? '1'), pages);
    return {
        data: {
            topic: { slug, name: cd.name, name_en: cd.name_en, icon: null, collection: { code: cd.bo, name: cd.boTen }, card_count: goc.length },
            parent: { slug: cd.parent[0], name: cd.parent[1] },
            group: { slug: cd.group[0], name: cd.group[1] },
            levels: demCap(goc),
            pos: demLoaiTu(goc),
            cards: loc.slice((page - 1) * 20, page * 20).map(tomTat),
        },
        meta: { page, per_page: 20, total: loc.length, pages },
    };
}

/** Trả lời GET /chu-de?bo=&cap=&loai_tu= như máy chủ. */
export function cayChuDe(u: URL): CayChuDe {
    const bo = u.searchParams.get('bo') ?? 'vocab';
    const loai = u.searchParams.get('loai_tu');
    const cap = docCap(u);
    const goc = CAC_THE.filter((th) => th.collection === bo);
    const loc = goc.filter((th) => khopCap(th, cap) && (!loai || th.pos === loai));
    const nhom = new Map<string, CayChuDe['groups'][number]>();
    // Thứ tự nhóm như máy chủ: Con số (sort 1) trước Ăn uống (sort 2)
    for (const slug of ['so-dem-tu-vung', 'banh-mi-tu-vung', 'o-tiem-banh-giao-tiep'] as const) {
        const cd = CHU_DE[slug];
        const n = loc.filter((th) => th.chuDe === slug).length;
        if (cd.bo !== bo || n === 0) continue;
        const g = nhom.get(cd.group[0]) ?? { slug: cd.group[0], name: cd.group[1], topic_count: 0, card_count: 0, parents: [] };
        g.topic_count += 1;
        g.card_count += n;
        g.parents.push({ slug: cd.parent[0], name: cd.parent[1], topics: [{ slug, name: cd.name, icon: null, card_count: n }] });
        nhom.set(cd.group[0], g);
    }
    const groups = [...nhom.values()];
    return {
        collection: { code: bo, name: bo === 'comm' ? 'Giao tiếp' : bo === 'phrase' ? 'Cụm câu' : 'Từ vựng' },
        topic_count: groups.reduce((a, g) => a + g.topic_count, 0),
        card_count: groups.reduce((a, g) => a + g.card_count, 0),
        levels: demCap(goc.filter((th) => !loai || th.pos === loai)),
        pos: demLoaiTu(goc.filter((th) => khopCap(th, cap))),
        groups,
    };
}

export function chiTiet(id: number, doi: Partial<TheChiTiet> = {}): TheChiTiet | null {
    const th = CAC_THE.find((x) => x.id === id);
    if (!th) return null;
    const trongChuDe = CAC_THE.filter((x) => x.chuDe === th.chuDe);
    const i = trongChuDe.findIndex((x) => x.id === id);
    const gon2 = (x?: TheMau) => (x ? { id: x.id, front: x.front } : null);
    const cd = CHU_DE[th.chuDe as keyof typeof CHU_DE];
    const rieng: Record<number, Partial<TheChiTiet>> = {
        1: {
            tip: 'Số nhiều giống số ít: ein Brötchen, zwei Brötchen.',
            examples: [
                { sentence: 'Ich möchte zwei Brötchen, bitte.', translations: { vi: 'Cho tôi hai cái bánh mì nhỏ.', en: 'Two bread rolls, please.' } },
                { sentence: 'Die Brötchen sind frisch.', translations: { vi: 'Bánh mì nhỏ còn tươi.' } },
            ],
        },
        4: {
            verb: {
                infinitive: 'backen', auxiliary: 'haben', is_separable: false, prefix: null, form_du: 'backst', form_er: 'backt', preterite: 'backte', perfect: 'hat gebacken',
                conjugation: {
                    pres: ['backe', 'backst', 'backt', 'backen', 'backt', 'backen'],
                    prat: ['backte', 'backtest', 'backte', 'backten', 'backtet', 'backten'],
                    perf: ['habe gebacken', 'hast gebacken', 'hat gebacken', 'haben gebacken', 'habt gebacken', 'haben gebacken'],
                },
            },
        },
    };
    return {
        ...tomTat(th),
        collection_name: cd.boTen,
        note: null,
        tip: null,
        lektion: null,
        extra: null,
        examples: [],
        verb: null,
        topic: { slug: th.chuDe, name: cd.name },
        nav: { index: i + 1, total: trongChuDe.length, prev: gon2(trongChuDe[i - 1]), next: gon2(trongChuDe[i + 1]) },
        bookmark: { starred: th.starred, note: null },
        ...rieng[id],
        ...doi,
    };
}

export function ketQuaTra(q: string): { data: KetQuaTra[]; meta: { total: number; limit: number } } {
    const thuong = q.toLowerCase();
    const ds = CAC_THE.filter((th) => khopChu(th, q))
        .map((th) => ({ th, hang: th.word.toLowerCase() === thuong ? 0 : th.forms?.toLowerCase().includes(thuong) ? 1 : gon(th.word) === gon(q) ? 2 : 5 }))
        .sort((a, b) => a.hang - b.hang || a.th.id - b.th.id);
    return {
        data: ds.slice(0, 30).map(({ th, hang }) => ({ ...tomTat(th), match: hang === 0 ? 'exact' : hang === 1 ? 'form' : hang === 2 ? 'close' : 'contains' })),
        meta: { total: ds.length, limit: 30 },
    };
}

export const LICH_SU: MucLichSu[] = [
    { id: 31, term: 'brot', card: { id: 2, front: 'das Brot' }, searched_at: 1764201600 },
    { id: 30, term: 'zahlen', card: null, searched_at: 1764201000 },
];

export function soTayMau(): TheSoTay[] {
    return [
        { ...tomTat(CAC_THE[2]), starred: true, bookmark: { starred: true, note: 'không có số nhiều', updated_at: 1764201600 } },
        { ...tomTat(CAC_THE[6]), bookmark: { starred: false, note: 'khác zählen (đếm)', updated_at: 1764201500 } },
        { ...tomTat(CAC_THE[1]), starred: true, bookmark: { starred: true, note: null, updated_at: 1764201400 } },
    ];
}

/** Đủ tuyến nội dung cho app (đè tuyến nào thì truyền vào doi). */
export function giaNoiDung(doi: Record<string, Tuyen> = {}): LanGoi[] {
    return giaApi({
        'GET /api/v1/bo-the': () => ({
            json: { data: [{ code: 'vocab', name: 'Từ vựng', card_count: 30 }, { code: 'phrase', name: 'Cụm câu', card_count: 12 }, { code: 'comm', name: 'Giao tiếp', card_count: 1 }] },
        }),
        'GET /api/v1/chu-de': (_b, u) => ({ json: { data: cayChuDe(u) } }),
        'GET /api/v1/tra-tu': (_b, u) => ({ json: ketQuaTra(u.searchParams.get('q') ?? '') }),
        'GET /api/v1/tra-tu/lich-su': () => ({ json: { data: LICH_SU } }),
        'POST /api/v1/tra-tu/lich-su': () => ({ status: 201, json: { data: { id: 40 } } }),
        'GET /api/v1/tra-chu': (_b, u) => {
            const chu = (u.searchParams.get('chu') ?? '').toLowerCase().replace(/[^\p{L}-]/gu, '');
            const ds = CAC_THE.filter((th) => th.word.toLowerCase() === chu || th.forms?.toLowerCase().split(/[\s·]+/).includes(chu));
            return { json: { data: ds.map(tomTat) } };
        },
        ...doi,
    });
}

/** Tuyến cho một trang chủ đề và các thẻ (dùng trong giaNoiDung qua doi). */
export const tuyenChuDe = (slug: string): Record<string, Tuyen> => ({
    [`GET /api/v1/chu-de/${slug}`]: (_b, u) => {
        const kq = trangChuDe(slug, u);
        return kq ? { json: kq } : { status: 404, json: { code: 'khong_thay', message: 'Không tìm thấy.' } };
    },
});

export const tuyenThe = (id: number, doi: Partial<TheChiTiet> = {}): Record<string, Tuyen> => ({
    [`GET /api/v1/the/${id}`]: () => {
        const kq = chiTiet(id, doi);
        return kq ? { json: { data: kq } } : { status: 404, json: { code: 'khong_thay', message: 'Không tìm thấy.' } };
    },
});
