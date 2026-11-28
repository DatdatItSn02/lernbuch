<?php

namespace App\Support\Ai;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Đồng ý gửi nội dung cho nhà cung cấp AI (bảng consents, kind ai_processing). Đồng ý hay rút đều thêm dòng mới, không sửa
 * dòng cũ. Đang đồng ý khi dòng mới nhất có granted = 1 và đúng phiên bản lời đồng ý hiện tại.
 * Đổi nhà cung cấp hoặc chuyển sang bậc trả tiền thì sửa lời đồng ý trong app và tăng PHIEN_BAN: mọi người phải đồng ý lại.
 */
final class DongYAi
{
    public const LOAI = 'ai_processing';

    public const PHIEN_BAN = '2026-11';

    public static function dangDongY(User $user): bool
    {
        $dong = DB::table('consents')->where('user_id', $user->id)->where('kind', self::LOAI)
            ->orderByDesc('created_at')->orderByDesc('id')->first();

        return $dong !== null && (int) $dong->granted === 1 && $dong->document_version === self::PHIEN_BAN;
    }

    public static function ghi(User $user, bool $dongY): void
    {
        $ip = request()->ip();

        DB::table('consents')->insert([
            'user_id' => $user->id,
            'kind' => self::LOAI,
            'document_version' => self::PHIEN_BAN,
            'granted' => $dongY ? 1 : 0,
            'source' => 'app',
            'ip' => $ip !== null ? (@inet_pton($ip) ?: null) : null,
            'created_at' => now()->getTimestamp(),
        ]);
    }
}
