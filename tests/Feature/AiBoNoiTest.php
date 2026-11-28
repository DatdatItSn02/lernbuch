<?php

use App\Support\Ai\BoNoi\BoNoiGemini;
use App\Support\Ai\BoNoi\BoNoiGia;
use App\Support\Ai\BoNoi\BoNoiOpenAi;
use App\Support\Ai\BoNoi\ChonBoNoi;
use App\Support\Ai\BoNoi\LoiBoNoi;
use App\Support\Ai\BoNoi\YeuCauAi;
use App\Support\Ai\KhuonAi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// Mục tự kiểm 1: nhà cung cấp lỗi. Phần đầu: từng bộ nối gửi đúng, đọc đúng, phân loại lỗi đúng, không lộ khoá.
// Phần đường ống (thử lại, không trừ lượt) ở cuối file.

const KHOA_GEMINI_THU = 'khoa-bi-mat-123';
const GOC_GEMINI = 'https://generativelanguage.googleapis.com/v1beta';
const URL_GEMINI = GOC_GEMINI.'/models/gemini-3.1-flash-lite:generateContent';

beforeEach(function () {
    Http::preventStrayRequests();
});

function yeuCauBoNoi(): YeuCauAi
{
    return new YeuCauAi('word', 'gemini-3.1-flash-lite', 'Hệ thống', 'Người dùng', 700, 0.2, KhuonAi::jsonSchema('word'), 20.0, ['word' => 'essen']);
}

/** @return array<string, mixed> */
function traLoiGemini(string $chu = '{"a":1}', string $lyDo = 'STOP'): array
{
    return [
        'candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'đang nghĩ…', 'thought' => true], ['text' => $chu]]], 'finishReason' => $lyDo]],
        'usageMetadata' => ['promptTokenCount' => 1500, 'candidatesTokenCount' => 400, 'thoughtsTokenCount' => 100, 'cachedContentTokenCount' => 200],
    ];
}

function gemini(string $khoa = KHOA_GEMINI_THU): BoNoiGemini
{
    return new BoNoiGemini($khoa, GOC_GEMINI);
}

function loiBoNoiCua(callable $goi): LoiBoNoi
{
    try {
        $goi();
    } catch (LoiBoNoi $e) {
        return $e;
    }

    throw new RuntimeException('không ném LoiBoNoi');
}

it('Gemini: đúng địa chỉ, khoá ở đầu x-goog-api-key (không ở URL), thân chỉ có ba phần, khuôn JSON qua responseFormat', function () {
    Http::fake([URL_GEMINI => Http::response(traLoiGemini())]);

    gemini()->goi(yeuCauBoNoi());

    Http::assertSentCount(1);
    Http::assertSent(function (Request $r) {
        $than = $r->data();

        return $r->url() === URL_GEMINI && $r->method() === 'POST'
            && $r->hasHeader('x-goog-api-key', KHOA_GEMINI_THU)
            && ! str_contains($r->url(), 'khoa-bi-mat')
            && array_keys($than) === ['systemInstruction', 'contents', 'generationConfig']
            && $than['systemInstruction'] === ['parts' => [['text' => 'Hệ thống']]]
            && $than['contents'] === [['role' => 'user', 'parts' => [['text' => 'Người dùng']]]]
            && $than['generationConfig'] === [
                'temperature' => 0.2, 'maxOutputTokens' => 700,
                'responseFormat' => ['text' => ['mimeType' => 'APPLICATION_JSON', 'schema' => KhuonAi::jsonSchema('word')]],
            ];
    });
});

it('Gemini: ghép chữ, bỏ phần suy nghĩ, đọc đúng ba số token', function () {
    Http::fake([URL_GEMINI => Http::response(traLoiGemini('{"meaning":"ăn"}'))]);

    $tl = gemini()->goi(yeuCauBoNoi());

    expect($tl->chu)->toBe('{"meaning":"ăn"}')
        ->and($tl->tokenVao)->toBe(1500)
        ->and($tl->tokenRa)->toBe(500)
        ->and($tl->tokenCache)->toBe(200);
});

