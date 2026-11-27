<?php

use App\Models\User;
use App\Models\UserSetting;
use App\Support\Hoc\CaiDatHoc;
use App\Support\Hoc\PhienHoc;
use Carbon\CarbonImmutable;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Học và đồng bộ: phiên học, lượt chấm theo lô, Hôm nay. Mục tự kiểm 4 (gửi trùng), 6 (hai máy chấm cùng thẻ),
// 7 (chấm quá nhanh), 8 (người A không đọc, không ghi được dữ liệu người B). Người học ở Asia/Ho_Chi_Minh.

const MUI_GIO_HOC = 'Asia/Ho_Chi_Minh';

function lucHoc(string $gio): CarbonImmutable
{
    return CarbonImmutable::parse($gio, MUI_GIO_HOC);
}

function gioHoc(?int $luc): ?string
{
    return $luc === null ? null : CarbonImmutable::createFromTimestamp($luc, MUI_GIO_HOC)->format('Y-m-d H:i:s');
}

/** @return array<string, mixed> một lượt chấm như app gửi */
function luot(int $the, int $cham, CarbonImmutable $luc, ?int $phien = null, ?int $thoiGian = 3000, string $cheDo = 'flashcard', ?string $uid = null): array
{
    return [
        'uid' => $uid ?? (string) Str::ulid(),
        'card_id' => $the,
        'session_id' => $phien,
        'mode' => $cheDo,
        'rating' => $cham,
        'reviewed_at_ms' => (int) $luc->getTimestampMs(),
        'duration_ms' => $thoiGian,
    ];
}

beforeEach(function () {
    $this->seed();
    $this->m = (new NoiDungThuSeeder)->tao();
    $this->travelTo(lucHoc('2026-11-27 09:00:00'));
});

it('người mới: phiên có 10 từ mới của chủ đề đầu tiên theo thứ tự trang Chủ đề, ghi phiên vào study_sessions', function () {
    $this->actingAs($u = User::factory()->create());

    $r = $this->postJson('/api/v1/hoc/phien')->assertCreated();
    $the = $r->json('data.cards');

    expect($r->json('data.counts'))->toBe(['review' => 0, 'new' => 10])
        ->and(array_column($the, 'front'))->toBe(['zahlen', 'zählen', 'die Zahl', 'null', 'eins', 'zwei', 'drei', 'vier', 'fünf', 'sechs'])
        ->and($r->json('data.new_topic'))->toBe(['slug' => 'so-dem-tu-vung', 'name' => 'Số đếm'])
        ->and($r->json('data.topic'))->toBeNull()
        ->and($the[2])->toMatchArray(['article' => 'die', 'word' => 'Zahl', 'box' => 0, 'due_at' => null, 'topic' => ['slug' => 'so-dem-tu-vung', 'name' => 'Số đếm']])
        ->and($the[2]['meanings'])->toBe(['en' => 'number', 'vi' => 'con số', 'zh' => '数字'])
        ->and($the[0]['example'])->toBeNull();

    $p = DB::table('study_sessions')->find($r->json('data.id'));
    expect($p->user_id)->toBe($u->id)->and($p->planned_new)->toBe(10)->and($p->planned_review)->toBe(0)
        ->and($p->mode)->toBe('flashcard')->and($p->started_at)->toBe(lucHoc('2026-11-27 09:00:00')->getTimestamp())
        ->and(DB::table('user_courses')->where('user_id', $u->id)->value('current_topic_id'))->toBe($this->m->chuDe['t-so.v']);
});

