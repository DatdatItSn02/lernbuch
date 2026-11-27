<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

/**
 * Đọc nội dung học (thẻ, chủ đề, loại thẻ) cho API. Chỉ nội dung đã xuất bản; thẻ riêng của người dùng (G5) chỉ chủ của nó thấy.
 * Tên theo ngôn ngữ giao diện của người học, thiếu bản dịch thì dùng tiếng Việt.
 */
final class NoiDung
{
    /** Ngôn ngữ đang học (G1 có một khoá học: tiếng Đức). */
    public const LANG = 'de';

    public const MOI_TRANG = 20;

    /** Mã cấp 1–6 = A1–C2; 0 = chưa xếp cấp. */
    public const CAP = [1 => 'A1', 2 => 'A2', 3 => 'B1', 4 => 'B2', 5 => 'C1', 6 => 'C2'];

    public const MAO_TU = ['der', 'die', 'das'];

    /** Thẻ người này được xem: thẻ hệ thống đã xuất bản, và thẻ riêng của chính họ. */
    public static function cacThe(User $user): Builder
    {
        return DB::table('cards')->where('cards.lang', self::LANG)->where('cards.status', 'published')->whereNull('cards.deleted_at')
            ->where(fn (Builder $q) => $q->whereNull('cards.owner_user_id')->orWhere('cards.owner_user_id', $user->id));
    }

    /** Thứ tự thẻ trong một chủ đề, như Lernbuch: có cấp trước, cấp thấp trước, rồi theo thứ tự nhập. */
    public static function xepThe(Builder $q): Builder
    {
        return $q->orderByRaw('cards.level IS NULL')->orderBy('cards.level_min')->orderBy('cards.id');
    }

    public static function cacChuDe(): Builder
    {
        return DB::table('topics')->where('topics.lang', self::LANG)->where('topics.status', 'published')->whereNull('topics.deleted_at');
    }

    /**
     * Lọc theo cấp: thẻ có cấp mà khoảng cấp chứa một trong các cấp chọn; 0 là thẻ chưa xếp cấp.
     *
     * @param  list<int>  $cap
     */
    public static function locCap(Builder $q, array $cap): Builder
    {
        if ($cap === []) {
            return $q;
        }

        return $q->where(function (Builder $q) use ($cap): void {
            foreach ($cap as $c) {
                $c === 0
                    ? $q->orWhereNull('cards.level')
                    : $q->orWhere(fn (Builder $q) => $q->whereNotNull('cards.level')->where('cards.level_min', '<=', $c)->where('cards.level_max', '>=', $c));
            }
        });
    }

    /**
     * Tìm trong chủ đề hay sổ tay: mọi chữ gõ vào đều phải có trong search_text (đã có bản bỏ dấu); so khớp không phân biệt hoa thường, dấu.
     */
    public static function locChu(Builder $q, ?string $chu): Builder
    {
        foreach (self::cacChu($chu) as $tu) {
            $q->where('cards.search_text', 'like', '%'.self::choLike($tu).'%');
        }

        return $q;
    }

    /**
     * Chữ để tìm của một thẻ (cột search_text): mặt trước, dạng từ và nghĩa, viết thường; thêm bản bỏ dấu
     * (gõ "banh mi", "brotchen") và bản viết ä, ö, ü, ß thành ae, oe, ue, ss ("broetchen").
     *
     * @param  array<string, string>  $nghia
     */
    public static function chuTim(string $front, ?string $forms, array $nghia): string
    {
        $chuDuc = mb_strtolower(implode(' ', array_filter([$front, $forms !== '–' ? $forms : null], fn ($v) => $v !== null && $v !== '')));
        $chu = mb_strtolower(implode(' ', array_filter([$chuDuc, ...array_values($nghia)], fn ($v) => $v !== '')));
        $boDau = [mb_strtolower(Str::ascii($chu))];

        if (preg_match('/[äöüß]/u', $chuDuc) === 1) {
            $boDau[] = mb_strtolower(Str::ascii($chuDuc, 'de'));
        }

        $ds = array_values(array_unique(array_filter([$chu, ...$boDau], fn ($v) => trim($v) !== '')));

        return Str::limit(trim(implode(' ', array_map('trim', $ds))), 1500, '');
    }

