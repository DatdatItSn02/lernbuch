<?php

use App\Models\User;
use App\Support\NoiDung;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Mục tự kiểm 17: dữ liệu cỡ thật (11.882 thẻ, 620 chủ đề nhỏ, mỗi thẻ đủ nghĩa ba thứ tiếng và một câu ví dụ),
// một người đã học 5.000 thẻ. Để nặng hơn thật, mọi thẻ đều thuộc loại Từ vựng. Mục tiêu: tạo phiên p95 dưới 150 ms,
// các API khác p95 dưới 200 ms. Không chạy trong lần test thường (dựng dữ liệu mất vài chục giây); chạy riêng:
// php artisan test --group=hieu-nang

const SO_THE_LON = 11_882;

const SO_CHU_DE_NHO = 620;

const SO_TIEN_DO = 5_000;

/** Luyện tập cỡ Lernbuch: 219 bài ngữ pháp, 364 bộ × 19 câu (6.916 câu), 889 động từ; một người đã làm 3.000 câu. */
const SO_BAI_NGU_PHAP = 219;

const SO_BO_BAI_TAP = 364;

const SO_DONG_TU = 889;

/** @return array{user: int, chu_de: string, the_moi: list<int>} */
function dungDuLieuLon(): array
{
    $bay = time();
    $chuDe = fn (array $dong): int => (int) DB::table('topics')->insertGetId($dong + ['lang' => 'de', 'status' => 'published', 'created_at' => $bay, 'updated_at' => $bay]);
    $nho = [];

    for ($g = 1; $g <= 7; $g++) {
        $nhom = $chuDe(['depth' => 1, 'code' => "g{$g}", 'slug' => "nhom-{$g}", 'name_vi' => "Nhóm {$g}", 'sort' => $g]);

        for ($v = 1; $v <= 4 && count($nho) < SO_CHU_DE_NHO; $v++) {
            $vua = $chuDe(['depth' => 2, 'parent_id' => $nhom, 'code' => "v{$g}-{$v}", 'slug' => "vua-{$g}-{$v}", 'name_vi' => "Chủ đề vừa {$g}.{$v}", 'sort' => $v]);

            for ($n = 1; $n <= 23 && count($nho) < SO_CHU_DE_NHO; $n++) {
                $nho[] = $chuDe([
                    'depth' => 3, 'parent_id' => $vua, 'collection' => 'vocab', 'code' => "n{$g}-{$v}-{$n}", 'slug' => "nho-{$g}-{$v}-{$n}",
                    'name_vi' => "Chủ đề nhỏ {$g}.{$v}.{$n}", 'icon' => '1F35E', 'sort' => $n,
                ]);
            }
        }
    }

    $the = [];
    for ($i = 1; $i <= SO_THE_LON; $i++) {
        $front = 'das Wort'.$i.($i % 3 === 0 ? 'ä' : '');
        $the[] = [
            'lang' => 'de', 'collection' => 'vocab', 'card_key' => "k{$i}", 'topic_id' => $nho[$i % SO_CHU_DE_NHO], 'front' => $front,
            'article' => 'das', 'gender' => 'n', 'pos' => 'noun', 'forms' => 'die Wörter', 'level' => 1 + $i % 4, 'level_min' => 1 + $i % 4,
            'level_max' => 1 + $i % 4, 'search_text' => NoiDung::chuTim($front, 'die Wörter', ['vi' => "từ số {$i}", 'en' => "word {$i}", 'zh' => "词{$i}"]),
            'status' => 'published', 'published_at' => $bay, 'created_at' => $bay, 'updated_at' => $bay,
        ];
    }
    foreach (array_chunk($the, 1000) as $lo) {
        DB::table('cards')->insert($lo);
    }

    /** @var list<int> $ids */
    $ids = DB::table('cards')->where('card_key', 'like', 'k%')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all();
    $dich = [];
    $vd = [];
    foreach ($ids as $k => $id) {
        $so = $k + 1;
        foreach (['vi' => "từ số {$so}", 'en' => "word {$so}", 'zh' => "词{$so}"] as $lang => $chu) {
            $dich[] = ['card_id' => $id, 'lang' => $lang, 'meaning' => $chu, 'updated_at' => $bay];
        }
        $vd[] = ['card_id' => $id, 'sort' => 0, 'sentence' => "Das ist das Wort {$so}.", 'translations' => json_encode(['vi' => "Đây là từ số {$so}."], JSON_UNESCAPED_UNICODE), 'created_at' => $bay];
    }
    foreach (array_chunk($dich, 2000) as $lo) {
        DB::table('card_translations')->insert($lo);
    }
    foreach (array_chunk($vd, 1000) as $lo) {
        DB::table('card_examples')->insert($lo);
    }

    $user = User::factory()->create();
    $now = now()->getTimestamp();
    $tienDo = [];
    // 5.000 thẻ đã học, rải khắp các chủ đề; cứ 8 thẻ thì một thẻ đến hạn hôm nay
    foreach (array_slice($ids, 0, SO_TIEN_DO) as $k => $id) {
        $tienDo[] = [
            'user_id' => $user->id, 'card_id' => $id, 'box' => 1 + $k % 5, 'due_at' => $k % 8 === 0 ? $now - 3600 : $now + 86400 * (1 + $k % 30),
            'reps' => 3, 'lapses' => $k % 2, 'last_rating' => 2, 'first_seen_at' => $now - 86400 * 40, 'last_seen_at' => $now - 86400,
        ];
    }
    foreach (array_chunk($tienDo, 1000) as $lo) {
        DB::table('card_progress')->insert($lo);
    }

    return ['user' => (int) $user->id, 'chu_de' => (string) DB::table('topics')->where('id', $nho[5])->value('slug'), 'the_moi' => array_slice($ids, SO_TIEN_DO)];
}

