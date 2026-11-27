<?php

use App\Http\Controllers\ServiceWorkerController;
use App\Models\User;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// PWA (tuần 5 G1, mục tự kiểm 15): service worker ở /sw.js theo bản build; gói một chủ đề để app học khi mất mạng
// (app lưu gói trên máy, tự lập phiên như máy chủ, lượt chấm gửi sau không có mã phiên).

beforeEach(function () {
    $this->seed();
    $this->travelTo(lucHoc('2026-11-27 09:00:00'));
});

it('service worker ở /sw.js: không cần đăng nhập, không lưu đệm, phiên bản theo bản build', function () {
    $r = $this->get('/sw.js')->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=utf-8');
    $phienBan = ServiceWorkerController::phienBan();

    expect($r->headers->get('Cache-Control'))->toContain('no-store')
        ->and($r->getContent())->toContain("const PHIEN_BAN = '{$phienBan}';")
        ->and($r->getContent())->not->toContain('__PHIEN_BAN__')
        ->and($phienBan)->toMatch(is_file(public_path('build/manifest.json')) ? '/^[0-9a-f]{12}$/' : '/^dev$/');
});

it('tải chủ đề: mọi thẻ người học xem được theo thứ tự trong chủ đề, kèm câu ví dụ, mức, hạn ôn, đáp án nhiễu', function () {
    $hoc = User::factory()->create(['timezone' => 'Europe/Berlin']);
    $khac = User::factory()->create();
    $m = (new NoiDungThuSeeder)->tao($hoc->id);
    $m->themThe('t-banh.v', 'der Stollen', 'noun', 'die Stollen', 2, 2, 2, ['vi' => 'bánh Stollen'], [], 'der', chu: $khac->id);
    $now = now()->getTimestamp();
    DB::table('card_progress')->insert(['user_id' => $hoc->id, 'card_id' => $m->the['das Brot'], 'box' => 2, 'due_at' => $now - 3600, 'reps' => 2]);
    DB::table('user_settings')->updateOrInsert(['user_id' => $hoc->id], ['new_per_session' => 0]);

    $d = $this->actingAs($hoc)->getJson('/api/v1/hoc/tai/banh-mi-tu-vung')->assertOk()->json('data');

    expect($d['topic'])->toBe(['slug' => 'banh-mi-tu-vung', 'name' => 'Bánh mì · bánh ngọt', 'collection' => 'vocab'])
        ->and($d['timezone'])->toBe('Europe/Berlin')
        ->and($d['new_per_session'])->toBe(0)
        ->and($d['saved_at'])->toBe($now)
        // Có cấp trước (cấp thấp trước), rồi chưa xếp cấp; có thẻ riêng của người này; không có thẻ nháp, thẻ riêng của người khác
        ->and(array_column($d['cards'], 'front'))->toBe(['das Brötchen', 'das Brot', 'das Mehl', 'backen', 'der Hefezopf', 'süß']);

    $theo = collect($d['cards'])->keyBy('front');
    expect($theo['das Brot'])->toMatchArray([
        'id' => $m->the['das Brot'],
        'box' => 2,
        'due_at' => $now - 3600,
        'example' => ['sentence' => 'Das Brot ist noch frisch.', 'translations' => ['vi' => 'Bánh mì vẫn còn tươi.']],
        'topic' => ['slug' => 'banh-mi-tu-vung', 'name' => 'Bánh mì · bánh ngọt'],
        'meanings' => ['vi' => 'bánh mì', 'en' => 'bread', 'zh' => '面包'],
    ])->and($theo['das Mehl'])->toMatchArray(['box' => 0, 'due_at' => null, 'example' => null]);

    // Đáp án nhiễu cho Quiz, Ghép cặp, Hỗn hợp: đủ 3 trở lên, không trùng thẻ đang hỏi, không có thẻ nháp hay thẻ của người khác
    $an = [$m->the['der Teig'], $m->the['der Stollen']];
    foreach ($d['cards'] as $the) {
        $ids = array_column($the['options'], 'id');
        expect(count($ids))->toBeGreaterThanOrEqual(3)
            ->and($ids)->not->toContain($the['id'])
            ->and(array_intersect($ids, $an))->toBe([])
            ->and($the['options'][0])->toHaveKeys(['front', 'article', 'word', 'meanings']);
    }
});

it('tải chủ đề: chủ đề không có, đã ẩn hay không phải chủ đề nhỏ thì 404; chưa đăng nhập thì 401; không ghi gì', function () {
    (new NoiDungThuSeeder)->tao();
    $this->getJson('/api/v1/hoc/tai/banh-mi-tu-vung')->assertUnauthorized();

    $this->actingAs(User::factory()->create());
    $this->getJson('/api/v1/hoc/tai/khong-co')->assertNotFound()->assertJson(['code' => 'khong_thay']);
    $this->getJson('/api/v1/hoc/tai/chu-de-da-an-tu-vung')->assertNotFound();
    $this->getJson('/api/v1/hoc/tai/banh-va-tiem-banh')->assertNotFound();
    $this->getJson('/api/v1/hoc/tai/Sai_Dang')->assertNotFound();

    $this->getJson('/api/v1/hoc/tai/so-dem-tu-vung')->assertOk()->assertJsonCount(25, 'data.cards');
    expect(DB::table('study_sessions')->count())->toBe(0)
        ->and(DB::table('user_courses')->count())->toBe(0);
});

it('lượt chấm lúc mất mạng gửi sau, không có mã phiên: máy chủ nhận như lượt thường và trả mức mới để app sửa gói', function () {
    $hoc = User::factory()->create();
    $m = (new NoiDungThuSeeder)->tao();
    $lucCham = lucHoc('2026-11-27 07:30:00');

    $kq = $this->actingAs($hoc)->postJson('/api/v1/hoc/luot-cham', ['reviews' => [
        luot($m->the['die Zahl'], 2, $lucCham, cheDo: 'quiz'),
        luot($m->the['zahlen'], 0, $lucCham->addSeconds(20), cheDo: 'quiz'),
    ]])->assertOk()->json('data');

    expect($kq['received'])->toBe(2)
        ->and(collect($kq['cards'])->keyBy('id')->map(fn (array $c) => $c['box'])->all())->toEqual([$m->the['die Zahl'] => 2, $m->the['zahlen'] => 1])
        ->and(DB::table('review_logs')->whereNull('session_id')->count())->toBe(2);
});
