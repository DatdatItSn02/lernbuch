<?php

namespace App\Support\LuyenTap;

use App\Support\NoiDung;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Ngữ pháp theo Lernbuch (/docs): bài theo cấp và chương (bài theo dàn ý), "Giáo trình gốc" (bài không thuộc chương),
 * chuyên đề (bài không có cấp, vd tiếng lóng). Nội dung bài là các khối (tiêu đề, đoạn, bảng, công thức, ví dụ…).
 */
final class NguPhap
{
    public static function cacBai(): Builder
    {
        return DB::table('grammar_lessons')->where('lang', NoiDung::LANG)->where('base_lang', BaiTap::BASE_LANG)
            ->where('status', 'published')->whereNull('deleted_at');
    }

    /**
     * Chương theo thứ tự hiện: mã "A1-3" là chương 3 của cấp A1.
     *
     * @param  list<int>|null  $ids  null là mọi chương
     * @return list<array{id: int, code: string, level: string|null, no: int, name: string}>
     */
    public static function cacChuong(?array $ids = null): array
    {
        $q = DB::table('grammar_chapters')->where('lang', NoiDung::LANG)->whereNull('deleted_at')->orderBy('sort')->orderBy('id');

        if ($ids !== null) {
            $q->whereIn('id', $ids);
        }

        $ra = [];

        foreach ($q->get(['id', 'code', 'name_vi', 'name_en']) as $c) {
            [$cap, $so] = array_pad(explode('-', (string) $c->code, 2), 2, '0');
            $ra[] = [
                'id' => (int) $c->id,
                'code' => (string) $c->code,
                'level' => in_array($cap, NoiDung::CAP, true) ? $cap : null,
                'no' => (int) $so,
                'name' => NoiDung::tenTheoNgonNgu($c->name_vi, $c->name_en),
            ];
        }

        return $ra;
    }

    /**
     * Mọi bài, theo thứ tự trong giáo trình, kèm số câu bài tập gắn với bài.
     *
     * @return array{chapters: list<array<string, mixed>>, lessons: list<array<string, mixed>>}
     */
    public static function danhSach(): array
    {
        $chuong = self::cacChuong();
        $maChuong = array_column($chuong, 'code', 'id');
        $soCau = BaiTap::cacCau()->whereNotNull('exercise_sets.grammar_lesson_id')->groupBy('exercise_sets.grammar_lesson_id')
            ->selectRaw('exercise_sets.grammar_lesson_id AS id, COUNT(*) AS n')->pluck('n', 'id');
        $bai = [];

        foreach (self::cacBai()->orderBy('sort')->orderBy('id')->get(['id', 'slug', 'title', 'level', 'chapter_id']) as $b) {
            $bai[] = [
                'slug' => (string) $b->slug,
                'title' => (string) $b->title,
                'level' => NoiDung::CAP[(int) $b->level] ?? null,
                'chapter' => $b->chapter_id !== null ? ($maChuong[(int) $b->chapter_id] ?? null) : null,
                'exercises' => (int) ($soCau[$b->id] ?? 0),
            ];
        }

        return ['chapters' => array_map(fn (array $c) => array_diff_key($c, ['id' => 0]), $chuong), 'lessons' => $bai];
    }

    /**
     * Một bài: các khối nội dung, bộ bài tập gắn với bài, bài trước và bài sau (cùng loại: bài theo chương, hoặc bài còn lại).
     *
     * @return array<string, mixed>|null
     */
    public static function bai(string $slug): ?array
    {
        $b = self::cacBai()->where('slug', $slug)->first(['id', 'slug', 'title', 'level', 'chapter_id', 'blocks']);

        if ($b === null) {
            return null;
        }

        $chuong = $b->chapter_id !== null ? (self::cacChuong([(int) $b->chapter_id])[0] ?? null) : null;
        $boBai = BaiTap::cacCau()->where('exercise_sets.grammar_lesson_id', $b->id)
            ->groupBy('exercise_sets.id', 'exercise_sets.slug', 'exercise_sets.title', 'exercise_sets.sort')
            ->orderBy('exercise_sets.sort')->orderBy('exercise_sets.id')
            ->get(['exercise_sets.slug', 'exercise_sets.title', DB::raw('COUNT(*) AS n')]);

        $thuTu = self::cacBai()->when($b->chapter_id !== null, fn (Builder $q) => $q->whereNotNull('chapter_id'), fn (Builder $q) => $q->whereNull('chapter_id'))
            ->orderBy('sort')->orderBy('id')->get(['slug', 'title'])->values();
        $i = $thuTu->search(fn ($x) => $x->slug === $b->slug);
        $ben = fn (int $k) => isset($thuTu[$k]) ? ['slug' => (string) $thuTu[$k]->slug, 'title' => (string) $thuTu[$k]->title] : null;
        $khoi = json_decode((string) $b->blocks, true);

        return [
            'slug' => (string) $b->slug,
            'title' => (string) $b->title,
            'level' => NoiDung::CAP[(int) $b->level] ?? null,
            'chapter' => $chuong !== null ? array_diff_key($chuong, ['id' => 0]) : null,
            'blocks' => is_array($khoi) ? array_values($khoi) : [],
            'sets' => $boBai->map(fn ($s) => ['slug' => (string) $s->slug, 'title' => (string) $s->title, 'count' => (int) $s->n])->values()->all(),
            'prev' => $i !== false ? $ben($i - 1) : null,
            'next' => $i !== false ? $ben($i + 1) : null,
        ];
    }
}