it('Gemini: thiếu số token thì coi là 0; khuôn null thì không gửi schema', function () {
    Http::fake([URL_GEMINI => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'x']]], 'finishReason' => 'MAX_TOKENS']]])]);
    $yc = new YeuCauAi('chat', 'gemini-3.1-flash-lite', 'H', 'N', 100, 0.5, null, 20.0);

    $tl = gemini()->goi($yc);

    expect([$tl->chu, $tl->tokenVao, $tl->tokenRa, $tl->tokenCache])->toBe(['x', 0, 0, 0]);
    Http::assertSent(fn (Request $r) => $r['generationConfig']['responseFormat'] === ['text' => ['mimeType' => 'APPLICATION_JSON']]);
});

it('Gemini: lỗi HTTP được phân loại; thông điệp không chứa khoá hay thân trả lời', function (int $ma, string $loai) {
    Http::fake([URL_GEMINI => Http::response(['error' => ['code' => $ma, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'chi tiết bí mật '.KHOA_GEMINI_THU]], $ma)]);

    $e = loiBoNoiCua(fn () => gemini()->goi(yeuCauBoNoi()));

    expect($e->loai)->toBe($loai)
        ->and($e->getMessage())->toContain("HTTP {$ma}")
        ->and($e->getMessage())->not->toContain('khoa-bi-mat')
        ->and($e->getMessage())->not->toContain('chi tiết');
})->with([
    '429' => [429, LoiBoNoi::TAM_THOI], '500' => [500, LoiBoNoi::TAM_THOI], '502' => [502, LoiBoNoi::TAM_THOI],
    '503' => [503, LoiBoNoi::TAM_THOI], '504' => [504, LoiBoNoi::TAM_THOI],
    '400' => [400, LoiBoNoi::CAU_HINH], '401' => [401, LoiBoNoi::CAU_HINH], '403' => [403, LoiBoNoi::CAU_HINH], '404' => [404, LoiBoNoi::CAU_HINH],
]);

it('Gemini: đứt mạng hay hết giờ là lỗi tạm thời', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    expect(loiBoNoiCua(fn () => gemini()->goi(yeuCauBoNoi()))->loai)->toBe(LoiBoNoi::TAM_THOI);
});

it('Gemini: thiếu khoá là lỗi cấu hình và không gọi HTTP', function () {
    Http::fake();

    expect(loiBoNoiCua(fn () => gemini('')->goi(yeuCauBoNoi()))->loai)->toBe(LoiBoNoi::CAU_HINH);
    Http::assertNothingSent();
});

it('Gemini: câu hỏi hay câu trả lời bị chặn vì an toàn', function (array $phanHoi) {
    Http::fake([URL_GEMINI => Http::response($phanHoi)]);

    expect(loiBoNoiCua(fn () => gemini()->goi(yeuCauBoNoi()))->loai)->toBe(LoiBoNoi::BI_CHAN);
})->with([
    'blockReason' => [['promptFeedback' => ['blockReason' => 'SAFETY']]],
    'không có câu trả lời' => [['candidates' => []]],
    'SAFETY' => [traLoiGemini('{}', 'SAFETY')],
    'PROHIBITED_CONTENT' => [traLoiGemini('{}', 'PROHIBITED_CONTENT')],
    'RECITATION' => [traLoiGemini('{}', 'RECITATION')],
]);

