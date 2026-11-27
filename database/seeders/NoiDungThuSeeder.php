<?php

namespace Database\Seeders;

use App\Support\NoiDung;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Nội dung mẫu nhỏ cho test và máy dev khi chưa nhập Lernbuch: 2 nhóm, 2 chủ đề vừa, 4 chủ đề nhỏ (1 đã ẩn),
 * thẻ đủ loại (có cấp, khoảng cấp, chưa xếp cấp, nháp, thẻ riêng của một người). Nghĩa và câu ví dụ đã soát.
 * taoLuyenTap(): thêm ngữ pháp, bài tập 4 dạng (theo cấp, Ôn tập, Số nhiều), động từ, câu Điền câu.
 * Chạy: php artisan db:seed --class=NoiDungThuSeeder. Không chạy trên máy chủ thật, không chạy khi đã có nội dung.
 */
class NoiDungThuSeeder extends Seeder
{
    /** @var array<string, int> mã → id chủ đề */
    public array $chuDe = [];

    /** @var array<string, int> mặt trước → id thẻ */
    public array $the = [];

    /** @var array<string, int> slug → id bài ngữ pháp */
    public array $bai = [];

    /** @var array<string, int> slug → id bộ bài tập */
    public array $bo = [];

    /** @var array<string, int> mã câu (sig) → id câu bài tập */
    public array $cau = [];

    /** 22 số đếm cho chủ đề Số đếm (để có 2 trang). */
    public const SO_DEM = [
        ['null', 'số không', 'zero'], ['eins', 'số một', 'one'], ['zwei', 'số hai', 'two'], ['drei', 'số ba', 'three'],
        ['vier', 'số bốn', 'four'], ['fünf', 'số năm', 'five'], ['sechs', 'số sáu', 'six'], ['sieben', 'số bảy', 'seven'],
        ['acht', 'số tám', 'eight'], ['neun', 'số chín', 'nine'], ['zehn', 'số mười', 'ten'], ['elf', 'số mười một', 'eleven'],
        ['zwölf', 'số mười hai', 'twelve'], ['dreizehn', 'số mười ba', 'thirteen'], ['vierzehn', 'số mười bốn', 'fourteen'],
        ['fünfzehn', 'số mười lăm', 'fifteen'], ['sechzehn', 'số mười sáu', 'sixteen'], ['siebzehn', 'số mười bảy', 'seventeen'],
        ['achtzehn', 'số mười tám', 'eighteen'], ['neunzehn', 'số mười chín', 'nineteen'], ['zwanzig', 'số hai mươi', 'twenty'],
        ['dreißig', 'số ba mươi', 'thirty'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Bỏ qua: không thêm nội dung mẫu trên máy chủ thật.');

            return;
        }

        if (DB::table('topics')->where('lang', NoiDung::LANG)->exists()) {
            $this->command?->warn('Bỏ qua: database đã có chủ đề (đã nhập Lernbuch hoặc đã có nội dung mẫu).');

            return;
        }

        $this->tao()->taoLuyenTap();
    }

