<?php

namespace App\Support\QuanTri;

use App\Models\AiPrompt;
use App\Support\Ai\BoNoi\ChonBoNoi;
use App\Support\Ai\BoNoi\LoiBoNoi;
use App\Support\Ai\BoNoi\YeuCauAi;
use App\Support\Ai\CaiDatAi;
use App\Support\Ai\HoiAi;
use App\Support\Ai\KhuonAi;
use App\Support\Ai\SoanLoiNhac;
use App\Support\Ai\TranAi;
use App\Support\NhatKy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Trang quản trị Prompt: mỗi loại có nhiều phiên bản, chỉ một bản đang dùng. Sửa là tạo phiên bản mới (chưa dùng); chạy
 * thử trước khi bấm "Dùng bản này"; quay lại bản trước bằng một lần bấm. Mọi thay đổi ghi nhật ký thao tác.
 */
final class PromptAi
{
    public const LOAI = [
        'translate' => 'Dịch',
        'grammar' => 'Sửa ngữ pháp',
        'chat' => 'Trò chuyện',
        'lesson' => 'Bài học AI',
        'lookup' => 'Tra từ AI',
        'doc' => 'Giải thích bài ngữ pháp',
        'ask' => 'Hỏi trong bài ngữ pháp',
        'word' => 'Giải thích từ',
        'answer' => 'Kiểm đáp án',
        'sentence' => 'Viết câu',
    ];

    public static function tenLoai(string $loai): string
    {
        return self::LOAI[$loai] ?? $loai;
    }

    /** Lỗi của prompt hệ thống, null nếu đúng: phải có {level} và chữ JSON (khuôn kết quả). */
    public static function loiHeThong(string $chu): ?string
    {
        if (! str_contains($chu, '{level}')) {
            return 'Prompt hệ thống phải có {level}.';
        }

        return str_contains($chu, 'JSON') ? null : 'Prompt hệ thống phải có chữ JSON và khuôn kết quả.';
    }

    /**
     * Lỗi của mẫu câu, null nếu đúng: có đủ tham số của loại, không có {tên} lạ; loại có chữ người học gõ thì có đúng một
     * cặp thẻ <input> và các tham số đó nằm giữa hai thẻ.
     */
    public static function loiMauCau(string $loai, string $chu): ?string
    {
        $thamSo = SoanLoiNhac::THAM_SO[$loai] ?? [];
        preg_match_all('/\{([a-z_]+)\}/', $chu, $m);
        $la = array_values(array_diff(array_unique($m[1]), $thamSo));
        $thieu = array_values(array_diff($thamSo, $m[1]));

        if ($thieu !== []) {
            return 'Mẫu câu thiếu: {'.implode('}, {', $thieu).'}.';
        }

        if ($la !== []) {
            return 'Mẫu câu có tham số lạ: {'.implode('}, {', $la).'}.';
        }

        $nguoiGo = array_values(array_intersect($thamSo, SoanLoiNhac::NGUOI_GO));

        if ($nguoiGo === []) {
            return null;
        }

        if (substr_count($chu, '<input>') !== 1 || substr_count($chu, '</input>') !== 1 || preg_match_all('/<\s*\/?\s*input\b/i', $chu) !== 2) {
            return 'Mẫu câu phải có đúng một cặp thẻ <input> … </input>.';
        }

        $dau = (int) strpos($chu, '<input>');
        $cuoi = (int) strpos($chu, '</input>');

        if ($dau > $cuoi) {
            return 'Thẻ <input> phải đứng trước </input>.';
        }

        $trong = substr($chu, $dau, $cuoi - $dau);
        $ngoai = substr($chu, 0, $dau).substr($chu, $cuoi);

        foreach ($nguoiGo as $ten) {
            if (! str_contains($trong, '{'.$ten.'}') || str_contains($ngoai, '{'.$ten.'}')) {
                return 'Chữ người học gõ ({'.$ten.'}) phải nằm giữa <input> và </input>.';
            }
        }

        return null;
    }

    /**
     * Tạo phiên bản mới từ một bản (chưa dùng ngay). Model của bản mới là model mặc định lúc tạo.
     *
     * @param  array{system_prompt: string, user_template: string, max_tokens: int|string, temperature: float|string}  $d
     */
    public static function taoBanMoi(AiPrompt $tu, array $d): AiPrompt
    {
        $loi = self::loiHeThong($d['system_prompt']) ?? self::loiMauCau($tu->kind, $d['user_template']);
        $token = (int) $d['max_tokens'];
        $nhiet = (float) $d['temperature'];

        if ($loi === null && ($token < 100 || $token > 4000)) {
            $loi = 'Token tối đa từ 100 đến 4.000.';
        }

        if ($loi === null && ($nhiet < 0 || $nhiet > 1)) {
            $loi = 'Nhiệt độ từ 0 đến 1.';
        }

        if ($loi !== null) {
            throw new InvalidArgumentException($loi);
        }

        $moi = new AiPrompt;
        $moi->forceFill([
            'kind' => $tu->kind,
            'version' => (int) AiPrompt::query()->where('kind', $tu->kind)->max('version') + 1,
            'model' => (string) CaiDatAi::tatCa()['default_model'],
            'system_prompt' => $d['system_prompt'],
            'user_template' => $d['user_template'],
            'max_tokens' => $token,
            'temperature' => round($nhiet, 2),
            'is_active' => false,
            'created_by' => NhatKy::nguoiLam()?->id,
        ])->save();

        NhatKy::ghi('ai_prompt.tao', $moi, ['version' => $tu->version], ['version' => $moi->version, 'kind' => $moi->kind]);

        return $moi;
    }