/** @return array{bai: string, cau: list<int>} */
function dungLuyenTapLon(int $userId): array
{
    $bay = time();
    $chuong = [];
    foreach (['A1', 'A2', 'B1', 'B2'] as $c => $cap) {
        for ($n = 1; $n <= 8; $n++) {
            $chuong[] = (int) DB::table('grammar_chapters')->insertGetId(['lang' => 'de', 'code' => "{$cap}-{$n}", 'name_vi' => "Chương {$n}", 'sort' => ($c + 1) * 100 + $n, 'created_at' => $bay]);
        }
    }

    $khoi = json_encode(array_merge(
        [['t' => 'h2', 'x' => 'Phần một'], ['t' => 'p', 'x' => str_repeat('Giải thích ngữ pháp bằng tiếng Việt. ', 20)]],
        array_fill(0, 12, ['t' => 'ex', 'x' => 'Ich lese das Buch.', 'who' => 'ich', 'L' => ['vi' => 'Tôi đọc quyển sách.', 'en' => 'I read the book.', 'zh' => '我读这本书。']]),
        [['t' => 'tbl', 'rows' => array_fill(0, 6, [['ich', 1, 2], ['lese', 1, 0], ['las', 1, 0], ['habe gelesen', 1, 0]])]],
    ), JSON_UNESCAPED_UNICODE);
    $bai = [];
    for ($i = 1; $i <= SO_BAI_NGU_PHAP; $i++) {
        $bai[] = [
            'lang' => 'de', 'base_lang' => 'vi', 'chapter_id' => $i % 5 === 0 ? null : $chuong[$i % count($chuong)], 'slug' => "bai-{$i}", 'level' => 1 + $i % 4,
            'title' => "Bài ngữ pháp {$i}", 'blocks' => $khoi, 'status' => 'published', 'sort' => $i, 'created_at' => $bay,
        ];
    }
    DB::table('grammar_lessons')->insert($bai);
    $baiId = DB::table('grammar_lessons')->orderBy('id')->pluck('id')->all();

    $bo = [];
    for ($i = 1; $i <= SO_BO_BAI_TAP; $i++) {
        $bo[] = [
            'lang' => 'de', 'base_lang' => 'vi', 'slug' => ($i % 3 === 0 ? 'dx-' : 'bt-').$i, 'title' => "Bộ {$i}", 'section' => $i % 7 === 0 ? 'review' : 'level',
            'level' => $i % 7 === 0 ? null : 1 + $i % 4, 'grammar_lesson_id' => $baiId[$i % count($baiId)], 'group_name' => $i % 3 === 0 ? 'Chương '.intdiv($i, 10) : null,
            'status' => 'published', 'sort' => $i, 'created_at' => $bay,
        ];
    }
    DB::table('exercise_sets')->insert($bo);
    $cau = [];
    foreach (DB::table('exercise_sets')->orderBy('id')->pluck('id') as $k => $boId) {
        for ($j = 0; $j < 19; $j++) {
            $tn = $j % 4 === 0;
            $cau[] = [
                'set_id' => $boId, 'sig' => "s{$k}:{$j}", 'kind' => $tn ? 'mc' : 'fill', 'prompt' => 'Ich sehe ____ Mann.', 'answers' => '["den"]',
                'choices' => $tn ? '["der", "den", "dem"]' : null, 'explanation' => 'Akkusativ: der Mann → den Mann.', 'extra' => '{"label": "Điền vào chỗ trống"}',
                'sort' => $j, 'status' => 'published', 'created_at' => $bay,
            ];
        }
    }
    foreach (array_chunk($cau, 1000) as $lo) {
        DB::table('exercises')->insert($lo);
    }
    $cauId = DB::table('exercises')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all();
    $tk = [];
    foreach (array_slice($cauId, 0, 3000) as $k => $id) {
        $tk[] = ['user_id' => $userId, 'exercise_id' => $id, 'correct_count' => $k % 3, 'wrong_count' => 1, 'correct_streak' => $k % 3, 'last_correct' => $k % 3 > 0 ? 1 : 0, 'last_answer' => 'den', 'last_at' => $bay - 86400];
    }
    foreach (array_chunk($tk, 1000) as $lo) {
        DB::table('user_exercise_stats')->insert($lo);
    }

    $chia = json_encode(['pres' => ['lese', 'liest', 'liest', 'lesen', 'lest', 'lesen'], 'prat' => ['las', 'last', 'las', 'lasen', 'last', 'lasen'],
        'perf' => ['habe gelesen', 'hast gelesen', 'hat gelesen', 'haben gelesen', 'habt gelesen', 'haben gelesen'], 'imp' => ['lies!', 'lest!', 'lesen Sie!']]);
    $dongTu = [];
    for ($i = 1; $i <= SO_DONG_TU; $i++) {
        $dongTu[] = [
            'lang' => 'de', 'infinitive' => "lesen{$i}", 'level' => 1 + $i % 4, 'is_separable' => $i % 6 === 0 ? 1 : 0, 'form_du' => 'liest', 'form_er' => 'liest',
            'preterite' => 'las', 'perfect' => 'hat gelesen', 'meanings' => json_encode(['vi' => "đọc {$i}", 'en' => "to read {$i}", 'zh' => "读{$i}"], JSON_UNESCAPED_UNICODE),
            'conjugation' => $chia, 'status' => 'published', 'created_at' => $bay,
        ];
    }
    DB::table('verbs')->insert($dongTu);

    // Một nửa số thẻ có câu Điền câu
    DB::table('cards')->where('card_key', 'like', 'k%')->whereRaw('id % 2 = 0')->update(['cloze' => 'Das ist das ____.', 'cloze_answer' => 'Wort']);

    return ['bai' => 'bai-7', 'cau' => array_slice($cauId, 4000, 15)];
}

