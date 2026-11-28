<?php

use App\Models\User;
use App\Support\Ai\CaiDatAi;
use App\Support\Ai\DanhSachThu;
use App\Support\Ai\DongYAi;
use Database\Seeders\NguoiHocThuSeeder;
use Illuminate\Support\Facades\DB;

// Tài khoản người học thử cho test trình duyệt: một tài khoản chung, và tài khoản riêng cho test có chấm thẻ.

it('tạo tài khoản chung, tài khoản học riêng cho mỗi cỡ màn hình và một nhân viên quản trị, đã xác minh; chạy lại không nhân đôi', function () {
    $this->seed();
    $this->seed(NguoiHocThuSeeder::class);
    $this->seed(NguoiHocThuSeeder::class);

    $emails = [NguoiHocThuSeeder::EMAIL, NguoiHocThuSeeder::EMAIL_QUAN_TRI];
    foreach (NguoiHocThuSeeder::CO_MAN_HINH as $co) {
        for ($i = 1; $i <= NguoiHocThuSeeder::SO_TAI_KHOAN_HOC; $i++) {
            $emails[] = NguoiHocThuSeeder::emailHoc($co, $i);
        }
    }

    // Một tài khoản chung, 13 tài khoản học cho mỗi cỡ màn hình, một nhân viên (chỉ nhân viên này là nhân viên)
    expect(count($emails))->toBe(28)
        ->and(User::query()->count())->toBe(28)
        ->and(User::query()->whereIn('email', $emails)->whereNotNull('email_verified_at')->count())->toBe(28)
        ->and(User::query()->where('is_staff', true)->pluck('email')->all())->toBe([NguoiHocThuSeeder::EMAIL_QUAN_TRI])
        ->and(User::query()->where('email', NguoiHocThuSeeder::EMAIL_QUAN_TRI)->firstOrFail()->hasRole('super_admin'))->toBeTrue()
        ->and(NguoiHocThuSeeder::emailHoc('dien-thoai', 3))->toBe('hoc-dien-thoai-3@lernbuch.test');
    $this->post('/dang-nhap', ['email' => 'hoc-may-tinh-2@lernbuch.test', 'password' => NguoiHocThuSeeder::MAT_KHAU]);
    $this->assertAuthenticated();
});

it('tài khoản AI (12, 13) trong danh sách thử, chạy lại thì về lúc chưa khai ngày sinh, chưa đồng ý, chưa hỏi; AI bật cho danh sách thử', function () {
    $this->seed();
    $this->seed(NguoiHocThuSeeder::class);
    $ai = User::query()->where('email', NguoiHocThuSeeder::emailHoc('may-tinh', 12))->firstOrFail();
    $thuong = User::query()->where('email', NguoiHocThuSeeder::emailHoc('may-tinh', 11))->firstOrFail();
    $ai->forceFill(['birth_date' => gmmktime(0, 0, 0, 1, 1, 1990)])->save();
    DongYAi::ghi($ai, true);
    dongAi($ai, 2);

    $this->seed(NguoiHocThuSeeder::class);

    $ai->refresh();
    expect($ai->birth_date)->toBeNull()
        ->and(DanhSachThu::co($ai))->toBeTrue()
        ->and(DanhSachThu::co($thuong))->toBeFalse()
        ->and(DB::table('consents')->where('user_id', $ai->id)->count())->toBe(0)
        ->and(DB::table('ai_requests')->where('user_id', $ai->id)->count())->toBe(0)
        ->and(CaiDatAi::tatCa()['enabled'])->toBeTrue()
        ->and(CaiDatAi::tatCa()['audience'])->toBe('testers');
});

it('không tạo gì trên máy chủ thật, kể cả khi chạy db:seed --force', function () {
    app()->detectEnvironment(fn () => 'production');
    // --force bỏ qua câu hỏi "chắc chắn chạy trên máy chủ thật?" của db:seed: chỉ còn chặn trong seeder
    $this->artisan('db:seed', ['--class' => NguoiHocThuSeeder::class, '--force' => true])
        ->expectsOutputToContain('Bỏ qua: không tạo tài khoản thử trên máy chủ thật.')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});