it('chấm một lô: tiến độ theo luật 6 mức, xu, nhật ký, phiên, thống kê ngày, sổ xu, Hôm nay', function () {
    $this->actingAs($u = User::factory()->create());
    $phien = $this->postJson('/api/v1/hoc/phien')->json('data.id');
    $zahlen = $this->m->the['zahlen'];
    $zaehlen = $this->m->the['zählen'];
    $zahl = $this->m->the['die Zahl'];

    $r = $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [
        luot($zahlen, 2, lucHoc('2026-11-27 09:00:05'), $phien),
        luot($zaehlen, 0, lucHoc('2026-11-27 09:00:10'), $phien),
        luot($zahl, 3, lucHoc('2026-11-27 09:00:15'), $phien),
    ]])->assertOk();

    expect($r->json('data'))->toMatchArray(['received' => 3, 'duplicates' => 0, 'skipped' => [], 'coins' => 10, 'too_fast' => 0])
        ->and($r->json('data.today'))->toBe(['reviewed' => 3, 'goal' => 20, 'coins' => 10, 'streak' => 1]);

    $p = DB::table('card_progress')->where('user_id', $u->id)->get()->keyBy('card_id');
    expect($p)->toHaveCount(3)
        ->and($p[$zahlen])->toMatchObject(['box' => 2, 'reps' => 1, 'lapses' => 0, 'last_rating' => 2])
        ->and(gioHoc($p[$zahlen]->due_at))->toBe('2026-11-30 00:00:00')
        ->and($p[$zaehlen])->toMatchObject(['box' => 1, 'lapses' => 0, 'last_rating' => 0])
        ->and(gioHoc($p[$zaehlen]->due_at))->toBe('2026-11-27 09:10:10')
        ->and(gioHoc($p[$zahl]->due_at))->toBe('2026-12-04 00:00:00')
        ->and($p[$zahl]->first_seen_at)->toBe(lucHoc('2026-11-27 09:00:15')->getTimestamp());

    $log = DB::table('review_logs')->where('user_id', $u->id)->orderBy('reviewed_at')->get();
    expect($log->pluck('box_after')->all())->toBe([2, 1, 3])->and($log->pluck('box_before')->all())->toBe([0, 0, 0])
        ->and($log->pluck('coins')->all())->toBe([5, 0, 5])->and($log->pluck('no_coin_reason')->filter()->all())->toBe([])
        ->and($log->pluck('session_id')->unique()->all())->toBe([$phien]);

    $s = DB::table('study_sessions')->find($phien);
    expect($s)->toMatchObject(['done_new' => 3, 'done_review' => 0, 'leveled_up' => 3, 'coins' => 10, 'ended_at' => lucHoc('2026-11-27 09:00:15')->getTimestamp()])
        ->and(json_decode($s->ratings, true))->toBe(['0' => 1, '2' => 1, '3' => 1]);

    $ngay = DB::table('user_daily_stats')->where('user_id', $u->id)->first();
    expect($ngay)->toMatchObject(['day_start' => lucHoc('2026-11-27 00:00:00')->getTimestamp(), 'reviews' => 0, 'new_cards' => 3, 'correct' => 2, 'coins' => 10, 'study_seconds' => 9, 'goal_met' => 0]);

    expect(DB::table('user_stats')->where('user_id', $u->id)->first())->toMatchObject([
        'coins_total' => 10, 'coins_balance' => 10, 'streak_current' => 1, 'streak_longest' => 1, 'cards_seen' => 3, 'cards_mastered' => 0, 'reviews_total' => 3,
    ])->and(DB::table('coin_transactions')->where('user_id', $u->id)->get()->map(fn ($x) => [$x->amount, $x->reason, $x->ref_type, $x->ref_id, $x->balance_after])->all())
        ->toBe([[10, 'study', 'study_session', $phien, 10]]);

    $h = $this->getJson('/api/v1/hom-nay')->assertOk()->json('data');
    expect($h)->toMatchArray([
        'date' => '2026-11-27', 'timezone' => MUI_GIO_HOC, 'due' => 1, 'due_by_level' => [0, 1, 0, 0, 0, 0], 'levels' => [28, 1, 1, 1, 0, 0],
        'new_next' => 10, 'new_available' => 27, 'reviewed_today' => 3, 'goal' => 20, 'coins' => 10, 'coins_today' => 10, 'streak' => 1, 'streak_longest' => 1,
    ])->and($h['new_topic'])->toBe(['slug' => 'so-dem-tu-vung', 'name' => 'Số đếm', 'new' => 22])
        ->and($h['days'])->toHaveCount(14)
        ->and(array_count_values(array_column($h['days'], 'kind')))->toBe(['past' => 10, 'today' => 1, 'future' => 3])
        ->and($h['days'][10])->toBe(['date' => '2026-11-27', 'count' => 3, 'kind' => 'today'])
        ->and(array_slice($h['days'], 11))->toBe([
            ['date' => '2026-11-28', 'count' => 0, 'kind' => 'future'],
            ['date' => '2026-11-29', 'count' => 0, 'kind' => 'future'],
            ['date' => '2026-11-30', 'count' => 1, 'kind' => 'future'],
        ])
        ->and($h['days'][0]['date'])->toBe('2026-11-17')
        ->and($h['due_topics'])->toBe([['slug' => 'so-dem-tu-vung', 'name' => 'Số đếm', 'collection' => 'vocab', 'due' => 1, 'new' => 22, 'levels' => [22, 1, 1, 1, 0, 0]]]);
});

