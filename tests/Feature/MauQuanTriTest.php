<?php

use App\Support\QuanTri\MauQuanTri;
use Filament\Support\Colors\Color;
use Filament\Support\View\Components\ButtonComponent;

// Bảng màu trang quản trị (mục tự kiểm 14): mọi nút màu Filament vẽ, sáng và tối, cả khi di chuột, đạt tương phản WCAG AA
// 4.5:1. Tương phản tính bằng công thức sRGB của WCAG 2 như axe, không dùng công thức gần đúng của Filament.

function tuongPhanWcag(string $mau1, string $mau2): float
{
    $sang = function (string $mau): float {
        [$r, $g, $b] = sscanf(str_replace(' ', '', Color::convertToRgb($mau)), 'rgb(%d,%d,%d)');
        $kenh = fn (int $v): float => ($v / 255) <= 0.04045 ? ($v / 255) / 12.92 : ((($v / 255) + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $kenh($r) + 0.7152 * $kenh($g) + 0.0722 * $kenh($b);
    };
    [$a, $b] = [$sang($mau1), $sang($mau2)];

    return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
}

/**
 * Nền và chữ của nút màu mà Filament chọn cho một bảng màu.
 *
 * @param  array<int, string>  $bang
 * @return array<string, float> trường hợp → tương phản
 */
function tuongPhanNutFilament(array $bang): array
{
    $map = ButtonComponent::make()->getColorMap($bang);
    $chu = fn (int $bac): string => $bac === 0 ? 'rgb(255, 255, 255)' : $bang[$bac];

    return [
        'sáng' => tuongPhanWcag($bang[$map['bg']], $chu($map['text'])),
        'sáng, di chuột' => tuongPhanWcag($bang[$map['hover:bg']], $chu($map['hover:text'])),
        'tối' => tuongPhanWcag($bang[$map['dark:bg']], $chu($map['dark:text'])),
        'tối, di chuột' => tuongPhanWcag($bang[$map['dark:hover:bg']], $chu($map['dark:hover:text'])),
    ];
}

it('nút mọi màu của trang quản trị đạt 4.5:1 ở giao diện sáng và tối, cả khi di chuột', function (string $ten) {
    foreach (tuongPhanNutFilament(MauQuanTri::tatCa()[$ten]) as $truongHop => $tp) {
        expect($tp)->toBeGreaterThanOrEqual(4.5, "{$ten}, {$truongHop}: ".round($tp, 2));
    }
})->with(['primary', 'success', 'danger', 'warning', 'info']);

it('chữ màu (bậc 600 trên nền trắng, bậc 400 trên nền tối slate-900, slate-800) đạt 4.5:1; bậc sáng dần đều', function (string $ten) {
    $bang = MauQuanTri::tatCa()[$ten];
    expect(tuongPhanWcag($bang[600], 'rgb(255, 255, 255)'))->toBeGreaterThanOrEqual(4.5)
        ->and(tuongPhanWcag($bang[400], Color::Slate[900]))->toBeGreaterThanOrEqual(4.5)
        ->and(tuongPhanWcag($bang[400], Color::Slate[800]))->toBeGreaterThanOrEqual(4.5);

    $doSang = array_map(fn (string $m): float => (float) sscanf($m, 'oklch(%f')[0], $bang);
    expect(array_keys($bang))->toBe([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]);
    $sau = array_values($doSang);
    foreach (array_slice($sau, 1) as $i => $l) {
        expect($l)->toBeLessThan($sau[$i]);
    }
})->with(['primary', 'success', 'danger', 'warning', 'info']);

it('primary bậc 600 đúng mực tím của app; bảng Filament tự sinh từ mã màu thì nút ở giao diện tối dưới 4.5:1 (lý do phải đổi)', function () {
    expect(Color::convertToHex(MauQuanTri::TIM[600]))->toBe('#442e9d');

    foreach (['#442e9d', '#21763c', '#b71824', '#e6ac3d', '#2266a4'] as $hex) {
        expect(tuongPhanNutFilament(Color::hex($hex))['tối'])->toBeLessThan(4.5);
    }
});