    /** @return list<string> tối đa 6 chữ, viết thường */
    public static function cacChu(?string $chu): array
    {
        $ds = preg_split('/\s+/u', mb_strtolower(trim((string) $chu)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique($ds)), 0, 6);
    }

    /** Bỏ khoảng trắng và dấu câu ở hai đầu ("Brötchen," → "Brötchen"), theo ký tự Unicode chứ không theo byte. */
    public static function catDauCau(string $chu): string
    {
        return (string) preg_replace('/^[\s.,;:!?…"\'«»„“”]+|[\s.,;:!?…"\'«»„“”]+$/u', '', $chu);
    }

    /** Thoát % _ \ để chữ người dùng gõ không thành ký tự đại diện của LIKE. */
    public static function choLike(string $chu): string
    {
        return addcslashes($chu, '%_\\');
    }

    /** Đếm thẻ theo cấp (1–6 và 0 = chưa xếp); thẻ có khoảng cấp được đếm ở mọi cấp trong khoảng. @return list<array{level: int, count: int}> */
    public static function demTheoCap(Builder $q): array
    {
        $cot = ['SUM(CASE WHEN cards.level IS NULL THEN 1 ELSE 0 END) AS c0'];

        foreach (array_keys(self::CAP) as $c) {
            $cot[] = "SUM(CASE WHEN cards.level IS NOT NULL AND cards.level_min <= {$c} AND cards.level_max >= {$c} THEN 1 ELSE 0 END) AS c{$c}";
        }

        $dem = (array) (clone $q)->selectRaw(implode(', ', $cot))->first();
        $ra = [];

        foreach ([...array_keys(self::CAP), 0] as $c) {
            if ((int) ($dem["c{$c}"] ?? 0) > 0) {
                $ra[] = ['level' => $c, 'count' => (int) $dem["c{$c}"]];
            }
        }

        return $ra;
    }

    /** @return list<array{pos: string, count: int}> nhiều thẻ trước */
    public static function demTheoLoaiTu(Builder $q): array
    {
        return (clone $q)->whereNotNull('cards.pos')->selectRaw('cards.pos AS pos, COUNT(*) AS n')->groupBy('cards.pos')->orderByDesc('n')->orderBy('pos')->get()
            ->map(fn ($d) => ['pos' => (string) $d->pos, 'count' => (int) $d->n])->values()->all();
    }

    public static function tenTheoNgonNgu(?string $vi, ?string $en): string
    {
        return App::getLocale() === 'en' && filled($en) ? (string) $en : (string) $vi;
    }

    /** @return array{code: string, name: string} */
    public static function boThe(string $ma): array
    {
        $ten = DB::table('card_collections')->where('code', $ma)->first(['name_vi', 'name_en']);

        return ['code' => $ma, 'name' => $ten !== null ? self::tenTheoNgonNgu($ten->name_vi, $ten->name_en) : $ma];
    }

    /** @return array{slug: string, name: string} */
    public static function tenGon(stdClass $t): array
    {
        return ['slug' => (string) $t->slug, 'name' => self::tenTheoNgonNgu($t->name_vi, $t->name_en)];
    }

    /** "A1", "A1–B2"; null khi chưa xếp cấp. */
    public static function chuCap(stdClass $t): ?string
    {
        if ($t->level === null) {
            return null;
        }

        $thap = self::CAP[(int) $t->level_min] ?? self::CAP[(int) $t->level] ?? null;
        $cao = self::CAP[(int) $t->level_max] ?? null;

        return $thap === null ? null : ($cao === null || $cao === $thap ? $thap : "{$thap}–{$cao}");
    }

