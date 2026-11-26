<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Notifications\DatLaiMatKhau;
use App\Notifications\XacMinhEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * @param  array<string, string>  $doi
 * @return array<string, string>
 */
function duLieuDangKy(array $doi = []): array
{
    return array_merge([
        'name' => 'Lan Anh',
        'email' => 'Lan.Anh@Example.com',
        'password' => 'hoc-tieng-duc-1',
        'password_confirmation' => 'hoc-tieng-duc-1',
    ], $doi);
}

function linkXacMinh(User $user, int $phut = 60, ?string $email = null): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes($phut), [
        'id' => $user->getKey(),
        'hash' => sha1($email ?? $user->getEmailForVerification()),
    ]);
}

it('hiện trang đăng nhập, đăng ký, quên mật khẩu bằng tiếng Việt', function () {
    $this->get('/dang-nhap')->assertOk()->assertSee('Đăng nhập')->assertSee('name="password"', false);
    $this->get('/dang-ky')->assertOk()->assertSee('Tạo tài khoản')->assertSee('name="password_confirmation"', false);
    $this->get('/quen-mat-khau')->assertOk()->assertSee('Gửi link đặt lại');
});

it('đăng ký: tạo tài khoản, email viết thường, gửi thư xác minh qua hàng đợi', function () {
    Notification::fake();

    $this->post('/dang-ky', duLieuDangKy())->assertRedirect('/app/hom-nay');

    $user = User::where('email', 'lan.anh@example.com')->firstOrFail();
    expect($user->name)->toBe('Lan Anh')
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(strlen($user->public_id))->toBe(26);
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, XacMinhEmail::class);
    expect(new XacMinhEmail)->toBeInstanceOf(ShouldQueue::class)
        ->and(new DatLaiMatKhau('ma'))->toBeInstanceOf(ShouldQueue::class);
});

it('đăng ký: email đã có thì báo lỗi ngay ô email', function () {
    User::factory()->create(['email' => 'lan.anh@example.com']);

    $this->from('/dang-ky')->post('/dang-ky', duLieuDangKy())
        ->assertRedirect('/dang-ky')
        ->assertSessionHasErrors(['email' => 'Email này đã được dùng.']);
    expect(User::count())->toBe(1);
    $this->assertGuest();
});

it('đăng ký: mật khẩu thiếu số hoặc nhập lại không khớp thì báo lỗi', function () {
    $this->post('/dang-ky', duLieuDangKy(['password' => 'chi-co-chu', 'password_confirmation' => 'chi-co-chu']))
        ->assertSessionHasErrors(['password' => 'Mật khẩu cần có ít nhất một chữ số.']);
    $this->post('/dang-ky', duLieuDangKy(['password_confirmation' => 'khac-han-123']))
        ->assertSessionHasErrors(['password' => 'Hai lần nhập mật khẩu không khớp.']);
    expect(User::count())->toBe(0);
});

it('đăng ký: mỗi địa chỉ IP tạo tối đa 5 tài khoản mỗi giờ, lần thứ 6 bị chặn tạm', function () {
    Notification::fake();

    foreach (range(1, CreateNewUser::GIOI_HAN_MOI_GIO) as $i) {
        $this->post('/dang-ky', duLieuDangKy(['email' => "nguoi{$i}@example.com"]))->assertRedirect('/app/hom-nay');
        Auth::logout();
    }

    $this->from('/dang-ky')->post('/dang-ky', duLieuDangKy(['email' => 'nguoi6@example.com']))
        ->assertRedirect('/dang-ky')
        ->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('quá nhiều tài khoản')
        ->and(User::count())->toBe(CreateNewUser::GIOI_HAN_MOI_GIO);
});

it('chưa xác minh email mà vào học thì được dẫn tới trang gửi lại thư', function () {
    $this->actingAs(User::factory()->unverified()->create(['email' => 'chua@example.com']));

    $this->get('/app/hom-nay')->assertRedirect('/xac-minh-email');
    $this->get('/xac-minh-email')->assertOk()->assertSee('chua@example.com')->assertSee('Gửi lại thư xác minh');
});

it('bấm link xác minh thì xác minh xong và vào học', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(linkXacMinh($user))->assertRedirect('/app/hom-nay?verified=1');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->get('/app/hom-nay')->assertOk();
});

