<?php

use App\Models\User;
use App\Support\Ai\CaiDatAi;
use App\Support\Ai\DanhSachThu;
use App\Support\Ai\DongYAi;
use App\Support\Ai\TrangThaiAi;
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

    // Một tài khoản chung, 15 tài khoản học cho mỗi cỡ màn hình, một nhân viên (chỉ nhân viên này là nhân viên)
    expect(count($emails))->toBe(32)
        ->and(User::query()->count())->toBe(32)
        ->and(User::query()->whereIn('email', $emails)->whereNotNull('email_verified_at')->count())->toBe(32)
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

it('tài khoản 14, 15 dùng được AI ngay; chạy lại thì mất cuộc trò chuyện, bài học AI, câu đã viết, nhật ký AI', function () {
    $this->seed();
    $this->seed(NguoiHocThuSeeder::class);
    $u = User::query()->where('email', NguoiHocThuSeeder::emailHoc('dien-thoai', 14))->firstOrFail();
    expect(TrangThaiAi::choToi($u)['state'])->toBe('ready');
    // Gói Premium cấp tay: 50 lượt AI mỗi ngày (gói free chỉ 3)
    expect(TrangThaiAi::choToi($u)['limit'])->toBe(50);

    $ma = cuocTroChuyen($u);
    DB::table('ai_messages')->insert(['conversation_id' => DB::table('ai_conversations')->where('public_id', $ma)->value('id'), 'role' => 'user', 'content' => 'Hallo', 'created_at' => 1]);
    DB::table('user_ai_lessons')->insert(['user_id' => $u->id, 'cache_hash' => str_repeat('a', 64), 'title' => 'x', 'created_at' => 1]);
    DB::table('writing_submissions')->insert(['user_id' => $u->id, 'content' => 'x', 'created_at' => 1]);
    dongAi($u, 2);

    $this->seed(NguoiHocThuSeeder::class);

    expect(TrangThaiAi::choToi($u->refresh())['state'])->toBe('ready')
        ->and(DB::table('ai_conversations')->where('user_id', $u->id)->count())->toBe(0)
        ->and(DB::table('ai_messages')->count())->toBe(0)
        ->and(DB::table('user_ai_lessons')->where('user_id', $u->id)->count())->toBe(0)
        ->and(DB::table('writing_submissions')->where('user_id', $u->id)->count())->toBe(0)
        ->and(DB::table('ai_requests')->where('user_id', $u->id)->count())->toBe(0);
});

it('không tạo gì trên máy chủ thật, kể cả khi chạy db:seed --force', function () {
    app()->detectEnvironment(fn () => 'production');
    // --force bỏ qua câu hỏi "chắc chắn chạy trên máy chủ thật?" của db:seed: chỉ còn chặn trong seeder
    $this->artisan('db:seed', ['--class' => NguoiHocThuSeeder::class, '--force' => true])
        ->expectsOutputToContain('Bỏ qua: không tạo tài khoản thử trên máy chủ thật.')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});
