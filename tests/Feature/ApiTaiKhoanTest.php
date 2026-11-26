<?php

use App\Models\User;
use App\Models\UserSetting;
use App\Providers\AppServiceProvider;
use App\Support\ThietBi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/** Gọi API như app học cùng tên miền: có Referer của app nên Sanctum dùng cookie phiên. */
function tuApp(): array
{
    return ['Referer' => rtrim((string) config('app.url'), '/').'/app/cai-dat', 'Accept' => 'application/json'];
}

function chenPhien(string $id, int $userId, string $ua, int $luc): void
{
    DB::table('sessions')->insert([
        'id' => $id, 'user_id' => $userId, 'ip_address' => '203.0.113.5', 'user_agent' => $ua,
        'payload' => base64_encode(serialize([])), 'last_activity' => $luc,
    ]);
}

const UA_CHROME_WIN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';
const UA_SAFARI_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';

it('chưa đăng nhập: 401 có mã chua_dang_nhap, lời nhắn tiếng Việt, không lộ dữ liệu', function () {
    $this->getJson('/api/v1/toi')->assertStatus(401)->assertExactJson(['code' => 'chua_dang_nhap', 'message' => 'Bạn cần đăng nhập lại.']);
    $this->putJson('/api/v1/toi/cai-dat', ['theme' => 'dark'])->assertStatus(401);
    $this->getJson('/api/user')->assertStatus(404)->assertJson(['code' => 'khong_thay']);
});

it('chưa xác minh email: 403 có mã chua_xac_minh', function () {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->getJson('/api/v1/toi')->assertStatus(403)->assertJson(['code' => 'chua_xac_minh', 'message' => 'Bạn cần xác minh email trước khi học.']);
});

it('GET /toi: mã ULID thay cho id số, cài đặt mặc định, khoá học từ dữ liệu khởi tạo', function () {
    $this->seed();
    $user = User::factory()->create(['name' => 'Lan']);

    $r = $this->actingAs($user)->getJson('/api/v1/toi')->assertOk();
    $r->assertJsonPath('data.id', $user->public_id)
        ->assertJsonPath('data.name', 'Lan')
        ->assertJsonPath('data.ui_locale', 'vi')
        ->assertJsonPath('data.settings', UserSetting::MAC_DINH)
        ->assertJsonPath('data.course', ['slug' => 'tieng-duc', 'level_min' => 1, 'level_max' => 4, 'goal' => null, 'level' => null]);
    expect(json_encode($r->json()))->not->toContain('"user_id"')->not->toContain('password');
    expect(strlen($user->public_id))->toBe(26);
});

it('hồ sơ: đổi tên, múi giờ; chọn mục tiêu rồi trình độ thì ghi danh một lần, đổi phần nào giữ phần kia', function () {
    $this->seed();
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/api/v1/toi/ho-so', ['name' => 'Lan Anh', 'timezone' => 'Europe/Berlin'])->assertOk()
        ->assertJsonPath('data.name', 'Lan Anh')->assertJsonPath('data.timezone', 'Europe/Berlin');
    $this->putJson('/api/v1/toi/ho-so', ['goal' => 'vocational'])->assertOk()->assertJsonPath('data.course.goal', 'vocational');
    $this->putJson('/api/v1/toi/ho-so', ['level' => 2])->assertOk()
        ->assertJsonPath('data.course.goal', 'vocational')->assertJsonPath('data.course.level', 2);

    $dong = DB::table('user_courses')->where('user_id', $user->id)->get();
    expect($dong)->toHaveCount(1)
        ->and($dong[0]->placement_level)->toBe(2)
        ->and($dong[0]->enrolled_at)->toBeGreaterThan(0);
    // Chỉ nhận tên múi giờ chuẩn IANA (tên cũ như Asia/Saigon thì app đổi sang tên chuẩn trước khi gửi)
    $this->putJson('/api/v1/toi/ho-so', ['timezone' => 'Asia/Saigon'])->assertStatus(422)->assertJsonValidationErrors('timezone');
});