    /** Thêm nội dung mẫu; $chuTheRieng là người có một thẻ riêng (thẻ "der Hefezopf"), để test quyền xem. */
    public function tao(?int $chuTheRieng = null): static
    {
        $m = $this;
        $bay = time();
        $chuDe = function (string $ma, int $depth, ?string $cha, ?string $bo, string $slug, string $vi, ?string $en, int $sort, string $trangThai = 'published') use ($m, $bay): void {
            $m->chuDe[$ma] = (int) DB::table('topics')->insertGetId([
                'lang' => 'de', 'parent_id' => $cha !== null ? $m->chuDe[$cha] : null, 'depth' => $depth, 'collection' => $bo, 'code' => $ma,
                'slug' => $slug, 'name_vi' => $vi, 'name_en' => $en, 'icon' => $depth === 3 ? '1F35E' : null, 'sort' => $sort, 'status' => $trangThai,
                'created_at' => $bay, 'updated_at' => $bay,
            ]);
        };

        $chuDe('g1', 1, null, null, 'an-uong', 'Ăn uống', 'Food & drink', 2);
        $chuDe('g2', 1, null, null, 'con-so', 'Con số', 'Numbers', 1);
        $chuDe('p1', 2, 'g1', null, 'banh-va-tiem-banh', 'Bánh và tiệm bánh', 'Bread and bakery', 1);
        $chuDe('p2', 2, 'g2', null, 'dem-so', 'Đếm số', 'Counting', 1);
        $chuDe('t-banh.v', 3, 'p1', 'vocab', 'banh-mi-tu-vung', 'Bánh mì · bánh ngọt', 'Bread · cake', 1);
        $chuDe('t-banh.c', 3, 'p1', 'comm', 'o-tiem-banh-giao-tiep', 'Ở tiệm bánh', 'At the bakery', 2);
        $chuDe('t-so.v', 3, 'p2', 'vocab', 'so-dem-tu-vung', 'Số đếm', 'Numbers', 1);
        $chuDe('t-cu.v', 3, 'p2', 'vocab', 'chu-de-da-an-tu-vung', 'Chủ đề đã ẩn', null, 2, 'archived');

        $m->themThe('t-banh.v', 'das Brötchen', 'noun', 'die Brötchen', 1, 1, 1, ['vi' => 'bánh mì nhỏ (ổ tròn)', 'en' => 'bread roll', 'zh' => '小面包'],
            ['lemma' => ['brötchen'], 'plural' => ['brötchen']], 'das',
            [['Ich möchte zwei Brötchen, bitte.', ['vi' => 'Cho tôi hai cái bánh mì nhỏ.', 'en' => 'Two bread rolls, please.']], ['Die Brötchen sind frisch.', ['vi' => 'Bánh mì nhỏ còn tươi.']]],
            tip: 'Số nhiều giống số ít: ein Brötchen, zwei Brötchen.', image: '1F956');
        $m->themThe('t-banh.v', 'das Brot', 'noun', 'die Brote', 1, 1, 1, ['vi' => 'bánh mì', 'en' => 'bread', 'zh' => '面包'],
            ['lemma' => ['brot'], 'plural' => ['brote']], 'das', [['Das Brot ist noch frisch.', ['vi' => 'Bánh mì vẫn còn tươi.']]]);
        $m->themThe('t-banh.v', 'das Mehl', 'noun', '–', 2, 2, 2, ['vi' => 'bột mì', 'en' => 'flour', 'zh' => '面粉'], ['lemma' => ['mehl']], 'das');
        $m->themThe('t-banh.v', 'backen', 'verb', 'er backt · hat gebacken', 2, 2, 2, ['vi' => 'nướng (bánh)', 'en' => 'to bake', 'zh' => '烤'],
            ['lemma' => ['backen'], 'conj' => ['backt', 'gebacken']]);
        $m->themThe('t-banh.v', 'süß', 'adj', 'süßer · am süßesten', null, 1, 6, ['vi' => 'ngọt', 'en' => 'sweet', 'zh' => '甜'],
            ['lemma' => ['süß'], 'comparative' => ['süßer', 'süßesten']]);
        $m->themThe('t-banh.v', 'der Teig', 'noun', 'die Teige', 3, 3, 3, ['vi' => 'bột nhào', 'en' => 'dough'], ['lemma' => ['teig'], 'plural' => ['teige']], 'der', trangThai: 'draft');
        if ($chuTheRieng !== null) {
            $m->themThe('t-banh.v', 'der Hefezopf', 'noun', 'die Hefezöpfe', 2, 2, 2, ['vi' => 'bánh mì bện'], ['lemma' => ['hefezopf']], 'der', chu: $chuTheRieng);
        }

        $m->themThe('t-banh.c', 'Ich hätte gern zwei Brötchen.', 'sentence', null, 1, 1, 1, ['vi' => 'Cho tôi hai cái bánh mì nhỏ.', 'en' => "I'd like two bread rolls."],
            ['lemma' => ['ich hätte gern zwei brötchen']], bo: 'comm');

        $m->themThe('t-so.v', 'zahlen', 'verb', 'er zahlt · hat gezahlt', 1, 1, 1, ['vi' => 'trả tiền', 'en' => 'to pay', 'zh' => '付钱'],
            ['lemma' => ['zahlen'], 'conj' => ['zahlt', 'gezahlt']]);
        $m->themThe('t-so.v', 'zählen', 'verb', 'er zählt · hat gezählt', 1, 1, 1, ['vi' => 'đếm', 'en' => 'to count', 'zh' => '数'],
            ['lemma' => ['zählen'], 'conj' => ['zählt', 'gezählt']]);
        $m->themThe('t-so.v', 'die Zahl', 'noun', 'die Zahlen', 1, 1, 1, ['vi' => 'con số', 'en' => 'number', 'zh' => '数字'],
            ['lemma' => ['zahl'], 'plural' => ['zahlen']], 'die');
        foreach (self::SO_DEM as [$de, $vi, $en]) {
            $m->themThe('t-so.v', $de, 'other', null, 1, 1, 1, ['vi' => $vi, 'en' => $en], ['lemma' => [$de]]);
        }

        DB::table('verbs')->insert([
            'lang' => 'de', 'card_id' => $m->the['backen'], 'infinitive' => 'backen', 'level' => 2, 'is_separable' => 0, 'auxiliary' => 'haben',
            'form_du' => 'backst', 'form_er' => 'backt', 'preterite' => 'backte', 'perfect' => 'hat gebacken',
            'meanings' => json_encode(['vi' => 'nướng (bánh)', 'en' => 'to bake'], JSON_UNESCAPED_UNICODE),
            'conjugation' => json_encode([
                'pres' => ['backe', 'backst', 'backt', 'backen', 'backt', 'backen'],
                'prat' => ['backte', 'backtest', 'backte', 'backten', 'backtet', 'backten'],
                'perf' => ['habe gebacken', 'hast gebacken', 'hat gebacken', 'haben gebacken', 'habt gebacken', 'haben gebacken'],
            ]),
            'status' => 'published', 'created_at' => $bay, 'updated_at' => $bay,
        ]);

        return $m;
    }

