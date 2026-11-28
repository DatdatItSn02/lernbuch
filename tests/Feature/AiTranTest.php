<?php

use App\Support\Ai\BoNoi\LoiBoNoi;
use Carbon\CarbonImmutable;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 5: trần của cả hệ thống (lượt mỗi phút, mỗi ngày theo giờ Thái Bình Dương, chi phí mỗi ngày, lượt cùng lúc).
// Chạm trần thì chặn trước khi gọi nhà cung cấp, người học không mất lượt, nhật ký ghi blocked.

beforeEach(function () {
    $this->seed();
    (new NoiDungThuSeeder)->tao();
    batAi();
});

function chanVoiQuaTai(): void
{
    giaiThich(theMau('das Brötchen'))->assertStatus(503)->assertJson(['code' => 'ai_qua_tai', 'message' => 'AI đang quá tải, thử lại sau ít phút.']);
}

it('quá lượt mỗi phút: chặn, không gọi AI, không trừ lượt, nhật ký blocked; 61 giây sau gọi được', function () {
    batAi(['rpm_cap' => 2]);
    dongAi(null, 1);
    dongAi(null, 1, ['status' => 'error']);
    $this->actingAs(nguoiHocAi());

    chanVoiQuaTai();
    expect(aiGia()->daNhan)->toBe([])
        ->and(DB::table('ai_requests')->where('status', 'blocked')->count())->toBe(1)
        ->and($this->getJson('/api/v1/toi')->json('data.ai.used'))->toBe(0);

    $this->travel(61)->seconds();
    giaiThich(theMau('das Brötchen'))->assertOk();
});

it('lượt bị chặn, hết lượt, lấy từ cache và chấm phát âm không tính vào trần', function () {
    batAi(['rpm_cap' => 1]);
    dongAi(null, 3, ['status' => 'blocked']);
    dongAi(null, 3, ['status' => 'quota']);
    dongAi(null, 3, ['cache_hit' => 1]);
    dongAi(null, 3, ['kind' => 'pron']);
    $this->actingAs(nguoiHocAi());

    giaiThich(theMau('das Brötchen'))->assertOk();
});

it('quá lượt mỗi ngày: chặn tới nửa đêm giờ Thái Bình Dương', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 23:50', 'America/Los_Angeles'));
    batAi(['rpd_cap' => 3]);
    dongAi(null, 3, ['created_at' => now()->getTimestamp() - 3600]);
    $this->actingAs(nguoiHocAi());

    chanVoiQuaTai();

    $this->travelTo(CarbonImmutable::parse('2026-03-02 00:00:30', 'America/Los_Angeles'));
    giaiThich(theMau('das Brötchen'))->assertOk();
});

it('chi phí quy đổi trong ngày chạm trần: chặn', function () {
    batAi(['daily_spend_cap_usd' => 1]);
    dongAi(null, 1, ['cost_micro_usd' => 999000, 'created_at' => now()->getTimestamp() - 600]);
    $this->actingAs(nguoiHocAi());

    giaiThich(theMau('das Brötchen'))->assertOk();
    // 999.000 + 1.125 phần triệu USD đã vượt 1 USD
    giaiThich(theMau('das Mehl'))->assertStatus(503)->assertJson(['code' => 'ai_qua_tai']);
});

it('đủ số lượt gọi cùng lúc: chặn; nhả một chỗ thì gọi được', function () {
    $giu = array_map(fn ($i) => Cache::lock('ai:cho:'.$i, 40), [1, 2, 3]);
    foreach ($giu as $khoa) {
        expect($khoa->get())->toBeTrue();
    }
    $this->actingAs(nguoiHocAi());

    chanVoiQuaTai();
    expect(aiGia()->daNhan)->toBe([]);

    $giu[1]->release();
    giaiThich(theMau('das Brötchen'))->assertOk();
});

it('bộ nối ném lỗi thì chỗ gọi vẫn được nhả', function () {
    batAi(['max_concurrent' => 1]);
    aiGia()->kich([new LoiBoNoi(LoiBoNoi::CAU_HINH)]);
    $this->actingAs(nguoiHocAi());

    giaiThich(theMau('das Brötchen'))->assertStatus(503)->assertJson(['code' => 'ai_loi_cau_hinh']);
    giaiThich(theMau('das Brötchen'))->assertOk();
    expect(Cache::lock('ai:cho:1', 40)->get())->toBeTrue();
});

it('lượt trúng cache không bị trần chặn', function () {
    $this->actingAs(nguoiHocAi());
    giaiThich(theMau('das Brötchen'))->assertOk();

    batAi(['rpm_cap' => 1]);
    giaiThich(theMau('das Brötchen'))->assertOk()->assertJsonPath('meta.cached', true);
    giaiThich(theMau('das Mehl'))->assertStatus(503);
});
