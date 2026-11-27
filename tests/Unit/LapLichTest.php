<?php

use App\Support\Hoc\LapLich;

// Mục tự kiểm 3: luật lặp lại ngắt quãng đúng mọi nhánh, kể cả khi đổi giờ mùa hè và đổi múi giờ.
// Bảng ca thử chung tests/chung/lap-lich.json: Vitest chạy đúng bảng này với bản TypeScript (srs.test.ts).

/** @return array<string, mixed> */
function bangLapLich(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__).'/chung/lap-lich.json'), true, flags: JSON_THROW_ON_ERROR);
}

function gioDiaPhuong(int $luc, string $muiGio): string
{
    return (new DateTimeImmutable('@'.$luc))->setTimezone(new DateTimeZone($muiGio))->format('Y-m-d\TH:i:s');
}

it('tính đúng như bảng ca thử chung', function (array $ca) {
    $luc = (new DateTimeImmutable($ca['luc']))->getTimestamp();
    $kq = LapLich::cham($ca['muc'], $ca['cham'], $luc, $ca['mui_gio']);

    expect(gioDiaPhuong($luc, $ca['mui_gio']))->toBe($ca['luc_dia_phuong'])
        ->and($kq['muc'])->toBe($ca['muc_moi'])
        ->and(gioDiaPhuong($kq['han'], $ca['mui_gio']))->toBe($ca['han'])
        ->and($kq['quen'])->toBe($ca['quen'])
        ->and(LapLich::soNgay($ca['muc'], $ca['cham']))->toBe($ca['so_ngay'])
        ->and(LapLich::mucMoi($ca['muc'], $ca['cham']))->toBe($ca['muc_moi']);
})->with(fn () => collect(bangLapLich()['ca'])->mapWithKeys(fn (array $ca) => [$ca['ten'] => [$ca]])->all());

it('mức hoặc cách chấm ngoài khoảng thì báo lỗi', function (array $ca) {
    expect(fn () => LapLich::cham($ca['muc'], $ca['cham'], 1_764_200_000, 'Asia/Ho_Chi_Minh'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => LapLich::mucMoi($ca['muc'], $ca['cham']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => LapLich::soNgay($ca['muc'], $ca['cham']))->toThrow(InvalidArgumentException::class);
})->with(fn () => collect(bangLapLich()['loi'])->mapWithKeys(fn (array $ca) => [$ca['ten'] => [$ca]])->all());

it('bảng ca thử phủ đủ 6 mức × 4 cách chấm và có ca đổi giờ, đổi múi giờ', function () {
    $bang = bangLapLich();
    $cap = collect($bang['ca'])->map(fn (array $ca) => $ca['muc'].'-'.$ca['cham'])->unique();

    expect($cap)->toHaveCount(24)
        ->and(collect($bang['ca'])->pluck('mui_gio')->unique()->values()->all())->toContain('Europe/Berlin', 'America/New_York', 'Pacific/Auckland');
});

it('đầu ngày mai và đầu ngày trước đúng cả ngày 23 giờ và 25 giờ', function () {
    $tz = 'Europe/Berlin';
    $luc = fn (string $s) => (new DateTimeImmutable($s, new DateTimeZone($tz)))->getTimestamp();

    // 29/3/2026 chỉ có 23 giờ, 25/10/2026 có 25 giờ
    expect(gioDiaPhuong(LapLich::dauNgayMai($luc('2026-03-29 10:00'), $tz), $tz))->toBe('2026-03-30T00:00:00')
        ->and(gioDiaPhuong(LapLich::dauNgayTruoc($luc('2026-03-30 00:00'), $tz), $tz))->toBe('2026-03-29T00:00:00')
        ->and(gioDiaPhuong(LapLich::dauNgayMai($luc('2026-10-25 23:30'), $tz), $tz))->toBe('2026-10-26T00:00:00')
        ->and(gioDiaPhuong(LapLich::dauNgayTruoc($luc('2026-10-26 00:00'), $tz), $tz))->toBe('2026-10-25T00:00:00')
        ->and(LapLich::dauNgayMai($luc('2026-10-25 10:00'), $tz) - LapLich::dauNgay($luc('2026-10-25 10:00'), $tz))->toBe(25 * 3600);
});
