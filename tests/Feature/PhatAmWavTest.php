<?php

use App\Support\Ai\TuChoiAi;
use App\Support\PhatAm\Wav;

// Mục tự kiểm 13: file ghi âm app gửi lên phải là WAV PCM 16 bit, 16 kHz, một kênh; dài quá, lớn quá, sai định dạng đều 422.

function loiWav(string $base64, int $giay = 15): ?string
{
    try {
        Wav::doc($base64, $giay);

        return null;
    } catch (TuChoiAi $e) {
        return $e->ma.':'.$e->trangThai;
    }
}

it('file đúng: độ dài, số byte, có tiếng', function () {
    $w = Wav::doc(wav64(2.0), 15);

    expect($w->ms)->toBe(2000)->and($w->bytes)->toBe(44 + 64000)->and($w->imLang)->toBeFalse();
});

it('có khối LIST nằm trước khối data vẫn đọc đúng', function () {
    $w = Wav::doc(base64_encode(wavMau(1.0, truocData: true)), 15);

    expect($w->ms)->toBe(1000)->and($w->imLang)->toBeFalse();
});

it('sai định dạng: 8 kHz, hai kênh, 8 bit, không phải PCM, thiếu data, không phải base64, MP3 đổi đuôi, rỗng', function (string $base64) {
    expect(loiWav($base64))->toBe('phat_am_sai_dinh_dang:422');
})->with([
    '8 kHz' => fn () => base64_encode(wavMau(1.0, tanSo: 8000)),
    'hai kênh' => fn () => base64_encode(wavMau(1.0, kenh: 2)),
    '8 bit' => fn () => base64_encode(wavMau(1.0, bit: 8)),
    'không phải PCM' => fn () => base64_encode(wavMau(1.0, ma: 3)),
    'thiếu data' => fn () => base64_encode(substr(wavMau(0.01), 0, 36)),
    'không phải base64' => fn () => 'đây không phải base64!!',
    'MP3 đổi đuôi' => fn () => base64_encode("ID3\x04\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x00", 50)),
    'data rỗng' => fn () => base64_encode(wavMau(0.0)),
]);

it('15,4 giây qua; 15,6 giây là quá dài', function () {
    expect(loiWav(wav64(15.4)))->toBeNull()
        ->and(loiWav(wav64(15.6)))->toBe('phat_am_qua_dai:422')
        ->and(loiWav(wav64(9.0), 8))->toBe('phat_am_qua_dai:422');
});

it('chuỗi quá dài bị chặn trước khi giải mã', function () {
    $chuoi = str_repeat('A', Wav::doDaiToiDa() + 1);

    expect(loiWav($chuoi))->toBe('phat_am_qua_lon:422');
});

it('im lặng, tiếng rất nhỏ và bản ghi dưới 0,3 giây là im lặng', function () {
    expect(Wav::doc(wav64(2.0, 0), 15)->imLang)->toBeTrue()
        ->and(Wav::doc(wav64(2.0, 300), 15)->imLang)->toBeTrue()
        ->and(Wav::doc(wav64(0.25), 15)->imLang)->toBeTrue()
        ->and(Wav::doc(wav64(0.5), 15)->imLang)->toBeFalse();
});