it('hồ sơ sai thì báo đúng ô, không ghi gì', function () {
    $this->seed();
    $user = User::factory()->create(['name' => 'Lan', 'timezone' => 'Asia/Ho_Chi_Minh']);

    $this->actingAs($user)->putJson('/api/v1/toi/ho-so', [
        'name' => '', 'timezone' => 'Sao Hoa/Thu Do', 'goal' => 'lam-giau', 'level' => 5, 'ui_locale' => 'en',
    ])->assertStatus(422)->assertJsonValidationErrors(['name', 'timezone', 'goal', 'level', 'ui_locale']);
    $this->putJson('/api/v1/toi/ho-so', ['name' => str_repeat('a', 101)])->assertStatus(422)->assertJsonValidationErrors('name');

    expect($user->fresh()->name)->toBe('Lan')->and($user->fresh()->timezone)->toBe('Asia/Ho_Chi_Minh');
    expect(DB::table('user_courses')->count())->toBe(0);
});

it('chưa có khoá học thì không chọn được mục tiêu, báo rõ lý do', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->putJson('/api/v1/toi/ho-so', ['goal' => 'work'])->assertStatus(422)
        ->assertJsonPath('errors.goal.0', 'Chưa có khoá học nào để chọn mục tiêu và trình độ.');
    $this->getJson('/api/v1/toi')->assertOk()->assertJsonPath('data.course', null);
});

it('cài đặt: đổi phần nào giữ phần kia; 0 từ mới = tất cả; mỗi người một bản', function () {
    $this->seed();
    [$a, $b] = User::factory()->count(2)->create();

    $this->actingAs($a)->putJson('/api/v1/toi/cai-dat', ['theme' => 'dark', 'new_per_session' => 0])->assertOk()
        ->assertJsonPath('data.theme', 'dark')->assertJsonPath('data.new_per_session', 0)->assertJsonPath('data.font_size', 'md');
    $this->putJson('/api/v1/toi/cai-dat', ['meaning_langs' => ['zh', 'vi'], 'font_size' => 'lg'])->assertOk()
        ->assertJsonPath('data.theme', 'dark')->assertJsonPath('data.meaning_langs', ['zh', 'vi']);
    $this->putJson('/api/v1/toi/cai-dat', ['meaning_langs' => []])->assertOk()->assertJsonPath('data.meaning_langs', []);

    $this->actingAs($b)->getJson('/api/v1/toi/cai-dat')->assertOk()->assertJsonPath('data', UserSetting::MAC_DINH);
    expect(DB::table('user_settings')->where('user_id', $a->id)->value('updated_at'))->toBeInt();
});

it('cài đặt sai thì 422 đúng ô, giá trị cũ giữ nguyên', function () {
    $this->seed();
    $user = User::factory()->create();
    $this->actingAs($user)->putJson('/api/v1/toi/cai-dat', ['theme' => 'dark'])->assertOk();

    $this->putJson('/api/v1/toi/cai-dat', [
        'theme' => 'tim', 'font_size' => 'xl', 'season' => 'mua-mua', 'new_per_session' => 7, 'daily_goal' => 1000,
    ])->assertStatus(422)->assertJsonValidationErrors(['theme', 'font_size', 'season', 'new_per_session', 'daily_goal']);
    // Tiếng Đức là ngôn ngữ đang học, không phải ngôn ngữ nghĩa; trùng mã cũng không nhận
    $this->putJson('/api/v1/toi/cai-dat', ['meaning_langs' => ['vi', 'de']])->assertStatus(422)->assertJsonValidationErrors('meaning_langs.1');
    $this->putJson('/api/v1/toi/cai-dat', ['meaning_langs' => ['vi', 'vi']])->assertStatus(422);
    $this->putJson('/api/v1/toi/cai-dat', ['meaning_langs' => 'vi'])->assertStatus(422)->assertJsonValidationErrors('meaning_langs');

    $this->getJson('/api/v1/toi/cai-dat')->assertJsonPath('data.theme', 'dark')->assertJsonPath('data.meaning_langs', ['vi', 'en', 'zh']);
});

it('danh sách máy: chỉ phiên của mình, đọc được trình duyệt, ghi rõ máy này, không trả mã phiên', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $khac = User::factory()->create();
    $hienTai = str_repeat('a', 40);
    chenPhien($hienTai, $user->id, UA_CHROME_WIN, 1764201600);
    chenPhien(str_repeat('b', 40), $user->id, UA_SAFARI_IPHONE, 1764100000);
    chenPhien(str_repeat('c', 40), $khac->id, UA_CHROME_WIN, 1764200000);

    $r = $this->actingAs($user)->withHeaders(tuApp())->withCredentials()->withCookie(config('session.cookie'), $hienTai)
        ->getJson('/api/v1/toi/phien')->assertOk()->assertJsonPath('supported', true);

    expect($r->json('data'))->toHaveCount(2);
    expect($r->json('data.0'))->toMatchArray(['browser' => 'Chrome', 'os' => 'Windows', 'device_type' => 'desktop', 'is_current' => true]);
    expect($r->json('data.1'))->toMatchArray(['browser' => 'Safari', 'os' => 'iOS', 'device_type' => 'phone', 'is_current' => false, 'last_active_at' => 1764100000]);
    expect($r->getContent())->not->toContain($hienTai)->not->toContain(str_repeat('b', 40));
});

