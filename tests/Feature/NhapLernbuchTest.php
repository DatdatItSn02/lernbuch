<?php

use App\Support\NhapLernbuch\BoNhapLernbuch;
use Illuminate\Support\Facades\DB;

// Lệnh nhập Lernbuch cũ (mục tự kiểm 1 và 2 của G1). Nguồn giả: database SQLite nhỏ cùng dạng với content/seed.db.

/** @return array<string, mixed> thẻ đủ cột, đổi bằng $doi */
function theCu(array $doi): array
{
    return $doi + [
        'lang' => 'de', 'collection' => 'vocab', 'src_no' => 1, 'article' => null, 'pos' => null, 'forms' => null, 'topic' => null,
        'level' => 'A1', 'level_min' => 1, 'level_max' => 1, 'lektion' => null, 'en' => null, 'zh' => null, 'vi' => null,
        'example' => null, 'example_vi' => null, 'note' => null, 'active' => 1, 'ex_en' => null, 'ex_zh' => null, 'extra' => null,
        'register' => null, 'tip' => null, 'ex2' => null, 'cloze' => null, 'cloze_ans' => null, 'src' => null, 'emoji' => null,
        'emoji_q' => 0, 'exs' => null, 'theme' => null,
    ];
}

/** @return list<array<string, mixed>> */
function cacTheMau(): array
{
    return [
        theCu(['id' => 1, 'key' => 'k-frau', 'src_no' => 1, 'front' => 'die Frau (Anrede)', 'article' => 'die', 'pos' => 'Noun', 'forms' => 'die Frauen',
            'theme' => 't-greet', 'vi' => 'bà (cách xưng hô)', 'en' => 'Mrs.', 'zh' => '女士', 'emoji' => '1F469', 'emoji_q' => 2,
            'example' => 'Guten Tag, Frau Müller!', 'example_vi' => 'Chào bà Müller!',
            'exs' => '[{"de": "Guten Tag, Frau Müller!", "vi": "Chào bà Müller!"}, {"de": "Frau Weber kommt.", "vi": "Bà Weber đến."}]',
            'ex2' => 'Die Frau lacht.']),
        theCu(['id' => 2, 'key' => 'k-zahlen', 'src_no' => 2, 'front' => 'zahlen', 'pos' => 'Verb', 'forms' => 'er zahlt · hat gezahlt',
            'theme' => 't-food', 'src' => 'tr', 'vi' => 'trả tiền', 'en' => 'to pay']),
        theCu(['id' => 3, 'key' => 'k-zaehlen', 'src_no' => 3, 'front' => 'zählen', 'pos' => 'Verb', 'forms' => 'er zählt · hat gezählt',
            'topic' => 'Food & Drink', 'src' => 'Collins German 3000 Words and Phrases', 'level' => 'A2', 'level_min' => 2, 'level_max' => 2,
            'vi' => 'đếm', 'en' => 'to count']),
        theCu(['id' => 4, 'key' => 'k-appetit', 'src_no' => 4, 'collection' => 'phrase', 'front' => 'Guten Appetit!', 'pos' => 'Phrase',
            'theme' => 't-food', 'src' => 'ct', 'vi' => 'Chúc ngon miệng',
            'extra' => '[{"de": "Guten Appetit, Kinder!", "vi": "Chúc các con ngon miệng!", "en": "Enjoy, kids!", "zh": "孩子们，慢慢吃！"}]']),
        theCu(['id' => 5, 'key' => 'k-geht', 'src_no' => 5, 'collection' => 'comm', 'front' => "Wie geht's?", 'theme' => 't-greet',
            'register' => 'Thân mật', 'vi' => 'Khoẻ không?', 'level' => null]),
        theCu(['id' => 6, 'key' => 'k-alt', 'src_no' => 6, 'front' => 'alt', 'theme' => 't-greet', 'active' => 0]),
        theCu(['id' => 7, 'key' => 'k-rong', 'src_no' => 7, 'collection' => 'phrase', 'front' => '', 'src' => 'bổ sung từ câu ví dụ', 'vi' => 'làm']),
        theCu(['id' => 8, 'key' => 'k-alter', 'src_no' => 8, 'collection' => 'phrase', 'front' => 'Alter!', 'theme' => 't-greet',
            'src' => 'German Slang – Sổ tay tiếng Đức', 'vi' => 'Ông bạn!']),
    ];
}

