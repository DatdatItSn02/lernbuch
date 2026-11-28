<?php

namespace App\Support\LuyenTap;

use App\Models\User;
use App\Support\NoiDung;
use Illuminate\Database\Query\JoinClause;

/** Dạng luyện Viết câu: chọn thẻ từ vựng để người học tự đặt câu (AI chấm ở POST ai/viet-cau). Ưu tiên thẻ đã học. */
final class VietCau
{
    public const N_TOI_THIEU = 3;

    public const N_TOI_DA = 10;

    /**
     * @param  list<int>  $cap
     * @return list<array<string, mixed>>
     */
    public static function phien(User $user, array $cap, int $n): array
    {
        $n = max(self::N_TOI_THIEU, min(self::N_TOI_DA, $n));
        $q = NoiDung::locCap(NoiDung::cacThe($user)->where('cards.collection', 'vocab')
            ->leftJoin('card_progress as p', fn (JoinClause $j) => $j->on('p.card_id', '=', 'cards.id')->where('p.user_id', '=', $user->id)), $cap);
        $dong = $q->orderByRaw('p.card_id IS NULL')->orderByRaw('COALESCE(p.box, 0)')->inRandomOrder()->limit($n)->get(NoiDung::cotTomTat());

        return array_values(NoiDung::tomTat($dong, $user));
    }
}
