<?php

use App\Models\User;
use App\Support\QuyenLoi;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 4 (phần gói): hạn mức AI mỗi ngày lấy từ quyền lợi của gói đang chạy, có tính chế độ mở tạm.

beforeEach(fn () => $this->seed());

function goiQuyenLoi(User $user, string $goi, string $trangThai = 'active', int $batDau = -86400, ?int $het = 86400 * 30): void
{
    $bay = now()->getTimestamp();
    DB::table('subscriptions')->insert([
        'user_id' => $user->id, 'plan_id' => DB::table('plans')->where('code', $goi)->value('id'), 'status' => $trangThai,
        'source' => 'admin', 'starts_at' => $bay + $batDau, 'ends_at' => $het === null ? null : $bay + $het, 'created_at' => $bay,
    ]);
}

function moTamQuyenLoi(bool $bat, string $goi = '"premium_12m"'): void
{
    DB::table('settings')->where('name', 'plans.open_access')->update(['value' => $bat ? 'true' : 'false']);
    DB::table('settings')->where('name', 'plans.open_access_plan')->update(['value' => $goi]);
}

it('không có gói: tắt mở tạm thì theo gói miễn phí (3), bật mở tạm thì theo Premium (50)', function () {
    $user = User::factory()->create();

    moTamQuyenLoi(false);
    expect(QuyenLoi::so($user, 'ai_daily'))->toBe(3)->and(QuyenLoi::so($user, 'pron_daily'))->toBe(2);

    moTamQuyenLoi(true);
    expect(QuyenLoi::so($user, 'ai_daily'))->toBe(50)->and(QuyenLoi::so($user, 'pron_daily'))->toBe(20);
});

it('gói đang chạy được tính, kể cả dùng thử; gói hết hạn, chưa bắt đầu hay đã huỷ thì như không có', function () {
    moTamQuyenLoi(false);
    $dang = User::factory()->create();
    goiQuyenLoi($dang, 'premium_1m');
    $thu = User::factory()->create();
    goiQuyenLoi($thu, 'premium_1m', 'trialing');
    $het = User::factory()->create();
    goiQuyenLoi($het, 'premium_1m', 'active', -86400 * 40, -86400);
    $chua = User::factory()->create();
    goiQuyenLoi($chua, 'premium_1m', 'active', 86400);
    $huy = User::factory()->create();
    goiQuyenLoi($huy, 'premium_1m', 'canceled');

    expect(QuyenLoi::so($dang, 'ai_daily'))->toBe(50)
        ->and(QuyenLoi::so($thu, 'ai_daily'))->toBe(50)
        ->and(QuyenLoi::so($het, 'ai_daily'))->toBe(3)
        ->and(QuyenLoi::so($chua, 'ai_daily'))->toBe(3)
        ->and(QuyenLoi::so($huy, 'ai_daily'))->toBe(3);
});

it('gói trọn đời không kèm AI (0), nhưng đang mở tạm thì vẫn được 50; không có ngày hết hạn là trọn đời', function () {
    $user = User::factory()->create();
    goiQuyenLoi($user, 'lifetime', 'active', -86400, null);

    moTamQuyenLoi(false);
    expect(QuyenLoi::so($user, 'ai_daily'))->toBe(0);
    moTamQuyenLoi(true);
    expect(QuyenLoi::so($user, 'ai_daily'))->toBe(50);
});

it('-1 (không giới hạn) thắng mọi số; gói mở tạm trỏ tới gói không có thì bỏ qua; quyền lợi lạ là 0', function () {
    $user = User::factory()->create();
    goiQuyenLoi($user, 'premium_12m');
    DB::table('plan_entitlements')->where('plan_id', DB::table('plans')->where('code', 'premium_12m')->value('id'))
        ->where('entitlement_code', 'ai_daily')->update(['value' => '-1']);
    moTamQuyenLoi(false);
    expect(QuyenLoi::so($user, 'ai_daily'))->toBe(-1);

    $khac = User::factory()->create();
    moTamQuyenLoi(true, '"goi-khong-co"');
    expect(QuyenLoi::so($khac, 'ai_daily'))->toBe(3)
        ->and(QuyenLoi::so($khac, 'khong-co'))->toBe(0);
});
