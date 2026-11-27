// Dữ liệu giả cho test màn hình Ngữ pháp, Bài tập, Động từ: cùng dạng với API /api/v1 (gần với NoiDungThuSeeder::taoLuyenTap).
import type { BaiNguPhap, BoBai, CauBaiTap, CauChiaDongTu, Chuong, DanhSachBaiTap, DanhSachNguPhap, DongTuBang, KetQuaNhan, TheDienCau } from '../app/lib/baiTap';

export const CHUONG: Chuong[] = [
    { code: 'A1-1', level: 'A1', no: 1, name: 'Danh từ & mạo từ' },
    { code: 'A1-9', level: 'A1', no: 9, name: 'Bổ trợ: phát âm, số, giờ' },
    { code: 'A2-3', level: 'A2', no: 3, name: 'Động từ' },
];

export const DS_NGU_PHAP: DanhSachNguPhap = {
    chapters: CHUONG,
    lessons: [
        { slug: 'a1-1', title: 'Nền tảng: đại từ, mạo từ, chia động từ, trật tự câu', level: 'A1', chapter: null, exercises: 0 },
        { slug: 'slang', title: 'Tiếng lóng & tiếng Đức đời thường', level: null, chapter: null, exercises: 0 },
        { slug: 'g-A1-22', title: 'Genus: der, die, das  ·  Giống: der, die, das', level: 'A1', chapter: 'A1-1', exercises: 7 },
        { slug: 'g-A1-23', title: 'Plural  ·  Số nhiều', level: 'A1', chapter: 'A1-1', exercises: 3 },
        { slug: 'g-A1-90', title: 'Die Uhrzeit  ·  Giờ giấc', level: 'A1', chapter: 'A1-9', exercises: 0 },
        { slug: 'g-A2-05', title: 'Perfekt  ·  Thì Perfekt', level: 'A2', chapter: 'A2-3', exercises: 12 },
    ],
};

export const BAI_GIONG: BaiNguPhap = {
    slug: 'g-A1-22',
    title: 'Genus: der, die, das  ·  Giống: der, die, das',
    level: 'A1',
    chapter: CHUONG[0],
    blocks: [
        { t: 'fm', x: 'der (m) · die (f) · das (n) · die (Plural)', L: { vi: 'Mỗi danh từ có một giống. Luôn học danh từ KÈM mạo từ.', en: 'Every noun has a gender.' } },
        { t: 'tipl', L: { vi: 'Đuôi -ung, -heit, -keit → die. LUÔN nhớ mạo từ.', en: 'Endings -ung, -heit, -keit → die.' } },
        { t: 'h2', x: 'Ví dụ' },
        { t: 'ex', x: 'Das Brot ist frisch.', who: 'das', L: { vi: 'Bánh mì còn tươi.', en: 'The bread is fresh.' } },
        { t: 'h3', x: 'Câu hỏi (A1)' },
        { t: 'ex', x: 'Ist die Lampe neu?', tr: 'Cái đèn có mới không?' },
        { t: 'h2', x: 'Bảng mạo từ – S. 12' },
        {
            t: 'tbl',
            rows: [
                [
                    ['', 1, 0],
                    ['giống đực', 1, 1],
                    ['giống cái', 1, 1],
                ],
                [
                    ['Nominativ', 1, 2],
                    ['der', 1, 0],
                    ['die', 1, 0],
                ],
            ],
        },
        { t: 'h2', x: 'Ghi nhớ' },
        { t: 'li', x: 'Danh từ viết hoa chữ cái đầu.' },
        { t: 'li', x: 'Số nhiều luôn là die.' },
        { t: 'kv', k: '-ung', x: 'die Zeitung, die Wohnung' },
        { t: 'kv', k: '-chen', x: 'das Mädchen, das Brötchen' },
        { t: 'p', x: 'Every noun has a gender.\n每个名词都有词性。\nMỗi danh từ có một giống.' },
        { t: 'recap', x: 'Học danh từ luôn kèm mạo từ.' },
        { t: 'note', x: 'Ghi chú: das Mädchen là giống trung.' },
    ],
    sets: [
        { slug: 'bt-LB3', title: 'Giống danh từ & mạo từ (der / die / das)', count: 4 },
        { slug: 'dx-g1-1A', title: '1A · Điền der / die / das', count: 3 },
    ],
    prev: null,
    next: { slug: 'g-A1-23', title: 'Plural  ·  Số nhiều' },
};

