<?php

use App\Http\Middleware\KhachXemThu;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Chế độ xem thử (tạm thời, XEM_THU=true): app học và API mở không cần đăng nhập bằng một tài khoản khách dùng chung;
// các trang đăng nhập, đăng ký tắt; trang quản trị vẫn cần đăng nhập.

beforeEach(fn () => $this->seed());

function batXemThu(): void
{
    config(['app.xem_thu' => true]);
}

/** Trong test, người dùng đặt cho một request còn lại ở request sau; trên máy chủ thật mỗi request bắt đầu lại từ đầu. */
function requestMoi(): void
{
    Auth::forgetGuards();
}

it('tắt (mặc định): app học về trang đăng nhập, API trả 401, trang đăng nhập và đăng ký vẫn mở, không tạo khách', function () {
    $this->get('/app/hom-nay')->assertRedirect('/dang-nhap');
    $this->getJson('/api/v1/toi')->assertUnauthorized();
    $this->get('/dang-nhap')->assertOk();
    $this->get('/dang-ky')->assertOk();
    $this->get('/quen-mat-khau')->assertOk();

    expect(User::where('email', KhachXemThu::EMAIL)->exists())->toBeFalse();
});

it('bật: mở app học không cần đăng nhập; chỉ có một tài khoản khách, không mật khẩu, không phải nhân viên, không ghi đăng nhập', function () {
    batXemThu();

    $this->get('/app/hom-nay')->assertOk()->assertSee('id="app"', false);
    $this->get('/app/chu-de')->assertOk();
    $this->getJson('/api/v1/toi')->assertOk();

    $khach = User::where('email', KhachXemThu::EMAIL)->get();
    expect($khach)->toHaveCount(1)
        ->and($khach[0]->password)->toBeNull()
        ->and($khach[0]->is_staff)->toBeFalse()
        ->and($khach[0]->status)->toBe('active')
        ->and($khach[0]->hasVerifiedEmail())->toBeTrue()
        ->and(DB::table('login_events')->count())->toBe(0)
        ->and(DB::table('sessions')->whereNotNull('user_id')->count())->toBe(0);
});

it('bật: API của app học chạy với tài khoản khách và báo guest', function () {
    batXemThu();

    $this->getJson('/api/v1/toi')->assertOk()
        ->assertJsonPath('data.guest', true)
        ->assertJsonPath('data.name', KhachXemThu::TEN)
        ->assertJsonPath('data.timezone', 'Asia/Ho_Chi_Minh');
    $this->getJson('/api/v1/hom-nay')->assertOk();
    $this->getJson('/api/v1/chu-de')->assertOk();
    $this->getJson('/api/v1/ngu-phap')->assertOk();
    $this->getJson('/api/v1/tra-tu?q=haus')->assertOk();
});

it('bật: các trang đăng nhập, đăng ký, quên và đặt lại mật khẩu đưa về trang chủ', function (string $duong) {
    batXemThu();

    $this->get($duong)->assertRedirect('/');
})->with(['/dang-nhap', '/dang-ky', '/quen-mat-khau', '/dat-lai-mat-khau/abc?email=a%40example.com']);

it('bật: trang quản trị vẫn cần đăng nhập; khách không vào được', function () {
    batXemThu();

    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk();
    $this->get('/app/hom-nay')->assertOk();
    requestMoi();
    $this->get('/admin')->assertRedirect('/admin/login');

    // Kể cả khi có người đăng nhập được bằng tài khoản khách thì cũng không qua cửa trang quản trị
    $this->actingAs(KhachXemThu::khach(), 'web')->get('/admin')->assertForbidden();
});

it('bật: người đã đăng nhập vẫn là chính họ, không bị đổi thành khách', function () {
    batXemThu();
    $lan = User::factory()->create(['name' => 'Lan', 'email' => 'lan@example.com']);

    $this->actingAs($lan, 'web')->getJson('/api/v1/toi')->assertOk()
        ->assertJsonPath('data.email', 'lan@example.com')
        ->assertJsonMissingPath('data.guest');
    $this->putJson('/api/v1/toi/ho-so', ['name' => 'Lan Anh'])->assertOk()->assertJsonPath('data.name', 'Lan Anh');
});

it('bật: khách không đổi được tên, múi giờ của tài khoản chung; cài đặt giao diện không lưu vào tài khoản chung', function () {
    batXemThu();

    $this->putJson('/api/v1/toi/ho-so', ['name' => 'Tên bậy', 'timezone' => 'Europe/Berlin'])->assertOk()
        ->assertJsonPath('data.name', KhachXemThu::TEN)
        ->assertJsonPath('data.timezone', 'Asia/Ho_Chi_Minh');
    $this->putJson('/api/v1/toi/cai-dat', ['theme' => 'dark'])->assertOk()->assertJsonPath('data.theme', 'light');
    $this->putJson('/api/v1/toi/cai-dat', ['theme' => 'tim'])->assertUnprocessable();

    $khach = KhachXemThu::khach();
    expect($khach->name)->toBe(KhachXemThu::TEN)
        ->and($khach->timezone)->toBe('Asia/Ho_Chi_Minh')
        ->and($this->getJson('/api/v1/toi/cai-dat')->json('data.theme'))->toBe('light');
});

it('bật: giới hạn số lần gọi API của khách tính theo IP, người này không làm người kia hết lượt', function () {
    batXemThu();
    config(['app.api_moi_phut' => 2]);

    $a = ['REMOTE_ADDR' => '203.0.113.10'];
    $b = ['REMOTE_ADDR' => '203.0.113.11'];
    $this->withServerVariables($a)->getJson('/api/v1/chu-de')->assertOk();
    $this->withServerVariables($a)->getJson('/api/v1/chu-de')->assertOk();
    $this->withServerVariables($a)->getJson('/api/v1/chu-de')->assertStatus(429);
    $this->withServerVariables($b)->getJson('/api/v1/chu-de')->assertOk();
});

it('bật: tài khoản khách bị khoá thì app học đóng lại, không chuyển trang vòng quanh', function () {
    batXemThu();
    KhachXemThu::khach()->forceFill(['status' => 'suspended'])->save();

    $this->get('/app/hom-nay')->assertRedirect('/dang-nhap');
    $this->get('/dang-nhap')->assertRedirect('/');
    $this->get('/')->assertOk();
    $this->getJson('/api/v1/toi')->assertUnauthorized();
});

it('bật rồi tắt: người chưa đăng nhập lại phải đăng nhập, tài khoản khách còn đó nhưng không đăng nhập được', function () {
    batXemThu();
    $this->get('/app/hom-nay')->assertOk();

    config(['app.xem_thu' => false]);
    requestMoi();
    $this->get('/app/hom-nay')->assertRedirect('/dang-nhap');
    $this->getJson('/api/v1/toi')->assertUnauthorized();
    $this->post('/dang-nhap', ['email' => KhachXemThu::EMAIL, 'password' => ''])->assertSessionHasErrors();
    $this->post('/dang-nhap', ['email' => KhachXemThu::EMAIL, 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest('web');
});
