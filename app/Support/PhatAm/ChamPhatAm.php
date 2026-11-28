<?php

namespace App\Support\PhatAm;

use App\Models\PronunciationAttempt;
use App\Models\User;
use App\Support\Ai\CaiDatAi;
use App\Support\Ai\TrangThaiAi;
use App\Support\Ai\TuChoiAi;
use App\Support\AmThanh;
use App\Support\NoiDung;
use Illuminate\Support\Facades\DB;

/**
 * Đường đi của một lượt chấm phát âm: cổng AI → cách chấm → câu mẫu (lấy từ database, không nhận từ app) → hạn mức → đọc
 * file WAV → im lặng thì thôi → bộ chấm → ghi điểm. File ghi âm chỉ sống trong lượt gọi này: không lưu, không vào log.
 */
final class ChamPhatAm
{
    /**
     * Câu mẫu và ngữ cảnh: thẻ người học xem được, hoặc câu ví dụ của một thẻ như vậy. Không có thì 404.
     *
     * @return array{cau: string, kieu: string, ma: int, card_id: int|null, example_id: int|null}
     */
    public static function cauMau(User $user, ?int $theId, ?int $viDuId): array
    {
        if ($theId !== null) {
            $the = NoiDung::cacThe($user)->where('cards.id', $theId)->first(['cards.id', 'cards.front']) ?? abort(404);

            return ['cau' => AmThanh::chuCanDoc((string) $the->front), 'kieu' => 'card', 'ma' => (int) $the->id, 'card_id' => (int) $the->id, 'example_id' => null];
        }

        $vd = DB::table('card_examples')->where('id', (int) $viDuId)->first(['id', 'card_id', 'sentence']) ?? abort(404);

        if (! NoiDung::cacThe($user)->where('cards.id', (int) $vd->card_id)->exists()) {
            abort(404);
        }

        return ['cau' => trim((string) $vd->sentence), 'kieu' => 'card_example', 'ma' => (int) $vd->id, 'card_id' => null, 'example_id' => (int) $vd->id];
    }

    /** @return array{data: array<string, mixed>, meta: array<string, mixed>} */
    public static function cham(User $user, ?int $theId, ?int $viDuId, string $base64): array
    {
        TrangThaiAi::kiem($user, CaiDatAi::tatCa());
        $cheDo = CheDoPhatAm::cua();

        if ($cheDo === CheDoPhatAm::TAT) {
            throw new TuChoiAi('phat_am_tam_tat', 503);
        }

        if ($cheDo !== CheDoPhatAm::AZURE) {
            throw new TuChoiAi('phat_am_doi_che_do', 409);
        }

        $mau = self::cauMau($user, $theId, $viDuId);
        $tt = HanMucPhatAm::trangThai($user);

        if (! HanMucPhatAm::conLuot($tt)) {
            self::ghiNhatKy($user, $mau, 'quota', 0);

            throw new TuChoiAi('phat_am_het_luot', 429, ['reset_at' => $tt['reset_at'], 'limit' => $tt['limit']]);
        }

        $cd = CaiDatPhatAm::tatCa();
        $wav = Wav::doc($base64, $cd['max_seconds']);
        unset($base64);

        $kq = $wav->imLang ? KetQuaCham::rong('silence', false) : ChonBoCham::boCham()->cham($wav->file, $mau['cau']);
        $ms = $wav->ms;
        unset($wav);

        PronunciationAttempt::query()->create([
            'user_id' => $user->id,
            'card_id' => $mau['card_id'],
            'example_id' => $mau['example_id'],
            'lang' => BoChamAzure::NGON_NGU,
            'reference_text' => mb_substr($mau['cau'], 0, 600),
            'provider' => CheDoPhatAm::AZURE,
            'status' => $kq->trangThai,
            'overall_score' => $kq->tong,
            'accuracy_score' => $kq->chinhXac,
            'fluency_score' => $kq->troiChay,
            'completeness_score' => $kq->dayDu,
            'words' => $kq->tu === [] ? null : $kq->tu,
            'audio_ms' => $kq->daGui ? $ms : null,
        ]);
        self::ghiNhatKy($user, $mau, $kq->trangThai === KetQuaCham::LOI ? 'error' : 'ok', $kq->ms);

        if ($kq->trangThai === KetQuaCham::LOI) {
            throw new TuChoiAi('phat_am_loi_tam_thoi', 503);
        }

        return [
            'data' => [
                'status' => $kq->trangThai,
                'reason' => $kq->lyDo,
                'overall' => $kq->tong,
                'accuracy' => $kq->chinhXac,
                'fluency' => $kq->troiChay,
                'completeness' => $kq->dayDu,
                'words' => $kq->tu,
                'reference' => $mau['cau'],
            ],
            'meta' => ['mode' => CheDoPhatAm::AZURE, ...HanMucPhatAm::meta(HanMucPhatAm::trangThai($user))],
        ];
    }

