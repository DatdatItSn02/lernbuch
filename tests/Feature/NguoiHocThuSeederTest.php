<?php

use App\Models\User;
use Database\Seeders\NguoiHocThuSeeder;

// Tài khoản người học thử cho test trình duyệt: một tài khoản chung, và tài khoản riêng cho test có chấm thẻ.

it('tạo tài khoản chung và tài khoản học riêng cho mỗi cỡ màn hình, đã xác minh; chạy lại không nhân đôi', function () {
    $this->seed(NguoiHocThuSeeder::class);
    $this->seed(NguoiHocThuSeeder::class);

    $emails = [NguoiHocThuSeeder::EMAIL];
    foreach (NguoiHocThuSeeder::CO_MAN_HINH as $co) {
        for ($i = 1; $i <= NguoiHocThuSeeder::SO_TAI_KHOAN_HOC; $i++) {
            $emails[] = NguoiHocThuSeeder::emailHoc($co, $i);
        }
    }

    expect(User::query()->count())->toBe(11)
        ->and(User::query()->whereIn('email', $emails)->whereNotNull('email_verified_at')->count())->toBe(11)
        ->and(NguoiHocThuSeeder::emailHoc('dien-thoai', 3))->toBe('hoc-dien-thoai-3@lernbuch.test');
    $this->post('/dang-nhap', ['email' => 'hoc-may-tinh-2@lernbuch.test', 'password' => NguoiHocThuSeeder::MAT_KHAU]);
    $this->assertAuthenticated();
});

it('không tạo gì trên máy chủ thật', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->seed(NguoiHocThuSeeder::class);

    expect(User::query()->count())->toBe(0);
});