    /** Dùng bản này: bản này đang dùng, mọi bản khác cùng loại thôi dùng. */
    public static function dung(AiPrompt $p, string $viec = 'ai_prompt.dung'): void
    {
        $cu = AiPrompt::dangDung($p->kind);

        DB::transaction(function () use ($p): void {
            AiPrompt::query()->where('kind', $p->kind)->where('id', '<>', $p->id)->update(['is_active' => 0]);
            AiPrompt::query()->where('id', $p->id)->update(['is_active' => 1, 'updated_by' => NhatKy::nguoiLam()?->id]);
        });

        NhatKy::ghi($viec, $p, ['version' => $cu?->version], ['version' => $p->version]);
    }

    /** Bản liền trước bản đang dùng của loại (version lớn nhất nhỏ hơn), null nếu không có. */
    public static function banTruoc(string $loai): ?AiPrompt
    {
        $dang = AiPrompt::dangDung($loai);

        return $dang === null ? null : AiPrompt::query()->where('kind', $loai)->whereNull('deleted_at')
            ->where('version', '<', $dang->version)->orderByDesc('version')->first();
    }

    public static function quayLai(string $loai): ?AiPrompt
    {
        $truoc = self::banTruoc($loai);

        if ($truoc !== null) {
            self::dung($truoc, 'ai_prompt.quay_lai');
        }

        return $truoc;
    }

    /** Tham số mẫu để chạy thử: câu đầu tiên của loại trong resources/ai/mau-thu.json (kèm trình độ). @return array<string, string> */
    public static function thamSoMau(string $loai): array
    {
        $ds = json_decode((string) file_get_contents(resource_path('ai/mau-thu.json')), true);

        foreach (is_array($ds) ? $ds : [] as $c) {
            if (is_array($c) && ($c['loai'] ?? null) === $loai && is_array($c['thamSo'] ?? null)) {
                return array_map('strval', $c['thamSo']) + ['level' => (string) ($c['level'] ?? 'A1')];
            }
        }

        return array_fill_keys(SoanLoiNhac::THAM_SO[$loai] ?? [], '') + ['level' => 'A1'];
    }

    /**
     * Chạy thử một phiên bản với model mặc định, không qua công tắc và hạn mức của người học, nhưng có qua trần hệ thống.
     * Ghi ai_requests không có người học (ngữ cảnh prompt_test). Không đổi bản đang dùng.
     *
     * @param  array<string, string>  $thamSo
     * @return array{trang_thai: string, chu: string, dung_khuon: bool, token_vao: int, token_ra: int, chi_phi_micro: int, ms: int, model: string}
     */
    public static function chayThu(AiPrompt $p, array $thamSo): array
    {
        $cd = CaiDatAi::tatCa();
        $model = (string) $cd['default_model'];
        $ra = ['trang_thai' => 'blocked', 'chu' => '', 'dung_khuon' => false, 'token_vao' => 0, 'token_ra' => 0, 'chi_phi_micro' => 0, 'ms' => 0, 'model' => $model];
        $ghi = fn (array $kq) => DB::table('ai_requests')->insert([
            'user_id' => null, 'kind' => $p->kind, 'provider' => substr(ChonBoNoi::ten(), 0, 20), 'prompt_id' => $p->id, 'model' => $model,
            'context_type' => 'prompt_test', 'context_id' => $p->id, 'input_excerpt' => mb_substr((string) json_encode($thamSo, JSON_UNESCAPED_UNICODE), 0, 500),
            'input_tokens' => $kq['token_vao'], 'output_tokens' => $kq['token_ra'], 'cost_micro_usd' => $kq['chi_phi_micro'], 'cache_hit' => 0,
            'status' => $kq['trang_thai'], 'latency_ms' => $kq['ms'], 'created_at' => now()->getTimestamp(),
        ]);

        if (TranAi::chamTran($cd)) {
            $ghi($ra);

            return ['chu' => 'Chạm trần hệ thống: chưa chạy thử được.'] + $ra;
        }

        $cho = TranAi::giuCho($cd);

        if ($cho === null) {
            $ghi($ra);

            return ['chu' => 'Đang có đủ lượt cùng lúc: thử lại sau ít giây.'] + $ra;
        }

        try {
            [$heThong, $nguoiDung] = SoanLoiNhac::soan($p->kind, $p->system_prompt, $p->user_template, $thamSo);
            $yc = new YeuCauAi($p->kind, $model, $heThong, $nguoiDung, $p->max_tokens, (float) $p->temperature,
                KhuonAi::jsonSchema($p->kind), (float) config('ai.cho_toi_da', 20), $thamSo);
            $bd = now()->getTimestampMs();

            try {
                $tl = ChonBoNoi::boNoi()->goi($yc);
                $ra = [
                    'trang_thai' => KhuonAi::doc($p->kind, $tl->chu) !== null ? 'ok' : 'error',
                    'chu' => $tl->chu,
                    'dung_khuon' => KhuonAi::doc($p->kind, $tl->chu) !== null,
                    'token_vao' => $tl->tokenVao,
                    'token_ra' => $tl->tokenRa,
                    'chi_phi_micro' => HoiAi::chiPhi($tl, $cd['price_usd_per_mtok']),
                    'ms' => now()->getTimestampMs() - $bd,
                    'model' => $model,
                ];
            } catch (LoiBoNoi $e) {
                $ra = ['trang_thai' => 'error', 'chu' => 'Nhà cung cấp lỗi ('.$e->loai.'): '.$e->getMessage(), 'ms' => now()->getTimestampMs() - $bd] + $ra;
            }

            $ghi($ra);

            return $ra;
        } finally {
            $cho->release();
        }
    }
}
