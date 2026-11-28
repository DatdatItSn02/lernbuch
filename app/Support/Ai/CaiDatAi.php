<?php

namespace App\Support\Ai;

use Illuminate\Support\Facades\DB;

/**
 * Cài đặt AI sửa trong trang quản trị (bảng settings, tên bắt đầu bằng "ai."). Mỗi lần gọi đọc lại từ database, không nhớ tạm:
 * bấm tắt khẩn cấp là có hiệu lực ngay ở lượt gọi kế tiếp. Ô thiếu hoặc sai kiểu thì dùng giá trị mặc định.
 */
final class CaiDatAi
{
    /** Khoá ngắn → giá trị mặc định; tên trong bảng settings là "ai." + khoá. */
    public const MAC_DINH = [
        'enabled' => false,
        'audience' => 'testers',
        'provider' => 'gemini',
        'default_model' => 'gemini-3.1-flash-lite',
        'rpm_cap' => 10,
        'rpd_cap' => 200,
        'max_concurrent' => 3,
        'daily_spend_cap_usd' => 20,
        'trial_daily_cap' => 50,
        'price_usd_per_mtok' => ['in' => 0.25, 'out' => 1.5],
        'min_age' => 18,
    ];

    public const AI_DUNG = ['testers', 'adults'];

    public const NHA_CUNG_CAP = ['gemini', 'openai_compatible'];

    /** Điều khoản Gemini API: người dùng phải từ 18 tuổi. Ô trong admin không hạ được dưới mức này. */
    public const TUOI_SAN = 18;

    /** @return array{enabled: bool, audience: string, provider: string, default_model: string, rpm_cap: int, rpd_cap: int, max_concurrent: int, daily_spend_cap_usd: int|float, trial_daily_cap: int, price_usd_per_mtok: array{in: int|float, out: int|float}, min_age: int} */
    public static function tatCa(): array
    {
        $ra = self::MAC_DINH;

        foreach (DB::table('settings')->where('name', 'like', 'ai.%')->get(['name', 'value']) as $dong) {
            $khoa = substr((string) $dong->name, 3);
            $giaTri = json_decode((string) $dong->value, true);

            if (array_key_exists($khoa, $ra) && self::hopLe($khoa, $giaTri)) {
                $ra[$khoa] = $giaTri;
            }
        }

        /** @var array{enabled: bool, audience: string, provider: string, default_model: string, rpm_cap: int, rpd_cap: int, max_concurrent: int, daily_spend_cap_usd: int|float, trial_daily_cap: int, price_usd_per_mtok: array{in: int|float, out: int|float}, min_age: int} $ra */
        return $ra;
    }

    /** Tuổi tối thiểu thật sự dùng: không bao giờ dưới 18. @param array{min_age: int} $cd */
    public static function tuoiToiThieu(array $cd): int
    {
        return max(self::TUOI_SAN, $cd['min_age']);
    }

    public static function hopLe(string $khoa, mixed $giaTri): bool
    {
        $so = fn (mixed $v, float $tu, float $den) => (is_int($v) || is_float($v)) && $v >= $tu && $v <= $den;
        $nguyen = fn (mixed $v, int $tu, int $den) => is_int($v) && $v >= $tu && $v <= $den;

        return match ($khoa) {
            'enabled' => is_bool($giaTri),
            'audience' => in_array($giaTri, self::AI_DUNG, true),
            'provider' => in_array($giaTri, self::NHA_CUNG_CAP, true),
            'default_model' => is_string($giaTri) && preg_match('/^[A-Za-z0-9._-]{1,60}$/', $giaTri) === 1,
            'rpm_cap' => $nguyen($giaTri, 1, 10000),
            'rpd_cap' => $nguyen($giaTri, 1, 1000000),
            'max_concurrent' => $nguyen($giaTri, 1, 10),
            'daily_spend_cap_usd' => $so($giaTri, 0.1, 1000),
            'trial_daily_cap' => $nguyen($giaTri, 0, 500),
            'price_usd_per_mtok' => is_array($giaTri) && array_keys($giaTri) === ['in', 'out'] && $so($giaTri['in'], 0, 1000) && $so($giaTri['out'], 0, 1000),
            'min_age' => $nguyen($giaTri, 0, 120),
            default => false,
        };
    }

    /**
     * Lưu những ô có trong $thayDoi và hợp lệ; ô lạ hoặc sai thì bỏ qua (trang quản trị đã báo lỗi từng ô trước khi gọi).
     *
     * @param  array<string, mixed>  $thayDoi
     */
    public static function luu(array $thayDoi, ?int $nguoiSua): void
    {
        $dong = [];

        foreach ($thayDoi as $khoa => $giaTri) {
            if ($khoa !== 'min_age' && array_key_exists($khoa, self::MAC_DINH) && self::hopLe($khoa, $giaTri)) {
                $dong[] = ['name' => 'ai.'.$khoa, 'value' => json_encode($giaTri), 'is_secret' => 0, 'updated_at' => now()->getTimestamp(), 'updated_by' => $nguoiSua];
            }
        }

        if ($dong !== []) {
            DB::table('settings')->upsert($dong, ['name'], ['value', 'updated_at', 'updated_by']);
        }
    }
}
