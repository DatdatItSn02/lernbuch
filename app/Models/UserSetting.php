<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cài đặt học và giao diện của một người, đồng bộ giữa các máy (bảng user_settings, khoá là user_id).
 * Giao diện (sáng/tối, cỡ chữ, mùa) và ngôn ngữ nghĩa nằm trong cột prefs (JSON).
 *
 * @property int $user_id
 * @property int|null $current_course_id
 * @property int $new_per_session
 * @property int $daily_goal
 * @property array<string, mixed>|null $prefs
 */
class UserSetting extends Model
{
    public const CREATED_AT = null;

    /** Giá trị khi người dùng chưa đổi gì (app học dùng cùng giá trị). 0 từ mới mỗi lượt = tất cả. */
    public const MAC_DINH = [
        'new_per_session' => 10,
        'daily_goal' => 20,
        'theme' => 'light',
        'font_size' => 'md',
        'season' => 'auto',
        'meaning_langs' => ['vi', 'en', 'zh'],
    ];

    /** Các khoá lưu trong cột prefs; còn lại là cột riêng. */
    public const TRONG_PREFS = ['theme', 'font_size', 'season', 'meaning_langs'];

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $dateFormat = 'U';

    protected $fillable = ['user_id', 'current_course_id', 'new_per_session', 'daily_goal', 'prefs'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'prefs' => 'array',
            'reminder_channels' => 'array',
            'new_per_session' => 'integer',
            'daily_goal' => 'integer',
            'current_course_id' => 'integer',
        ];
    }

    /**
     * Cài đặt đầy đủ của một người: giá trị đã lưu, thiếu thì lấy mặc định.
     *
     * @return array{new_per_session: int, daily_goal: int, theme: string, font_size: string, season: string, meaning_langs: list<string>}
     */
    public static function cua(User $user): array
    {
        $dong = self::query()->find($user->id);
        $prefs = $dong !== null ? ($dong->prefs ?? []) : [];
        $kq = self::MAC_DINH;

        if ($dong !== null) {
            $kq['new_per_session'] = $dong->new_per_session;
            $kq['daily_goal'] = $dong->daily_goal;
        }

        foreach (self::TRONG_PREFS as $khoa) {
            if (array_key_exists($khoa, $prefs)) {
                $kq[$khoa] = $prefs[$khoa];
            }
        }

        /** @var array{new_per_session: int, daily_goal: int, theme: string, font_size: string, season: string, meaning_langs: list<string>} $kq */
        return $kq;
    }

    /**
     * Lưu những khoá có trong $thayDoi (đã kiểm hợp lệ), giữ nguyên phần còn lại.
     *
     * @param  array<string, mixed>  $thayDoi
     */
    public static function luu(User $user, array $thayDoi): void
    {
        $dong = self::query()->find($user->id) ?? new self([
            'user_id' => $user->id,
            'new_per_session' => self::MAC_DINH['new_per_session'],
            'daily_goal' => self::MAC_DINH['daily_goal'],
        ]);
        $prefs = $dong->prefs ?? [];

        foreach ($thayDoi as $khoa => $giaTri) {
            if (in_array($khoa, self::TRONG_PREFS, true)) {
                $prefs[$khoa] = $giaTri;
            } elseif (in_array($khoa, ['new_per_session', 'daily_goal'], true)) {
                $dong->{$khoa} = $giaTri;
            }
        }

        $dong->prefs = $prefs;
        $dong->save();
    }
}