it('mục 4: gửi một lô hai lần (và trùng ngay trong lô) thì ghi một lần, xu cộng một lần', function () {
    $this->actingAs($u = User::factory()->create());
    $phien = $this->postJson('/api/v1/hoc/phien')->json('data.id');
    $lo = [
        luot($this->m->the['zahlen'], 2, lucHoc('2026-11-27 09:00:05'), $phien),
        luot($this->m->the['zählen'], 3, lucHoc('2026-11-27 09:00:10'), $phien),
    ];

    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => $lo])->assertOk()->assertJsonPath('data.received', 2)->assertJsonPath('data.coins', 10);
    $truoc = DB::table('card_progress')->where('user_id', $u->id)->orderBy('card_id')->get();

    $r = $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [...$lo, $lo[0]]])->assertOk();
    expect($r->json('data'))->toMatchArray(['received' => 0, 'duplicates' => 3, 'coins' => 0])
        ->and(DB::table('review_logs')->where('user_id', $u->id)->count())->toBe(2)
        ->and(DB::table('card_progress')->where('user_id', $u->id)->orderBy('card_id')->get())->toEqual($truoc)
        ->and(DB::table('user_stats')->where('user_id', $u->id)->value('coins_total'))->toBe(10)
        ->and(DB::table('user_daily_stats')->where('user_id', $u->id)->value('new_cards'))->toBe(2)
        ->and(DB::table('coin_transactions')->where('user_id', $u->id)->count())->toBe(1)
        ->and(DB::table('study_sessions')->where('id', $phien)->value('done_new'))->toBe(2);

    // Trùng trong cùng một lô: lượt sau bỏ qua
    $mot = luot($this->m->the['die Zahl'], 2, lucHoc('2026-11-27 09:00:20'), $phien);
    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [$mot, $mot]])->assertOk()->assertJsonPath('data.received', 1)->assertJsonPath('data.duplicates', 1);
    expect(DB::table('review_logs')->where('user_id', $u->id)->count())->toBe(3);
});