    /** Mặt trước bỏ mạo từ đứng đầu (để tô màu mạo từ riêng): "der Gruß" → "Gruß". Không có mạo từ thì giữ nguyên. */
    public static function boMaoTu(string $front, ?string $article): string
    {
        return $article !== null && str_starts_with($front, $article.' ') ? mb_substr($front, mb_strlen($article) + 1) : $front;
    }

    /**
     * Tóm tắt thẻ cho danh sách: mặt trước, mạo từ, loại từ, dạng, cấp, hình, nghĩa các thứ tiếng, đã đánh dấu sao chưa.
     *
     * @param  iterable<stdClass>  $the
     * @return list<array<string, mixed>>
     */
    public static function tomTat(iterable $the, User $user): array
    {
        $ds = [];

        foreach ($the as $t) {
            $ds[(int) $t->id] = $t;
        }

        if ($ds === []) {
            return [];
        }

        $ids = array_keys($ds);
        $nghia = self::nghia($ids);
        $sao = DB::table('user_card_notes')->where('user_id', $user->id)->whereIn('card_id', $ids)->where('is_starred', 1)->pluck('card_id')
            ->map(fn ($v) => (int) $v)->flip()->all();
        $ra = [];

        foreach ($ds as $id => $t) {
            $ra[] = [
                'id' => $id,
                'collection' => (string) $t->collection,
                ...self::matTruoc($t),
                'pos' => $t->pos !== null ? (string) $t->pos : null,
                'forms' => $t->forms !== null ? (string) $t->forms : null,
                'level' => self::chuCap($t),
                'image' => $t->image !== null ? (string) $t->image : null,
                'meanings' => (object) ($nghia[$id] ?? []),
                'starred' => isset($sao[$id]),
            ];
        }

        return $ra;
    }

    /**
     * Mặt trước tách mạo từ để app tô màu. Mạo từ chỉ đưa ra khi nó đứng đầu mặt trước ("der Gruß");
     * câu như "Das ist nett." không có mạo từ.
     *
     * @return array{front: string, article: string|null, word: string, gender: string|null}
     */
    public static function matTruoc(stdClass $t): array
    {
        $article = in_array($t->article, self::MAO_TU, true) ? (string) $t->article : null;
        $front = (string) $t->front;
        $coMaoTu = $article !== null && str_starts_with($front, $article.' ');

        return [
            'front' => $front,
            'article' => $coMaoTu ? $article : null,
            'word' => $coMaoTu ? self::boMaoTu($front, $article) : $front,
            'gender' => $coMaoTu && $t->gender !== null ? (string) $t->gender : null,
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, string>> id thẻ → {vi, en, zh}
     */
    public static function nghia(array $ids): array
    {
        $ra = [];

        if ($ids === []) {
            return $ra;
        }

        foreach (DB::table('card_translations')->whereIn('card_id', $ids)->orderBy('lang')->get(['card_id', 'lang', 'meaning']) as $d) {
            $ra[(int) $d->card_id][(string) $d->lang] = (string) $d->meaning;
        }

        return $ra;
    }

    /** Cột cần cho tóm tắt thẻ. @return list<string> */
    public static function cotTomTat(): array
    {
        return ['cards.id', 'cards.collection', 'cards.front', 'cards.article', 'cards.gender', 'cards.pos', 'cards.forms', 'cards.level', 'cards.level_min', 'cards.level_max', 'cards.image'];
    }

    /**
     * Đọc ?cap=1,2,0 thành danh sách cấp hợp lệ; sai dạng thì báo lỗi 422 ở ô cap.
     *
     * @return list<int>
     */
    public static function docCap(?string $chu): array
    {
        if ($chu === null || trim($chu) === '') {
            return [];
        }

        $ds = [];

        foreach (explode(',', $chu) as $c) {
            $c = trim($c);

            if (! ctype_digit($c) || (int) $c > 6) {
                throw ValidationException::withMessages(['cap' => __('validation.in', ['attribute' => 'cap'])]);
            }

            $ds[(int) $c] = (int) $c;
        }

        return array_values($ds);
    }
}
