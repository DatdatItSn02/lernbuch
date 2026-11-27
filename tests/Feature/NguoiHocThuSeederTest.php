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

    // Một tài khoản chung và 10 tài khoản học cho mỗi cỡ màn hình
    expect(count($emails))->toBe(21)
        ->and(User::query()->count())->toBe(21)
        ->and(User::query()->whereIn('email', $emails)->whereNotNull('email_verified_at')->count())->toBe(21)
        ->and(NguoiHocThuSeeder::emailHoc('dien-thoai', 3))->toBe('hoc-dien-thoai-3@lernbuch.test');
    $this->post('/dang-nhap', ['email' => 'hoc-may-tinh-2@lernbuch.test', 'password' => NguoiHocThuSeeder::MAT_KHAU]);
    $this->assertAuthenticated();
});

it('không tạo gì trên máy chủ thật, kể cả khi chạy db:seed --force', function () {
    app()->detectEnvironment(fn () => 'production');
    // --force bỏ qua câu hỏi "chắc chắn chạy trên máy chủ thật?" của db:seed: chỉ còn chặn trong seeder
    $this->artisan('db:seed', ['--class' => NguoiHocThuSeeder::class, '--force' => true])
        ->expectsOutputToContain('Bỏ qua: không tạo tài khoản thử trên máy chủ thật.')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});