const bo = (slug: string, title: string, doi: Partial<BoBai> = {}): BoBai => ({
    slug,
    title,
    section: 'level',
    level: 'A1',
    lesson: null,
    group: null,
    note: null,
    count: 4,
    done: 0,
    weak: 0,
    ...doi,
});

export const DS_BAI_TAP: DanhSachBaiTap = {
    sets: [
        bo('bt-LB3', 'Giống danh từ & mạo từ (der / die / das)', { lesson: 'g-A1-22', done: 1, weak: 2 }),
        bo('dx-g1-1A', '1A · Điền der / die / das', { lesson: 'g-A1-22', group: 'Chương 1 · Artikel & Genus', note: 'Mạo từ xác định ở Nominativ.', count: 3 }),
        bo('dx-g2-2A', '2A · Đại từ nhân xưng', { group: 'Chương 2 · Pronomen', count: 3 }),
        bo('bt-A2-11', 'Dativ, Imperativ, sollen', { level: 'A2', lesson: 'g-A2-05', count: 5 }),
        bo('bt-B1-Gram', 'Ngữ pháp B1 tổng hợp', { level: 'B1', count: 6 }),
        bo('dx-v-on1', 'Ôn tập 1', { section: 'review', level: null, group: 'Ôn tập 1 · Bài 1 đến Bài 2', count: 4 }),
        bo('dx-v1-1A', '1A · Điền từ vào chỗ trống', { section: 'review', level: null, group: 'Bài 1 · Person & Familie', count: 2 }),
        bo('dx-v2-2A', '2A · Điền từ vào chỗ trống', { section: 'review', level: null, group: 'Bài 2 · Wohnen', count: 2 }),
        bo('pl-e', 'Số nhiều: Thêm -e', {
            section: 'plural',
            level: null,
            lesson: 'g-A1-23',
            group: 'Số nhiều',
            note: 'Việc cần làm: viết dạng số nhiều của danh từ. Quy tắc: nhiều danh từ một âm tiết thêm -e.',
            count: 3,
            done: 1,
        }),
        bo('pl-n', 'Số nhiều: Thêm -n', { section: 'plural', level: null, lesson: 'g-A1-23', group: 'Số nhiều', count: 2 }),
    ],
    lessons: [
        { slug: 'g-A1-22', title: 'Genus: der, die, das  ·  Giống: der, die, das', level: 'A1', chapter: 'A1-1' },
        { slug: 'g-A1-23', title: 'Plural  ·  Số nhiều', level: 'A1', chapter: 'A1-1' },
        { slug: 'g-A2-05', title: 'Perfekt  ·  Thì Perfekt', level: 'A2', chapter: 'A2-3' },
    ],
    chapters: [CHUONG[0], CHUONG[2]],
};

const cau = (id: number, doi: Partial<CauBaiTap>): CauBaiTap => ({
    id,
    kind: 'fill',
    prompt: '',
    answers: [],
    options: null,
    hint: null,
    explanation: null,
    label: null,
    instr: null,
    case_sensitive: false,
    bank: null,
    tokens: null,
    lead: null,
    frag: [],
    set: { slug: 'bt-LB3', title: 'Giống danh từ & mạo từ (der / die / das)', group: null, note: null },
    ...doi,
});

/** Bộ bt-LB3: trắc nghiệm, điền (phân biệt hoa thường), sắp xếp, sửa lỗi. */
export const CAU_LB3: CauBaiTap[] = [
    cau(101, { kind: 'mc', prompt: '____ Tisch ist neu.', answers: ['Der'], options: ['Die', 'Der', 'Das'], explanation: 'Tisch là giống đực: der Tisch.', label: 'Chọn đáp án đúng' }),
    cau(102, { prompt: '____ Buch ist interessant.', answers: ['Das'], case_sensitive: true, explanation: 'Buch là giống trung: das Buch.' }),
    cau(103, { kind: 'order', answers: ['Das Kind spielt im Garten'], tokens: ['spielt', 'Das Kind', 'im Garten'], hint: 'Từ đầu câu viết hoa.' }),
    cau(104, { kind: 'fix', prompt: 'Das Frau heißt Anna.', answers: ['Die Frau heißt Anna'], frag: ['Die Frau'], explanation: 'Frau là giống cái → die Frau.' }),
];

const boOnTap = { slug: 'dx-g1-1A', title: '1A · Điền der / die / das', group: 'Chương 1 · Artikel & Genus', note: 'Mạo từ xác định ở Nominativ: der, die, das.' };