it('mục 6: hai máy chấm cùng thẻ, lô tới lệch thứ tự: kết quả theo giờ chấm, như nhau dù lô nào tới trước', function () {
    $zahl = $this->m->the['die Zahl'];
    $mayA = fn () => [luot($zahl, 2, lucHoc('2026-11-27 08:00:00'), uid: '01KB0000000000000000000AAA'), luot($zahl, 3, lucHoc('2026-11-27 08:30:00'), uid: '01KB0000000000000000000AAB')];
    $mayB = fn () => [luot($zahl, 0, lucHoc('2026-11-27 08:45:00'), uid: '01KB0000000000000000000BBA')];
    $ketQua = function (User $u, array ...$cacLo): array {
        $this->actingAs($u);
        foreach ($cacLo as $lo) {
            $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => $lo])->assertOk();
        }

        return [
            (array) DB::table('card_progress')->where('user_id', $u->id)->first(['box', 'due_at', 'reps', 'lapses', 'last_rating', 'first_seen_at', 'last_seen_at']),
            DB::table('review_logs')->where('user_id', $u->id)->orderBy('reviewed_at')->get(['rating', 'box_before', 'box_after'])->map(fn ($x) => (array) $x)->all(),
        ];
    };

    $theoThuTu = $ketQua(User::factory()->create(), $mayA(), $mayB());
    $nguocLai = $ketQua(User::factory()->create(), $mayB(), $mayA());

    // Tốt 0→2, Dễ 2→4, Quên 4→2 (lùi 2 mức, một lần quên); hạn: 10 phút sau lượt Quên
    expect($nguocLai)->toBe($theoThuTu)
        ->and($theoThuTu[0])->toMatchArray(['box' => 2, 'reps' => 3, 'lapses' => 1, 'last_rating' => 0])
        ->and(gioHoc($theoThuTu[0]['due_at']))->toBe('2026-11-27 08:55:00')
        ->and($theoThuTu[1])->toBe([
            ['rating' => 2, 'box_before' => 0, 'box_after' => 2],
            ['rating' => 3, 'box_before' => 2, 'box_after' => 4],
            ['rating' => 0, 'box_before' => 4, 'box_after' => 2],
        ]);

    // Cùng một giây ở hai máy: xếp theo mã lượt (ULID), vẫn không phụ thuộc lô nào tới trước
    $brot = $this->m->the['das Brot'];
    $cungGiay = fn (string $uid, int $cham) => [luot($brot, $cham, lucHoc('2026-11-27 08:50:00'), uid: $uid)];
    $mot = $ketQua(User::factory()->create(), $cungGiay('01KB0000000000000000000CCA', 3), $cungGiay('01KB0000000000000000000CCB', 0));
    $hai = $ketQua(User::factory()->create(), $cungGiay('01KB0000000000000000000CCB', 0), $cungGiay('01KB0000000000000000000CCA', 3));
    expect($hai)->toBe($mot)->and($mot[0]['box'])->toBe(1);
});

it('mục 7: chấm 20 thẻ trong 2 giây thì tiến độ vẫn ghi, xu không cộng, nhật ký ghi lý do; chấm thong thả thì có xu', function () {
    $this->actingAs($u = User::factory()->create());
    $phien = $this->postJson('/api/v1/hoc/phien')->json('data.id');
    $so = DB::table('cards')->where('topic_id', $this->m->chuDe['t-so.v'])->orderBy('id')->limit(20)->pluck('id')->all();
    $bat = lucHoc('2026-11-27 09:00:01');

    $lo = array_map(fn (int $i) => luot($so[$i], 2, $bat->addMilliseconds($i * 100), $phien, 90), array_keys($so));
    $r = $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => $lo])->assertOk();

    expect($r->json('data'))->toMatchArray(['received' => 20, 'coins' => 0, 'too_fast' => 20])
        ->and(DB::table('card_progress')->where('user_id', $u->id)->where('box', 2)->count())->toBe(20)
        ->and(DB::table('review_logs')->where('user_id', $u->id)->where('no_coin_reason', CaiDatHoc::CHAM_QUA_NHANH)->where('coins', 0)->count())->toBe(20)
        ->and(DB::table('user_stats')->where('user_id', $u->id)->value('coins_total'))->toBe(0)
        ->and(DB::table('coin_transactions')->where('user_id', $u->id)->count())->toBe(0);

    // Thong thả (cách 4 giây, thẻ hiện 3 giây): có xu
    $con = DB::table('cards')->where('topic_id', $this->m->chuDe['t-so.v'])->orderBy('id')->skip(20)->limit(2)->pluck('id')->all();
    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [
        luot($con[0], 2, lucHoc('2026-11-27 09:00:10'), $phien),
        luot($con[1], 1, lucHoc('2026-11-27 09:00:14'), $phien),
    ]])->assertOk()->assertJsonPath('data.coins', 7)->assertJsonPath('data.too_fast', 0);

    // Quản trị hạ ngưỡng Flashcard xuống 50 ms: chấm cách 100 ms không còn bị coi là quá nhanh
    CaiDatHoc::luuNguong(['flashcard' => 50], null);
    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [
        luot($so[0], 2, lucHoc('2026-11-27 09:00:20'), $phien, 90),
        luot($so[1], 2, lucHoc('2026-11-27 09:00:20.100'), $phien, 90),
    ]])->assertOk()->assertJsonPath('data.too_fast', 0)->assertJsonPath('data.coins', 10);
});

