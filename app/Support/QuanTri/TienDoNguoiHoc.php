<?php

namespace App\Support\QuanTri;

use App\Models\User;
use App\Support\Hoc\HomNay;
use App\Support\KhoaHoc;
use Illuminate\Support\Facades\DB;
use WeakMap;

/**
 * Tiến độ của một người học cho trang quản trị: cùng số liệu người đó thấy ở trang Hôm nay (thẻ theo mức, đến hạn,
 * đã ôn hôm nay, chuỗi ngày, xu), thêm phiên học, bài tập, máy đang đăng nhập và mục tiêu khoá học. Chỉ đọc.
 */
final class TienDoNguoiHoc
{
    /** Tên 6 mức thẻ (trang quản trị chỉ có tiếng Việt). */
    public const TEN_MUC = ['Mới', 'Đang học', 'Quen', 'Khá', 'Thuộc', 'Thành thạo'];

    public const MUC_TIEU = [
        'study_abroad' => 'Du học',
        'vocational' => 'Học nghề',
        'work' => 'Đi làm',
        'exam' => 'Thi chứng chỉ',
        'travel' => 'Du lịch',
        'other' => 'Khác',
    ];

    public const TRINH_DO = [1 => 'A1', 2 => 'A2', 3 => 'B1', 4 => 'B2', 5 => 'C1', 6 => 'C2'];

    /** @var WeakMap<User, array<string, mixed>>|null tính một lần cho mỗi lần vẽ trang (mỗi ô của trang đọc chung) */
    private static ?WeakMap $daTinh = null;

    /**
     * @return array{levels: list<int>, da_hoc: int, due: int, reviewed_today: int, goal: int, streak: int, streak_longest: int,
     *     coins: int, coins_today: int, dang_hoc: string|null, phien: int, phien_cuoi: int|null, bai_tap: int, may: int,
     *     muc_tieu: string|null, trinh_do: string|null, ngay: list<array{date: string, count: int}>}
     */
    public static function cua(User $user): array
    {
        self::$daTinh ??= new WeakMap;

        /** @var array{levels: list<int>, da_hoc: int, due: int, reviewed_today: int, goal: int, streak: int, streak_longest: int, coins: int, coins_today: int, dang_hoc: string|null, phien: int, phien_cuoi: int|null, bai_tap: int, may: int, muc_tieu: string|null, trinh_do: string|null, ngay: list<array{date: string, count: int}>} $ra */
        $ra = self::$daTinh[$user] ??= self::tinh($user);

        return $ra;
    }

    /** @return array<string, mixed> */
    private static function tinh(User $user): array
    {
        $h = HomNay::cua($user);
        /** @var list<int> $levels */
        $levels = $h['levels'];
        $khoa = KhoaHoc::hienTai($user);
        $dangKy = $khoa !== null ? DB::table('user_courses')->where('user_id', $user->id)->where('course_id', $khoa->id)->first() : null;
        /** @var list<array{date: string, count: int, kind: string}> $ngay */
        $ngay = $h['days'];
        $phienCuoi = DB::table('study_sessions')->where('user_id', $user->id)->max('started_at');

        return [
            'levels' => $levels,
            'da_hoc' => array_sum(array_slice($levels, 1)),
            'due' => (int) $h['due'],
            'reviewed_today' => (int) $h['reviewed_today'],
            'goal' => (int) $h['goal'],
            'streak' => (int) $h['streak'],
            'streak_longest' => (int) $h['streak_longest'],
            'coins' => (int) $h['coins'],
            'coins_today' => (int) $h['coins_today'],
            'dang_hoc' => is_array($h['new_topic']) ? (string) $h['new_topic']['name'] : null,
            'phien' => DB::table('study_sessions')->where('user_id', $user->id)->count(),
            'phien_cuoi' => $phienCuoi !== null ? (int) $phienCuoi : null,
            'bai_tap' => DB::table('exercise_runs')->where('user_id', $user->id)->count(),
            'may' => DB::table('sessions')->where('user_id', $user->id)->count(),
            'muc_tieu' => $dangKy?->goal !== null ? (self::MUC_TIEU[(string) $dangKy->goal] ?? (string) $dangKy->goal) : null,
            'trinh_do' => $dangKy?->level_goal !== null ? (self::TRINH_DO[(int) $dangKy->level_goal] ?? null) : null,
            'ngay' => array_values(array_map(
                fn (array $d) => ['date' => $d['date'], 'count' => $d['count']],
                array_filter($ngay, fn (array $d) => $d['kind'] !== 'future'),
            )),
        ];
    }

    /**
     * "Mới 11.872 · Đang học 2 · Quen 8 · …" (bỏ mức bằng 0 trừ Mới).
     *
     * @param  list<int>  $levels
     */
    public static function theoMuc(array $levels): string
    {
        $phan = [];

        foreach ($levels as $muc => $n) {
            if ($muc === 0 || $n > 0) {
                $phan[] = (self::TEN_MUC[$muc] ?? (string) $muc).' '.number_format((int) $n, 0, ',', '.');
            }
        }

        return implode(' · ', $phan);
    }
}
