// Dữ liệu mẫu cho bản phác G0: hai chủ đề nhỏ, tiến độ và lịch sử 14 ngày tự đặt.
// Nghĩa, mạo từ, số nhiều và câu ví dụ đã soát; G1 thay bằng dữ liệu thật qua API.

export type Gender = 'der' | 'die' | 'das';
export type PartOfSpeech = 'noun' | 'verb' | 'adj';
export type Cefr = 'A1' | 'A2' | 'B1';

export type Example = { de: string; vi: string };

export type Word = {
    id: string;
    topicId: string;
    lemma: string;
    gender?: Gender;
    pos: PartOfSpeech;
    /** Số nhiều (danh từ), null khi thường chỉ dùng số ít. */
    plural?: string | null;
    /** Ba dạng chính của động từ: ngôi ba hiện tại · quá khứ · Perfekt. */
    principalParts?: string;
    /** Chia thì hiện tại: ich, du, er/sie/es, wir, ihr, sie/Sie. */
    present?: [string, string, string, string, string, string];
    /** So sánh hơn · so sánh nhất (tính từ). */
    comparison?: string;
    cefr: Cefr;
    vi: string;
    en: string;
    zh: string;
    examples: Example[];
    tip?: string;
};

export type Topic = {
    id: string;
    /** Tên chủ đề nhỏ: 2–3 nghĩa tiêu biểu nối bằng " · ", như Lernbuch. */
    name: string;
    nameEn: string;
    group: string;
    parent: string;
};

export const topics: Topic[] = [
    { id: 'tiem-banh', name: 'Bánh mì · bánh ngọt · tiệm bánh', nameEn: 'Bread · cake · bakery', group: 'Ăn uống', parent: 'Bánh và tiệm bánh' },
    { id: 'trai-cay', name: 'Táo · chuối · cam', nameEn: 'Apple · banana · orange', group: 'Ăn uống', parent: 'Rau và trái cây' },
];

const w = (word: Omit<Word, 'examples'> & { examples?: Example[] }): Word => ({ examples: [], ...word });

