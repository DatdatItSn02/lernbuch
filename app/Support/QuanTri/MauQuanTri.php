<?php

namespace App\Support\QuanTri;

use Filament\Support\Colors\Color;

/**
 * Bảng màu trang quản trị, theo màu của app học (design/tokens.css).
 *
 * Filament sinh bảng từ một mã màu nhưng giữ độ sáng cố định cho từng bậc, nên nút ở giao diện tối (nền bậc 600, chữ trắng)
 * chỉ đạt khoảng 4:1, dưới mức WCAG AA 4.5:1. Ở đây bậc 500–950 tối hơn: bậc 600 của primary đúng mực tím của app, các màu
 * khác sáng 48%; bậc 50–400 giữ độ sáng như Filament (chữ màu trên nền tối). Độ đậm màu giữ trong gamut sRGB.
 * tests/Unit/MauQuanTriTest.php kiểm mọi cặp nền nút và chữ Filament chọn, sáng và tối, bằng công thức tương phản WCAG.
 */
final class MauQuanTri
{
    /** primary: #442e9d (mực tím, design/tokens.css) */
    public const TIM = [
        50 => 'oklch(0.977 0.011 284.834)',
        100 => 'oklch(0.95 0.024 284.834)',
        200 => 'oklch(0.905 0.047 284.834)',
        300 => 'oklch(0.84 0.081 284.834)',
        400 => 'oklch(0.754 0.129 284.834)',
        500 => 'oklch(0.47 0.17 284.834)',
        600 => 'oklch(0.4 0.169 284.834)',
        700 => 'oklch(0.335 0.149 284.834)',
        800 => 'oklch(0.3 0.123 284.834)',
        900 => 'oklch(0.26 0.1 284.834)',
        950 => 'oklch(0.19 0.071 284.834)',
    ];

    /** success: #21763c */
    public const XANH_LA = [
        50 => 'oklch(0.977 0.014 149.902)',
        100 => 'oklch(0.95 0.033 149.902)',
        200 => 'oklch(0.905 0.063 149.902)',
        300 => 'oklch(0.84 0.106 149.902)',
        400 => 'oklch(0.754 0.15 149.902)',
        500 => 'oklch(0.53 0.143 149.902)',
        600 => 'oklch(0.48 0.13 149.902)',
        700 => 'oklch(0.42 0.114 149.902)',
        800 => 'oklch(0.36 0.098 149.902)',
        900 => 'oklch(0.3 0.081 149.902)',
        950 => 'oklch(0.22 0.06 149.902)',
    ];

    /** danger: #b71824 */
    public const GACH = [
        50 => 'oklch(0.977 0.011 24.895)',
        100 => 'oklch(0.95 0.024 24.895)',
        200 => 'oklch(0.905 0.048 24.895)',
        300 => 'oklch(0.84 0.086 24.895)',
        400 => 'oklch(0.754 0.145 24.895)',
        500 => 'oklch(0.53 0.17 24.895)',
        600 => 'oklch(0.48 0.169 24.895)',
        700 => 'oklch(0.42 0.149 24.895)',
        800 => 'oklch(0.36 0.123 24.895)',
        900 => 'oklch(0.3 0.1 24.895)',
        950 => 'oklch(0.22 0.071 24.895)',
    ];

    /** warning: #e6ac3d (mật ong) */
    public const MAT_ONG = [
        50 => 'oklch(0.977 0.014 79.823)',
        100 => 'oklch(0.95 0.033 79.823)',
        200 => 'oklch(0.905 0.063 79.823)',
        300 => 'oklch(0.84 0.106 79.823)',
        400 => 'oklch(0.754 0.15 79.823)',
        500 => 'oklch(0.53 0.108 79.823)',
        600 => 'oklch(0.48 0.098 79.823)',
        700 => 'oklch(0.42 0.085 79.823)',
        800 => 'oklch(0.36 0.073 79.823)',
        900 => 'oklch(0.3 0.061 79.823)',
        950 => 'oklch(0.22 0.045 79.823)',
    ];

    /** info: #2266a4 */
    public const XANH_DUONG = [
        50 => 'oklch(0.977 0.011 249.956)',
        100 => 'oklch(0.95 0.024 249.956)',
        200 => 'oklch(0.905 0.047 249.956)',
        300 => 'oklch(0.84 0.081 249.956)',
        400 => 'oklch(0.754 0.129 249.956)',
        500 => 'oklch(0.53 0.147 249.956)',
        600 => 'oklch(0.48 0.133 249.956)',
        700 => 'oklch(0.42 0.117 249.956)',
        800 => 'oklch(0.36 0.1 249.956)',
        900 => 'oklch(0.3 0.084 249.956)',
        950 => 'oklch(0.22 0.062 249.956)',
    ];

    /**
     * @return array<string, array<int, string>>
     */
    public static function tatCa(): array
    {
        return [
            'primary' => self::TIM,
            'success' => self::XANH_LA,
            'danger' => self::GACH,
            'warning' => self::MAT_ONG,
            'info' => self::XANH_DUONG,
            'gray' => Color::Slate,
        ];
    }
}
