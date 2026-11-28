<?php

namespace App\Support\Ai;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Bài học AI: người học chọn chủ đề, trình độ, tối đa 10 từ; AI soạn bài đọc, từ mới, câu hỏi, bài tập. Nội dung nằm ở
 * ai_cache (mục lesson không hết hạn, dùng chung khi cùng đầu vào); mỗi người một dòng user_ai_lessons trỏ tới đó.
 */
final class BaiHocAi
{
    public const CAP = ['A1', 'A2', 'B1', 'B2'];

    public const TOI_DA_TU = 10;

    public const DANH_SACH = 30;

    /**
     * @param  list<string>  $tu
     * @return array<string, mixed> data (nội dung bài), meta, lesson (dòng đã lưu)
     */
    public static function soan(User $user, string $chuDe, string $cap, array $tu): array
    {
        $kq = HoiAi::hoiKem($user, 'lesson', [
            'level' => $cap,
            'topic' => $chuDe,
            'words' => $tu !== [] ? implode(', ', $tu) : '(tự chọn từ phù hợp)',
        ], $chuDe.($tu !== [] ? ' · '.implode(', ', $tu) : ''));

        $hash = (string) $kq['hash'];
        $bay = now()->getTimestamp();
        DB::table('user_ai_lessons')->insertOrIgnore([
            'user_id' => $user->id,
            'cache_hash' => $hash,
            'level' => $cap,
            'title' => mb_substr((string) $kq['data']['title'], 0, 160),
            'created_at' => $bay,
        ]);
        /** @var stdClass $dong */
        $dong = DB::table('user_ai_lessons')->where('user_id', $user->id)->where('cache_hash', $hash)->first();

        return ['data' => $kq['data'], 'meta' => $kq['meta'], 'lesson' => self::baiApi($dong)];
    }

    /** @return list<array<string, mixed>> */
    public static function danhSach(User $user): array
    {
        return DB::table('user_ai_lessons')->where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::DANH_SACH)->get()->map(fn (stdClass $b) => self::baiApi($b))->values()->all();
    }

    /** @return array{data: array<string, mixed>, lesson: array<string, mixed>} */
    public static function xem(User $user, int $id): array
    {
        $b = self::bai($user, $id);
        $noiDung = BoNhoAi::theoHash((string) $b->cache_hash) ?? abort(404);

        return ['data' => $noiDung, 'lesson' => self::baiApi($b)];
    }

    /** @return array<string, mixed> */
    public static function ghiDiem(User $user, int $id, int $diem): array
    {
        $b = self::bai($user, $id);
        DB::table('user_ai_lessons')->where('id', $b->id)->update(['score' => $diem, 'completed_at' => now()->getTimestamp()]);

        return self::baiApi(self::bai($user, $id));
    }

    public static function xoa(User $user, int $id): void
    {
        DB::table('user_ai_lessons')->where('id', self::bai($user, $id)->id)->delete();
    }

    private static function bai(User $user, int $id): stdClass
    {
        return DB::table('user_ai_lessons')->where('id', $id)->where('user_id', $user->id)->first() ?? abort(404);
    }

    /** @return array<string, mixed> */
    private static function baiApi(stdClass $b): array
    {
        return [
            'id' => (int) $b->id,
            'title' => (string) $b->title,
            'level' => $b->level !== null ? (string) $b->level : null,
            'score' => $b->score !== null ? (int) $b->score : null,
            'completed_at' => $b->completed_at !== null ? (int) $b->completed_at : null,
            'created_at' => (int) $b->created_at,
        ];
    }
}