it('link xác minh hết hạn hoặc không khớp email thì không dùng được', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(linkXacMinh($user, -1))->assertForbidden();
    $this->get(linkXacMinh($user, 60, 'nguoi-khac@example.com'))->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('gửi lại thư xác minh', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->from('/xac-minh-email')->post('/xac-minh-email/gui-lai')
        ->assertRedirect('/xac-minh-email')
        ->assertSessionHas('status', 'verification-link-sent');
    Notification::assertSentTo($user, XacMinhEmail::class);
    $this->get('/xac-minh-email')->assertSee('Đã gửi lại thư xác minh.');
});

it('đăng nhập đúng (email viết hoa cũng được) thì vào app học và ghi lịch sử', function () {
    $user = User::factory()->create(['email' => 'lan@example.com']);

    $this->post('/dang-nhap', ['email' => 'LAN@example.com', 'password' => 'password'])->assertRedirect('/app/hom-nay');
    $this->assertAuthenticatedAs($user);
    expect(DB::table('login_events')->where('user_id', $user->id)->where('event', 'login')->exists())->toBeTrue()
        ->and($user->fresh()->last_login_at)->not->toBeNull();
});

it('sai mật khẩu 5 lần thì lần thứ 6 bị chặn tạm, kể cả đúng mật khẩu', function () {
    User::factory()->create(['email' => 'lan@example.com']);

    foreach (range(1, 5) as $lan) {
        $this->from('/dang-nhap')->post('/dang-nhap', ['email' => 'lan@example.com', 'password' => 'sai-mat-khau-'.$lan])
            ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
    }

    $this->from('/dang-nhap')->post('/dang-nhap', ['email' => 'lan@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Thử lại sau');
    $this->assertGuest();
    expect(DB::table('login_events')->where('event', 'failed')->count())->toBe(5)
        ->and(DB::table('login_events')->where('event', 'locked')->where('email', 'lan@example.com')->exists())->toBeTrue();
});

it('quên mật khẩu: gửi link, đặt mật khẩu mới, dùng lại link thì không được', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'lan@example.com']);

    $this->from('/quen-mat-khau')->post('/quen-mat-khau', ['email' => 'lan@example.com'])
        ->assertRedirect('/quen-mat-khau')
        ->assertSessionHas('status', trans('passwords.sent'));

    $token = null;
    Notification::assertSentTo($user, DatLaiMatKhau::class, function (DatLaiMatKhau $thu) use (&$token) {
        $token = $thu->token;

        return true;
    });

    $moi = ['token' => $token, 'email' => 'lan@example.com', 'password' => 'mat-khau-moi-2', 'password_confirmation' => 'mat-khau-moi-2'];
    $this->post('/dat-lai-mat-khau', $moi)
        ->assertRedirect('/dang-nhap')
        ->assertSessionHas('status', trans('passwords.reset'));
    expect(Hash::check('mat-khau-moi-2', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('login_events')->where('user_id', $user->id)->where('event', 'password_reset')->exists())->toBeTrue();

    $lanHai = array_merge($moi, ['password' => 'lan-thu-ba-3', 'password_confirmation' => 'lan-thu-ba-3']);
    $this->from('/dat-lai-mat-khau/'.$token)->post('/dat-lai-mat-khau', $lanHai)
        ->assertSessionHasErrors(['email' => trans('passwords.token')]);
    expect(Hash::check('mat-khau-moi-2', $user->fresh()->password))->toBeTrue();
});

it('thư xác minh và thư đặt lại mật khẩu viết bằng tiếng Việt, link tới đúng trang', function () {
    $user = User::factory()->unverified()->create(['email' => 'lan@example.com']);

    $xacMinh = (new XacMinhEmail)->toMail($user);
    expect($xacMinh->subject)->toBe('Xác minh email Lernbuch')
        ->and($xacMinh->actionText)->toBe('Xác minh email Lernbuch')
        ->and($xacMinh->actionUrl)->toContain('/xac-minh-email/'.$user->id.'/');

    $datLai = (new DatLaiMatKhau('ma-thu'))->toMail($user);
    expect($datLai->subject)->toBe('Đặt lại mật khẩu Lernbuch')
        ->and($datLai->actionUrl)->toContain('/dat-lai-mat-khau/ma-thu?email=lan%40example.com')
        ->and(implode(' ', $datLai->introLines))->toContain('yêu cầu đặt lại mật khẩu');
    expect($user->preferredLocale())->toBe('vi');
});

it('đăng xuất thì về trang chào và ghi lịch sử', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/dang-xuat')->assertRedirect('/');
    $this->assertGuest();
    expect(DB::table('login_events')->where('user_id', $user->id)->where('event', 'logout')->exists())->toBeTrue();
});