it('mục 8: phiên của người khác thì 404 và không ghi gì; thẻ riêng của người khác thì bỏ lượt; số liệu tách theo người', function () {
    $b = User::factory()->create();
    $m = (new NoiDungThuSeeder);
    $m->chuDe = $this->m->chuDe;
    $m->the = $this->m->the;
    $hefezopf = $m->themThe('t-banh.v', 'der Hefezopf', 'noun', 'die Hefezöpfe', 2, 2, 2, ['vi' => 'bánh mì bện'], [], 'der', chu: $b->id);

    $this->actingAs($b);
    $phienB = $this->postJson('/api/v1/hoc/phien')->json('data.id');
    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [luot($hefezopf, 2, lucHoc('2026-11-27 09:00:05'), $phienB)]])->assertOk()->assertJsonPath('data.received', 1);

    $this->actingAs($a = User::factory()->create());
    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [luot($this->m->the['zahlen'], 2, lucHoc('2026-11-27 09:00:05'), $phienB)]])->assertNotFound();
    expect(DB::table('review_logs')->where('user_id', $a->id)->count())->toBe(0)
        ->and(DB::table('card_progress')->where('user_id', $a->id)->count())->toBe(0)
        ->and(DB::table('study_sessions')->where('id', $phienB)->value('done_new'))->toBe(1);

    $uid = (string) Str::ulid();
    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [luot($hefezopf, 2, lucHoc('2026-11-27 09:00:05'), uid: $uid)]])->assertOk()
        ->assertJsonPath('data.received', 0)->assertJsonPath('data.skipped', [$uid]);

    $h = $this->getJson('/api/v1/hom-nay')->assertOk()->json('data');
    expect($h['reviewed_today'])->toBe(0)->and($h['coins'])->toBe(0)->and($h['levels'][0])->toBe(31)
        ->and($this->getJson('/api/v1/chu-de/banh-mi-tu-vung')->json('data.progress'))->toBe(['due' => 0, 'new' => 5, 'levels' => [5, 0, 0, 0, 0, 0]]);
});

it('dữ liệu sai thì 422 đúng ô, không ghi gì', function (array $doi, string $o) {
    $this->actingAs($u = User::factory()->create());
    $l = array_merge(luot($this->m->the['zahlen'], 2, lucHoc('2026-11-27 09:00:05')), $doi);

    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [$l]])->assertStatus(422)->assertJsonValidationErrors($o);
    expect(DB::table('review_logs')->count())->toBe(0);
})->with([
    'mã lượt viết thường' => [['uid' => strtolower((string) Str::ulid())], 'reviews.0.uid'],
    'mã lượt có chữ I' => [['uid' => 'I1KB0000000000000000000AAA'], 'reviews.0.uid'],
    'chấm 4' => [['rating' => 4], 'reviews.0.rating'],
    'chế độ lạ' => [['mode' => 'hack'], 'reviews.0.mode'],
    'giờ chấm tính bằng giây' => [['reviewed_at_ms' => 1_764_208_800], 'reviews.0.reviewed_at_ms'],
    'thẻ 0' => [['card_id' => 0], 'reviews.0.card_id'],
    'thời gian âm' => [['duration_ms' => -1], 'reviews.0.duration_ms'],
]);

