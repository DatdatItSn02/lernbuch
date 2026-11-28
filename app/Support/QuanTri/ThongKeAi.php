<?php

namespace App\Support\QuanTri;

use App\Support\Ai\CaiDatAi;
use App\Support\Ai\TranAi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Thống kê AI theo ngày giờ Thái Bình Dương (cùng cách tính với trần ngày của Google). Mỗi ngày một câu SUM, COUNT
 * theo khoảng created_at (có chỉ mục); không tải dòng nhật ký về PHP. Thời gian chờ chỉ tính lượt ok không từ cache.
 */
final class ThongKeAi
{
    /** @return list<array{ngay: string, dau: int, luot: int, ok: int, cache: int, loi: int, chan: int, het_luot: int, token_vao: int, token_ra: int, chi_phi_micro: int, cho_tb: int|null, cho_max: int|null}> */
    public static function theoNgay(int $soNgay = 30): array
    {
        $homNay = CarbonImmutable::now(TranAi::MUI_GIO_NGAY)->startOfDay();
        $ra = [];

        for ($i = 0; $i < $soNgay; $i++) {
            $dau = $homNay->subDays($i);
            $ra[] = ['ngay' => $dau->format('d/m/Y'), 'dau' => $dau->getTimestamp()] + self::trongKhoang($dau->getTimestamp(), $dau->addDay()->getTimestamp());
        }

        return $ra;
    }

    /** @return list<array{kind: string, luot: int, cache: int, loi: int, chi_phi_micro: int}> 7 ngày gần nhất theo loại */
    public static function theoLoai(int $soNgay = 7): array
    {
        $tu = CarbonImmutable::now(TranAi::MUI_GIO_NGAY)->startOfDay()->subDays($soNgay - 1)->getTimestamp();

        return DB::table('ai_requests')->where('created_at', '>=', $tu)->groupBy('kind')->orderBy('kind')
            ->selectRaw("kind, COUNT(*) AS luot, SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) AS cache, SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) AS loi, COALESCE(SUM(cost_micro_usd), 0) AS chi_phi")
            ->get()->map(fn ($d) => ['kind' => (string) $d->kind, 'luot' => (int) $d->luot, 'cache' => (int) $d->cache, 'loi' => (int) $d->loi, 'chi_phi_micro' => (int) $d->chi_phi])
            ->values()->all();
    }

    /** @return array{luot: int, tran_luot: int, chi_phi_micro: int, tran_chi_phi_usd: float} lượt tới nhà cung cấp và chi phí hôm nay so với trần */
    public static function homNay(): array
    {
        $cd = CaiDatAi::tatCa();
        $dau = CarbonImmutable::now(TranAi::MUI_GIO_NGAY)->startOfDay()->getTimestamp();
        $q = fn () => DB::table('ai_requests')->where('created_at', '>=', $dau)->where('cache_hit', 0)->whereIn('status', ['ok', 'error']);

        return [
            'luot' => $q()->count(),
            'tran_luot' => (int) $cd['rpd_cap'],
            'chi_phi_micro' => (int) $q()->sum('cost_micro_usd'),
            'tran_chi_phi_usd' => (float) $cd['daily_spend_cap_usd'],
        ];
    }

    public static function usd(int $micro): string
    {
        return number_format($micro / 1_000_000, 4, ',', '.');
    }

    /** @return array{luot: int, ok: int, cache: int, loi: int, chan: int, het_luot: int, token_vao: int, token_ra: int, chi_phi_micro: int, cho_tb: int|null, cho_max: int|null} */
    private static function trongKhoang(int $tu, int $den): array
    {
        $d = DB::table('ai_requests')->where('created_at', '>=', $tu)->where('created_at', '<', $den)->selectRaw(
            "COUNT(*) AS luot,
            SUM(CASE WHEN status = 'ok' THEN 1 ELSE 0 END) AS ok,
            SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) AS cache,
            SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) AS loi,
            SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) AS chan,
            SUM(CASE WHEN status = 'quota' THEN 1 ELSE 0 END) AS het_luot,
            COALESCE(SUM(input_tokens), 0) AS token_vao,
            COALESCE(SUM(output_tokens), 0) AS token_ra,
            COALESCE(SUM(cost_micro_usd), 0) AS chi_phi,
            AVG(CASE WHEN status = 'ok' AND cache_hit = 0 THEN latency_ms END) AS cho_tb,
            MAX(CASE WHEN status = 'ok' AND cache_hit = 0 THEN latency_ms END) AS cho_max"
        )->first();

        return [
            'luot' => (int) ($d->luot ?? 0),
            'ok' => (int) ($d->ok ?? 0),
            'cache' => (int) ($d->cache ?? 0),
            'loi' => (int) ($d->loi ?? 0),
            'chan' => (int) ($d->chan ?? 0),
            'het_luot' => (int) ($d->het_luot ?? 0),
            'token_vao' => (int) ($d->token_vao ?? 0),
            'token_ra' => (int) ($d->token_ra ?? 0),
            'chi_phi_micro' => (int) ($d->chi_phi ?? 0),
            'cho_tb' => isset($d->cho_tb) ? (int) round((float) $d->cho_tb) : null,
            'cho_max' => isset($d->cho_max) ? (int) $d->cho_max : null,
        ];
    }
}
