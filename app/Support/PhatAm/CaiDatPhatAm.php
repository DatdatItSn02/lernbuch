<?php

namespace App\Support\PhatAm;

use Illuminate\Support\Facades\DB;

/**
 * Cài đặt chấm phát âm sửa trong trang quản trị (bảng settings, tên "pron." + khoá, giá trị JSON). Thiếu dòng hoặc giá trị
 * sai thì lấy mặc định. Cách làm như App\Support\Ai\CaiDatAi.
 */
final class CaiDatPhatAm
{
    /** Khoá ngắn → giá trị mặc định. */
    public const MAC_DINH = [
        'provider' => 'azure',
        'max_seconds' => 15,
        'trial_daily_cap' => 20,
        // Gói F0 của Azure: 5 giờ âm thanh miễn phí mỗi tháng
        'monthly_free_seconds' => 18000,
        // Tới mức này thì tự chuyển sang bản dự phòng của trình duyệt (4,5 giờ)
        'switch_at_seconds' => 16200,
    ];

    public const CACH_CHAM = ['azure', 'browser', 'off'];

    /** Azure nhận tối đa 30 giây âm thanh cho một lượt chấm phát âm. */
    public const GIAY_TOI_DA = 30;

    /** @return array{provider: string, max_seconds: int, trial_daily_cap: int, monthly_free_seconds: int, switch_at_seconds: int} */
    public static function tatCa(): array
    {
        $ra = self::MAC_DINH;

        foreach (DB::table('settings')->where('name', 'like', 'pron.%')->get(['name', 'value']) as $dong) {
            $khoa = substr((string) $dong->name, 5);
            $giaTri = json_decode((string) $dong->value, true);

            if (array_key_exists($khoa, $ra) && self::hopLe($khoa, $giaTri, $ra)) {
                $ra[$khoa] = $giaTri;
            }
        }

        // Ngưỡng chuyển không được vượt số giây miễn phí (hai ô sửa riêng nên có thể lệch nhau)
        $ra['switch_at_seconds'] = min($ra['switch_at_seconds'], $ra['monthly_free_seconds']);

        /** @var array{provider: string, max_seconds: int, trial_daily_cap: int, monthly_free_seconds: int, switch_at_seconds: int} $ra */
        return $ra;
    }

    /** @param array<string, mixed> $cungLuc các giá trị khác lưu cùng lúc (để kiểm ngưỡng chuyển với số giây miễn phí) */
    public static function hopLe(string $khoa, mixed $giaTri, array $cungLuc = []): bool
    {
        $nguyen = fn (mixed $v, int $tu, int $den) => is_int($v) && $v >= $tu && $v <= $den;

        return match ($khoa) {
            'provider' => in_array($giaTri, self::CACH_CHAM, true),
            'max_seconds' => $nguyen($giaTri, 5, self::GIAY_TOI_DA),
            'trial_daily_cap' => $nguyen($giaTri, 0, 200),
            'monthly_free_seconds' => $nguyen($giaTri, 0, 360000),
            'switch_at_seconds' => $nguyen($giaTri, 0, (int) ($cungLuc['monthly_free_seconds'] ?? 360000)),
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
        $cungLuc = [...self::tatCa(), ...$thayDoi];
        $dong = [];

        foreach ($thayDoi as $khoa => $giaTri) {
            if (array_key_exists($khoa, self::MAC_DINH) && self::hopLe($khoa, $giaTri, $cungLuc)) {
                $dong[] = ['name' => 'pron.'.$khoa, 'value' => json_encode($giaTri), 'is_secret' => 0, 'updated_at' => now()->getTimestamp(), 'updated_by' => $nguoiSua];
            }
        }

        if ($dong !== []) {
            DB::table('settings')->upsert($dong, ['name'], ['value', 'updated_at', 'updated_by']);
        }
    }
}