export const words: Word[] = [
    // Bánh mì · bánh ngọt · tiệm bánh
    w({
        id: 'broetchen', topicId: 'tiem-banh', lemma: 'Brötchen', gender: 'das', pos: 'noun', plural: 'Brötchen', cefr: 'A1',
        vi: 'bánh mì nhỏ (ổ tròn)', en: 'bread roll', zh: '小面包',
        examples: [
            { de: 'Ich möchte zwei Brötchen, bitte.', vi: 'Cho tôi hai cái bánh mì nhỏ.' },
            { de: 'Morgens kaufe ich frische Brötchen beim Bäcker.', vi: 'Buổi sáng tôi mua bánh mì nhỏ còn tươi ở tiệm bánh.' },
            { de: 'Die Brötchen sind schön knusprig.', vi: 'Bánh mì nhỏ giòn ngon.' },
        ],
        tip: 'Số nhiều giống số ít: ein Brötchen, zwei Brötchen.',
    }),
    w({ id: 'brot', topicId: 'tiem-banh', lemma: 'Brot', gender: 'das', pos: 'noun', plural: 'Brote', cefr: 'A1', vi: 'bánh mì', en: 'bread', zh: '面包', examples: [{ de: 'Das Brot ist noch frisch.', vi: 'Bánh mì vẫn còn tươi.' }] }),
    w({ id: 'baeckerei', topicId: 'tiem-banh', lemma: 'Bäckerei', gender: 'die', pos: 'noun', plural: 'Bäckereien', cefr: 'A1', vi: 'tiệm bánh', en: 'bakery', zh: '面包店', examples: [{ de: 'Die Bäckerei öffnet um sechs Uhr.', vi: 'Tiệm bánh mở cửa lúc sáu giờ.' }] }),
    w({ id: 'baecker', topicId: 'tiem-banh', lemma: 'Bäcker', gender: 'der', pos: 'noun', plural: 'Bäcker', cefr: 'A1', vi: 'thợ làm bánh', en: 'baker', zh: '面包师', examples: [{ de: 'Der Bäcker steht jeden Tag um drei Uhr auf.', vi: 'Ngày nào thợ làm bánh cũng dậy lúc ba giờ.' }] }),
    w({ id: 'brezel', topicId: 'tiem-banh', lemma: 'Brezel', gender: 'die', pos: 'noun', plural: 'Brezeln', cefr: 'A2', vi: 'bánh xoắn kiểu Đức', en: 'pretzel', zh: '椒盐卷饼', examples: [{ de: 'In Bayern isst man gern Brezeln.', vi: 'Ở Bayern người ta thích ăn bánh xoắn.' }] }),
    w({ id: 'kuchen', topicId: 'tiem-banh', lemma: 'Kuchen', gender: 'der', pos: 'noun', plural: 'Kuchen', cefr: 'A1', vi: 'bánh ngọt', en: 'cake', zh: '蛋糕', examples: [{ de: 'Möchtest du ein Stück Kuchen?', vi: 'Bạn có muốn một miếng bánh ngọt không?' }, { de: 'Der Kuchen ist sehr lecker.', vi: 'Bánh ngọt rất ngon.' }] }),
    w({ id: 'torte', topicId: 'tiem-banh', lemma: 'Torte', gender: 'die', pos: 'noun', plural: 'Torten', cefr: 'A2', vi: 'bánh kem', en: 'gateau, cream cake', zh: '奶油蛋糕', examples: [{ de: 'Zum Geburtstag gibt es eine Torte.', vi: 'Sinh nhật thì có bánh kem.' }] }),
    w({ id: 'croissant', topicId: 'tiem-banh', lemma: 'Croissant', gender: 'das', pos: 'noun', plural: 'Croissants', cefr: 'A1', vi: 'bánh sừng bò', en: 'croissant', zh: '牛角面包' }),
    w({ id: 'vollkornbrot', topicId: 'tiem-banh', lemma: 'Vollkornbrot', gender: 'das', pos: 'noun', plural: 'Vollkornbrote', cefr: 'A2', vi: 'bánh mì nguyên cám', en: 'wholemeal bread', zh: '全麦面包' }),
    w({ id: 'mehl', topicId: 'tiem-banh', lemma: 'Mehl', gender: 'das', pos: 'noun', plural: null, cefr: 'A2', vi: 'bột mì', en: 'flour', zh: '面粉', examples: [{ de: 'Für das Brot braucht man Mehl, Wasser und Hefe.', vi: 'Làm bánh mì cần bột mì, nước và men.' }] }),
    w({ id: 'teig', topicId: 'tiem-banh', lemma: 'Teig', gender: 'der', pos: 'noun', plural: 'Teige', cefr: 'B1', vi: 'bột nhào', en: 'dough', zh: '面团' }),
    w({ id: 'hefe', topicId: 'tiem-banh', lemma: 'Hefe', gender: 'die', pos: 'noun', plural: null, cefr: 'B1', vi: 'men nở', en: 'yeast', zh: '酵母' }),
    w({ id: 'zucker', topicId: 'tiem-banh', lemma: 'Zucker', gender: 'der', pos: 'noun', plural: null, cefr: 'A1', vi: 'đường', en: 'sugar', zh: '糖' }),
    w({ id: 'butter', topicId: 'tiem-banh', lemma: 'Butter', gender: 'die', pos: 'noun', plural: null, cefr: 'A1', vi: 'bơ', en: 'butter', zh: '黄油' }),
    w({ id: 'ei', topicId: 'tiem-banh', lemma: 'Ei', gender: 'das', pos: 'noun', plural: 'Eier', cefr: 'A1', vi: 'quả trứng', en: 'egg', zh: '鸡蛋' }),
    w({ id: 'scheibe', topicId: 'tiem-banh', lemma: 'Scheibe', gender: 'die', pos: 'noun', plural: 'Scheiben', cefr: 'A2', vi: 'lát (mỏng)', en: 'slice', zh: '片', examples: [{ de: 'Eine Scheibe Brot, bitte.', vi: 'Cho tôi một lát bánh mì.' }] }),
    w({ id: 'stueck', topicId: 'tiem-banh', lemma: 'Stück', gender: 'das', pos: 'noun', plural: 'Stücke', cefr: 'A1', vi: 'miếng', en: 'piece', zh: '块' }),
    w({ id: 'tuete', topicId: 'tiem-banh', lemma: 'Tüte', gender: 'die', pos: 'noun', plural: 'Tüten', cefr: 'A2', vi: 'túi (giấy, ni lông)', en: 'bag', zh: '袋子', examples: [{ de: 'Brauchen Sie eine Tüte?', vi: 'Anh chị có cần túi không?' }] }),
    w({ id: 'kleingeld', topicId: 'tiem-banh', lemma: 'Kleingeld', gender: 'das', pos: 'noun', plural: null, cefr: 'A2', vi: 'tiền lẻ', en: 'small change', zh: '零钱', examples: [{ de: 'Haben Sie Kleingeld?', vi: 'Anh chị có tiền lẻ không?' }] }),
    w({
        id: 'backen', topicId: 'tiem-banh', lemma: 'backen', pos: 'verb', cefr: 'A2', vi: 'nướng (bánh)', en: 'to bake', zh: '烤',
        principalParts: 'backt (bäckt) · backte · hat gebacken',
        present: ['backe', 'backst / bäckst', 'backt / bäckt', 'backen', 'backt', 'backen'],
        examples: [{ de: 'Wir backen am Wochenende einen Kuchen.', vi: 'Cuối tuần chúng tôi nướng một cái bánh ngọt.' }],
    }),
    w({ id: 'frisch', topicId: 'tiem-banh', lemma: 'frisch', pos: 'adj', cefr: 'A1', vi: 'tươi', en: 'fresh', zh: '新鲜', comparison: 'frischer · am frischesten' }),
    w({ id: 'suess', topicId: 'tiem-banh', lemma: 'süß', pos: 'adj', cefr: 'A1', vi: 'ngọt', en: 'sweet', zh: '甜', comparison: 'süßer · am süßesten' }),
    w({ id: 'knusprig', topicId: 'tiem-banh', lemma: 'knusprig', pos: 'adj', cefr: 'B1', vi: 'giòn', en: 'crispy', zh: '酥脆', comparison: 'knuspriger · am knusprigsten' }),
    w({ id: 'lecker', topicId: 'tiem-banh', lemma: 'lecker', pos: 'adj', cefr: 'A1', vi: 'ngon', en: 'tasty', zh: '好吃', comparison: 'leckerer · am leckersten' }),

    // Táo · chuối · cam
    w({ id: 'apfel', topicId: 'trai-cay', lemma: 'Apfel', gender: 'der', pos: 'noun', plural: 'Äpfel', cefr: 'A1', vi: 'quả táo', en: 'apple', zh: '苹果', examples: [{ de: 'Ich esse jeden Tag einen Apfel.', vi: 'Ngày nào tôi cũng ăn một quả táo.' }] }),
    w({ id: 'banane', topicId: 'trai-cay', lemma: 'Banane', gender: 'die', pos: 'noun', plural: 'Bananen', cefr: 'A1', vi: 'quả chuối', en: 'banana', zh: '香蕉' }),
    w({ id: 'orange', topicId: 'trai-cay', lemma: 'Orange', gender: 'die', pos: 'noun', plural: 'Orangen', cefr: 'A1', vi: 'quả cam', en: 'orange', zh: '橙子' }),
    w({ id: 'birne', topicId: 'trai-cay', lemma: 'Birne', gender: 'die', pos: 'noun', plural: 'Birnen', cefr: 'A1', vi: 'quả lê', en: 'pear', zh: '梨' }),
    w({ id: 'erdbeere', topicId: 'trai-cay', lemma: 'Erdbeere', gender: 'die', pos: 'noun', plural: 'Erdbeeren', cefr: 'A1', vi: 'quả dâu tây', en: 'strawberry', zh: '草莓' }),
    w({ id: 'traube', topicId: 'trai-cay', lemma: 'Traube', gender: 'die', pos: 'noun', plural: 'Trauben', cefr: 'A1', vi: 'quả nho', en: 'grape', zh: '葡萄' }),
    w({ id: 'zitrone', topicId: 'trai-cay', lemma: 'Zitrone', gender: 'die', pos: 'noun', plural: 'Zitronen', cefr: 'A1', vi: 'quả chanh vàng', en: 'lemon', zh: '柠檬' }),
    w({ id: 'kirsche', topicId: 'trai-cay', lemma: 'Kirsche', gender: 'die', pos: 'noun', plural: 'Kirschen', cefr: 'A2', vi: 'quả anh đào', en: 'cherry', zh: '樱桃' }),
    w({ id: 'pflaume', topicId: 'trai-cay', lemma: 'Pflaume', gender: 'die', pos: 'noun', plural: 'Pflaumen', cefr: 'A2', vi: 'quả mận', en: 'plum', zh: '李子' }),
    w({ id: 'pfirsich', topicId: 'trai-cay', lemma: 'Pfirsich', gender: 'der', pos: 'noun', plural: 'Pfirsiche', cefr: 'A2', vi: 'quả đào', en: 'peach', zh: '桃子' }),
    w({ id: 'melone', topicId: 'trai-cay', lemma: 'Melone', gender: 'die', pos: 'noun', plural: 'Melonen', cefr: 'A2', vi: 'quả dưa', en: 'melon', zh: '瓜' }),
    w({ id: 'ananas', topicId: 'trai-cay', lemma: 'Ananas', gender: 'die', pos: 'noun', plural: 'Ananas (Ananasse)', cefr: 'A2', vi: 'quả dứa', en: 'pineapple', zh: '菠萝' }),
];

