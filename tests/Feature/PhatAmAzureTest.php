<?php

use App\Support\PhatAm\BoChamAzure;
use App\Support\PhatAm\ChonBoCham;
use App\Support\PhatAm\KetQuaCham;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Mục tự kiểm 13 và 8: gọi Azure đúng địa chỉ, đầu, thân; đọc đúng câu trả lời; lỗi ra đúng lý do; khoá không vào log.

const CAU_AZURE = 'Ich möchte zwei Brötchen';

function mauAzure(string $ten): array
{
    return json_decode((string) file_get_contents(base_path('tests/chung/azure-phat-am.json')), true)[$ten];
}

function boChamThu(): BoChamAzure
{
    return new BoChamAzure('khoa-bi-mat-123', BoChamAzure::diaChi('westeurope', ''), 15);
}

it('gửi đúng địa chỉ, tham số, bốn đầu và nguyên file WAV', function () {
    Http::fake(['*' => Http::response(mauAzure('dung'))]);
    $wav = wavMau(1.0);

    boChamThu()->cham($wav, CAU_AZURE);

    Http::assertSent(function (Request $r) use ($wav) {
        $dau = json_decode(base64_decode($r->header('Pronunciation-Assessment')[0]), true);

        return $r->url() === 'https://westeurope.stt.speech.microsoft.com/speech/recognition/conversation/cognitiveservices/v1?language=de-DE&format=detailed'
            && $r->method() === 'POST'
            && $r->header('Ocp-Apim-Subscription-Key')[0] === 'khoa-bi-mat-123'
            && $r->header('Accept')[0] === 'application/json'
            && $r->header('Content-Type')[0] === 'audio/wav; codecs=audio/pcm; samplerate=16000'
            && $r->body() === $wav
            && $dau === ['ReferenceText' => CAU_AZURE, 'GradingSystem' => 'HundredMark', 'Granularity' => 'Phoneme', 'Dimension' => 'Comprehensive', 'EnableMiscue' => 'True'];
    });
});

it('đầu Pronunciation-Assessment giữ nguyên chữ có dấu (UTF-8, không \u-escape)', function () {
    expect(base64_decode(BoChamAzure::dauChamPhatAm('Grüß Gott, schön!')))->toContain('"ReferenceText":"Grüß Gott, schön!"');
});

it('AZURE_SPEECH_ENDPOINT thắng vùng; thiếu cả hai thì không gọi', function () {
    config(['ai.azure.vung' => 'westeurope', 'ai.azure.goc' => 'https://ten.cognitiveservices.azure.com/stt/speech/recognition/conversation/cognitiveservices/v1/']);
    expect(ChonBoCham::diaChi())->toBe('https://ten.cognitiveservices.azure.com/stt/speech/recognition/conversation/cognitiveservices/v1');

    Http::fake();
    $kq = (new BoChamAzure('khoa', '', 15))->cham(wavMau(1.0), CAU_AZURE);
    expect($kq->trangThai)->toBe('error')->and($kq->lyDo)->toBe('config')->and($kq->daGui)->toBeFalse();
    Http::assertNothingSent();
});

it('sáu câu trả lời mẫu ra đúng kết quả', function () {
    $dung = BoChamAzure::docTraLoi(mauAzure('dung'));
    expect([$dung->trangThai, $dung->tong, $dung->chinhXac, $dung->troiChay, $dung->dayDu])->toBe(['ok', 94, 96, 91, 100])
        ->and(array_column($dung->tu, 'word'))->toBe(['ich', 'möchte', 'zwei', 'brötchen'])
        ->and(array_column($dung->tu, 'score'))->toBe([98, 95, 97, 95])
        ->and(array_unique(array_column($dung->tu, 'error')))->toBe(['none']);

    $sai = BoChamAzure::docTraLoi(mauAzure('sai_mot_tu'));
    expect($sai->tu[2])->toBe(['word' => 'zwei', 'score' => 35, 'error' => 'mispronounced'])->and($sai->tong)->toBe(80);

    $bo = BoChamAzure::docTraLoi(mauAzure('bo_mot_tu'));
    expect($bo->tu[3])->toBe(['word' => 'brötchen', 'score' => 0, 'error' => 'omitted'])->and($bo->dayDu)->toBe(75);

    $am = BoChamAzure::docTraLoi(mauAzure('co_am'));
    expect($am->tu[0]['phonemes'])->toHaveCount(7)->and($am->tu[0]['phonemes'][2])->toBe(['p' => 'ø', 'score' => 41])
        ->and($dung->tu[0])->not->toHaveKey('phonemes');

    expect([BoChamAzure::docTraLoi(mauAzure('im_lang'))->trangThai, BoChamAzure::docTraLoi(mauAzure('im_lang'))->lyDo])->toBe(['empty', 'silence'])
        ->and([BoChamAzure::docTraLoi(mauAzure('on'))->trangThai, BoChamAzure::docTraLoi(mauAzure('on'))->lyDo])->toBe(['empty', 'noise']);
});

it('Success mà không có NBest hay Words là im lặng; Error là lỗi; từ đọc thừa', function () {
    expect(BoChamAzure::docTraLoi(['RecognitionStatus' => 'Success'])->lyDo)->toBe('silence')
        ->and(BoChamAzure::docTraLoi(['RecognitionStatus' => 'Success', 'NBest' => [['Words' => []]]])->lyDo)->toBe('silence')
        ->and(BoChamAzure::docTraLoi(['RecognitionStatus' => 'Error'])->trangThai)->toBe('error')
        ->and(BoChamAzure::docTraLoi(['RecognitionStatus' => 'Success', 'NBest' => [['PronScore' => 50, 'Words' => [['Word' => 'und', 'AccuracyScore' => 0, 'ErrorType' => 'Insertion']]]]])->tu[0]['error'])->toBe('inserted');
});

it('lỗi HTTP và mạng ra đúng lý do; 401 ghi log cảnh báo nhưng không có khoá', function (int|string $traLoi, string $lyDo) {
    Log::spy();
    Http::fake(['*' => $traLoi === 'het-gio' ? Http::failedConnection() : Http::response(['error' => 'x'], $traLoi)]);

    $kq = boChamThu()->cham(wavMau(1.0), CAU_AZURE);

    expect($kq->trangThai)->toBe(KetQuaCham::LOI)->and($kq->lyDo)->toBe($lyDo)->and($kq->daGui)->toBeTrue();
    Log::shouldNotHaveReceived('warning', [Mockery::on(fn ($m) => str_contains((string) $m, 'khoa-bi-mat'))]);
    Log::shouldNotHaveReceived('warning', [Mockery::any(), Mockery::on(fn ($c) => str_contains((string) json_encode($c), 'khoa-bi-mat'))]);
})->with([
    '401' => [401, 'config'],
    '403' => [403, 'config'],
    '400' => [400, 'http'],
    '429' => [429, 'http'],
    '500' => [500, 'http'],
    'hết giờ' => ['het-gio', 'timeout'],
]);

it('trả 200 nhưng không phải JSON là lỗi http', function () {
    Http::fake(['*' => Http::response('<html>', 200)]);

    expect(boChamThu()->cham(wavMau(1.0), CAU_AZURE)->lyDo)->toBe('http');
});
