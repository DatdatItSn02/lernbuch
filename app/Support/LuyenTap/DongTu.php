<?php

namespace App\Support\LuyenTap;

use App\Support\AmThanh;
use App\Support\NoiDung;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Bảng động từ và luyện chia động từ theo Lernbuch (/verbs, /verbs/drill): tìm theo nguyên mẫu hoặc nghĩa (không cần dấu),
 * lọc cấp và động từ tách; câu luyện hỏi một dạng (du, er, Präteritum, Perfekt, hoặc một ngôi ở một thì).
 */
final class DongTu
{
    public const MOI_TRANG = 80;

    public const N_TOI_THIEU = 3;

    public const N_TOI_DA = 40;

    /** Dạng hỏi khi luyện: cột → nhãn (Lernbuch: forms). */
    private const DANG = ['du' => ['form_du', 'du'], 'er' => ['form_er', 'er / sie / es'], 'prat' => ['preterite', 'Präteritum (er)'], 'perf' => ['perfect', 'Perfekt (er)']];

    /** Thì trong bảng chia: khoá → tên. */
    private const THI = ['pres' => 'Präsens', 'prat' => 'Präteritum', 'perf' => 'Perfekt'];

    /** Ngôi theo thứ tự của bảng chia. */
    private const NGOI = ['ich', 'du', 'er / sie / es', 'wir', 'ihr', 'sie / Sie'];

    public static function cacDongTu(): Builder
    {
        return DB::table('verbs')->where('lang', NoiDung::LANG)->where('status', 'published')->whereNull('deleted_at');
    }

    /**
     * Một trang bảng động từ, theo thứ tự chữ cái. Tìm: mọi chữ gõ đều có trong nguyên mẫu hoặc nghĩa, không phân biệt dấu.
     *
     * @param  list<int>  $cap
     * @return array{verbs: list<array<string, mixed>>, total: int}
     */
    public static function danhSach(string $tim, array $cap, bool $chiTach, int $trang): array
    {
        $q = self::cacDongTu();

        if ($cap !== []) {
            $q->whereIn('level', $cap);
        }

        if ($chiTach) {
            $q->where('is_separable', 1);
        }

        $cacChu = array_map(fn (string $c) => self::boDau($c), NoiDung::cacChu($tim));
        $khop = [];

        foreach ($q->get(['id', 'infinitive', 'meanings']) as $v) {
            $nghia = json_decode((string) $v->meanings, true);
            $chu = mb_strtolower((string) $v->infinitive.' '.implode(' ', is_array($nghia) ? array_filter($nghia, 'is_string') : []));
            // Gõ "zahlen" hay "zaehlen" đều ra "zählen": so với cả hai cách bỏ dấu
            $boDau = self::boDau($chu).' '.mb_strtolower(Str::ascii($chu, 'de'));
            $du = true;

            foreach ($cacChu as $c) {
                $du = $du && str_contains($boDau, $c);
            }

            if ($du) {
                $khop[] = ['id' => (int) $v->id, 'key' => self::boDau((string) $v->infinitive), 'inf' => (string) $v->infinitive];
            }
        }

        usort($khop, fn (array $a, array $b) => [$a['key'], $a['inf']] <=> [$b['key'], $b['inf']]);
        $ids = array_column(array_slice($khop, ($trang - 1) * self::MOI_TRANG, self::MOI_TRANG), 'id');
        $dong = $ids === [] ? collect() : self::cacDongTu()->whereIn('id', $ids)->get()->keyBy('id');

        return [
            'verbs' => array_values(array_map(fn (int $id) => self::mot($dong[$id]), array_filter($ids, fn (int $id) => isset($dong[$id])))),
            'total' => count($khop),
        ];
    }

    /** @return array<string, mixed> */
    public static function mot(stdClass $v): array
    {
        $chia = $v->conjugation !== null ? json_decode((string) $v->conjugation, true) : null;
        $nghia = $v->meanings !== null ? json_decode((string) $v->meanings, true) : null;

        return [
            'infinitive' => (string) $v->infinitive,
            'level' => NoiDung::CAP[(int) $v->level] ?? null,
            'is_separable' => (bool) $v->is_separable,
            'prefix' => $v->prefix !== null ? (string) $v->prefix : null,
            'auxiliary' => $v->auxiliary !== null ? (string) $v->auxiliary : null,
            'form_du' => $v->form_du !== null ? (string) $v->form_du : null,
            'form_er' => $v->form_er !== null ? (string) $v->form_er : null,
            'preterite' => $v->preterite !== null ? (string) $v->preterite : null,
            'perfect' => $v->perfect !== null ? (string) $v->perfect : null,
            'meanings' => (object) (is_array($nghia) ? array_filter($nghia, 'is_string') : []),
            'conjugation' => is_array($chia) ? $chia : null,
            'card_id' => $v->card_id !== null ? (int) $v->card_id : null,
        ];
    }

