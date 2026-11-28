<?php

namespace App\Support\Ai;

use App\Models\AiPrompt;
use App\Models\User;
use App\Support\Ai\BoNoi\BoNoiAi;
use App\Support\Ai\BoNoi\ChonBoNoi;
use App\Support\Ai\BoNoi\LoiBoNoi;
use App\Support\Ai\BoNoi\TraLoiAi;
use App\Support\Ai\BoNoi\YeuCauAi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Đường đi chung của mọi lượt hỏi AI, tám chặng; không qua chặng nào thì ném TuChoiAi và không đi tiếp:
 * 1 công tắc, danh sách thử · 2 tuổi, đồng ý · 3 hạn mức người học · 4 cache · 5 trần hệ thống · 6 gọi nhà cung cấp
 * (thử lại đúng 1 lần nếu lỗi tạm thời và còn ít nhất 3 giây) · 7 kiểm khuôn · 8 trả lời, ghi nhật ký.
 * Chỉ lượt thành công tới nhà cung cấp mới trừ lượt. Không gửi tên, email, mã tài khoản cho nhà cung cấp.
 */
final class HoiAi
{
    /** Còn ít hơn chừng này giây thì không thử lại. */
    public const CON_IT_NHAT_DE_THU_LAI = 3.0;

    public const TRICH_TOI_DA = 500;

    private const LOI_BO_NOI = [
        LoiBoNoi::TAM_THOI => ['ai_loi_tam_thoi', 503],
        LoiBoNoi::CAU_HINH => ['ai_loi_cau_hinh', 503],
        LoiBoNoi::BI_CHAN => ['ai_bi_tu_choi', 422],
    ];

    /**
     * @param  array<string, string>  $thamSo  tham số của loại (SoanLoiNhac::THAM_SO) và level
     * @param  string  $trich  phần ghi vào nhật ký (tối đa 500 ký tự, xoá sau 30 ngày)
     * @return array{data: array<string, mixed>, meta: array{cached: bool, limit: int, used: int, remaining: int, reset_at: int}}
     */
    public static function hoi(User $user, string $loai, array $thamSo, string $trich, ?string $kieuNguCanh = null, ?int $maNguCanh = null): array
    {
        $cd = CaiDatAi::tatCa();
        TrangThaiAi::kiem($user, $cd);

        $khoaNguoi = Cache::lock('ai:nguoi:'.$user->id, TranAi::GIU_KHOA_GIAY);

        if (! $khoaNguoi->get()) {
            throw new TuChoiAi('ai_dang_xu_ly', 429);
        }

        try {
            $ghi = fn (string $trangThai, array $them = []) => self::ghi($user, $loai, $trich, $kieuNguCanh, $maNguCanh, $trangThai, $them);
            $hm = HanMucAi::trangThai($user, $cd);

            if (! HanMucAi::conLuot($hm)) {
                $ghi('quota');

                throw new TuChoiAi('ai_het_luot', 429, ['reset_at' => $hm['reset_at'], 'limit' => $hm['limit']]);
            }

            $prompt = AiPrompt::dangDung($loai) ?? throw new TuChoiAi('ai_loi_cau_hinh', 503);
            $model = $cd['default_model'];
            $coSo = ['prompt_id' => $prompt->id, 'model' => $model];
            $khoaCache = BoNhoAi::duocLuu($loai) ? BoNhoAi::khoa($loai, $prompt->version, $model, $thamSo) : null;
            $cu = $khoaCache !== null ? BoNhoAi::lay($khoaCache) : null;

            if ($cu !== null) {
                $ghi('ok', $coSo + ['cache_hit' => 1, 'latency_ms' => 0]);

                return ['data' => $cu, 'meta' => HanMucAi::meta($hm, true)];
            }

            if (TranAi::chamTran($cd)) {
                $ghi('blocked', $coSo);

                throw new TuChoiAi('ai_qua_tai', 503);
            }

            $cho = TranAi::giuCho($cd);

            if ($cho === null) {
                $ghi('blocked', $coSo);

                throw new TuChoiAi('ai_qua_tai', 503);
            }

            try {
                [$heThong, $nguoiDung] = SoanLoiNhac::soan($loai, $prompt->system_prompt, $prompt->user_template, $thamSo);
                $yc = new YeuCauAi($loai, $model, $heThong, $nguoiDung, $prompt->max_tokens, (float) $prompt->temperature,
                    KhuonAi::jsonSchema($loai), (float) config('ai.cho_toi_da', 20), $thamSo);
                $batDau = now()->getTimestampMs();

                try {
                    $tl = self::goi(ChonBoNoi::boNoi(), $yc);
                } catch (LoiBoNoi $e) {
                    $ghi('error', $coSo + ['latency_ms' => now()->getTimestampMs() - $batDau]);
                    [$ma, $http] = self::LOI_BO_NOI[$e->loai] ?? self::LOI_BO_NOI[LoiBoNoi::TAM_THOI];

                    throw new TuChoiAi($ma, $http);
                }

                $luot = $coSo + [
                    'input_tokens' => $tl->tokenVao,
                    'cached_tokens' => $tl->tokenCache,
                    'output_tokens' => $tl->tokenRa,
                    'cost_micro_usd' => self::chiPhi($tl, $cd['price_usd_per_mtok']),
                    'latency_ms' => now()->getTimestampMs() - $batDau,
                ];
                $kq = KhuonAi::doc($loai, $tl->chu);

                if ($kq === null) {
                    $ghi('error', $luot);

                    throw new TuChoiAi('ai_tra_loi_hong', 502);
                }

                if ($khoaCache !== null) {
                    BoNhoAi::luu($khoaCache, $loai, $kq);
                }

                $ghi('ok', $luot);
                $hm['used']++;

                return ['data' => $kq, 'meta' => HanMucAi::meta($hm, false)];
            } finally {
                $cho->release();
            }
        } finally {
            $khoaNguoi->release();
        }
    }

