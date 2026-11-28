<?php

namespace App\Support\Ai\BoNoi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Gemini API (generateContent). Khoá đi trong đầu x-goog-api-key, không nằm trong URL. Kết quả JSON theo khuôn qua
 * generationConfig.responseFormat (responseSchema và _responseJsonSchema đã bị Google đánh dấu bỏ). Đã kiểm ngày 28/11/2026.
 */
final class BoNoiGemini implements BoNoiAi
{
    /** finishReason nghĩa là nội dung bị chặn. */
    public const LY_DO_CHAN = ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII', 'LANGUAGE'];

    public function __construct(private readonly string $khoa, private readonly string $goc) {}

    public function goi(YeuCauAi $yc): TraLoiAi
    {
        if ($this->khoa === '') {
            throw new LoiBoNoi(LoiBoNoi::CAU_HINH, 'Gemini: chưa có khoá');
        }

        $dang = ['mimeType' => 'APPLICATION_JSON'];

        if ($yc->khuon !== null) {
            $dang['schema'] = $yc->khuon;
        }

        $than = [
            'systemInstruction' => ['parts' => [['text' => $yc->heThong]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $yc->nguoiDung]]]],
            'generationConfig' => ['temperature' => $yc->nhietDo, 'maxOutputTokens' => $yc->maxTokens, 'responseFormat' => ['text' => $dang]],
        ];
        $giay = max(1, (int) ceil($yc->choGiay));

        try {
            $r = Http::withHeaders(['x-goog-api-key' => $this->khoa])->acceptJson()
                ->connectTimeout(min(5, $giay))->timeout($giay)
                ->post(rtrim($this->goc, '/').'/models/'.rawurlencode($yc->model).':generateContent', $than);
        } catch (ConnectionException) {
            throw new LoiBoNoi(LoiBoNoi::TAM_THOI, 'Gemini: không kết nối được hoặc hết giờ');
        }

        $j = $r->json();

        if ($r->failed()) {
            throw LoiBoNoi::theoMa('Gemini', $r->status(), is_array($j) ? data_get($j, 'error.status') : null);
        }

        if (! is_array($j)) {
            throw new LoiBoNoi(LoiBoNoi::TAM_THOI, 'Gemini: trả về không phải JSON');
        }

        $ungVien = data_get($j, 'candidates.0');

        if (data_get($j, 'promptFeedback.blockReason') !== null || ! is_array($ungVien)) {
            throw new LoiBoNoi(LoiBoNoi::BI_CHAN, 'Gemini: câu hỏi bị chặn');
        }

        if (in_array($ungVien['finishReason'] ?? null, self::LY_DO_CHAN, true)) {
            throw new LoiBoNoi(LoiBoNoi::BI_CHAN, 'Gemini: câu trả lời bị chặn');
        }

        $chu = '';
        $phan = data_get($ungVien, 'content.parts');

        foreach (is_array($phan) ? $phan : [] as $p) {
            if (is_array($p) && is_string($p['text'] ?? null) && empty($p['thought'])) {
                $chu .= $p['text'];
            }
        }

        return new TraLoiAi(
            $chu,
            (int) data_get($j, 'usageMetadata.promptTokenCount', 0),
            (int) data_get($j, 'usageMetadata.candidatesTokenCount', 0) + (int) data_get($j, 'usageMetadata.thoughtsTokenCount', 0),
            (int) data_get($j, 'usageMetadata.cachedContentTokenCount', 0),
        );
    }
}
