<?php

use App\Support\Ai\CaiDatAi;
use App\Support\Hoc\LapLich;
use Carbon\CarbonImmutable;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 4: hạn mức AI của từng người, ngày tính theo múi giờ người học. Lượt lỗi, sai khuôn, lấy từ cache không bị trừ.

beforeEach(function () {
    $this->seed();
    (new NoiDungThuSeeder)->tao();
    batAi();
});

it('dùng hết lượt: 429 ai_het_luot kèm giờ có lượt mới và hạn mức; nhật ký quota; không gọi AI', function () {
    $user = nguoiHocAi();
    dongAi($user, 50);
    $this->actingAs($user);

    giaiThich(theMau('das Brötchen'))->assertStatus(429)->assertExactJson([
        'code' => 'ai_het_luot', 'message' => 'Bạn đã dùng hết lượt AI hôm nay.',
        'reset_at' => LapLich::dauNgayMai(now()->getTimestamp(), $user->timezone), 'limit' => 50,
    ]);

    expect(aiGia()->daNhan)->toBe([])
        ->and(DB::table('ai_requests')->where('status', 'quota')->count())->toBe(1);
});

it('qua nửa đêm theo múi giờ của người học thì có lượt mới', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 23:50', 'Asia/Ho_Chi_Minh'));
    $user = nguoiHocAi(['timezone' => 'Asia/Ho_Chi_Minh']);
    dongAi($user, 50);
    $this->actingAs($user);
    giaiThich(theMau('das Brötchen'))->assertStatus(429)
        ->assertJsonPath('reset_at', CarbonImmutable::parse('2026-03-02 00:00', 'Asia/Ho_Chi_Minh')->getTimestamp());

    $this->travelTo(CarbonImmutable::parse('2026-03-02 00:00:01', 'Asia/Ho_Chi_Minh'));
    giaiThich(theMau('das Brötchen'))->assertOk()->assertJsonPath('meta.used', 1);
});

it('hai người ở Việt Nam và ở Đức có lượt mới ở hai giờ khác nhau', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 16:00', 'UTC'));
    $vn = nguoiHocAi(['timezone' => 'Asia/Ho_Chi_Minh']);
    $duc = nguoiHocAi(['timezone' => 'Europe/Berlin']);
    dongAi($vn, 50);
    dongAi($duc, 50);

    // 17:30 UTC: ở Việt Nam đã sang ngày 2/3 (0:30), ở Berlin vẫn là 1/3 (18:30)
    $this->travelTo(CarbonImmutable::parse('2026-03-01 17:30', 'UTC'));
    $this->actingAs($vn);
    giaiThich(theMau('das Brötchen'))->assertOk();
    $this->actingAs($duc);
    giaiThich(theMau('das Mehl'))->assertStatus(429)
        ->assertJsonPath('reset_at', CarbonImmutable::parse('2026-03-02 00:00', 'Europe/Berlin')->getTimestamp());
});

it('lượt lỗi, sai khuôn, lấy từ cache, bị chặn, chấm phát âm không bị trừ', function () {
    $user = nguoiHocAi();
    dongAi($user, 1, ['status' => 'error']);
    dongAi($user, 1, ['status' => 'quota']);
    dongAi($user, 1, ['status' => 'blocked']);
    dongAi($user, 1, ['cache_hit' => 1]);
    dongAi($user, 1, ['kind' => 'pron']);
    $this->actingAs($user);

    $this->getJson('/api/v1/toi')->assertJsonPath('data.ai.used', 0);
    aiGia()->kich(['khong phai json']);
    giaiThich(theMau('das Brötchen'))->assertStatus(502);
    $this->getJson('/api/v1/toi')->assertJsonPath('data.ai.used', 0);
});

it('đang có câu trước chưa trả lời xong (giữ khoá của người này): 429 ai_dang_xu_ly, không ghi gì', function () {
    $user = nguoiHocAi();
    $khoa = Cache::lock('ai:nguoi:'.$user->id, 40);
    expect($khoa->get())->toBeTrue();
    $this->actingAs($user);

    giaiThich(theMau('das Brötchen'))->assertStatus(429)->assertJson(['code' => 'ai_dang_xu_ly']);
    expect(DB::table('ai_requests')->count())->toBe(0)->and(aiGia()->daNhan)->toBe([]);

    $khoa->release();
    giaiThich(theMau('das Brötchen'))->assertOk();
});

it('còn 1 lượt mà hỏi hai câu: câu thứ hai hết lượt', function () {
    batAi(['trial_daily_cap' => 1]);
    $this->actingAs(nguoiHocAi());

    giaiThich(theMau('das Brötchen'))->assertOk()->assertJsonPath('meta.remaining', 0);
    giaiThich(theMau('das Mehl'))->assertStatus(429)->assertJsonPath('limit', 1);
    expect(aiGia()->daNhan)->toHaveCount(1);
});

it('giai đoạn thử dùng số nhỏ hơn giữa gói và ô Hạn mức giai đoạn thử; mở cho mọi người lớn thì chỉ theo gói', function () {
    batAi(['trial_daily_cap' => 5]);
    $this->actingAs(nguoiHocAi());
    $this->getJson('/api/v1/toi')->assertJsonPath('data.ai.limit', 5);

    CaiDatAi::luu(['audience' => 'adults'], null);
    $this->getJson('/api/v1/toi')->assertJsonPath('data.ai.limit', 50);

    DB::table('settings')->where('name', 'plans.open_access')->update(['value' => 'false']);
    $this->getJson('/api/v1/toi')->assertJsonPath('data.ai.limit', 3);
});