it('lô rỗng, quá 200 lượt hoặc thiếu ô phiên thì 422', function () {
    $this->actingAs(User::factory()->create());
    $l = luot($this->m->the['zahlen'], 2, lucHoc('2026-11-27 09:00:05'));
    unset($l['session_id']);

    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => []])->assertStatus(422)->assertJsonValidationErrors('reviews');
    $this->postJson('/api/v1/hoc/luot-cham', [])->assertStatus(422)->assertJsonValidationErrors('reviews');
    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [$l]])->assertStatus(422)->assertJsonValidationErrors('reviews.0.session_id');
    $nhieu = array_map(fn () => luot($this->m->the['zahlen'], 2, lucHoc('2026-11-27 09:00:05')), range(1, 201));
    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => $nhieu])->assertStatus(422)->assertJsonValidationErrors('reviews');

    $this->postJson('/api/v1/hoc/phien', ['topic' => 'khong-co'])->assertNotFound();
    $this->postJson('/api/v1/hoc/phien', ['topic' => 'Khong Co'])->assertStatus(422)->assertJsonValidationErrors('topic');
    $this->postJson('/api/v1/hoc/phien', ['mode' => 'hack'])->assertStatus(422)->assertJsonValidationErrors('mode');
    $this->getJson('/api/v1/hoc/con-lai?chu_de=khong-co')->assertNotFound();
    expect(DB::table('study_sessions')->count())->toBe(0);
});

it('giờ chấm trên máy: quá xa tương lai thì lấy giờ máy chủ, trước lúc mở phiên thì lấy lúc mở phiên', function () {
    $this->actingAs($u = User::factory()->create());
    $phien = $this->postJson('/api/v1/hoc/phien')->json('data.id');

    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [
        luot($this->m->the['zahlen'], 2, lucHoc('2026-11-27 12:00:00'), $phien),
        luot($this->m->the['zählen'], 2, lucHoc('2026-11-27 08:00:00'), $phien),
    ]])->assertOk();

    $luc = DB::table('review_logs')->where('user_id', $u->id)->pluck('reviewed_at', 'card_id');
    expect(gioHoc($luc[$this->m->the['zahlen']]))->toBe('2026-11-27 09:00:00')
        ->and(gioHoc($luc[$this->m->the['zählen']]))->toBe('2026-11-27 09:00:00');
});

it('phiên ngày sau: thẻ đến hạn (quá hạn lâu nhất trước) xen kẽ với từ mới như Lernbuch', function () {
    $this->actingAs($u = User::factory()->create());
    $this->travelTo(lucHoc('2026-11-26 09:00:00'));
    $phien = $this->postJson('/api/v1/hoc/phien')->json('data.id');
    $truoc = $this->postJson('/api/v1/hoc/phien')->json('data.cards');
    $ids = array_column(array_slice($truoc, 0, 4), 'id');
    // Khó ở thẻ mới: mức 1, hạn sau 1 ngày (00:00 ngày 27/11)
    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => array_map(fn (int $i) => luot($ids[$i], 1, lucHoc('2026-11-26 09:00:0'.($i + 1)), $phien, 2000), array_keys($ids))])->assertOk();

    $this->travelTo(lucHoc('2026-11-27 09:00:00'));
    $r = $this->postJson('/api/v1/hoc/phien')->assertCreated();
    $the = $r->json('data.cards');

    expect($r->json('data.counts'))->toBe(['review' => 4, 'new' => 10])
        ->and(array_column($the, 'box'))->toBe([1, 0, 1, 0, 1, 0, 1, 0, 0, 0, 0, 0, 0, 0])
        ->and(array_column(array_filter($the, fn ($c) => $c['box'] === 1), 'id'))->toBe($ids)
        ->and(array_column($the, 'front'))->toContain('sieben')
        ->and($this->getJson('/api/v1/hoc/con-lai')->json('data'))->toBe(['review' => 4, 'new' => 10])
        ->and($this->getJson('/api/v1/hoc/con-lai?on=1')->json('data'))->toBe(['review' => 4, 'new' => 0]);

    // Chỉ ôn: không có từ mới
    expect($this->postJson('/api/v1/hoc/phien', ['review_only' => true])->json('data.counts'))->toBe(['review' => 4, 'new' => 0]);
});

