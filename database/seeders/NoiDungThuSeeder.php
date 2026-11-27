<?php

namespace Database\Seeders;

use App\Support\NoiDung;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Nội dung mẫu nhỏ cho test và máy dev khi chưa nhập Lernbuch: 2 nhóm, 2 chủ đề vừa, 4 chủ đề nhỏ (1 đã ẩn),
 * thẻ đủ loại (có cấp, khoảng cấp, chưa xếp cấp, nháp, thẻ riêng của một người). Nghĩa và câu ví dụ đã soát.
 * Chạy: php artisan db:seed --class=NoiDungThuSeeder. Không chạy trên máy chủ thật, không chạy khi đã có nội dung.
 */
class NoiDungThuSeeder extends Seeder
{
    /** @var array<string, int> mã → id chủ đề */
    public array $chuDe = [];

    /** @var array<string, int> mặt trước → id thẻ */
    public array $the = [];

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

        $this->tao();
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
