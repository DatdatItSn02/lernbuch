<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Khoá học người học đang học (user_settings.current_course_id, chưa chọn thì khoá đầu tiên đang mở) và mục tiêu, trình độ đã chọn. */
final class KhoaHoc
{
    public const MUC_TIEU = ['study_abroad', 'vocational', 'work', 'exam', 'travel', 'other'];

    public static function hienTai(User $user): ?stdClass
    {
        $id = DB::table('user_settings')->where('user_id', $user->id)->value('current_course_id');
        $moi = fn () => DB::table('courses')->whereNull('deleted_at')->where('status', 'published');

        $khoa = $id !== null ? $moi()->where('id', $id)->first() : null;

        return $khoa ?? $moi()->orderBy('sort')->orderBy('id')->first();
    }

    /**
     * Ngôn ngữ nghĩa người học được chọn: theo khoá học, chưa có khoá thì mọi ngôn ngữ dùng làm nghĩa.
     *
     * @return list<string>
     */
    public static function ngonNguNghia(?stdClass $khoa): array
    {
        if ($khoa !== null) {
            $ds = json_decode((string) $khoa->meaning_langs, true);

            if (is_array($ds)) {
                return array_values(array_filter($ds, 'is_string'));
            }
        }

        /** @var list<string> $ds */
        $ds = DB::table('languages')->whereNull('deleted_at')->where('is_meaning', 1)->orderBy('sort')->pluck('code')->all();

        return $ds;
    }

    /** @return array{slug: string, level_min: int, level_max: int, goal: string|null, level: int|null}|null */
    public static function choApi(User $user, ?stdClass $khoa): ?array
    {
        if ($khoa === null) {
            return null;
        }

        $dang = DB::table('user_courses')->where('user_id', $user->id)->where('course_id', $khoa->id)->first();
        $mucTieu = $dang !== null && $dang->goal !== null ? (string) $dang->goal : null;
        $trinhDo = $dang !== null && $dang->placement_level !== null ? (int) $dang->placement_level : null;

        return [
            'slug' => (string) $khoa->slug,
            'level_min' => (int) $khoa->level_min,
            'level_max' => (int) $khoa->level_max,
            'goal' => $mucTieu,
            'level' => $trinhDo,
        ];
    }

    /**
     * Ghi mục tiêu (goal) và trình độ tự chọn (placement_level) có trong $thayDoi, giữ phần còn lại;
     * lần đầu thì ghi danh vào khoá học.
     *
     * @param  array{goal?: string|null, placement_level?: int|null}  $thayDoi
     */
    public static function luuMucTieu(User $user, stdClass $khoa, array $thayDoi): void
    {
        $da = DB::table('user_courses')->where('user_id', $user->id)->where('course_id', $khoa->id);

        if ($da->exists()) {
            $da->update($thayDoi);

            return;
        }

        DB::table('user_courses')->insert($thayDoi + [
            'user_id' => $user->id,
            'course_id' => $khoa->id,
            'enrolled_at' => now()->getTimestamp(),
        ]);
    }
}