it('hết từ mới của chủ đề đang học thì sang chủ đề kế theo thứ tự; "tất cả" thì chỉ lấy hết một chủ đề', function () {
    $this->actingAs($u = User::factory()->create());
    UserSetting::luu($u, ['new_per_session' => 50]);

    // Từ vựng còn 25 (Số đếm) + 5 (Bánh mì) từ mới: lấy hết rồi dừng, không lấy chủ đề Giao tiếp hay chủ đề đã ẩn
    $r = $this->postJson('/api/v1/hoc/phien')->assertCreated();
    $the = $r->json('data.cards');
    expect($r->json('data.counts.new'))->toBe(30)
        ->and(count(array_filter($the, fn ($c) => $c['topic']['slug'] === 'so-dem-tu-vung')))->toBe(25)
        ->and(array_column(array_slice($the, 25), 'front'))->toBe(['das Brötchen', 'das Brot', 'das Mehl', 'backen', 'süß'])
        ->and($r->json('data.new_topic.slug'))->toBe('banh-mi-tu-vung')
        ->and(DB::table('user_courses')->where('user_id', $u->id)->value('current_topic_id'))->toBe($this->m->chuDe['t-banh.v']);
});

it('"tất cả" từ mới: lấy hết từ mới của chủ đề đang học, không sang chủ đề kế', function () {
    $this->actingAs($u = User::factory()->create());
    UserSetting::luu($u, ['new_per_session' => 0]);

    $r = $this->postJson('/api/v1/hoc/phien')->assertCreated();
    expect($r->json('data.counts'))->toBe(['review' => 0, 'new' => 25])
        ->and(collect($r->json('data.cards'))->pluck('topic.slug')->unique()->all())->toBe(['so-dem-tu-vung'])
        ->and($this->getJson('/api/v1/hom-nay')->json('data.new_next'))->toBe(25);
});

it('học riêng một chủ đề: ôn và mới đều trong chủ đề đó, chủ đề đó thành chủ đề đang học', function () {
    $this->actingAs($u = User::factory()->create());

    $r = $this->postJson('/api/v1/hoc/phien', ['topic' => 'banh-mi-tu-vung'])->assertCreated();
    expect(array_column($r->json('data.cards'), 'front'))->toBe(['das Brötchen', 'das Brot', 'das Mehl', 'backen', 'süß'])
        ->and($r->json('data.topic'))->toBe(['slug' => 'banh-mi-tu-vung', 'name' => 'Bánh mì · bánh ngọt'])
        ->and($r->json('data.cards.0.example'))->toBe(['sentence' => 'Ich möchte zwei Brötchen, bitte.', 'translations' => ['vi' => 'Cho tôi hai cái bánh mì nhỏ.', 'en' => 'Two bread rolls, please.']])
        ->and(DB::table('study_sessions')->value('topic_id'))->toBe($this->m->chuDe['t-banh.v'])
        ->and(PhienHoc::chuDeDangHoc($u)?->slug)->toBe('banh-mi-tu-vung');

    // Chủ đề giao tiếp, và chủ đề đã ẩn thì 404
    expect($this->postJson('/api/v1/hoc/phien', ['topic' => 'o-tiem-banh-giao-tiep'])->json('data.counts'))->toBe(['review' => 0, 'new' => 1]);
    $this->postJson('/api/v1/hoc/phien', ['topic' => 'chu-de-da-an-tu-vung'])->assertNotFound();
});