    /** Chi phí quy đổi theo giá trả tiền, phần triệu USD. @param array{in: int|float, out: int|float} $gia USD cho 1 triệu token */
    public static function chiPhi(TraLoiAi $tl, array $gia): int
    {
        return (int) round($tl->tokenVao * $gia['in'] + $tl->tokenRa * $gia['out']);
    }

    /** Gọi bộ nối; lỗi tạm thời mà còn ít nhất 3 giây thì gọi lần hai với thời gian còn lại. Không có lần ba. */
    private static function goi(BoNoiAi $boNoi, YeuCauAi $yc): TraLoiAi
    {
        $batDau = now()->getTimestampMs();

        try {
            return $boNoi->goi($yc);
        } catch (LoiBoNoi $e) {
            $conLai = $yc->choGiay - (now()->getTimestampMs() - $batDau) / 1000;

            if ($e->loai !== LoiBoNoi::TAM_THOI || $conLai < self::CON_IT_NHAT_DE_THU_LAI) {
                throw $e;
            }

            return $boNoi->goi($yc->voiCho($conLai));
        }
    }

    /** @param array<string, mixed> $them */
    private static function ghi(User $user, string $loai, string $trich, ?string $kieuNguCanh, ?int $maNguCanh, string $trangThai, array $them): void
    {
        DB::table('ai_requests')->insert($them + [
            'user_id' => $user->id,
            'kind' => $loai,
            'provider' => substr(ChonBoNoi::ten(), 0, 20),
            'context_type' => $kieuNguCanh,
            'context_id' => $maNguCanh,
            'input_excerpt' => mb_substr($trich, 0, self::TRICH_TOI_DA),
            'cache_hit' => 0,
            'status' => $trangThai,
            'created_at' => now()->getTimestamp(),
        ]);
    }
}
