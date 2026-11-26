<?php

namespace App\Support;

/** Đoán trình duyệt, hệ điều hành và loại máy từ User-Agent, để danh sách phiên đăng nhập dễ nhận ra máy nào. */
final class ThietBi
{
    /** Thứ tự quan trọng: Edge, Opera, Samsung đều có chữ "Chrome" trong User-Agent nên phải dò trước. */
    private const TRINH_DUYET = [
        '/\bEdg(?:e|A|iOS)?\//' => 'Edge',
        '/\b(?:OPR|Opera)\//' => 'Opera',
        '/\bSamsungBrowser\//' => 'Samsung Internet',
        '/\bCriOS\//' => 'Chrome',
        '/\bFxiOS\//' => 'Firefox',
        '/\bFirefox\//' => 'Firefox',
        '/\bChrome\//' => 'Chrome',
        '/\bVersion\/[\d.]+.*\bSafari\//' => 'Safari',
    ];

    private const HE_DIEU_HANH = [
        '/\biP(?:hone|od)\b/' => 'iOS',
        '/\biPad\b/' => 'iPadOS',
        '/\bAndroid\b/' => 'Android',
        '/\bWindows NT\b/' => 'Windows',
        '/\bCrOS\b/' => 'ChromeOS',
        '/\bMac OS X\b|\bMacintosh\b/' => 'macOS',
        '/\bLinux\b/' => 'Linux',
    ];

    /** @return array{browser: string|null, os: string|null, device_type: 'phone'|'tablet'|'desktop'} */
    public static function tu(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        return [
            'browser' => self::dauTien(self::TRINH_DUYET, $ua),
            'os' => self::dauTien(self::HE_DIEU_HANH, $ua),
            'device_type' => match (true) {
                (bool) preg_match('/\biPad\b|\bTablet\b/', $ua),
                str_contains($ua, 'Android') && ! str_contains($ua, 'Mobile') => 'tablet',
                (bool) preg_match('/\biP(?:hone|od)\b|\bMobile\b/', $ua) => 'phone',
                default => 'desktop',
            },
        ];
    }

    /** @param  array<string, string>  $mau */
    private static function dauTien(array $mau, string $ua): ?string
    {
        foreach ($mau as $regex => $ten) {
            if (preg_match($regex, $ua) === 1) {
                return $ten;
            }
        }

        return null;
    }
}
