<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Tra từ: gõ tiếng Đức, Việt, Anh hay Trung, có dấu hay không dấu đều ra. Thứ tự kết quả:
 * đúng dạng từ đã gõ (zahlen ≠ zählen) → đúng dạng khác (số nhiều, dạng chia) → mặt trước trùng khi bỏ dấu →
 * nghĩa trùng → mặt trước bắt đầu bằng chữ gõ → có chứa chữ gõ. Cùng hạng thì Từ vựng trước, chữ ngắn trước.
 */
final class TraTu
{
    public const KET_QUA_TOI_DA = 30;

    /** Kiểu đuôi động từ thường gặp → đuôi nguyên mẫu, để bấm "wohnst" trong câu ra "wohnen" (như Lernbuch). */
    private const DUOI_DONG_TU = [['en', ''], ['e', 'en'], ['st', 'en'], ['t', 'en'], ['te', 'en'], ['n', ''], ['ten', 'en'], ['test', 'en']];

    /** @return array{cards: list<array<string, mixed>>, total: int} */
    public static function tim(User $user, string $chu, int $toiDa = self::KET_QUA_TOI_DA): array
    {
        $thuong = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $chu)));
        $cacChu = NoiDung::cacChu($thuong);

        // Một chữ cái Latin thì ra quá nhiều; một chữ Hán thì đã là một từ
        if ($cacChu === [] || (mb_strlen($thuong) < 2 && preg_match('/\p{Han}/u', $thuong) !== 1)) {
            return ['cards' => [], 'total' => 0];
        }

        $dang = self::dangTrungKhop($thuong);
        $q = NoiDung::cacThe($user)->where(function (Builder $w) use ($cacChu, $dang): void {
            $w->where(function (Builder $w) use ($cacChu): void {
                foreach ($cacChu as $tu) {
                    $w->where('cards.search_text', 'like', '%'.NoiDung::choLike($tu).'%');
                }
            });

            if ($dang['ids'] !== []) {
                $w->orWhereIn('cards.id', $dang['ids']);
            }
        });

        $tong = (clone $q)->count();
        [$hang, $thamSo] = self::hang($thuong, $dang);
        $dong = $q->select(NoiDung::cotTomTat())->selectRaw("{$hang} AS hang", $thamSo)
            ->orderBy('hang')->orderByRaw("CASE WHEN cards.collection = 'vocab' THEN 0 ELSE 1 END")->orderByRaw('CHAR_LENGTH(cards.front)')->orderBy('cards.id')
            ->limit($toiDa)->get();

        return ['cards' => self::kemHang(NoiDung::tomTat($dong, $user), $dong), 'total' => $tong];
    }

    /**
     * Bấm một chữ trong câu: tìm đúng dạng từ (số nhiều, dạng chia); không có thì thử đưa động từ về nguyên mẫu.
     *
     * @return list<array<string, mixed>> tối đa 3 thẻ
     */
    public static function traChu(User $user, string $chu): array
    {
        $tu = mb_strtolower((string) preg_replace('/[^\p{L}\p{M}\-]/u', '', $chu));

        if ($tu === '' || mb_strlen($tu) > 120) {
            return [];
        }

        $khop = self::dangTu([$tu]);

        if ($khop === []) {
            foreach (self::DUOI_DONG_TU as [$cat, $them]) {
                if (str_ends_with($tu, $cat) && mb_strlen($tu) - mb_strlen($cat) >= 3) {
                    $goc = mb_substr($tu, 0, mb_strlen($tu) - mb_strlen($cat)).$them;
                    $khop = array_filter(self::dangTu([$goc]), fn ($kieu) => $kieu === 'lemma');

                    if ($khop !== []) {
                        break;
                    }
                }
            }
        }

        if ($khop === []) {
            return [];
        }

        $dong = NoiDung::cacThe($user)->whereIn('cards.id', array_keys($khop))->get(NoiDung::cotTomTat())
            ->sortBy([
                fn ($a, $b) => ($khop[(int) $a->id] === 'lemma' ? 0 : 1) <=> ($khop[(int) $b->id] === 'lemma' ? 0 : 1),
                fn ($a, $b) => ($a->collection === 'vocab' ? 0 : 1) <=> ($b->collection === 'vocab' ? 0 : 1),
                fn ($a, $b) => (int) $a->id <=> (int) $b->id,
            ])->take(3)->values();

        return NoiDung::tomTat($dong, $user);
    }

    /**
     * Thẻ có dạng từ đúng như chữ đã gõ (so khớp từng ký tự, phân biệt ä với a); thử cả khi bỏ mạo từ hay "sich" ở đầu.
     *
     * @return array{ids: list<int>, lemma: list<int>, khac: list<int>}
     */
    private static function dangTrungKhop(string $thuong): array
    {
        $tim = [NoiDung::catDauCau($thuong)];

        if (preg_match('/^(der|die|das|sich)\s+(.+)$/u', $tim[0], $m) === 1) {
            $tim[] = $m[2];
        }

        $khop = self::dangTu($tim);
        $lemma = array_keys(array_filter($khop, fn ($k) => $k === 'lemma'));
        $khac = array_keys(array_filter($khop, fn ($k) => $k !== 'lemma'));

        return ['ids' => [...$lemma, ...$khac], 'lemma' => $lemma, 'khac' => $khac];
    }

    /**
     * @param  list<string>  $dang
     * @return array<int, string> id thẻ → kiểu dạng tốt nhất (lemma trước)
     */
    private static function dangTu(array $dang): array
    {
        $dang = array_values(array_filter($dang, fn ($d) => $d !== '' && mb_strlen($d) <= 120));

        if ($dang === []) {
            return [];
        }

        $ra = [];
        foreach (DB::table('word_forms')->where('lang', NoiDung::LANG)->whereIn('form', $dang)->get(['card_id', 'kind']) as $d) {
            $id = (int) $d->card_id;
            $ra[$id] = ($ra[$id] ?? null) === 'lemma' ? 'lemma' : (string) $d->kind;
        }

        return $ra;
    }

    /**
     * Biểu thức SQL tính hạng kết quả (số nhỏ đứng trước) và tham số của nó.
     *
     * @param  array{ids: list<int>, lemma: list<int>, khac: list<int>}  $dang
     * @return array{string, list<int|string>}
     */
    private static function hang(string $thuong, array $dang): array
    {
        $khi = [];
        $thamSo = [];

        foreach (['lemma' => 0, 'khac' => 1] as $k => $so) {
            if ($dang[$k] !== []) {
                $khi[] = 'WHEN cards.id IN ('.implode(', ', array_fill(0, count($dang[$k]), '?')).") THEN {$so}";
                array_push($thamSo, ...$dang[$k]);
            }
        }

        $like = NoiDung::choLike($thuong);
        // Collation utf8mb4_unicode_ci không phân biệt hoa thường và dấu: "zahlen" bằng "zählen", "banh mi" bằng "bánh mì"
        $khi[] = 'WHEN cards.front = ? OR cards.front = ? OR cards.front = ? OR cards.front = ? THEN 2';
        array_push($thamSo, $thuong, "der {$thuong}", "die {$thuong}", "das {$thuong}");
        $khi[] = 'WHEN EXISTS (SELECT 1 FROM card_translations ct WHERE ct.card_id = cards.id AND (ct.meaning = ? OR ct.meaning = ? '
            .'OR ct.meaning LIKE ? OR ct.meaning LIKE ? OR ct.meaning LIKE ? OR ct.meaning LIKE ?)) THEN 3';
        array_push($thamSo, $thuong, "to {$thuong}", "{$like},%", "{$like} (%", "{$like};%", "to {$like},%");
        $khi[] = 'WHEN cards.front LIKE ? OR cards.front LIKE ? OR cards.front LIKE ? OR cards.front LIKE ? THEN 4';
        array_push($thamSo, "{$like}%", "der {$like}%", "die {$like}%", "das {$like}%");

        return ['CASE '.implode(' ', $khi).' ELSE 5 END', $thamSo];
    }

    /**
     * Gắn hạng khớp vào kết quả: 'exact' (đúng dạng từ), 'form' (dạng khác), 'close' (gần đúng), 'contains'.
     *
     * @param  list<array<string, mixed>>  $the
     * @param  iterable<stdClass>  $dong
     * @return list<array<string, mixed>>
     */
    private static function kemHang(array $the, iterable $dong): array
    {
        $hang = [];
        foreach ($dong as $d) {
            $hang[(int) $d->id] = (int) $d->hang;
        }

        return array_map(fn (array $t) => [...$t, 'match' => match ($hang[$t['id']] ?? 5) {
            0 => 'exact',
            1 => 'form',
            2, 3 => 'close',
            default => 'contains',
        }], $the);
    }
}