    /**
     * Bản dự phòng: trình duyệt đã tự nhận giọng, máy chủ chỉ so chữ. Không trừ lượt, không ghi ai_requests (không gửi gì đi).
     *
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public static function duPhong(User $user, ?int $theId, ?int $viDuId, string $ngheDuoc): array
    {
        TrangThaiAi::kiem($user, CaiDatAi::tatCa());
        $cheDo = CheDoPhatAm::cua();

        if ($cheDo === CheDoPhatAm::TAT) {
            throw new TuChoiAi('phat_am_tam_tat', 503);
        }

        if ($cheDo !== CheDoPhatAm::TRINH_DUYET) {
            throw new TuChoiAi('phat_am_doi_che_do', 409);
        }

        $mau = self::cauMau($user, $theId, $viDuId);
        $rong = SoChu::rong($ngheDuoc);
        $tu = SoChu::so($mau['cau'], $ngheDuoc);

        PronunciationAttempt::query()->create([
            'user_id' => $user->id,
            'card_id' => $mau['card_id'],
            'example_id' => $mau['example_id'],
            'lang' => BoChamAzure::NGON_NGU,
            'reference_text' => mb_substr($mau['cau'], 0, 600),
            'provider' => CheDoPhatAm::TRINH_DUYET,
            'status' => $rong ? KetQuaCham::RONG : KetQuaCham::OK,
            'words' => $tu,
        ]);

        return [
            'data' => ['status' => $rong ? KetQuaCham::RONG : KetQuaCham::OK, 'words' => $tu, 'reference' => $mau['cau']],
            'meta' => ['mode' => CheDoPhatAm::TRINH_DUYET],
        ];
    }

    /** Khối "pron" trong khối ai của GET /toi (chỉ khi AI sẵn sàng). @return array<string, mixed> */
    public static function choToi(User $user): array
    {
        $tt = HanMucPhatAm::trangThai($user);

        return ['mode' => CheDoPhatAm::cua(), 'limit' => $tt['limit'], 'used' => $tt['used'], 'reset_at' => $tt['reset_at'], 'max_seconds' => CaiDatPhatAm::tatCa()['max_seconds']];
    }

    /** @param array{cau: string, kieu: string, ma: int} $mau */
    private static function ghiNhatKy(User $user, array $mau, string $trangThai, int $ms): void
    {
        DB::table('ai_requests')->insert([
            'user_id' => $user->id,
            'kind' => 'pron',
            'provider' => 'azure_speech',
            'context_type' => $mau['kieu'],
            'context_id' => $mau['ma'],
            'input_excerpt' => mb_substr($mau['cau'], 0, 500),
            'cache_hit' => 0,
            'status' => $trangThai,
            'latency_ms' => $ms > 0 ? $ms : null,
            'created_at' => now()->getTimestamp(),
        ]);
    }
}