/** Tiến độ ban đầu của từng thẻ: mức 0–5 và hạn ôn tính theo ngày so với hôm nay (âm: đã quá hạn). */
export type Progress = { level: number; dueInDays: number | null; lapses: number };

const P = (level: number, dueInDays: number | null, lapses = 0): Progress => ({ level, dueInDays, lapses });

export const initialProgress: Record<string, Progress> = {
    broetchen: P(2, 0), brot: P(3, -1), baeckerei: P(1, 0, 1), baecker: P(2, 0), brezel: P(1, 0),
    kuchen: P(4, 6), torte: P(3, 2), croissant: P(5, 21), vollkornbrot: P(2, 1), mehl: P(1, 1, 2),
    teig: P(0, null), hefe: P(0, null), zucker: P(4, 9), butter: P(5, 18), ei: P(3, 3),
    scheibe: P(0, null), stueck: P(0, null), tuete: P(0, null), kleingeld: P(0, null), backen: P(0, null),
    frisch: P(2, 2), suess: P(0, null), knusprig: P(0, null), lecker: P(3, 4),
    apfel: P(3, 0), banane: P(2, 0), orange: P(2, -2), birne: P(1, 0), erdbeere: P(4, 0), traube: P(1, 0),
    zitrone: P(2, 0), kirsche: P(1, 3), pflaume: P(0, null), pfirsich: P(0, null), melone: P(1, 5), ananas: P(0, null),
};

/** Số thẻ đã chấm mỗi ngày, 10 ngày trước hôm nay (cũ nhất trước). */
export const pastReviews = [12, 0, 18, 25, 9, 0, 22, 31, 14, 20];
/** Thẻ đã chấm hôm nay trước khi mở phiên mới. */
export const reviewedTodayBefore = 8;
export const coinsBefore = 340;
export const dailyGoal = 20;
/** Chủ đề đang học: học hết từ mới ở đây mới sang chủ đề kế. */
export const currentTopicId = 'tiem-banh';
