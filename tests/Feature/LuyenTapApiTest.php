<?php

use App\Models\User;
use App\Support\LuyenTap\BaiTap;
use App\Support\LuyenTap\DongTu;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Luyện tập theo Lernbuch: ngữ pháp (theo chương, giáo trình gốc, chuyên đề), bài tập (bộ theo cấp, Ôn tập, Số nhiều;
// Luyện nhanh, Ôn câu sai; máy chủ chấm lại, chống gửi trùng, đúng 2 lần liên tiếp là đã thuộc), động từ, Điền câu.

beforeEach(function () {
    $this->seed();
    $this->m = (new NoiDungThuSeeder)->tao()->taoLuyenTap();
    $this->travelTo(lucHoc('2026-11-27 09:00:00'));
});

/** @param  list<array{0: string, 1: string}>  $traLoi  mã câu, chữ trả lời */
function ketQuaBaiTap(NoiDungThuSeeder $m, array $traLoi, int $giay = 60, string $loai = 'set', ?string $bo = 'bt-LB3', ?string $uid = null, int $lechMs = 0): array
{
    $xong = (int) now()->getTimestampMs() + $lechMs;

    return [
        'uid' => $uid ?? (string) Str::ulid(),
        'mode' => $loai,
        'grading' => 'instant',
        'set' => $bo,
        'started_at_ms' => $xong - $giay * 1000,
        'finished_at_ms' => $xong,
        'items' => array_map(fn (array $x) => $loai === 'conjugate' ? ['sig' => $x[0], 'answer' => $x[1]] : ['id' => $m->cau[$x[0]], 'answer' => $x[1]], $traLoi),
    ];
}

// ---------------------------------------------------------------- ngữ pháp

it('danh sách ngữ pháp: bài theo thứ tự giáo trình, kèm chương và số câu bài tập; bài nháp không hiện', function () {
    $this->actingAs(User::factory()->create());

    $d = $this->getJson('/api/v1/ngu-phap')->assertOk()->json('data');

    expect($d['chapters'])->toBe([['code' => 'A1-1', 'level' => 'A1', 'no' => 1, 'name' => 'Danh từ & mạo từ']])
        ->and($d['lessons'])->toBe([
            ['slug' => 'a1-1', 'title' => 'Nền tảng: đại từ, mạo từ, chia động từ, trật tự câu', 'level' => 'A1', 'chapter' => null, 'exercises' => 0],
            ['slug' => 'slang', 'title' => 'Tiếng lóng & tiếng Đức đời thường', 'level' => null, 'chapter' => null, 'exercises' => 0],
            // 4 câu của bộ theo cấp (câu nháp không tính) và 3 câu của bộ Ôn tập ngữ pháp gắn với bài
            ['slug' => 'g-A1-22', 'title' => 'Genus: der, die, das  ·  Giống: der, die, das', 'level' => 'A1', 'chapter' => 'A1-1', 'exercises' => 7],
            ['slug' => 'g-A1-23', 'title' => 'Plural  ·  Số nhiều', 'level' => 'A1', 'chapter' => 'A1-1', 'exercises' => 3],
        ]);
});

it('một bài ngữ pháp: khối nội dung, bộ bài tập của bài, bài trước và bài sau cùng loại', function () {
    $this->actingAs(User::factory()->create());

    $b = $this->getJson('/api/v1/ngu-phap/g-A1-22')->assertOk()->json('data');
    expect($b)->toMatchArray([
        'slug' => 'g-A1-22', 'level' => 'A1', 'prev' => null, 'next' => ['slug' => 'g-A1-23', 'title' => 'Plural  ·  Số nhiều'],
        'chapter' => ['code' => 'A1-1', 'level' => 'A1', 'no' => 1, 'name' => 'Danh từ & mạo từ'],
        'sets' => [['slug' => 'bt-LB3', 'title' => 'Giống danh từ & mạo từ (der / die / das)', 'count' => 4], ['slug' => 'dx-g1-1A', 'title' => '1A · Điền der / die / das', 'count' => 3]],
    ])->and($b['blocks'])->toEqual(json_decode((string) DB::table('grammar_lessons')->where('slug', 'g-A1-22')->value('blocks'), true))
        ->and(array_column($b['blocks'], 't'))->toBe(['fm', 'tipl', 'h2', 'ex', 'ex', 'h2', 'tbl', 'recap']);

    // Bài cuối theo chương: bài nháp sau nó không tính
    expect($this->getJson('/api/v1/ngu-phap/g-A1-23')->json('data'))->toMatchArray(['prev' => ['slug' => 'g-A1-22', 'title' => 'Genus: der, die, das  ·  Giống: der, die, das'], 'next' => null]);
    // Giáo trình gốc và chuyên đề nối với nhau, không nối với bài theo chương
    expect($this->getJson('/api/v1/ngu-phap/a1-1')->json('data'))->toMatchArray(['chapter' => null, 'sets' => [], 'prev' => null, 'next' => ['slug' => 'slang', 'title' => 'Tiếng lóng & tiếng Đức đời thường']])
        ->and($this->getJson('/api/v1/ngu-phap/slang')->json('data'))->toMatchArray(['level' => null, 'prev' => ['slug' => 'a1-1', 'title' => 'Nền tảng: đại từ, mạo từ, chia động từ, trật tự câu'], 'next' => null]);
});

it('bài nháp, bài không có, slug lạ thì 404; chưa đăng nhập thì 401', function () {
    $this->getJson('/api/v1/ngu-phap')->assertUnauthorized();
    $this->actingAs(User::factory()->create());

    $this->getJson('/api/v1/ngu-phap/g-A1-99')->assertNotFound();
    $this->getJson('/api/v1/ngu-phap/khong-co')->assertNotFound();
    $this->getJson('/api/v1/ngu-phap/a%20b')->assertNotFound();
});

// ---------------------------------------------------------------- bài tập: danh sách, phiên

it('danh sách bộ bài: theo thứ tự, kèm bài ngữ pháp, chương, số câu; bộ nháp và câu nháp không tính', function () {
    $this->actingAs(User::factory()->create());

    $d = $this->getJson('/api/v1/bai-tap')->assertOk()->json('data');

    expect($d['sets'])->toBe([
        ['slug' => 'bt-LB3', 'title' => 'Giống danh từ & mạo từ (der / die / das)', 'section' => 'level', 'level' => 'A1', 'lesson' => 'g-A1-22', 'group' => null, 'note' => null, 'count' => 4, 'done' => 0, 'weak' => 0],
        ['slug' => 'dx-g1-1A', 'title' => '1A · Điền der / die / das', 'section' => 'level', 'level' => 'A1', 'lesson' => 'g-A1-22', 'group' => 'Chương 1 · Artikel & Genus', 'note' => 'Mạo từ xác định ở Nominativ: der, die, das.', 'count' => 3, 'done' => 0, 'weak' => 0],
        ['slug' => 'dx-v1-1A', 'title' => '1A · Điền từ vào chỗ trống', 'section' => 'review', 'level' => null, 'lesson' => null, 'group' => 'Bài 1 · Person & Familie', 'note' => null, 'count' => 2, 'done' => 0, 'weak' => 0],
        ['slug' => 'pl-e', 'title' => 'Số nhiều: Thêm -e', 'section' => 'plural', 'level' => null, 'lesson' => 'g-A1-23', 'group' => 'Số nhiều', 'note' => 'Việc cần làm: viết dạng số nhiều của danh từ. Quy tắc: nhiều danh từ giống đực và giống trung một âm tiết thêm -e.', 'count' => 3, 'done' => 0, 'weak' => 0],
    ])->and($d['lessons'])->toBe([
        ['slug' => 'g-A1-22', 'title' => 'Genus: der, die, das  ·  Giống: der, die, das', 'level' => 'A1', 'chapter' => 'A1-1'],
        ['slug' => 'g-A1-23', 'title' => 'Plural  ·  Số nhiều', 'level' => 'A1', 'chapter' => 'A1-1'],
    ])->and($d['chapters'])->toBe([['code' => 'A1-1', 'level' => 'A1', 'no' => 1, 'name' => 'Danh từ & mạo từ']]);
});

it('phiên theo bộ có thứ tự: đúng thứ tự trong bộ, đủ thông tin để làm và chấm 4 dạng câu', function () {
    $this->actingAs(User::factory()->create());

    $ds = $this->getJson('/api/v1/bai-tap/phien?bo=dx-g1-1A&thu_tu=1&n=20')->assertOk()->json('data');

    $bo = ['slug' => 'dx-g1-1A', 'title' => '1A · Điền der / die / das', 'group' => 'Chương 1 · Artikel & Genus', 'note' => 'Mạo từ xác định ở Nominativ: der, die, das.'];
    expect(array_column($ds, 'kind'))->toBe(['fill', 'fix', 'order'])
        ->and($ds[0])->toBe([
            'id' => $this->m->cau['dx:g1:1A:1'], 'kind' => 'fill', 'prompt' => 'Hier ist ____ Lehrer.', 'answers' => ['der'], 'options' => null,
            'hint' => null, 'explanation' => 'Lehrer là giống đực: der Lehrer.', 'label' => 'Điền vào chỗ trống', 'instr' => 'Điền der, die hoặc das.',
            'case_sensitive' => false, 'bank' => null, 'tokens' => null, 'lead' => null, 'frag' => [], 'set' => $bo,
        ])
        ->and($ds[1])->toMatchArray(['prompt' => 'Das Frau heißt Anna.', 'answers' => ['Die Frau heißt Anna'], 'hint' => 'Sửa lại cho đúng', 'frag' => ['Die Frau']])
        ->and($ds[2])->toMatchArray(['prompt' => '', 'answers' => ['Das Kind spielt im Garten'], 'tokens' => ['spielt', 'Das Kind', 'im Garten'], 'lead' => null]);
});

it('trắc nghiệm: lựa chọn đủ và trộn; đáp án lưu khác lựa chọn chỉ ở hoa thường thì dùng chữ của lựa chọn; "fixed" giữ thứ tự', function () {
    $this->actingAs(User::factory()->create());
    $bo = $this->m->bo['bt-LB3'];
    DB::table('exercises')->insert([
        ['set_id' => $bo, 'sig' => 'mc:hoa', 'kind' => 'mc', 'prompt' => '____ Mann ist groß.', 'answers' => '["der"]', 'choices' => '["Der", "Die", "Das"]', 'extra' => null, 'sort' => 50, 'status' => 'published'],
        ['set_id' => $bo, 'sig' => 'mc:thieu', 'kind' => 'mc', 'prompt' => 'Ich sehe ____ Mann.', 'answers' => '["den"]', 'choices' => '["der", "dem"]', 'extra' => null, 'sort' => 51, 'status' => 'published'],
        ['set_id' => $bo, 'sig' => 'mc:rf', 'kind' => 'mc', 'prompt' => 'Alle Pluralartikel sind die.', 'answers' => '["Richtig"]', 'choices' => '["Richtig", "Falsch"]', 'extra' => '{"fixed": 1}', 'sort' => 52, 'status' => 'published'],
    ]);

    $ds = collect($this->getJson('/api/v1/bai-tap/phien?bo=bt-LB3&thu_tu=1&n=20')->json('data'))->keyBy('prompt');

    expect($ds['____ Tisch ist neu.']['options'])->toEqualCanonicalizing(['Der', 'Die', 'Das'])
        ->and($ds['____ Mann ist groß.'])->toMatchArray(['answers' => ['Der']])
        ->and($ds['____ Mann ist groß.']['options'])->toEqualCanonicalizing(['Der', 'Die', 'Das'])
        ->and($ds['Ich sehe ____ Mann.']['options'])->toEqualCanonicalizing(['der', 'dem', 'den'])
        ->and($ds['Alle Pluralartikel sind die.']['options'])->toBe(['Richtig', 'Falsch']);

    // Máy chủ chấm theo đáp án đã chỉnh: chọn "Der" là đúng
    $r = $this->postJson('/api/v1/bai-tap/ket-qua', [...ketQuaBaiTap($this->m, []), 'items' => [['id' => $ds['____ Mann ist groß.']['id'], 'answer' => 'Der']]])->assertOk();
    expect($r->json('data.correct'))->toBe(1);
});

it('Luyện nhanh: không lấy bộ Ôn tập; lọc cấp thì bộ không có cấp (Số nhiều) hợp mọi cấp; n từ 3 đến 60', function () {
    $this->actingAs(User::factory()->create());

    $ds = $this->getJson('/api/v1/bai-tap/phien')->assertOk()->json('data');
    expect($ds)->toHaveCount(7)
        ->and(collect($ds)->pluck('set.slug')->unique()->sort()->values()->all())->toBe(['bt-LB3', 'pl-e'])
        ->and(collect($ds)->pluck('id')->unique())->toHaveCount(7);

    expect(collect($this->getJson('/api/v1/bai-tap/phien?cap=2')->json('data'))->pluck('set.slug')->unique()->values()->all())->toBe(['pl-e'])
        ->and($this->getJson('/api/v1/bai-tap/phien?cap=1')->json('data'))->toHaveCount(7)
        ->and($this->getJson('/api/v1/bai-tap/phien?bo=bt-LB3&n=1')->json('data'))->toHaveCount(BaiTap::N_TOI_THIEU)
        // Chọn bộ thì lấy cả bộ Ôn tập; bộ nháp không có câu nào
        ->and($this->getJson('/api/v1/bai-tap/phien?bo=dx-v1-1A,bt-nhap')->json('data'))->toHaveCount(2);

    $this->getJson('/api/v1/bai-tap/phien?bo=a;b')->assertUnprocessable()->assertJsonValidationErrors('bo');
    $this->getJson('/api/v1/bai-tap/phien?cap=x')->assertUnprocessable()->assertJsonValidationErrors('cap');
    $this->getJson('/api/v1/bai-tap/phien?n=abc')->assertUnprocessable()->assertJsonValidationErrors('n');
});

// ---------------------------------------------------------------- bài tập: kết quả

it('ghi kết quả: máy chủ chấm lại từng câu, đếm đúng liên tiếp, cộng 5 xu mỗi câu đúng, sổ xu, thống kê ngày', function () {
    $this->actingAs($u = User::factory()->create());

    $r = $this->postJson('/api/v1/bai-tap/ket-qua', ketQuaBaiTap($this->m, [['lb3:1', 'Der'], ['lb3:2', 'Das'], ['lb3:3', 'das'], ['lb3:4', 'die']]))->assertOk();

    expect($r->json('data'))->toMatchArray(['duplicate' => false, 'coins' => 10, 'correct' => 2, 'total' => 4, 'too_fast' => false])
        ->and($r->json('data.items'))->toBe([
            ['id' => $this->m->cau['lb3:1'], 'score' => 2], ['id' => $this->m->cau['lb3:2'], 'score' => 0],
            // Đúng chữ nhưng sai hoa thường ở câu phân biệt hoa thường: 1 điểm, không tính là đúng
            ['id' => $this->m->cau['lb3:3'], 'score' => 1], ['id' => $this->m->cau['lb3:4'], 'score' => 2],
        ])
        ->and($r->json('data.today'))->toBe(['coins' => 10, 'coins_today' => 10, 'exercises_today' => 4]);

    $luc = now()->getTimestamp();
    $run = DB::table('exercise_runs')->where('user_id', $u->id)->first();
    expect($run)->toMatchObject(['mode' => 'set', 'grading' => 'instant', 'set_id' => $this->m->bo['bt-LB3'], 'total' => 4, 'correct' => 2, 'coins' => 10,
        'no_coin_reason' => null, 'started_at' => $luc - 60, 'finished_at' => $luc]);

    $tk = DB::table('user_exercise_stats')->where('user_id', $u->id)->get()->keyBy('exercise_id');
    expect($tk)->toHaveCount(4)
        ->and($tk[$this->m->cau['lb3:1']])->toMatchObject(['correct_count' => 1, 'wrong_count' => 0, 'correct_streak' => 1, 'last_correct' => 1, 'last_answer' => 'Der', 'last_at' => $luc])
        ->and($tk[$this->m->cau['lb3:2']])->toMatchObject(['correct_count' => 0, 'wrong_count' => 1, 'correct_streak' => 0, 'last_correct' => 0, 'last_answer' => 'Das'])
        ->and($tk[$this->m->cau['lb3:3']])->toMatchObject(['wrong_count' => 1, 'correct_streak' => 0, 'last_correct' => 0]);

    expect(DB::table('coin_transactions')->where('user_id', $u->id)->get()->all())->toHaveCount(1)
        ->and(DB::table('coin_transactions')->where('user_id', $u->id)->first())->toMatchObject(['amount' => 10, 'reason' => 'exercise', 'ref_type' => 'exercise_run', 'ref_id' => $run->id, 'balance_after' => 10])
        ->and(DB::table('user_stats')->where('user_id', $u->id)->first())->toMatchObject(['coins_total' => 10, 'coins_balance' => 10, 'streak_current' => 0])
        ->and(DB::table('user_daily_stats')->where('user_id', $u->id)->first())->toMatchObject(['day_start' => lucHoc('2026-11-27 00:00:00')->getTimestamp(), 'exercises' => 4, 'coins' => 10, 'reviews' => 0]);

    // Tiến độ trên danh sách: 2 câu đang sai; đúng thêm một lần nữa thì câu đúng 2 lần liên tiếp là đã thuộc
    expect($this->getJson('/api/v1/bai-tap')->json('data.sets.0'))->toMatchArray(['slug' => 'bt-LB3', 'done' => 0, 'weak' => 2]);
    $this->postJson('/api/v1/bai-tap/ket-qua', ketQuaBaiTap($this->m, [['lb3:1', 'Der'], ['lb3:2', 'Die'], ['lb3:3', 'Das'], ['lb3:4', 'die.']]))->assertOk()->assertJsonPath('data.coins', 20);
    expect($this->getJson('/api/v1/bai-tap')->json('data.sets.0'))->toMatchArray(['done' => 2, 'weak' => 0])
        ->and(DB::table('user_stats')->where('user_id', $u->id)->value('coins_balance'))->toBe(30);
});

it('gửi lại cùng mã: không ghi lần hai, xu không cộng lần hai, trả kết quả đã ghi', function () {
    $this->actingAs($u = User::factory()->create());
    $lan = ketQuaBaiTap($this->m, [['lb3:1', 'Der'], ['lb3:4', 'die']]);

    $this->postJson('/api/v1/bai-tap/ket-qua', $lan)->assertOk()->assertJsonPath('data.duplicate', false);
    $r = $this->postJson('/api/v1/bai-tap/ket-qua', $lan)->assertOk();

    expect($r->json('data'))->toMatchArray(['duplicate' => true, 'coins' => 10, 'correct' => 2, 'total' => 2])
        ->and(DB::table('exercise_runs')->where('user_id', $u->id)->count())->toBe(1)
        ->and(DB::table('coin_transactions')->where('user_id', $u->id)->count())->toBe(1)
        ->and(DB::table('user_stats')->where('user_id', $u->id)->value('coins_balance'))->toBe(10)
        ->and(DB::table('user_exercise_stats')->where('user_id', $u->id)->where('exercise_id', $this->m->cau['lb3:1'])->value('correct_count'))->toBe(1)
        ->and(DB::table('user_daily_stats')->where('user_id', $u->id)->value('exercises'))->toBe(2);
});

it('làm quá nhanh (dưới 1 giây mỗi câu): vẫn ghi đúng sai, không cộng xu, ghi lý do', function () {
    $this->actingAs($u = User::factory()->create());

    $r = $this->postJson('/api/v1/bai-tap/ket-qua', ketQuaBaiTap($this->m, [['lb3:1', 'Der'], ['lb3:2', 'Die'], ['lb3:3', 'Das'], ['lb3:4', 'die']], giay: 2))->assertOk();

    expect($r->json('data'))->toMatchArray(['coins' => 0, 'correct' => 4, 'too_fast' => true])
        ->and(DB::table('exercise_runs')->where('user_id', $u->id)->value('no_coin_reason'))->toBe('too_fast')
        ->and(DB::table('user_exercise_stats')->where('user_id', $u->id)->sum('correct_count'))->toEqual(4)
        ->and(DB::table('coin_transactions')->where('user_id', $u->id)->exists())->toBeFalse()
        ->and(DB::table('user_daily_stats')->where('user_id', $u->id)->first())->toMatchObject(['exercises' => 4, 'coins' => 0]);
});

it('kết quả tới trễ (làm trước lần đã ghi): chỉ cộng vào số đếm, không đổi chuỗi đúng và lần trả lời cuối', function () {
    $this->actingAs($u = User::factory()->create());

    $this->postJson('/api/v1/bai-tap/ket-qua', ketQuaBaiTap($this->m, [['lb3:1', 'Der']]))->assertOk();
    $this->postJson('/api/v1/bai-tap/ket-qua', ketQuaBaiTap($this->m, [['lb3:1', 'Die']], lechMs: -3_600_000))->assertOk();

    expect(DB::table('user_exercise_stats')->where('user_id', $u->id)->first())->toMatchObject([
        'correct_count' => 1, 'wrong_count' => 1, 'correct_streak' => 1, 'last_correct' => 1, 'last_answer' => 'Der', 'last_at' => now()->getTimestamp(),
    ]);
});

it('Ôn câu sai: câu đã làm mà chưa thuộc, ở mọi bộ (cả bộ Ôn tập); câu chưa làm và câu đã thuộc không lấy', function () {
    $this->actingAs(User::factory()->create());
    expect($this->getJson('/api/v1/bai-tap/phien?on=1')->assertOk()->json('data'))->toBe([]);

    $this->postJson('/api/v1/bai-tap/ket-qua', ketQuaBaiTap($this->m, [['lb3:1', 'Der'], ['lb3:2', 'Das']]))->assertOk();
    $this->postJson('/api/v1/bai-tap/ket-qua', ketQuaBaiTap($this->m, [['lb3:1', 'Der']]))->assertOk();
    $this->postJson('/api/v1/bai-tap/ket-qua', ketQuaBaiTap($this->m, [['dx:v1:1A:1', 'Onkel'], ['dx:v1:1A:2', 'Geschwister']], loai: 'quick', bo: null))->assertOk();

    // lb3:1 đúng 2 lần là đã thuộc; dx:v1:1A:2 mới đúng 1 lần nên vẫn ôn
    expect(collect($this->getJson('/api/v1/bai-tap/phien?on=1')->json('data'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->m->cau['lb3:2'], $this->m->cau['dx:v1:1A:1'], $this->m->cau['dx:v1:1A:2']])->sort()->values()->all());
});

it('người khác không thấy và không đổi được tiến độ bài tập của mình', function () {
    $this->actingAs(User::factory()->create());
    $this->postJson('/api/v1/bai-tap/ket-qua', ketQuaBaiTap($this->m, [['lb3:1', 'Der'], ['lb3:2', 'Das']]))->assertOk();

    $this->actingAs($b = User::factory()->create());
    expect($this->getJson('/api/v1/bai-tap')->json('data.sets.0'))->toMatchArray(['done' => 0, 'weak' => 0])
        ->and($this->getJson('/api/v1/bai-tap/phien?on=1')->json('data'))->toBe([]);

    // Cùng mã uid với lượt của người khác vẫn là lượt mới của người này
    $uid = (string) DB::table('exercise_runs')->value('client_uid');
    $this->postJson('/api/v1/bai-tap/ket-qua', ketQuaBaiTap($this->m, [['lb3:2', 'Die']], uid: $uid))->assertOk()->assertJsonPath('data.duplicate', false);
    expect(DB::table('user_exercise_stats')->where('user_id', $b->id)->count())->toBe(1)
        ->and(DB::table('user_exercise_stats')->where('user_id', '<>', $b->id)->where('exercise_id', $this->m->cau['lb3:2'])->value('correct_count'))->toBe(0);
});

it('kết quả sai dạng thì báo lỗi đúng ô; câu nháp, câu của bộ nháp, câu không có thì bỏ; không còn câu nào thì 422', function () {
    $this->actingAs($u = User::factory()->create());
    $tot = ketQuaBaiTap($this->m, [['lb3:1', 'Der']]);

    $this->postJson('/api/v1/bai-tap/ket-qua', [...$tot, 'mode' => 'gian-lan'])->assertUnprocessable()->assertJsonValidationErrors('mode');
    $this->postJson('/api/v1/bai-tap/ket-qua', [...$tot, 'uid' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('uid');
    $this->postJson('/api/v1/bai-tap/ket-qua', [...$tot, 'items' => []])->assertUnprocessable()->assertJsonValidationErrors('items');
    $this->postJson('/api/v1/bai-tap/ket-qua', [...$tot, 'items' => [['answer' => 'x']]])->assertUnprocessable()->assertJsonValidationErrors('items.0.id');
    $this->postJson('/api/v1/bai-tap/ket-qua', [...$tot, 'set' => 'a b'])->assertUnprocessable()->assertJsonValidationErrors('set');
    $this->postJson('/api/v1/bai-tap/ket-qua', [...$tot, 'items' => array_fill(0, BaiTap::CAU_TOI_DA + 1, ['id' => 1, 'answer' => 'x'])])->assertUnprocessable()->assertJsonValidationErrors('items');
    $this->postJson('/api/v1/bai-tap/ket-qua', ketQuaBaiTap($this->m, [['lb3:nhap', 'x'], ['nhap:1', 'x']]))
        ->assertUnprocessable()->assertJsonValidationErrors(['items' => 'Không có câu nào hợp lệ để ghi']);
    expect(DB::table('exercise_runs')->count())->toBe(0);

    $r = $this->postJson('/api/v1/bai-tap/ket-qua', [...ketQuaBaiTap($this->m, [['lb3:1', 'Der'], ['lb3:nhap', 'x'], ['nhap:1', 'x']]), 'items' => [
        ['id' => $this->m->cau['lb3:1'], 'answer' => 'Der'], ['id' => $this->m->cau['lb3:1'], 'answer' => 'Die'], ['id' => 999999, 'answer' => 'x'], ['id' => $this->m->cau['lb3:nhap'], 'answer' => null],
    ]])->assertOk();
    // Một câu gửi hai lần: lấy lần đầu
    expect($r->json('data'))->toMatchArray(['total' => 1, 'correct' => 1])
        ->and(DB::table('user_exercise_stats')->where('user_id', $u->id)->pluck('exercise_id')->all())->toBe([$this->m->cau['lb3:1']]);
});

// ---------------------------------------------------------------- động từ

it('bảng động từ: theo chữ cái, tìm nguyên mẫu hoặc nghĩa không cần dấu, lọc cấp, động từ tách, chia trang', function () {
    $this->actingAs(User::factory()->create());
    $ten = fn (string $q) => array_column($this->getJson('/api/v1/dong-tu'.$q)->assertOk()->json('data'), 'infinitive');

    $r = $this->getJson('/api/v1/dong-tu')->assertOk();
    expect(array_column($r->json('data'), 'infinitive'))->toBe(['anrufen', 'backen', 'lesen'])
        ->and($r->json('meta'))->toBe(['page' => 1, 'per_page' => DongTu::MOI_TRANG, 'total' => 3, 'pages' => 1])
        ->and($ten('?q=doc'))->toBe(['lesen'])
        ->and($ten('?q=ĐỌC'))->toBe(['lesen'])
        ->and($ten('?q=LESEN'))->toBe(['lesen'])
        ->and($ten('?q=to%20call'))->toBe(['anrufen'])
        ->and($ten('?q=nuong'))->toBe(['backen'])
        ->and($ten('?q=khong-co'))->toBe([])
        ->and($ten('?cap=2'))->toBe(['backen'])
        ->and($ten('?tach=1'))->toBe(['anrufen'])
        ->and($this->getJson('/api/v1/dong-tu?trang=2')->json('data'))->toBe([])
        ->and($this->getJson('/api/v1/dong-tu?trang=2')->json('meta.total'))->toBe(3);

    $lesen = collect($r->json('data'))->firstWhere('infinitive', 'lesen');
    expect($lesen)->toMatchArray(['level' => 'A1', 'is_separable' => false, 'prefix' => null, 'auxiliary' => 'haben', 'form_du' => 'liest', 'form_er' => 'liest',
        'preterite' => 'las', 'perfect' => 'hat gelesen', 'card_id' => null])
        ->and($lesen['meanings'])->toEqual(['vi' => 'đọc', 'en' => 'to read'])
        ->and($lesen['conjugation']['pres'])->toBe(['lese', 'liest', 'liest', 'lesen', 'lest', 'lesen'])
        ->and(collect($r->json('data'))->firstWhere('infinitive', 'backen'))->toMatchArray(['card_id' => $this->m->the['backen'], 'level' => 'A2']);

    $this->getJson('/api/v1/dong-tu?trang=0')->assertUnprocessable()->assertJsonValidationErrors('trang');
    $this->getJson('/api/v1/dong-tu?cap=x')->assertUnprocessable()->assertJsonValidationErrors('cap');
});

it('luyện chia động từ: mỗi động từ một câu, đáp án đúng dạng hỏi; máy chủ chấm lại theo mã câu, 10 xu mỗi câu đúng', function () {
    $this->actingAs($u = User::factory()->create());

    $ds = $this->getJson('/api/v1/dong-tu/luyen?n=50')->assertOk()->json('data');
    expect(collect($ds)->pluck('verb')->sort()->values()->all())->toBe(['anrufen', 'backen', 'lesen']);
    foreach ($ds as $c) {
        expect($c['sig'])->toMatch('/^v:(anrufen|backen|lesen):(du|er|prat|perf|(pres|prat|perf)[0-5])$/')
            ->and($c['answers'])->toBe(DongTu::dapAnLuyen($c['sig']))
            ->and($c['prompt'])->toStartWith($c['verb'].' → ')
            ->and($c['kind'])->toBe('fill');
    }
    expect(collect($this->getJson('/api/v1/dong-tu/luyen?tach=1')->json('data'))->pluck('verb')->all())->toBe(['anrufen'])
        ->and(collect($this->getJson('/api/v1/dong-tu/luyen?cap=2')->json('data'))->pluck('verb')->all())->toBe(['backen'])
        ->and(DongTu::dapAnLuyen('v:lesen:prat1'))->toBe(['last'])
        ->and(DongTu::dapAnLuyen('v:anrufen:pres0'))->toBeNull()
        ->and(DongTu::dapAnLuyen('v:lesen:foo'))->toBeNull()
        ->and(DongTu::dapAnLuyen('lesen:du'))->toBeNull();

    $r = $this->postJson('/api/v1/bai-tap/ket-qua', ketQuaBaiTap($this->m, [
        ['v:lesen:du', 'liest'], ['v:anrufen:prat', 'rief an'], ['v:lesen:pres3', 'Lesen'], ['v:backen:perf2', 'hat gebackt'], ['v:xyz:du', 'x'], ['v:lesen:foo', 'x'],
    ], loai: 'conjugate', bo: null))->assertOk();

    expect($r->json('data'))->toMatchArray(['total' => 4, 'correct' => 3, 'coins' => 30])
        ->and($r->json('data.items.0'))->toBe(['sig' => 'v:lesen:du', 'score' => 2])
        ->and(DB::table('exercise_runs')->where('user_id', $u->id)->first())->toMatchObject(['mode' => 'conjugate', 'set_id' => null, 'total' => 4, 'correct' => 3, 'coins' => 30])
        ->and(DB::table('user_exercise_stats')->where('user_id', $u->id)->exists())->toBeFalse();

    $this->postJson('/api/v1/bai-tap/ket-qua', [...ketQuaBaiTap($this->m, [], loai: 'conjugate', bo: null), 'items' => [['answer' => 'x']]])
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.sig');
});

// ---------------------------------------------------------------- Điền câu

it('Điền câu: thẻ Từ vựng có câu trống; câu đầy đủ kèm bản dịch; danh từ phân biệt hoa thường; bỏ thẻ Giao tiếp và thẻ nháp', function () {
    $this->actingAs(User::factory()->create());

    $ds = collect($this->getJson('/api/v1/bai-tap/dien-cau')->assertOk()->json('data'))->keyBy('front');

    expect($ds->keys()->sort()->values()->all())->toBe(['das Brot', 'die Zahl', 'zählen'])
        ->and($ds['das Brot'])->toMatchArray(['cloze' => 'Das ____ ist noch frisch.', 'answer' => 'Brot', 'case_sensitive' => true, 'box' => 0,
            'topic' => ['slug' => 'banh-mi-tu-vung', 'name' => 'Bánh mì · bánh ngọt'], 'article' => 'das', 'word' => 'Brot'])
        ->and($ds['das Brot']['sentence'])->toEqual(['sentence' => 'Das Brot ist noch frisch.', 'translations' => ['vi' => 'Bánh mì vẫn còn tươi.']])
        ->and($ds['zählen'])->toMatchArray(['answer' => 'zählt', 'case_sensitive' => false, 'sentence' => null])
        ->and($this->getJson('/api/v1/bai-tap/dien-cau?cap=2')->json('data'))->toBe([]);
});

it('Điền câu: thẻ đã gặp đứng trước thẻ chưa học; kết quả gửi như lượt chấm thẻ chế độ cloze, 10 xu khi đúng', function () {
    $this->actingAs($u = User::factory()->create());
    foreach ([['das Brötchen', 'Ich möchte zwei ____, bitte.', 'Brötchen'], ['das Mehl', 'Ich brauche ____ für den Kuchen.', 'Mehl']] as [$front, $cau, $dapAn]) {
        DB::table('cards')->where('id', $this->m->the[$front])->update(['cloze' => $cau, 'cloze_answer' => $dapAn]);
    }

    $r = $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [luot($this->m->the['die Zahl'], 2, lucHoc('2026-11-27 09:00:05'), null, 4000, 'cloze')]])->assertOk();
    expect($r->json('data'))->toMatchArray(['received' => 1, 'coins' => 10])
        ->and(DB::table('review_logs')->where('user_id', $u->id)->value('mode'))->toBe('cloze')
        ->and(DB::table('card_progress')->where('user_id', $u->id)->value('box'))->toBe(2);

    for ($i = 0; $i < 3; $i++) {
        expect(collect($this->getJson('/api/v1/bai-tap/dien-cau?n=3')->json('data'))->pluck('front'))->toHaveCount(3)->toContain('die Zahl');
    }
    expect(collect($this->getJson('/api/v1/bai-tap/dien-cau?n=3')->json('data'))->firstWhere('front', 'die Zahl'))->toMatchArray(['box' => 2]);

    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [luot($this->m->the['die Zahl'], 2, lucHoc('2026-11-27 09:01:00'), null, 4000, 'gian-lan')]])
        ->assertUnprocessable()->assertJsonValidationErrors('reviews.0.mode');
});