    /**
     * Luyện tập: 1 chương, 4 bài ngữ pháp (2 bài theo chương, 1 bài giáo trình gốc, 1 chuyên đề không cấp) và 1 bài nháp;
     * 4 bộ bài tập (theo cấp, Ôn tập ngữ pháp gắn với bài, Ôn tập từ vựng, Số nhiều) đủ 4 dạng câu, 1 bộ nháp, 1 câu nháp;
     * thêm 2 động từ (một động từ tách); câu Điền câu cho vài thẻ (cả thẻ Giao tiếp và thẻ nháp, để test bỏ chúng).
     */
    public function taoLuyenTap(): static
    {
        $bay = time();
        $chuong = (int) DB::table('grammar_chapters')->insertGetId(['lang' => 'de', 'code' => 'A1-1', 'name_vi' => 'Danh từ & mạo từ', 'sort' => 101, 'created_at' => $bay, 'updated_at' => $bay]);
        $bai = function (string $slug, string $tieuDe, ?int $cap, ?int $chuongId, int $sort, array $khoi, string $trangThai = 'published') use ($bay): void {
            $this->bai[$slug] = (int) DB::table('grammar_lessons')->insertGetId([
                'lang' => 'de', 'base_lang' => 'vi', 'chapter_id' => $chuongId, 'slug' => $slug, 'level' => $cap, 'title' => $tieuDe,
                'blocks' => json_encode($khoi, JSON_UNESCAPED_UNICODE), 'status' => $trangThai, 'sort' => $sort, 'published_at' => $bay,
                'created_at' => $bay, 'updated_at' => $bay,
            ]);
        };

        $bai('g-A1-22', 'Genus: der, die, das  ·  Giống: der, die, das', 1, $chuong, 10122, [
            ['t' => 'fm', 'x' => 'der (m) · die (f) · das (n) · die (Plural)', 'L' => ['vi' => 'Mỗi danh từ có một giống. Luôn học danh từ kèm mạo từ.', 'en' => 'Every noun has a gender. Always learn the noun with its article.']],
            ['t' => 'tipl', 'L' => ['vi' => 'Đuôi -ung, -heit, -keit → die; đuôi -chen, -lein → das.', 'en' => 'Endings -ung, -heit, -keit → die; -chen, -lein → das.']],
            ['t' => 'h2', 'x' => 'Ví dụ'],
            ['t' => 'ex', 'x' => 'Das Brot ist frisch.', 'who' => 'das', 'L' => ['vi' => 'Bánh mì còn tươi.', 'en' => 'The bread is fresh.']],
            ['t' => 'ex', 'x' => 'Die Zeitung liegt auf dem Tisch.', 'who' => 'die', 'L' => ['vi' => 'Tờ báo nằm trên bàn.', 'en' => 'The newspaper is on the table.']],
            ['t' => 'h2', 'x' => 'Bảng mạo từ'],
            ['t' => 'tbl', 'rows' => [[['', 1, 0], ['giống đực', 1, 1], ['giống cái', 1, 1], ['giống trung', 1, 1]], [['Nominativ', 1, 2], ['der', 1, 0], ['die', 1, 0], ['das', 1, 0]]]],
            ['t' => 'recap', 'x' => 'Học danh từ luôn kèm mạo từ: der Tisch, die Lampe, das Buch.'],
        ]);
        $bai('g-A1-23', 'Plural  ·  Số nhiều', 1, $chuong, 10123, [
            ['t' => 'p', 'x' => 'Danh từ số nhiều luôn đi với mạo từ die.'],
            ['t' => 'kv', 'k' => '-e', 'x' => 'der Tisch → die Tische · der Hund → die Hunde'],
            ['t' => 'kv', 'k' => '-(e)n', 'x' => 'die Lampe → die Lampen · die Frau → die Frauen'],
            ['t' => 'note', 'x' => 'Danh từ đuôi -chen, -lein không đổi ở số nhiều: das Brötchen → die Brötchen.'],
        ]);
        $bai('a1-1', 'Nền tảng: đại từ, mạo từ, chia động từ, trật tự câu', 1, null, 1, [
            ['t' => 'h2', 'x' => 'Đại từ nhân xưng'],
            ['t' => 'tbl', 'rows' => [[['', 1, 0], ['ich', 1, 1], ['du', 1, 1], ['er', 1, 1], ['wir', 1, 1]], [['Nominativ', 1, 2], ['ich', 1, 0], ['du', 1, 0], ['er', 1, 0], ['wir', 1, 0]]]],
            ['t' => 'li', 'x' => 'Câu khẳng định: động từ chia ở vị trí 2 (Ich wohne in Berlin.).'],
        ]);
        $bai('slang', 'Tiếng lóng & tiếng Đức đời thường', null, null, 9000, [['t' => 'kv', 'k' => 'Na?', 'x' => 'Thế nào? Khoẻ không? (chào hỏi thân mật)']]);
        $bai('g-A1-99', 'Bài nháp', 1, $chuong, 10199, [['t' => 'p', 'x' => 'Chưa xuất bản.']], 'draft');

        $bo = function (string $slug, string $tieuDe, string $phan, ?int $cap, ?string $bai, ?string $nhom, ?string $ghiChu, int $sort, string $trangThai = 'published') use ($bay): void {
            $this->bo[$slug] = (int) DB::table('exercise_sets')->insertGetId([
                'lang' => 'de', 'base_lang' => 'vi', 'slug' => $slug, 'title' => $tieuDe, 'section' => $phan, 'level' => $cap,
                'grammar_lesson_id' => $bai !== null ? $this->bai[$bai] : null, 'group_name' => $nhom, 'description' => $ghiChu,
                'status' => $trangThai, 'sort' => $sort, 'created_at' => $bay, 'updated_at' => $bay,
            ]);
        };
        $cau = function (string $bo, string $sig, string $loai, string $de, array $dapAn, ?array $luaChon = null, ?string $goiY = null, ?string $giaiThich = null, array $extra = [], string $trangThai = 'published') use ($bay): void {
            $this->cau[$sig] = (int) DB::table('exercises')->insertGetId([
                'set_id' => $this->bo[$bo], 'sig' => $sig, 'kind' => $loai, 'prompt' => $de, 'answers' => json_encode($dapAn, JSON_UNESCAPED_UNICODE),
                'choices' => $luaChon !== null ? json_encode($luaChon, JSON_UNESCAPED_UNICODE) : null, 'hint' => $goiY, 'explanation' => $giaiThich,
                'extra' => $extra !== [] ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null, 'sort' => count($this->cau), 'status' => $trangThai,
                'created_at' => $bay, 'updated_at' => $bay,
            ]);
        };

        $bo('bt-LB3', 'Giống danh từ & mạo từ (der / die / das)', 'level', 1, 'g-A1-22', null, null, 6);
        $cau('bt-LB3', 'lb3:1', 'mc', '____ Tisch ist neu.', ['Der'], ['Der', 'Die', 'Das'], null, 'Tisch là giống đực: der Tisch.', ['label' => 'Chọn đáp án đúng']);
        $cau('bt-LB3', 'lb3:2', 'mc', '____ Lampe ist hell.', ['Die'], ['Der', 'Die', 'Das'], null, 'Lampe là giống cái: die Lampe.', ['label' => 'Chọn đáp án đúng']);
        $cau('bt-LB3', 'lb3:3', 'fill', '____ Buch ist interessant.', ['Das'], null, null, 'Buch là giống trung: das Buch.', ['label' => 'Điền vào chỗ trống', 'case_sensitive' => true]);
        $cau('bt-LB3', 'lb3:4', 'fill', 'Ich lese ____ Zeitung.', ['die'], null, null, 'Zeitung là giống cái; ở Akkusativ vẫn là die.', ['label' => 'Điền vào chỗ trống']);
        $cau('bt-LB3', 'lb3:nhap', 'fill', 'Câu nháp ____.', ['x'], null, null, null, [], 'draft');

        $bo('dx-g1-1A', '1A · Điền der / die / das', 'level', 1, 'g-A1-22', 'Chương 1 · Artikel & Genus', 'Mạo từ xác định ở Nominativ: der, die, das.', 1510);
        $cau('dx-g1-1A', 'dx:g1:1A:1', 'fill', 'Hier ist ____ Lehrer.', ['der'], null, null, 'Lehrer là giống đực: der Lehrer.', ['label' => 'Điền vào chỗ trống', 'instr' => 'Điền der, die hoặc das.']);
        $cau('dx-g1-1A', 'dx:g1:1A:2', 'fix', 'Das Frau heißt Anna.', ['Die Frau heißt Anna'], null, 'Sửa lại cho đúng', 'Frau là giống cái → die Frau.', ['label' => 'Tìm và sửa lỗi', 'frag' => ['Die Frau']]);
        $cau('dx-g1-1A', 'dx:g1:1A:3', 'order', '', ['Das Kind spielt im Garten'], null, 'Từ đầu câu viết hoa.', 'Động từ đứng ở vị trí 2.', ['label' => 'Sắp xếp thành câu', 'tokens' => ['spielt', 'Das Kind', 'im Garten'], 'lead' => '']);

        $bo('dx-v1-1A', '1A · Điền từ vào chỗ trống', 'review', null, null, 'Bài 1 · Person & Familie', null, 9010);
        $kho = ['Familie', 'Geschwister', 'Onkel'];
        $cau('dx-v1-1A', 'dx:v1:1A:1', 'fill', 'Meine ___ ist groß: Wir sind sechs Personen.', ['Familie'], null, null, null, ['label' => 'Điền vào chỗ trống', 'bank' => $kho]);
        $cau('dx-v1-1A', 'dx:v1:1A:2', 'fill', 'Ich habe keine ___. Ich bin Einzelkind.', ['Geschwister'], null, null, null, ['label' => 'Điền vào chỗ trống', 'bank' => $kho]);

        $bo('pl-e', 'Số nhiều: Thêm -e', 'plural', null, 'g-A1-23', 'Số nhiều', 'Việc cần làm: viết dạng số nhiều của danh từ. Quy tắc: nhiều danh từ giống đực và giống trung một âm tiết thêm -e.', 9501);
        foreach ([['der Tisch', 'Tische'], ['der Hund', 'Hunde'], ['das Brot', 'Brote']] as [$so, $nhieu]) {
            $cau('pl-e', 'pl:e:'.substr($so, 4), 'fill', $so.'  →  die ____', [$nhieu], null, null, null, ['label' => 'Viết số nhiều', 'case_sensitive' => true]);
        }

        $bo('bt-nhap', 'Bộ nháp', 'level', 1, null, null, null, 7, 'draft');
        $cau('bt-nhap', 'nhap:1', 'fill', 'Câu của bộ nháp ____.', ['x']);

        DB::table('verbs')->insert([
            [
                'lang' => 'de', 'card_id' => null, 'infinitive' => 'lesen', 'level' => 1, 'is_separable' => 0, 'prefix' => null, 'auxiliary' => 'haben', 'is_irregular' => 1,
                'form_du' => 'liest', 'form_er' => 'liest', 'preterite' => 'las', 'perfect' => 'hat gelesen',
                'meanings' => json_encode(['vi' => 'đọc', 'en' => 'to read'], JSON_UNESCAPED_UNICODE),
                'conjugation' => json_encode([
                    'pres' => ['lese', 'liest', 'liest', 'lesen', 'lest', 'lesen'],
                    'prat' => ['las', 'last', 'las', 'lasen', 'last', 'lasen'],
                    'perf' => ['habe gelesen', 'hast gelesen', 'hat gelesen', 'haben gelesen', 'habt gelesen', 'haben gelesen'],
                    'imp' => ['lies!', 'lest!', 'lesen Sie!'],
                ]),
                'status' => 'published', 'created_at' => $bay, 'updated_at' => $bay,
            ],
            [
                'lang' => 'de', 'card_id' => null, 'infinitive' => 'anrufen', 'level' => 1, 'is_separable' => 1, 'prefix' => 'an', 'auxiliary' => 'haben', 'is_irregular' => 1,
                'form_du' => 'rufst an', 'form_er' => 'ruft an', 'preterite' => 'rief an', 'perfect' => 'hat angerufen',
                'meanings' => json_encode(['vi' => 'gọi điện', 'en' => 'to call (on the phone)'], JSON_UNESCAPED_UNICODE),
                'conjugation' => null, 'status' => 'published', 'created_at' => $bay, 'updated_at' => $bay,
            ],
        ]);

        // Câu Điền câu (từ câu ví dụ, chỗ trống là ____); thẻ Giao tiếp và thẻ nháp có câu nhưng không được hỏi
        foreach ([
            ['das Brot', 'Das ____ ist noch frisch.', 'Brot'],
            ['zählen', 'Das Kind ____ bis zehn.', 'zählt'],
            ['die Zahl', 'Die ____ ist sehr groß.', 'Zahl'],
            ['Ich hätte gern zwei Brötchen.', 'Ich hätte gern zwei ____.', 'Brötchen'],
            ['der Teig', 'Der ____ muss ruhen.', 'Teig'],
        ] as [$front, $cauTrong, $dapAn]) {
            DB::table('cards')->where('id', $this->the[$front])->update(['cloze' => $cauTrong, 'cloze_answer' => $dapAn]);
        }

        return $this;
    }