/**
 * Dựng nguồn giả: <thư mục>/content/seed.db và content/de/topics_fine.json.
 *
 * @param  list<array<string, mixed>>|null  $the
 */
function taoNguon(?array $the = null): string
{
    $goc = sys_get_temp_dir().'/lernweb-'.bin2hex(random_bytes(5));
    mkdir($goc.'/content/de', 0777, true);
    file_put_contents($goc.'/content/de/topics_fine.json', json_encode([
        'topics' => [
            ['code' => 't-greet', 'vi' => 'Chào hỏi & xưng hô', 'en' => 'Greetings', 'emoji' => '1F44B', 'pcode' => 'a11', 'pvi' => 'Con người & Đời sống cá nhân › Giao tiếp cơ bản', 'gid' => 'g1', 'sort' => 1, 'n' => 3],
            ['code' => 't-food', 'vi' => 'Đồ ăn & bữa ăn', 'en' => 'Food', 'emoji' => '1F35E', 'pcode' => 'a21', 'pvi' => 'Sinh hoạt & Giao tiếp hằng ngày › Ăn uống', 'gid' => 'g2', 'sort' => 2, 'n' => 3],
        ],
        'theme_map' => ['Food & Drink' => 't-food'],
    ], JSON_UNESCAPED_UNICODE));

    $db = new PDO('sqlite:'.$goc.'/content/seed.db', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $cot = array_keys(theCu(['id' => 0, 'key' => '', 'front' => '']));
    $db->exec('CREATE TABLE cards('.implode(', ', array_map(fn ($c) => "\"{$c}\"", $cot)).')');
    $db->exec('CREATE TABLE kv(key TEXT PRIMARY KEY, val TEXT)');
    $db->exec("INSERT INTO kv VALUES ('extra_de', 'x19')");
    $db->exec('CREATE TABLE docs(id INTEGER PRIMARY KEY, lang TEXT, slug TEXT, grp TEXT, title TEXT, level TEXT, sort INTEGER, blocks TEXT, ex_key TEXT)');
    $db->exec('CREATE TABLE ex_sets(id INTEGER PRIMARY KEY, lang TEXT, key TEXT, grp TEXT, title TEXT, level TEXT, sort INTEGER, doc_slug TEXT, n INTEGER, sub TEXT, note TEXT, gslug TEXT)');
    $db->exec('CREATE TABLE exercises(id INTEGER PRIMARY KEY, set_id INTEGER, sort INTEGER, kind TEXT, q TEXT, answers TEXT, options TEXT, hint TEXT, expl TEXT, cs INTEGER, sig TEXT, label TEXT, extra TEXT)');
    $db->exec('CREATE TABLE verbs(id INTEGER PRIMARY KEY, lang TEXT, inf TEXT, level TEXT, du TEXT, er TEXT, prat TEXT, perf TEXT, en TEXT, vi TEXT, zh TEXT, sep INTEGER, prefix TEXT, example TEXT, sort INTEGER, conj TEXT)');

    $chen = fn (string $bang, array $dong) => $db->prepare("INSERT INTO {$bang} (".implode(', ', array_map(fn ($c) => "\"{$c}\"", array_keys($dong))).') VALUES ('.implode(', ', array_fill(0, count($dong), '?')).')')->execute(array_values($dong));

    foreach ($the ?? cacTheMau() as $t) {
        $chen('cards', $t);
    }

    $chen('docs', ['id' => 1, 'lang' => 'de', 'slug' => 'g-A1-01', 'grp' => 'A1|1|Danh từ & mạo từ', 'title' => 'Giống của danh từ', 'level' => 'A1', 'sort' => 1, 'blocks' => '[{"t": "p", "L": {"vi": "Mỗi danh từ có một giống."}}]']);
    $chen('docs', ['id' => 2, 'lang' => 'de', 'slug' => 'a1-1', 'grp' => 'A1', 'title' => 'Nền tảng', 'level' => 'A1', 'sort' => 2, 'blocks' => '[]']);
    $chen('docs', ['id' => 3, 'lang' => 'de', 'slug' => 'slang', 'grp' => 'Slang', 'title' => 'Tiếng lóng', 'level' => null, 'sort' => 3, 'blocks' => '[]']);
    $chen('ex_sets', ['id' => 1, 'lang' => 'de', 'key' => 'bt:TB1', 'grp' => 'A1', 'title' => 'Mạo từ', 'level' => 'A1', 'sort' => 1, 'n' => 2, 'gslug' => 'g-A1-01']);
    $chen('ex_sets', ['id' => 2, 'lang' => 'de', 'key' => 'pl:e', 'grp' => 'Số nhiều', 'title' => 'Số nhiều: Thêm -e', 'level' => 'A1 A2 B1 B2', 'sort' => 2, 'n' => 1, 'sub' => 'Số nhiều', 'note' => 'Viết dạng số nhiều.']);
    $chen('ex_sets', ['id' => 3, 'lang' => 'de', 'key' => 'dx:v1:1A', 'grp' => 'Từ vựng', 'title' => '1A · Điền từ', 'level' => 'A1 A2 B1 B2', 'sort' => 3, 'n' => 1, 'sub' => 'Bài 1 · Person & Familie']);
    $chen('exercises', ['id' => 1, 'set_id' => 1, 'sort' => 0, 'kind' => 'mc', 'q' => 'Ich sehe ____ Mann.', 'answers' => '["den"]', 'options' => '["der", "den", "dem"]', 'expl' => 'Akkusativ.', 'cs' => 0, 'sig' => 's1', 'label' => 'Chọn đáp án đúng']);
    $chen('exercises', ['id' => 2, 'set_id' => 1, 'sort' => 1, 'kind' => 'fill', 'q' => 'Das ist ____ Frau.', 'answers' => '["die"]', 'cs' => 1, 'sig' => 's2', 'label' => 'Điền vào chỗ trống']);
    $chen('exercises', ['id' => 3, 'set_id' => 2, 'sort' => 0, 'kind' => 'fill', 'q' => 'Hund → ____', 'answers' => '["Hunde"]', 'cs' => 0, 'sig' => 's3', 'extra' => '{"bank": ["Hunde", "Tage"]}']);
    $chen('exercises', ['id' => 4, 'set_id' => 3, 'sort' => 0, 'kind' => 'fill', 'q' => 'Mein ____ heißt Max.', 'answers' => '["Bruder"]', 'cs' => 0, 'sig' => null]);
    $chen('verbs', ['id' => 1, 'lang' => 'de', 'inf' => 'zahlen', 'level' => 'A1', 'du' => 'zahlst', 'er' => 'zahlt', 'prat' => 'zahlte', 'perf' => 'hat gezahlt', 'vi' => 'trả tiền', 'en' => 'to pay', 'sep' => 0, 'sort' => 1,
        'conj' => '{"pres": ["zahle", "zahlst", "zahlt", "zahlen", "zahlt", "zahlen"], "perf": ["habe gezahlt", "hast gezahlt"]}', 'example' => 'Ich zahle bar.']);
    $chen('verbs', ['id' => 2, 'lang' => 'de', 'inf' => 'abbauen', 'level' => 'B1', 'du' => 'baust ab', 'er' => 'baut ab', 'prat' => 'baute ab', 'perf' => 'hat abgebaut', 'vi' => 'tháo dỡ', 'sep' => 1, 'prefix' => 'ab', 'sort' => 2, 'conj' => '{"pres": ["baue ab"]}']);

    return $goc;
}

/** @return array<string, mixed> */
function nhap(string $nguon, bool $ghi, int $maThoat = 0): array
{
    $json = tempnam(sys_get_temp_dir(), 'bc');
    test()->artisan('app:nhap-lernbuch', array_filter(['nguon' => $nguon, '--ghi' => $ghi, '--json' => $json]))->assertExitCode($maThoat)->run();

    return json_decode((string) file_get_contents($json), true);
}

beforeEach(fn () => $this->seed());

it('chạy thử: đếm đúng theo loại, nguồn, cấp; bỏ thẻ đã tắt và dòng rỗng; không ghi gì', function () {
    $bc = nhap(taoNguon(), false);

    expect($bc['phien_ban'])->toBe('x19')
        ->and($bc['so'])->toMatchArray(['the' => 6, 'chu_de_nhom' => 2, 'chu_de_vua' => 2, 'chu_de_nho' => 5, 'bai_ngu_phap' => 3, 'chuong_ngu_phap' => 1, 'bo_bai_tap' => 3, 'cau_bai_tap' => 4, 'dong_tu' => 2])
        ->and($bc['the_theo_loai'])->toEqual(['vocab' => 3, 'phrase' => 2, 'comm' => 1])
        ->and($bc['the_theo_nguon'])->toEqual(['lernbuch_xlsx' => 2, 'vocab_trainer' => 1, 'collins_3000' => 1, 'cum_tu_mau_cau' => 1, 'german_slang' => 1])
        ->and($bc['the_theo_cap'])->toEqual(['A1' => 4, 'A2' => 1, 'chua_xep' => 1])
        ->and($bc['the_da_tat'])->toBe(1)
        ->and($bc['loi'])->toBe([])
        ->and($bc['canh_bao'])->toHaveCount(1)
        ->and($bc['canh_bao'][0])->toContain('thẻ #7')
        ->and($bc['da_ghi'])->toBeFalse();

    foreach (['cards', 'topics', 'grammar_lessons', 'exercise_sets', 'exercises', 'verbs', 'card_translations'] as $bang) {
        expect(DB::table($bang)->count())->toBe(0, "bảng {$bang} phải trống sau khi chạy thử");
    }
});

it('ghi: thẻ, chủ đề 3 tầng, nghĩa, câu ví dụ, dạng từ, ngữ pháp, bài tập, động từ đúng nội dung', function () {
    nhap(taoNguon(), true);

    expect(DB::table('cards')->count())->toBe(6)
        ->and(DB::table('topics')->where('depth', 1)->pluck('name_vi')->all())->toBe(['Con người & Đời sống cá nhân', 'Sinh hoạt & Giao tiếp hằng ngày'])
        ->and(DB::table('topics')->where('depth', 2)->count())->toBe(2)
        ->and(DB::table('topics')->where('depth', 3)->orderBy('code')->pluck('code')->all())->toBe(['t-food.p', 't-food.v', 't-greet.c', 't-greet.p', 't-greet.v']);

    $frau = DB::table('cards')->where('card_key', 'k-frau')->first();
    $chuDe = DB::table('topics')->find($frau->topic_id);
    expect($frau)->toMatchArray(['front' => 'die Frau (Anrede)', 'article' => 'die', 'gender' => 'f', 'pos' => 'noun', 'forms' => 'die Frauen', 'level' => 1, 'image' => '1F469', 'image_quality' => 2, 'status' => 'published'])
        ->and($chuDe->code)->toBe('t-greet.v')
        ->and($chuDe->slug)->toBe('chao-hoi-xung-ho-tu-vung')
        ->and($chuDe->card_count)->toBe(1)
        ->and(DB::table('topics')->find($chuDe->parent_id)->name_vi)->toBe('Giao tiếp cơ bản')
        ->and($frau->search_text)->toContain('die frau')->toContain('bà (cách xưng hô)')->toContain('ba (cach xung ho)');

    expect(DB::table('card_translations')->where('card_id', $frau->id)->orderBy('lang')->pluck('meaning', 'lang')->all())
        ->toBe(['en' => 'Mrs.', 'vi' => 'bà (cách xưng hô)', 'zh' => '女士']);
    $viDu = DB::table('card_examples')->where('card_id', $frau->id)->orderBy('sort')->get();
    expect($viDu->pluck('sentence')->all())->toBe(['Guten Tag, Frau Müller!', 'Frau Weber kommt.', 'Die Frau lacht.'])
        ->and(json_decode($viDu[0]->translations, true))->toBe(['vi' => 'Chào bà Müller!'])
        ->and($viDu[2]->translations)->toBeNull();
    expect(DB::table('word_forms')->where('card_id', $frau->id)->orderBy('kind')->pluck('form', 'kind')->all())->toBe(['lemma' => 'frau', 'plural' => 'frauen']);

    // zählen không có theme: lấy chủ đề qua theme_map; dạng chia ghi riêng, khác hẳn zahlen
    $zaehlen = DB::table('cards')->where('card_key', 'k-zaehlen')->first();
    $zahlen = DB::table('cards')->where('card_key', 'k-zahlen')->first();
    expect(DB::table('topics')->find($zaehlen->topic_id)->code)->toBe('t-food.v')
        ->and(DB::table('word_forms')->where('card_id', $zaehlen->id)->pluck('form')->sort()->values()->all())->toBe(['gezählt', 'zählen', 'zählt'])
        ->and(DB::table('word_forms')->where('form', 'zahlen')->pluck('card_id')->unique()->all())->toBe([$zahlen->id]);

    // Câu ví dụ đủ 4 thứ tiếng của cụm câu; ghi chú cách nói thân mật
    $appetit = DB::table('cards')->where('card_key', 'k-appetit')->first();
    expect(json_decode(DB::table('card_examples')->where('card_id', $appetit->id)->value('translations'), true))->toEqual(['vi' => 'Chúc các con ngon miệng!', 'en' => 'Enjoy, kids!', 'zh' => '孩子们，慢慢吃！'])
        ->and(json_decode(DB::table('cards')->where('card_key', 'k-geht')->value('extra'), true))->toBe(['register' => 'Thân mật']);

    // Ngữ pháp, bài tập, động từ
    $bai = DB::table('grammar_lessons')->where('slug', 'g-A1-01')->first();
    expect(DB::table('grammar_lessons')->count())->toBe(3)
        ->and(DB::table('grammar_chapters')->find($bai->chapter_id))->toMatchArray(['code' => 'A1-1', 'name_vi' => 'Danh từ & mạo từ'])
        ->and($bai->base_lang)->toBe('vi')
        ->and(json_decode($bai->blocks, true)[0]['L']['vi'])->toBe('Mỗi danh từ có một giống.')
        ->and(DB::table('grammar_lessons')->where('slug', 'a1-1')->value('chapter_id'))->toBeNull();
    $bo = DB::table('exercise_sets')->orderBy('sort')->get()->keyBy('slug');
    expect($bo->keys()->all())->toBe(['bt-TB1', 'pl-e', 'dx-v1-1A'])
        ->and($bo['bt-TB1'])->toMatchArray(['section' => 'level', 'level' => 1, 'grammar_lesson_id' => $bai->id, 'item_count' => 2])
        ->and($bo['pl-e'])->toMatchArray(['section' => 'plural', 'level' => null, 'description' => 'Viết dạng số nhiều.'])
        ->and($bo['dx-v1-1A'])->toMatchArray(['section' => 'review', 'group_name' => 'Bài 1 · Person & Familie']);
    expect(DB::table('exercises')->count())->toBe(4)
        ->and(json_decode(DB::table('exercises')->where('sig', 's1')->value('choices'), true))->toBe(['der', 'den', 'dem'])
        ->and(json_decode(DB::table('exercises')->where('sig', 's2')->value('extra'), true))->toEqual(['label' => 'Điền vào chỗ trống', 'case_sensitive' => true])
        ->and(json_decode(DB::table('exercises')->where('sig', 's3')->value('extra'), true))->toEqual(['bank' => ['Hunde', 'Tage']])
        ->and(DB::table('exercises')->whereNotIn('sig', ['s1', 's2', 's3'])->value('sig'))->toHaveLength(14);
    $verb = DB::table('verbs')->where('infinitive', 'zahlen')->first();
    expect($verb)->toMatchArray(['card_id' => $zahlen->id, 'auxiliary' => 'haben', 'is_separable' => 0, 'perfect' => 'hat gezahlt'])
        ->and(json_decode($verb->conjugation, true)['example'])->toBe('Ich zahle bar.')
        ->and(DB::table('verbs')->where('infinitive', 'abbauen')->value('card_id'))->toBeNull()
        ->and(DB::table('word_forms')->where('card_id', $zahlen->id)->where('kind', 'conj')->pluck('form')->all())->toContain('zahle', 'zahlst', 'gezahlt');
});

it('chạy lại lần hai: không thêm dòng nào, giữ id (nên giữ tiến độ học)', function () {
    $nguon = taoNguon();
    nhap($nguon, true);
    $truoc = ['cards' => DB::table('cards')->orderBy('id')->pluck('id', 'card_key')->all()];

    foreach (['topics', 'card_translations', 'card_examples', 'word_forms', 'grammar_lessons', 'grammar_chapters', 'exercise_sets', 'exercises', 'verbs'] as $bang) {
        $truoc[$bang] = DB::table($bang)->count();
    }

    $bc = nhap($nguon, true);

    expect(DB::table('cards')->orderBy('id')->pluck('id', 'card_key')->all())->toBe($truoc['cards'])
        ->and($bc['ket_qua_ghi']['the'])->toBe(['moi' => 0, 'cap_nhat' => 6, 'xoa' => 0])
        ->and($bc['ket_qua_ghi']['cau_bai_tap']['moi'])->toBe(0);

    foreach (array_diff_key($truoc, ['cards' => 1]) as $bang => $so) {
        expect(DB::table($bang)->count())->toBe($so, "bảng {$bang} bị thêm dòng ở lần chạy thứ hai");
    }
});

it('nguồn đánh dấu "Không" thì không có dòng nào của nguồn đó, kể cả dòng đã nhập lần trước', function () {
    $nguon = taoNguon();
    nhap($nguon, true);
    expect(DB::table('cards')->where('card_key', 'k-zaehlen')->exists())->toBeTrue();

    DB::table('content_sources')->whereIn('code', ['collins_3000', 'illustrated_dict'])->update(['commercial_use' => 'forbidden']);
    $bc = nhap($nguon, true);
    $collins = DB::table('content_sources')->where('code', 'collins_3000')->value('id');

    expect($bc['bo_vi_nguon'])->toEqual(['collins_3000' => 1, 'illustrated_dict' => 2])
        ->and(DB::table('cards')->where('source_id', $collins)->count())->toBe(0)
        ->and(DB::table('card_translations')->where('meaning', 'đếm')->exists())->toBeFalse()
        ->and(DB::table('word_forms')->where('form', 'zählen')->exists())->toBeFalse()
        ->and(DB::table('exercise_sets')->where('slug', 'pl-e')->exists())->toBeFalse()
        ->and(DB::table('exercises')->where('sig', 's3')->exists())->toBeFalse()
        ->and(DB::table('cards')->count())->toBe(5);
    // Chủ đề nhỏ chỉ còn thẻ của nguồn bị cấm thì ẩn đi (ở đây t-food.v vẫn còn zahlen)
    expect(DB::table('topics')->where('code', 't-food.v')->value('card_count'))->toBe(1);
});

it('quy tắc dữ liệu: cụm từ trong Từ vựng, thẻ không có chủ đề thì báo đúng dòng, không ghi gì', function () {
    $the = cacTheMau();
    $the[] = theCu(['id' => 21, 'key' => 'k-tasse', 'front' => 'eine Tasse Kaffee', 'theme' => 't-food']);
    $the[] = theCu(['id' => 22, 'key' => 'k-lac', 'collection' => 'phrase', 'front' => 'Na und?', 'topic' => 'Chủ đề lạ']);
    $bc = nhap(taoNguon($the), true, 1);

    expect($bc['loi'])->toHaveCount(2)
        ->and($bc['loi'][0])->toBe('thẻ #21 (vocab) "eine Tasse Kaffee": Từ vựng chỉ nhận từ đơn, đây là cụm từ.')
        ->and($bc['loi'][1])->toBe('thẻ #22 (phrase) "Na und?": không có chủ đề nhỏ (mỗi thẻ phải thuộc đúng 1 chủ đề).')
        ->and($bc['da_ghi'])->toBeFalse()
        ->and(DB::table('cards')->count())->toBe(0)
        ->and(DB::table('topics')->count())->toBe(0);

    $this->artisan('app:nhap-lernbuch', ['nguon' => taoNguon($the)])
        ->expectsOutputToContain('Lỗi dữ liệu: 2. Không ghi gì vào database')
        ->expectsOutputToContain('thẻ #21 (vocab) "eine Tasse Kaffee"')
        ->assertExitCode(1);
});

it('nhận cả file lernweb.zip và thẳng file seed.db', function () {
    $goc = taoNguon();
    $zip = $goc.'/lernweb.zip';
    $z = new ZipArchive;
    $z->open($zip, ZipArchive::CREATE);
    $z->addFile($goc.'/content/seed.db', 'lernweb/content/seed.db');
    $z->addFile($goc.'/content/de/topics_fine.json', 'lernweb/content/de/topics_fine.json');
    $z->addFromString('lernweb/README.md', 'Lernbuch');
    $z->close();

    expect(nhap($zip, false)['so']['the'])->toBe(6)
        ->and(nhap($goc.'/content/seed.db', false)['so']['the'])->toBe(6);
});

it('nguồn sai: báo lỗi rõ, mã thoát 2, không ghi gì', function () {
    $this->artisan('app:nhap-lernbuch', ['nguon' => '/khong/co/thu/muc/nay'])->expectsOutputToContain('Không thấy')->assertExitCode(2);

    $rong = sys_get_temp_dir().'/rong-'.bin2hex(random_bytes(4));
    mkdir($rong.'/content/de', 0777, true);
    file_put_contents($rong.'/content/de/topics_fine.json', '{"topics": []}');
    (new PDO('sqlite:'.$rong.'/content/seed.db'))->exec('CREATE TABLE khac(x)');
    $this->artisan('app:nhap-lernbuch', ['nguon' => $rong])->expectsOutputToContain('không phải database của Lernbuch')->assertExitCode(2);

    expect(DB::table('cards')->count())->toBe(0);
});

it('kiểm từ đơn của Từ vựng: bỏ mạo từ, ngoặc, sich; các cách viết ngăn bằng /', function (string $front, bool $tuDon) {
    expect(BoNhapLernbuch::laTuDon($front))->toBe($tuDon);
})->with([
    ['die Frau (Anrede)', true],
    ['der/die Deutsche(r)', true],
    ['der Maurer / die Maurerin', true],
    ['Polnisch / polnisch', true],
    ['sich freuen', true],
    ['eine Tasse Kaffee', false],
    ['zu Hause', false],
]);
