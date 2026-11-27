<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