    /**
     * @param  array<string, string>  $nghia
     * @param  array<string, list<string>>  $dang  kiểu → các dạng (viết thường)
     * @param  list<array{string, array<string, string>}>  $viDu
     */
    public function themThe(
        string $chuDe, string $front, ?string $pos, ?string $forms, ?int $cap, int $capThap, int $capCao, array $nghia, array $dang = [],
        ?string $article = null, array $viDu = [], string $bo = 'vocab', ?string $tip = null, ?string $image = null, string $trangThai = 'published', ?int $chu = null,
    ): int {
        $bay = time();
        $id = (int) DB::table('cards')->insertGetId([
            'lang' => 'de', 'collection' => $bo, 'card_key' => 'k-'.(count($this->the) + 1), 'owner_user_id' => $chu, 'topic_id' => $this->chuDe[$chuDe],
            'front' => $front, 'article' => $article, 'gender' => ['der' => 'm', 'die' => 'f', 'das' => 'n'][$article] ?? null, 'pos' => $pos,
            'forms' => $forms, 'level' => $cap, 'level_min' => $capThap, 'level_max' => $capCao, 'image' => $image, 'tip' => $tip,
            'search_text' => NoiDung::chuTim($front, $forms, $nghia), 'status' => $trangThai, 'published_at' => $bay, 'created_at' => $bay, 'updated_at' => $bay,
        ]);
        $this->the[$front] = $id;

        foreach ($nghia as $lang => $chuNghia) {
            DB::table('card_translations')->insert(['card_id' => $id, 'lang' => $lang, 'meaning' => $chuNghia, 'updated_at' => $bay]);
        }
        foreach ($viDu as $i => [$cau, $dich]) {
            DB::table('card_examples')->insert(['card_id' => $id, 'sort' => $i, 'sentence' => $cau, 'translations' => json_encode($dich, JSON_UNESCAPED_UNICODE), 'created_at' => $bay]);
        }
        foreach ($dang as $kieu => $ds) {
            foreach ($ds as $d) {
                DB::table('word_forms')->insert(['lang' => 'de', 'form' => $d, 'card_id' => $id, 'kind' => $kieu]);
            }
        }

        return $id;
    }
}