it('chuỗi ngày: ngày liền nhau thì tăng, bỏ một ngày thì về 1; lượt của ngày cũ tới trễ thì đếm lại', function () {
    $this->actingAs($u = User::factory()->create());
    $the = DB::table('cards')->where('topic_id', $this->m->chuDe['t-so.v'])->orderBy('id')->pluck('id')->all();
    $cham = fn (int $i, string $luc) => $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [luot($the[$i], 2, lucHoc($luc))]])->assertOk()->json('data.today.streak');

    foreach (['2026-11-25', '2026-11-26', '2026-11-27'] as $i => $ngay) {
        $this->travelTo(lucHoc("{$ngay} 20:00:00"));
        expect($cham($i, "{$ngay} 20:00:00"))->toBe($i + 1);
    }

    // Bỏ ngày 28, học ngày 29: chuỗi về 1, dài nhất giữ 3
    $this->travelTo(lucHoc('2026-11-29 08:00:00'));
    expect($cham(3, '2026-11-29 08:00:00'))->toBe(1)
        ->and(DB::table('user_stats')->where('user_id', $u->id)->value('streak_longest'))->toBe(3);

    // Máy học offline ngày 28, giờ mới gửi: chuỗi nối lại thành 5 ngày
    expect($cham(4, '2026-11-28 21:00:00'))->toBe(5)
        ->and(DB::table('user_stats')->where('user_id', $u->id)->first())->toMatchObject(['streak_current' => 5, 'streak_longest' => 5]);

    // Hôm nay: hôm qua có học thì vẫn hiện chuỗi; quá hai ngày không học thì về 0
    $this->travelTo(lucHoc('2026-11-30 10:00:00'));
    expect($this->getJson('/api/v1/hom-nay')->json('data.streak'))->toBe(5);
    $this->travelTo(lucHoc('2026-12-01 10:00:00'));
    expect($this->getJson('/api/v1/hom-nay')->json('data.streak'))->toBe(0)->and($this->getJson('/api/v1/hom-nay')->json('data.streak_longest'))->toBe(5);
});

it('mục tiêu ngày: đủ số thẻ thì ghi đã đạt', function () {
    $this->actingAs($u = User::factory()->create());
    UserSetting::luu($u, ['daily_goal' => 5]);
    $the = DB::table('cards')->where('topic_id', $this->m->chuDe['t-so.v'])->orderBy('id')->limit(5)->pluck('id')->all();

    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => array_map(fn (int $i) => luot($the[$i], 2, lucHoc('2026-11-27 08:5'.$i.':00')), range(0, 3))])->assertOk();
    expect(DB::table('user_daily_stats')->where('user_id', $u->id)->value('goal_met'))->toBe(0);
    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [luot($the[4], 2, lucHoc('2026-11-27 08:59:00'))]])->assertOk()->assertJsonPath('data.today', ['reviewed' => 5, 'goal' => 5, 'coins' => 25, 'streak' => 1]);
    expect(DB::table('user_daily_stats')->where('user_id', $u->id)->value('goal_met'))->toBe(1);
});

it('người mới mở Hôm nay: mọi số bằng 0, 14 ngày, chủ đề đang học là chủ đề đầu tiên', function () {
    $this->actingAs(User::factory()->create());

    $h = $this->getJson('/api/v1/hom-nay')->assertOk()->json('data');
    expect($h)->toMatchArray(['due' => 0, 'new_next' => 10, 'new_available' => 30, 'reviewed_today' => 0, 'coins' => 0, 'streak' => 0, 'levels' => [31, 0, 0, 0, 0, 0], 'due_topics' => []])
        ->and($h['new_topic'])->toBe(['slug' => 'so-dem-tu-vung', 'name' => 'Số đếm', 'new' => 25])
        ->and(array_column($h['days'], 'count'))->toBe(array_fill(0, 14, 0));
});

it('trang chủ đề có tiến độ của người học: đến hạn, chưa học, số thẻ theo mức', function () {
    $this->actingAs($u = User::factory()->create());
    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [
        luot($this->m->the['das Brot'], 3, lucHoc('2026-11-27 08:00:00')),
        luot($this->m->the['backen'], 0, lucHoc('2026-11-27 08:00:10')),
    ]])->assertOk();

    expect($this->getJson('/api/v1/chu-de/banh-mi-tu-vung')->assertOk()->json('data.progress'))->toBe(['due' => 1, 'new' => 3, 'levels' => [3, 1, 0, 1, 0, 0]]);
});

it('chưa đăng nhập thì 401', function () {
    $this->getJson('/api/v1/hom-nay')->assertUnauthorized();
    $this->postJson('/api/v1/hoc/phien')->assertUnauthorized();
    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => []])->assertUnauthorized();
});