it('OpenAI tương thích: đúng đường, đầu Bearer, đọc đúng chữ và token; lỗi phân loại như Gemini', function () {
    Http::fake([
        'https://ai.vidu.test/v1/chat/completions' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => '{"a":1}'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]])
            ->push(['error' => ['code' => 'rate_limit']], 429)
            ->push(['error' => ['code' => 'invalid_api_key']], 401)
            ->push(['choices' => [['message' => ['content' => ''], 'finish_reason' => 'content_filter']]]),
    ]);
    $bn = new BoNoiOpenAi('khoa-openai', 'https://ai.vidu.test/v1/');

    $tl = $bn->goi(yeuCauBoNoi());
    expect([$tl->chu, $tl->tokenVao, $tl->tokenRa])->toBe(['{"a":1}', 10, 5]);
    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer khoa-openai')
        && $r['model'] === 'gemini-3.1-flash-lite' && $r['response_format'] === ['type' => 'json_object']
        && $r['messages'][0] === ['role' => 'system', 'content' => 'Hệ thống']);

    expect(loiBoNoiCua(fn () => $bn->goi(yeuCauBoNoi()))->loai)->toBe(LoiBoNoi::TAM_THOI)
        ->and(loiBoNoiCua(fn () => $bn->goi(yeuCauBoNoi()))->loai)->toBe(LoiBoNoi::CAU_HINH)
        ->and(loiBoNoiCua(fn () => $bn->goi(yeuCauBoNoi()))->loai)->toBe(LoiBoNoi::BI_CHAN)
        ->and(loiBoNoiCua(fn () => (new BoNoiOpenAi('', 'https://ai.vidu.test/v1'))->goi(yeuCauBoNoi()))->loai)->toBe(LoiBoNoi::CAU_HINH);
});

it('AI giả: theo kịch bản, hết kịch bản thì trả mẫu đúng khuôn có chứa từ đang hỏi; ghi lại mọi yêu cầu', function () {
    $gia = app(BoNoiGia::class)->kich(['{"x":1}', fn (YeuCauAi $yc) => 'hỏi '.$yc->thamSo['word'], new LoiBoNoi(LoiBoNoi::TAM_THOI)]);

    expect($gia->goi(yeuCauBoNoi())->chu)->toBe('{"x":1}')
        ->and($gia->goi(yeuCauBoNoi())->chu)->toBe('hỏi essen')
        ->and(loiBoNoiCua(fn () => $gia->goi(yeuCauBoNoi()))->loai)->toBe(LoiBoNoi::TAM_THOI);

    $mau = $gia->goi(yeuCauBoNoi());
    expect(KhuonAi::doc('word', $mau->chu))->not->toBeNull()
        ->and($mau->chu)->toContain('essen')
        ->and([$mau->tokenVao, $mau->tokenRa])->toBe([1500, 500])
        ->and($gia->daNhan)->toHaveCount(4);

    $gia->datLai();
    expect($gia->daNhan)->toBe([]);
});

it('AI giả không chạy ở production', function () {
    $this->app['env'] = 'production';

    expect(loiBoNoiCua(fn () => app(BoNoiGia::class)->goi(yeuCauBoNoi()))->loai)->toBe(LoiBoNoi::CAU_HINH);
});

it('chọn bộ nối: AI_DRIVER thắng ô Nhà cung cấp; thiếu khoá ngoài production thì dùng AI giả; ở production là lỗi cấu hình', function () {
    $this->seed();

    config(['ai.bo_noi' => 'fake']);
    expect(ChonBoNoi::ten())->toBe('fake')->and(ChonBoNoi::boNoi())->toBeInstanceOf(BoNoiGia::class);

    config(['ai.bo_noi' => null, 'ai.gemini.khoa' => null]);
    expect(ChonBoNoi::ten())->toBe('fake');

    config(['ai.gemini.khoa' => 'k']);
    expect(ChonBoNoi::ten())->toBe('gemini')->and(ChonBoNoi::boNoi())->toBeInstanceOf(BoNoiGemini::class);

    config(['ai.bo_noi' => 'openai_compatible', 'ai.openai.khoa' => 'k2', 'ai.openai.goc' => 'https://ai.vidu.test/v1']);
    expect(ChonBoNoi::ten())->toBe('openai_compatible')->and(ChonBoNoi::boNoi())->toBeInstanceOf(BoNoiOpenAi::class);

    $this->app['env'] = 'production';
    config(['ai.bo_noi' => null, 'ai.gemini.khoa' => null]);
    expect(ChonBoNoi::ten())->toBe('gemini')
        ->and(loiBoNoiCua(fn () => ChonBoNoi::boNoi()->goi(yeuCauBoNoi()))->loai)->toBe(LoiBoNoi::CAU_HINH);
});
