<?php

namespace App\Support\PhatAm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Azure Speech, "Speech to text REST API for short audio" kèm chấm phát âm (đã kiểm tài liệu ngày 29/11/2026). Gửi đi chỉ có
 * file ghi âm, câu mẫu và mã ngôn ngữ; khoá đi trong đầu Ocp-Apim-Subscription-Key, không bao giờ vào log.
 */
final class BoChamAzure implements BoCham
{
    public const NGON_NGU = 'de-DE';

    private const LOI_TU = ['None' => 'none', 'Mispronunciation' => 'mispronounced', 'Omission' => 'omitted', 'Insertion' => 'inserted'];

    public function __construct(private readonly string $khoa, private readonly string $diaChi, private readonly int $choGiay) {}

    /** Địa chỉ gọi: AZURE_SPEECH_ENDPOINT nếu có, không thì dựng từ vùng; thiếu cả hai thì chuỗi rỗng. */
    public static function diaChi(string $vung, string $goc): string
    {
        if (trim($goc) !== '') {
            return rtrim(trim($goc), '/');
        }

        $vung = trim($vung);

        return $vung === '' ? '' : 'https://'.rawurlencode($vung).'.stt.speech.microsoft.com/speech/recognition/conversation/cognitiveservices/v1';
    }

    /** Giá trị đầu Pronunciation-Assessment: JSON UTF-8 mã hoá base64. */
    public static function dauChamPhatAm(string $cauMau): string
    {
        return base64_encode((string) json_encode([
            'ReferenceText' => $cauMau,
            'GradingSystem' => 'HundredMark',
            'Granularity' => 'Phoneme',
            'Dimension' => 'Comprehensive',
            'EnableMiscue' => 'True',
        ], JSON_UNESCAPED_UNICODE));
    }

    public function cham(string $wav, string $cauMau): KetQuaCham
    {
        if ($this->khoa === '' || $this->diaChi === '') {
            return KetQuaCham::loi('config', false);
        }

        $bd = hrtime(true);
        $ms = fn () => (int) ((hrtime(true) - $bd) / 1_000_000);

        try {
            $r = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->khoa,
                'Accept' => 'application/json',
                'Pronunciation-Assessment' => self::dauChamPhatAm($cauMau),
            ])->connectTimeout(min(5, $this->choGiay))->timeout($this->choGiay)
                ->withBody($wav, 'audio/wav; codecs=audio/pcm; samplerate=16000')
                ->post($this->diaChi.'?language='.self::NGON_NGU.'&format=detailed');
        } catch (ConnectionException) {
            return KetQuaCham::loi('timeout', true, $ms());
        }

        if (in_array($r->status(), [401, 403], true)) {
            Log::warning('Azure Speech từ chối khoá hoặc vùng', ['status' => $r->status()]);

            return KetQuaCham::loi('config', true, $ms());
        }

        $j = $r->json();

        if ($r->failed() || ! is_array($j)) {
            return KetQuaCham::loi('http', true, $ms());
        }

        return self::docTraLoi($j)->voiMs($ms());
    }

    /** Đọc câu trả lời 200 của Azure. @param array<mixed> $j */
    public static function docTraLoi(array $j): KetQuaCham
    {
        $trangThai = $j['RecognitionStatus'] ?? null;

        if ($trangThai === 'NoMatch' || $trangThai === 'InitialSilenceTimeout') {
            return KetQuaCham::rong('silence');
        }

        if ($trangThai === 'BabbleTimeout') {
            return KetQuaCham::rong('noise');
        }

        if ($trangThai !== 'Success') {
            return KetQuaCham::loi('http');
        }

        $nb = data_get($j, 'NBest.0');
        $words = is_array($nb) ? ($nb['Words'] ?? null) : null;

        if (! is_array($nb) || ! is_array($words) || $words === []) {
            return KetQuaCham::rong('silence');
        }

        $tu = [];

        foreach ($words as $w) {
            if (! is_array($w) || ! is_string($w['Word'] ?? null)) {
                continue;
            }

            $mot = [
                'word' => $w['Word'],
                'score' => self::diem($w['AccuracyScore'] ?? null),
                'error' => self::LOI_TU[$w['ErrorType'] ?? 'None'] ?? 'none',
            ];
            // Điểm từng âm: tên trường theo tài liệu cũ của Azure, đọc phòng thủ; thiếu là bình thường
            $am = [];

            foreach (is_array($w['Phonemes'] ?? null) ? $w['Phonemes'] : [] as $p) {
                if (is_array($p) && is_string($p['Phoneme'] ?? null) && self::diem($p['AccuracyScore'] ?? null) !== null) {
                    $am[] = ['p' => $p['Phoneme'], 'score' => (int) self::diem($p['AccuracyScore'])];
                }
            }

            if ($am !== []) {
                $mot['phonemes'] = $am;
            }

            $tu[] = $mot;
        }

        if ($tu === []) {
            return KetQuaCham::rong('silence');
        }

        return new KetQuaCham(
            KetQuaCham::OK,
            null,
            self::diem($nb['PronScore'] ?? null),
            self::diem($nb['AccuracyScore'] ?? null),
            self::diem($nb['FluencyScore'] ?? null),
            self::diem($nb['CompletenessScore'] ?? null),
            $tu,
        );
    }

    private static function diem(mixed $v): ?int
    {
        return is_int($v) || is_float($v) ? max(0, min(100, (int) round($v))) : null;
    }
}