/** Bộ dx-g1-1A (làm cả phiếu): điền một chỗ trống, điền có từ cho sẵn, trắc nghiệm. */
export const CAU_PHIEU: CauBaiTap[] = [
    cau(201, { prompt: 'Hier ist ____ Lehrer.', answers: ['der'], instr: 'Điền der, die hoặc das.', set: boOnTap }),
    cau(202, { prompt: 'Meine ___ ist groß.', answers: ['Familie'], bank: ['Familie', 'Onkel'], set: boOnTap }),
    cau(203, { kind: 'mc', prompt: '____ Lampe ist hell.', answers: ['Die'], options: ['Der', 'Die', 'Das'], explanation: 'Lampe là giống cái.', set: boOnTap }),
];

export const CAU_DONG_TU: CauChiaDongTu[] = [
    { sig: 'v:lesen:du', kind: 'fill', prompt: 'lesen → du', answers: ['liest'], explanation: 'liest · liest · las · hat gelesen', meanings: { vi: 'đọc', en: 'to read' }, verb: 'lesen', audio: null },
    { sig: 'v:anrufen:prat', kind: 'fill', prompt: 'anrufen → Präteritum (er)', answers: ['rief an'], explanation: 'rufst an · ruft an · rief an · hat angerufen', meanings: { vi: 'gọi điện' }, verb: 'anrufen', audio: null },
];

export const THE_DIEN_CAU: TheDienCau[] = [
    {
        id: 2,
        collection: 'vocab',
        front: 'das Brot',
        article: 'das',
        word: 'Brot',
        gender: 'n',
        pos: 'noun',
        forms: 'die Brote',
        level: 'A1',
        image: null,
        meanings: { vi: 'bánh mì', en: 'bread' },
        audio: null,
        starred: false,
        cloze: 'Das ____ ist noch frisch.',
        answer: 'Brot',
        case_sensitive: true,
        sentence: { sentence: 'Das Brot ist noch frisch.', translations: { vi: 'Bánh mì vẫn còn tươi.' } },
        topic: { slug: 'banh-mi-tu-vung', name: 'Bánh mì · bánh ngọt' },
        box: 0,
    },
    {
        id: 9,
        collection: 'vocab',
        front: 'zählen',
        article: null,
        word: 'zählen',
        gender: null,
        pos: 'verb',
        forms: 'er zählt · hat gezählt',
        level: 'A1',
        image: null,
        meanings: { vi: 'đếm' },
        audio: null,
        starred: false,
        cloze: 'Das Kind ____ bis zehn.',
        answer: 'zählt',
        case_sensitive: false,
        sentence: null,
        topic: { slug: 'so-dem-tu-vung', name: 'Số đếm' },
        box: 1,
    },
];

const dongTu = (infinitive: string, doi: Partial<DongTuBang>): DongTuBang => ({
    infinitive,
    level: 'A1',
    is_separable: false,
    prefix: null,
    auxiliary: 'haben',
    form_du: null,
    form_er: null,
    preterite: null,
    perfect: null,
    meanings: {},
    conjugation: null,
    card_id: null,
    ...doi,
});

export const DONG_TU: DongTuBang[] = [
    dongTu('anrufen', { is_separable: true, prefix: 'an', form_du: 'rufst an', form_er: 'ruft an', preterite: 'rief an', perfect: 'hat angerufen', meanings: { vi: 'gọi điện', en: 'to call' } }),
    dongTu('backen', {
        level: 'A2',
        form_du: 'backst',
        form_er: 'backt',
        preterite: 'backte',
        perfect: 'hat gebacken',
        meanings: { vi: 'nướng (bánh)' },
        card_id: 4,
        conjugation: {
            pres: ['backe', 'backst', 'backt', 'backen', 'backt', 'backen'],
            prat: ['backte', 'backtest', 'backte', 'backten', 'backtet', 'backten'],
            perf: ['habe gebacken', 'hast gebacken', 'hat gebacken', 'haben gebacken', 'habt gebacken', 'haben gebacken'],
            imp: ['back!', 'backt!', 'backen Sie!'],
        },
    }),
];

export const nhanKetQua = (doi: Partial<KetQuaNhan> = {}): { json: { data: KetQuaNhan } } => ({
    json: { data: { duplicate: false, coins: 10, correct: 2, total: 4, too_fast: false, items: [], today: { coins: 10, coins_today: 10, exercises_today: 4 }, ...doi } },
});
