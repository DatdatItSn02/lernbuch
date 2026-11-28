<?php

namespace App\Support\Ai\BoNoi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Dịch vụ nhận cùng kiểu lệnh gọi với OpenAI (chat/completions), để đổi nhà cung cấp mà không viết lại. */
final class BoNoiOpenAi implements BoNoiAi
{
    public function __construct(private readonly string $khoa, private readonly string $goc) {}

    public function goi(YeuCauAi $yc): TraLoiAi
    {
        if ($this->khoa === '' || $this->goc === '') {
            throw new LoiBoNoi(LoiBoNoi::CAU_HINH, 'OpenAI tương thích: chưa có khoá hoặc địa chỉ');
        }

        $giay = max(1, (int) ceil($yc->choGiay));

        try {
            $r = Http::withToken($this->khoa)->acceptJson()->connectTimeout(min(5, $giay))->timeout($giay)
                ->post(rtrim($this->goc, '/').'/chat/completions', [
                    'model' => $yc->model,
                    'messages' => [['role' => 'system', 'content' => $yc->heThong], ['role' => 'user', 'content' => $yc->nguoiDung]],
                    'temperature' => $yc->nhietDo,
                    'max_tokens' => $yc->maxTokens,
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (ConnectionException) {
            throw new LoiBoNoi(LoiBoNoi::TAM_THOI, 'OpenAI tương thích: không kết nối được hoặc hết giờ');
        }

        $j = $r->json();

        if ($r->failed()) {
            throw LoiBoNoi::theoMa('OpenAI tương thích', $r->status(), is_array($j) ? data_get($j, 'error.code') : null);
        }

        if (! is_array($j) || ! is_array(data_get($j, 'choices.0'))) {
            throw new LoiBoNoi(LoiBoNoi::TAM_THOI, 'OpenAI tương thích: trả về sai dạng');
        }

        if (data_get($j, 'choices.0.finish_reason') === 'content_filter') {
            throw new LoiBoNoi(LoiBoNoi::BI_CHAN, 'OpenAI tương thích: câu trả lời bị chặn');
        }

        $chu = data_get($j, 'choices.0.message.content');

        return new TraLoiAi(is_string($chu) ? $chu : '', (int) data_get($j, 'usage.prompt_tokens', 0), (int) data_get($j, 'usage.completion_tokens', 0));
    }
}