it('đăng xuất các máy khác: sai mật khẩu thì không làm gì; đúng thì xoá phiên khác, giữ máy này, ghi lịch sử', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create(['password' => 'dung-mat-khau-1']);
    $hienTai = str_repeat('a', 40);
    chenPhien($hienTai, $user->id, UA_CHROME_WIN, now()->getTimestamp());
    chenPhien(str_repeat('b', 40), $user->id, UA_SAFARI_IPHONE, now()->getTimestamp());
    $matKhauCu = $user->password;

    $goi = fn (string $mk) => $this->actingAs($user)->withHeaders(tuApp())->withCredentials()->withCookie(config('session.cookie'), $hienTai)
        ->postJson('/api/v1/toi/phien/dang-xuat-may-khac', ['password' => $mk]);

    $goi('sai-mat-khau-1')->assertStatus(422)->assertJsonValidationErrors('password');
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(2);

    $goi('dung-mat-khau-1')->assertOk()->assertJsonPath('data.remaining', 1);
    expect(DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all())->toBe([$hienTai]);
    // Mật khẩu được băm lại (vẫn là mật khẩu cũ): cookie "Ghi nhớ" của máy khác hết hiệu lực
    $moi = $user->fresh()->password;
    expect($moi)->not->toBe($matKhauCu)->and(Hash::check('dung-mat-khau-1', $moi))->toBeTrue();
    expect(DB::table('login_events')->where('user_id', $user->id)->where('event', 'logout_others')->count())->toBe(1);
});

it('đăng xuất các máy khác bị giới hạn 6 lần mỗi phút để chặn đoán mật khẩu', function () {
    $user = User::factory()->create(['password' => 'dung-mat-khau-1']);
    $this->actingAs($user);
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/v1/toi/phien/dang-xuat-may-khac', ['password' => 'sai-'.$i])->assertStatus(422);
    }
    $this->postJson('/api/v1/toi/phien/dang-xuat-may-khac', ['password' => 'dung-mat-khau-1'])
        ->assertStatus(429)->assertJson(['code' => 'qua_nhieu_lan'])->assertHeader('Retry-After');
});

it('giới hạn chung của API: 120 lần mỗi phút cho mỗi người', function () {
    $user = User::factory()->create();
    $request = Request::create('/api/v1/toi');
    $request->setUserResolver(fn () => $user);
    $limit = RateLimiter::limiter('api')($request);

    expect($limit->maxAttempts)->toBe(AppServiceProvider::API_MOI_PHUT)->and(AppServiceProvider::API_MOI_PHUT)->toBe(120)
        ->and($limit->key)->toBe($user->id);
});

it('đoán trình duyệt và loại máy từ User-Agent', function (string $ua, ?string $trinhDuyet, ?string $heDieuHanh, string $loai) {
    expect(ThietBi::tu($ua))->toBe(['browser' => $trinhDuyet, 'os' => $heDieuHanh, 'device_type' => $loai]);
})->with([
    'Chrome Windows' => [UA_CHROME_WIN, 'Chrome', 'Windows', 'desktop'],
    'Safari iPhone' => [UA_SAFARI_IPHONE, 'Safari', 'iOS', 'phone'],
    'Edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36 Edg/131.0', 'Edge', 'Windows', 'desktop'],
    'Samsung Android' => ['Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0 Mobile Safari/537.36', 'Samsung Internet', 'Android', 'phone'],
    'Firefox Mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14.6; rv:133.0) Gecko/20100101 Firefox/133.0', 'Firefox', 'macOS', 'desktop'],
    'Chrome iPad' => ['Mozilla/5.0 (iPad; CPU OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/131.0 Mobile/15E148 Safari/604.1', 'Chrome', 'iPadOS', 'tablet'],
    'Android máy tính bảng' => ['Mozilla/5.0 (Linux; Android 13; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36', 'Chrome', 'Android', 'tablet'],
    'trống' => ['', null, null, 'desktop'],
]);
