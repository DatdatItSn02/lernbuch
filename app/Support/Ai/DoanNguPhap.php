<?php

namespace App\Support\Ai;

/**
 * Chữ của một phần bài ngữ pháp để gửi AI giải thích. Phần thứ n là từ khối h2 thứ n tới trước h2 kế tiếp (đếm như
 * cacPhan ở KhoiNguPhap.tsx); bài không có h2 thì phần 0 là cả bài. Mỗi khối thành một dòng chữ thường.
 */
final class DoanNguPhap
{
    public const TOI_DA = 1500;

    /** @param array<int, mixed> $khoi */
    public static function soPhan(array $khoi): int
    {
        $n = count(array_filter($khoi, fn ($k) => is_array($k) && ($k['t'] ?? null) === 'h2'));

        return max(1, $n);
    }

    /** @param array<int, mixed> $khoi các khối của bài (grammar_lessons.blocks) */
    public static function chu(array $khoi, int $phan): ?string
    {
        $coH2 = array_filter($khoi, fn ($k) => is_array($k) && ($k['t'] ?? null) === 'h2') !== [];

        if ($phan < 0 || $phan >= self::soPhan($khoi)) {
            return null;
        }

        $dong = [];
        $dangO = $coH2 ? -1 : 0;

        foreach ($khoi as $k) {
            if (! is_array($k)) {
                continue;
            }

            if ($coH2 && ($k['t'] ?? null) === 'h2') {
                $dangO++;
            }

            if ($dangO !== $phan) {
                continue;
            }

            $chu = self::motKhoi($k);

            if ($chu !== '') {
                $dong[] = $chu;
            }
        }

        $ra = trim(str_replace('**', '', implode("\n", $dong)));

        return $ra === '' ? null : mb_substr($ra, 0, self::TOI_DA);
    }

    /** @param array<string, mixed> $k */
    private static function motKhoi(array $k): string
    {
        $x = is_string($k['x'] ?? null) ? $k['x'] : '';
        $vi = is_array($k['L'] ?? null) && is_string($k['L']['vi'] ?? null) ? $k['L']['vi'] : '';

        return trim(match ($k['t'] ?? '') {
            'kv' => (is_string($k['k'] ?? null) ? $k['k'].': ' : '').$x,
            'fm' => trim($x.($vi !== '' ? ' ('.$vi.')' : '')),
            'tipl' => $vi,
            'ex' => trim($x.' '.($vi !== '' ? '('.$vi.')' : (is_string($k['tr'] ?? null) ? '('.$k['tr'].')' : ''))),
            'tbl' => self::bang($k['rows'] ?? null),
            default => $x,
        });
    }

    private static function bang(mixed $hang): string
    {
        if (! is_array($hang)) {
            return '';
        }

        $ra = [];

        foreach ($hang as $h) {
            if (is_array($h)) {
                $ra[] = implode(' | ', array_map(fn ($o) => is_array($o) && is_string($o[0] ?? null) ? $o[0] : '', $h));
            }
        }

        return implode("\n", $ra);
    }
}
