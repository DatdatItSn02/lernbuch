<?php

use App\Models\User;
use App\Support\Ai\BoNoi\BoNoiGia;
use App\Support\Ai\CaiDatAi;
use App\Support\Ai\DanhSachThu;
use App\Support\Ai\DongYAi;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

// Dùng chung cho các test học (HocApiTest, CheDoHocTest): giờ theo múi giờ của người học, một lượt chấm như app gửi.

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

// Dùng chung cho các test trang quản trị (QuanTriTest, QuanTriNguoiHocTest, QuanTriNoiDungTest).

/** Nhân viên đang làm, có một vai trò (cần seed trước: vai trò và quyền nằm trong seed.sql). */
function nhanVien(string $vaiTro, array $doi = []): User
{
    $user = User::factory()->create($doi);
    $user->forceFill(['is_staff' => true])->save();
    $user->assignRole($vaiTro);

    return $user;
}

/** @return array<string, mixed> dòng nhật ký thao tác mới nhất của một việc */
function dongNhatKy(string $viec): array
{
    $dong = DB::table('audit_logs')->where('action', $viec)->latest('id')->first();
    expect($dong)->not->toBeNull("không có nhật ký {$viec}");

    return (array) $dong;
}

// Dùng chung cho các test AI (tests/Feature/Ai*Test.php): AI giả (AI_DRIVER=fake trong phpunit.xml), nội dung mẫu.

/**
 * Bật AI với các ô cài đặt cho trước. Mặc định giai đoạn thử; trần lượt mỗi phút, mỗi ngày đặt cao để không vướng
 * (test trần hệ thống tự đặt lại).
 *
 * @param  array<string, mixed>  $doi
 */
function batAi(array $doi = []): void
{
    CaiDatAi::luu($doi + ['enabled' => true, 'rpm_cap' => 10000, 'rpd_cap' => 1000000], null);
}

/**
 * Người học dùng được AI: đủ tuổi (sinh 1/1/1990), trong danh sách thử, đã đồng ý.
 *
 * @param  array<string, mixed>  $doi
 */
function nguoiHocAi(array $doi = [], bool $thu = true, bool $dongY = true): User
{
    $user = User::factory()->create($doi + ['birth_date' => gmmktime(0, 0, 0, 1, 1, 1990)]);

    if ($thu) {
        DanhSachThu::dat($user, true);
    }

    if ($dongY) {
        DongYAi::ghi($user, true);
    }

    return $user;
}

function aiGia(): BoNoiGia
{
    return app(BoNoiGia::class);
}

/** Mã thẻ mẫu của NoiDungThuSeeder theo mặt trước. */
function theMau(string $front): int
{
    return (int) DB::table('cards')->where('front', $front)->value('id');
}

/** Gọi Giải thích từ cho một thẻ bằng tài khoản đang đăng nhập. */
function giaiThich(int $the): TestResponse
{
    return test()->postJson('/api/v1/ai/giai-thich-tu', ['card_id' => $the]);
}

/**
 * Thêm các dòng nhật ký AI giả lập (vd lượt đã dùng hôm nay).
 *
 * @param  array<string, mixed>  $doi
 */
function dongAi(?User $user, int $soDong, array $doi = []): void
{
    for ($i = 0; $i < $soDong; $i++) {
        DB::table('ai_requests')->insert($doi + [
            'user_id' => $user?->id, 'kind' => 'word', 'provider' => 'fake', 'status' => 'ok', 'cache_hit' => 0,
            'cost_micro_usd' => 0, 'created_at' => now()->getTimestamp(),
        ]);
    }
}