/**
 * Gọi 3 lần chạy nóng (nạp route, cấu hình, bộ đệm của database) rồi đo $lan lần.
 *
 * @param  callable(int): mixed  $goi  nhận số thứ tự lần gọi (lần chạy nóng là số âm)
 * @return array{p50: float, p95: float, max: float} mili giây
 */
function doThoiGian(callable $goi, int $lan = 20): array
{
    for ($i = -3; $i < 0; $i++) {
        $goi($i);
    }

    $ms = [];
    for ($i = 0; $i < $lan; $i++) {
        $bat = hrtime(true);
        $goi($i);
        $ms[] = (hrtime(true) - $bat) / 1e6;
    }
    sort($ms);

    return ['p50' => round($ms[intdiv($lan, 2)], 1), 'p95' => round($ms[(int) ceil($lan * 0.95) - 1], 1), 'max' => round($ms[$lan - 1], 1)];
}

it('dữ liệu cỡ thật: tạo phiên p95 dưới 150 ms, các API học và nội dung p95 dưới 200 ms', function () {
    $this->seed();
    $this->travelTo(CarbonImmutable::parse('2026-11-27 09:00:00', 'Asia/Ho_Chi_Minh'));
    config(['app.api_moi_phut' => 100_000]); // bài đo gọi hơn 120 lần trong một phút đứng yên

    $d = dungDuLieuLon();
    $this->actingAs(User::query()->findOrFail($d['user']));
    $theMoi = $d['the_moi'];
    $ketQua = [];

    $ketQua['POST hoc/phien'] = doThoiGian(fn () => $this->postJson('/api/v1/hoc/phien')->assertCreated()->assertJsonPath('data.counts.review', 50));
    $ketQua['POST hoc/phien (Quiz)'] = doThoiGian(fn () => $this->postJson('/api/v1/hoc/phien', ['mode' => 'quiz'])->assertCreated()->assertJsonCount(6, 'data.cards.0.options'));
    $ketQua['GET hoc/con-lai'] = doThoiGian(fn () => $this->getJson('/api/v1/hoc/con-lai')->assertOk());
    $ketQua['GET hom-nay'] = doThoiGian(fn () => $this->getJson('/api/v1/hom-nay')->assertOk()->assertJsonPath('data.due', 625));
    $ketQua['GET chu-de/{slug}'] = doThoiGian(fn () => $this->getJson("/api/v1/chu-de/{$d['chu_de']}")->assertOk());
    $ketQua['GET chu-de (cây)'] = doThoiGian(fn () => $this->getJson('/api/v1/chu-de')->assertOk()->assertJsonPath('data.topic_count', SO_CHU_DE_NHO));
    $ketQua['GET tra-tu'] = doThoiGian(fn (int $i) => $this->getJson('/api/v1/tra-tu?q=wort'.(120 + $i))->assertOk());
    $ketQua['POST hoc/luot-cham (20 lượt)'] = doThoiGian(function (int $i) use ($theMoi) {
        // mỗi lô là một phút học trong quá khứ gần, 20 thẻ mới khác nhau, 3 giây một thẻ
        $goc = CarbonImmutable::now()->subMinutes(40)->addMinutes($i + 3);
        $lo = [];
        for ($k = 0; $k < 20; $k++) {
            $lo[] = [
                'uid' => (string) Str::ulid(), 'card_id' => $theMoi[($i + 3) * 20 + $k], 'session_id' => null, 'mode' => 'flashcard',
                'rating' => 2, 'reviewed_at_ms' => (int) $goc->addSeconds($k * 3)->getTimestampMs(), 'duration_ms' => 2500,
            ];
        }
        $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => $lo])->assertOk()->assertJsonPath('data.received', 20);
    });

    $lt = dungLuyenTapLon($d['user']);
    $ketQua['GET ngu-phap'] = doThoiGian(fn () => $this->getJson('/api/v1/ngu-phap')->assertOk()->assertJsonCount(SO_BAI_NGU_PHAP, 'data.lessons'));
    $ketQua['GET ngu-phap/{slug}'] = doThoiGian(fn () => $this->getJson("/api/v1/ngu-phap/{$lt['bai']}")->assertOk());
    $ketQua['GET bai-tap'] = doThoiGian(fn () => $this->getJson('/api/v1/bai-tap')->assertOk()->assertJsonCount(SO_BO_BAI_TAP, 'data.sets'));
    $ketQua['GET bai-tap/phien (Luyện nhanh)'] = doThoiGian(fn () => $this->getJson('/api/v1/bai-tap/phien?n=15&cap=1,2')->assertOk()->assertJsonCount(15, 'data'));
    $ketQua['GET bai-tap/phien (Ôn câu sai)'] = doThoiGian(fn () => $this->getJson('/api/v1/bai-tap/phien?on=1&n=15')->assertOk()->assertJsonCount(15, 'data'));
    $ketQua['POST bai-tap/ket-qua (15 câu)'] = doThoiGian(fn () => $this->postJson('/api/v1/bai-tap/ket-qua', [
        'uid' => (string) Str::ulid(), 'mode' => 'quick', 'grading' => 'instant', 'set' => null,
        'started_at_ms' => (int) now()->subMinutes(2)->getTimestampMs(), 'finished_at_ms' => (int) now()->getTimestampMs(),
        'items' => array_map(fn (int $id) => ['id' => $id, 'answer' => 'den'], $lt['cau']),
    ])->assertOk()->assertJsonPath('data.correct', 15));
    $ketQua['GET dong-tu'] = doThoiGian(fn () => $this->getJson('/api/v1/dong-tu')->assertOk()->assertJsonPath('meta.total', SO_DONG_TU));
    $ketQua['GET dong-tu?q='] = doThoiGian(fn (int $i) => $this->getJson('/api/v1/dong-tu?q=doc%20'.(100 + $i))->assertOk());
    $ketQua['GET dong-tu/luyen'] = doThoiGian(fn () => $this->getJson('/api/v1/dong-tu/luyen')->assertOk()->assertJsonCount(12, 'data'));
    $ketQua['GET bai-tap/dien-cau'] = doThoiGian(fn () => $this->getJson('/api/v1/bai-tap/dien-cau')->assertOk()->assertJsonCount(15, 'data'));

    $bang = collect($ketQua)->map(fn (array $t, string $api) => sprintf('  %s p50 %6.1f ms   p95 %6.1f ms   max %6.1f ms', mb_str_pad($api, 30), $t['p50'], $t['p95'], $t['max']))->implode("\n");
    fwrite(STDERR, "\nHiệu năng (".SO_THE_LON.' thẻ, '.SO_CHU_DE_NHO.' chủ đề nhỏ, '.SO_TIEN_DO." dòng tiến độ):\n{$bang}\n");

    expect(DB::table('cards')->count())->toBe(SO_THE_LON)
        ->and(DB::table('review_logs')->where('user_id', $d['user'])->count())->toBe(23 * 20)
        ->and($ketQua['POST hoc/phien']['p95'])->toBeLessThan(150.0)
        ->and($ketQua['POST hoc/phien (Quiz)']['p95'])->toBeLessThan(150.0);

    foreach ($ketQua as $api => $t) {
        expect($t['p95'])->toBeLessThan(200.0, "{$api}: p95 {$t['p95']} ms");
    }
})->group('hieu-nang');
