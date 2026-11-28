<?php

use App\Filament\Pages\TrangThongKeAi;
use App\Support\QuanTri\ThongKeAi;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

// Mục tự kiểm 17 (phần Thống kê AI): ngày theo giờ Thái Bình Dương, đúng từng cột.

beforeEach(function () {
    $this->seed();
    // 10:00 ngày 28/11 giờ Los Angeles
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00:00', 'America/Los_Angeles'));
});

function lucLA(string $gio): int
{
    return CarbonImmutable::parse($gio, 'America/Los_Angeles')->getTimestamp();
}

it('từng cột của một ngày đúng số; lượt sát nửa đêm nằm đúng ngày; ngày không có lượt là 0', function () {
    $hoc = nguoiHocAi();
    dongAi($hoc, 2, ['created_at' => lucLA('2026-11-28 00:00:00'), 'input_tokens' => 100, 'output_tokens' => 50, 'cost_micro_usd' => 200, 'latency_ms' => 1000]);
    dongAi($hoc, 1, ['created_at' => lucLA('2026-11-28 09:00:00'), 'latency_ms' => 3000]);
    dongAi($hoc, 1, ['created_at' => lucLA('2026-11-28 09:30:00'), 'cache_hit' => 1, 'latency_ms' => 0]);
    dongAi($hoc, 1, ['created_at' => lucLA('2026-11-28 09:40:00'), 'status' => 'error', 'latency_ms' => 20000]);
    dongAi($hoc, 1, ['created_at' => lucLA('2026-11-28 09:50:00'), 'status' => 'blocked']);
    dongAi($hoc, 1, ['created_at' => lucLA('2026-11-28 09:55:00'), 'status' => 'quota']);
    dongAi($hoc, 3, ['created_at' => lucLA('2026-11-27 23:59:59'), 'cost_micro_usd' => 1000]);

    $ngay = ThongKeAi::theoNgay();
    expect($ngay)->toHaveCount(30)
        ->and($ngay[0])->toMatchArray(['ngay' => '28/11/2026', 'luot' => 7, 'ok' => 4, 'cache' => 1, 'loi' => 1, 'chan' => 1, 'het_luot' => 1,
            'token_vao' => 200, 'token_ra' => 100, 'chi_phi_micro' => 400, 'cho_tb' => 1667, 'cho_max' => 3000])
        ->and($ngay[1])->toMatchArray(['ngay' => '27/11/2026', 'luot' => 3, 'chi_phi_micro' => 3000, 'cho_tb' => null])
        ->and($ngay[2])->toMatchArray(['luot' => 0, 'ok' => 0, 'chi_phi_micro' => 0, 'cho_max' => null]);
});

it('theo loại 7 ngày; hôm nay so với trần chỉ đếm lượt tới nhà cung cấp', function () {
    batAi(['rpd_cap' => 500, 'daily_spend_cap_usd' => 20]);
    dongAi(null, 2, ['kind' => 'translate', 'created_at' => lucLA('2026-11-28 08:00:00'), 'cost_micro_usd' => 1000]);
    dongAi(null, 1, ['kind' => 'word', 'created_at' => lucLA('2026-11-28 08:00:00'), 'cache_hit' => 1]);
    dongAi(null, 1, ['kind' => 'word', 'created_at' => lucLA('2026-11-28 08:00:00'), 'status' => 'quota']);
    dongAi(null, 5, ['kind' => 'word', 'created_at' => lucLA('2026-11-20 08:00:00')]);

    expect(ThongKeAi::theoLoai())->toBe([
        ['kind' => 'translate', 'luot' => 2, 'cache' => 0, 'loi' => 0, 'chi_phi_micro' => 2000],
        ['kind' => 'word', 'luot' => 2, 'cache' => 1, 'loi' => 0, 'chi_phi_micro' => 0],
    ])->and(ThongKeAi::homNay())->toBe(['luot' => 2, 'tran_luot' => 500, 'chi_phi_micro' => 2000, 'tran_chi_phi_usd' => 20.0]);
});

it('trang mở được khi chưa có lượt nào và khi có số liệu', function () {
    $this->actingAs(nhanVien('admin'));
    Livewire::test(TrangThongKeAi::class)->assertOk()->assertSee('Thái Bình Dương')->assertSee('Chưa có lượt hỏi AI nào');

    dongAi(null, 3, ['kind' => 'chat', 'created_at' => lucLA('2026-11-28 08:00:00'), 'cost_micro_usd' => 12345]);
    Livewire::test(TrangThongKeAi::class)->assertSee('28/11/2026')->assertSee('Trò chuyện')->assertSee('0,0370');
});
