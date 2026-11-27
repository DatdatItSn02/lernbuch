<?php

namespace App\Support\LuyenTap;

use App\Models\User;
use App\Support\NoiDung;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Điền câu (Lernbuch /cloze): câu có chỗ trống làm từ thẻ Từ vựng, điền đúng từ. Kết quả là lượt chấm thẻ (chế độ cloze,
 * gửi qua hàng chờ như các chế độ học): Tốt khi đúng, Khó khi chỉ sai hoa thường (danh từ), Quên khi sai.
 * Ưu tiên thẻ người học đã gặp mà mức còn thấp; thiếu mới lấy thẻ chưa học, để không trộn từ mới của chủ đề khác vào.
 */
final class DienCau
{
    public const N_TOI_THIEU = 3;

    public const N_TOI_DA = 50;

    /**
     * @param  list<int>  $cap
     * @return list<array<string, mixed>>
     */
    public static function phien(User $user, array $cap, int $n): array
    {
        $n = max(self::N_TOI_THIEU, min(self::N_TOI_DA, $n));
        $q = NoiDung::locCap(NoiDung::cacThe($user)->where('cards.collection', 'vocab')
            ->whereNotNull('cards.cloze')->where('cards.cloze', '<>', '')->whereNotNull('cards.cloze_answer')->where('cards.cloze_answer', '<>', '')
            ->leftJoin('card_progress as p', fn (JoinClause $j) => $j->on('p.card_id', '=', 'cards.id')->where('p.user_id', '=', $user->id)), $cap);

        $dong = $q->orderByRaw('p.card_id IS NULL')->orderByRaw('COALESCE(p.box, 0)')->inRandomOrder()->limit($n)
            ->get([...NoiDung::cotTomTat(), 'cards.topic_id', 'cards.cloze', 'cards.cloze_answer', 'p.box'])->keyBy('id');

        if ($dong->isEmpty()) {
            return [];
        }

        $ids = $dong->keys()->map(fn ($v) => (int) $v)->all();
        $tomTat = collect(NoiDung::tomTat($dong->values(), $user))->keyBy('id');
        $chuDe = NoiDung::cacChuDe()->whereIn('id', $dong->pluck('topic_id')->filter()->unique()->values()->all())->get()->keyBy('id');
        $viDu = DB::table('card_examples')->whereIn('card_id', $ids)->orderBy('sort')->get(['card_id', 'sentence', 'translations'])->groupBy('card_id');
        $ra = [];

        foreach ($dong as $id => $t) {
            if (! isset($tomTat[$id])) {
                continue;
            }

            $dapAn = trim((string) $t->cloze_answer);
            // Câu đầy đủ (câu ví dụ làm ra chỗ trống) để hiện kèm bản dịch sau khi trả lời
            $day = self::gon((string) preg_replace('/_{2,}/u', $dapAn, (string) $t->cloze, 1));
            $cau = collect($viDu[$id] ?? [])->first(fn ($v) => self::gon((string) $v->sentence) === $day);
            $chu = $t->topic_id !== null ? ($chuDe[$t->topic_id] ?? null) : null;
            $ra[] = [
                ...$tomTat[$id],
                'cloze' => (string) $t->cloze,
                'answer' => $dapAn,
                // Danh từ viết hoa: đúng chữ mà sai hoa thường thì chỉ được Khó (Lernbuch: cs khi là danh từ)
                'case_sensitive' => $t->pos === 'noun',
                'sentence' => $cau !== null ? ['sentence' => (string) $cau->sentence, 'translations' => (object) (json_decode((string) $cau->translations, true) ?: [])] : null,
                'topic' => $chu !== null ? NoiDung::tenGon($chu) : null,
                'box' => $t->box !== null ? (int) $t->box : 0,
            ];
        }

        shuffle($ra);

        return $ra;
    }

    private static function gon(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }
}