    /**
     * Câu luyện chia (Lernbuch /verbs/drill): mỗi động từ một câu; nửa số câu (khi có bảng chia) hỏi một ngôi ở một thì.
     *
     * @param  list<int>  $cap
     * @return list<array<string, mixed>>
     */
    public static function luyen(array $cap, bool $chiTach, int $n): array
    {
        $n = max(self::N_TOI_THIEU, min(self::N_TOI_DA, $n));
        $q = self::cacDongTu()->whereNotNull('form_du')->whereNotNull('form_er')->whereNotNull('preterite')->whereNotNull('perfect');

        if ($cap !== []) {
            $q->whereIn('level', $cap);
        }

        if ($chiTach) {
            $q->where('is_separable', 1);
        }

        $dong = $q->inRandomOrder()->limit($n)->get();
        $audio = AmThanh::diaChi($dong->mapWithKeys(fn (stdClass $v) => [(int) $v->id => (string) $v->infinitive])->all());
        $ra = [];

        foreach ($dong as $v) {
            $dang = array_rand(self::DANG);
            [$cot, $nhan] = self::DANG[$dang];
            $dapAn = self::tachCach((string) $v->{$cot});
            $chia = $v->conjugation !== null ? json_decode((string) $v->conjugation, true) : null;

            if (is_array($chia) && mt_rand(0, 1) === 1) {
                $thi = array_rand(self::THI);
                $ngoi = mt_rand(0, 5);
                $o = $chia[$thi][$ngoi] ?? null;

                if (is_string($o) && trim($o) !== '') {
                    [$dang, $nhan, $dapAn] = [$thi.$ngoi, self::NGOI[$ngoi].' · '.self::THI[$thi], [trim($o)]];
                }
            }

            $nghia = $v->meanings !== null ? json_decode((string) $v->meanings, true) : null;
            $ra[] = [
                'sig' => 'v:'.$v->infinitive.':'.$dang,
                'kind' => 'fill',
                'prompt' => $v->infinitive.' → '.$nhan,
                'answers' => $dapAn,
                'explanation' => implode(' · ', [$v->form_du, $v->form_er, $v->preterite, $v->perfect]),
                'meanings' => (object) (is_array($nghia) ? array_filter($nghia, 'is_string') : []),
                'verb' => (string) $v->infinitive,
                'audio' => $audio[(int) $v->id] ?? null,
            ];
        }

        return $ra;
    }

    /**
     * Đáp án của một câu luyện chia theo mã câu "v:nguyên mẫu:dạng" (máy chủ chấm lại); mã sai hay động từ không còn thì null.
     *
     * @return list<string>|null
     */
    public static function dapAnLuyen(string $sig): ?array
    {
        if (preg_match('/^v:(.+):(du|er|prat|perf|(?:pres|prat|perf)[0-5])$/u', $sig, $m) !== 1) {
            return null;
        }

        $v = self::cacDongTu()->where('infinitive', $m[1])->first(['form_du', 'form_er', 'preterite', 'perfect', 'conjugation']);

        if ($v === null) {
            return null;
        }

        if (isset(self::DANG[$m[2]])) {
            $o = $v->{self::DANG[$m[2]][0]};

            return $o !== null && trim((string) $o) !== '' ? self::tachCach((string) $o) : null;
        }

        $chia = $v->conjugation !== null ? json_decode((string) $v->conjugation, true) : null;
        $o = is_array($chia) ? ($chia[substr($m[2], 0, -1)][(int) substr($m[2], -1)] ?? null) : null;

        return is_string($o) && trim($o) !== '' ? [trim($o)] : null;
    }

    /** "las / lasen" → ["las", "lasen"] (Lernbuch: tách theo dấu gạch chéo). @return list<string> */
    private static function tachCach(string $dang): array
    {
        $ds = array_values(array_filter(array_map('trim', preg_split('/\s*\/\s*/u', $dang) ?: []), fn (string $x) => $x !== ''));

        return $ds !== [] ? $ds : [$dang];
    }

    /** Viết thường, bỏ dấu (ä → a, ß → ss, đ → d). */
    private static function boDau(string $chu): string
    {
        return mb_strtolower(Str::ascii(mb_strtolower($chu)));
    }
}
