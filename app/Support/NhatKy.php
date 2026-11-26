<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nhật ký thao tác của nhân viên (bảng audit_logs, chỉ thêm không sửa): ai, làm gì, trên dòng nào, trước và sau.
 * Chỉ ghi khi người đang đăng nhập là nhân viên; người học tự sửa hồ sơ của mình thì không ghi ở đây.
 */
final class NhatKy
{
    /** Cột không đưa vào phần "trước/sau" (bí mật hoặc tự đổi). */
    public const BO_QUA = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'created_at', 'updated_at', 'created_by', 'updated_by'];

    public static function nguoiLam(): ?User
    {
        $user = Auth::guard('web')->user();

        return $user instanceof User && $user->is_staff ? $user : null;
    }

    /**
     * @param  array<string, mixed>  $truoc
     * @param  array<string, mixed>  $sau
     */
    public static function ghi(string $viec, ?Model $doiTuong, array $truoc = [], array $sau = []): void
    {
        $nguoi = self::nguoiLam();

        if ($nguoi === null) {
            return;
        }

        $truoc = array_diff_key($truoc, array_flip(self::BO_QUA));
        $sau = array_diff_key($sau, array_flip(self::BO_QUA));
        $ip = request()->ip();

        DB::table('audit_logs')->insert([
            'actor_id' => $nguoi->id,
            'action' => Str::limit($viec, 60, ''),
            'subject_type' => $doiTuong !== null ? Str::limit($doiTuong->getMorphClass(), 60, '') : null,
            'subject_id' => $doiTuong?->getKey(),
            'changes' => $truoc === [] && $sau === [] ? null : json_encode(['before' => $truoc, 'after' => $sau], JSON_UNESCAPED_UNICODE),
            'ip' => $ip !== null ? (@inet_pton($ip) ?: null) : null,
            'user_agent' => Str::limit((string) request()->userAgent(), 255, ''),
            'created_at' => now()->getTimestamp(),
        ]);
    }

    /** Tên việc theo bảng: content_sources + update → content_source.update. */
    public static function viec(Model $doiTuong, string $kieu): string
    {
        return Str::singular($doiTuong->getTable()).'.'.$kieu;
    }
}
