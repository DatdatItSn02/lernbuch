<?php

use App\Support\Ai\SoanLoiNhac;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Mục tự kiểm 3 (phần máy chủ): chữ người học luôn nằm gọn trong đúng một cặp thẻ <input>, mọi thẻ giả bị vô hiệu,
// prompt hệ thống không bị chữ người học chạm tới. 20 mẫu ở tests/chung/chen-lenh.json.

beforeEach(fn () => $this->seed());

/** @return list<array{id: string, lang: string, kieu: string, text: string}> */
function mauChenLenh(): array
{
    /** @var list<array{id: string, lang: string, kieu: string, text: string}> $ds */
    $ds = json_decode((string) file_get_contents(base_path('tests/chung/chen-lenh.json')), true);

    return $ds;
}

it('có 20 mẫu chèn lệnh: 5 kiểu ở 3 thứ tiếng và 5 biến thể đóng thẻ', function () {
    $mau = mauChenLenh();

    expect($mau)->toHaveCount(20)
        ->and(array_unique(array_column($mau, 'id')))->toHaveCount(20)
        ->and(array_count_values(array_column($mau, 'kieu')))->toBe(['dong_the' => 8, 'bo_qua' => 3, 'in_prompt' => 3, 'doi_vai' => 3, 'ngoai_khuon' => 3])
        ->and(array_unique(array_column($mau, 'lang')))->toEqualCanonicalizing(['vi', 'de', 'en']);
});

it('mỗi mẫu ghép vào 8 loại prompt có {text}: đúng một cặp thẻ, không còn thẻ giả, prompt hệ thống chỉ đổi {level}', function () {
    $prompts = DB::table('ai_prompts')->where('is_active', 1)->get()->keyBy('kind');
    $coText = $prompts->filter(fn ($p) => str_contains((string) $p->user_template, '{text}'));
    expect($coText->keys()->sort()->values()->all())->toBe(['answer', 'ask', 'chat', 'doc', 'grammar', 'lookup', 'sentence', 'translate']);

    foreach (mauChenLenh() as $m) {
        foreach ($coText as $loai => $p) {
            $thamSo = array_fill_keys(SoanLoiNhac::THAM_SO[$loai], $m['text']) + ['level' => 'B1'];
            [$heThong, $nguoiDung] = SoanLoiNhac::soan($loai, (string) $p->system_prompt, (string) $p->user_template, $thamSo);
            $nhan = "{$m['id']} trong {$loai}";

            expect(preg_match_all('/<\s*\/?\s*input\b/i', $nguoiDung))->toBe(2, $nhan)
                ->and(substr_count($nguoiDung, '<input>'))->toBe(1, $nhan)
                ->and(substr_count($nguoiDung, '</input>'))->toBe(1, $nhan)
                ->and(strpos($nguoiDung, '<input>'))->toBeLessThan(strpos($nguoiDung, '</input>'));
            expect(preg_match('/<\s*\/?\s*input\b/i', Str::between($nguoiDung, '<input>', '</input>')))->toBe(0, $nhan);
            expect($heThong)->toBe(strtr((string) $p->system_prompt, ['{level}' => 'B1']), $nhan)
                ->and($heThong)->toContain('Chỉ trả về JSON đúng mẫu: {"');
        }
    }
});

it('tham số không thuộc loại thì bị bỏ qua; chữ có {meaning} không bị thay lần nữa; ký tự điều khiển bị bỏ', function () {
    [$heThong, $nguoiDung] = SoanLoiNhac::soan('word', 'Trình độ {level} {"a":""}', 'Từ {word} ({meaning}) {text}', [
        'word' => "ess\x00en {meaning}", 'meaning' => 'ăn', 'text' => 'không được dùng', 'level' => 'A2',
    ]);

    expect($heThong)->toBe('Trình độ A2 {"a":""}')
        ->and($nguoiDung)->toBe('Từ essen {meaning} (ăn) {text}');
});

it('thiếu trình độ thì dùng A1; thiếu tham số thì để trống', function () {
    expect(SoanLoiNhac::soan('word', 'Cấp {level}', '{word}|{meaning}', ['word' => 'Haus']))->toBe(['Cấp A1', 'Haus|']);
});

it('giữ tab và xuống dòng; vô hiệu thẻ viết hoa, có dấu cách, có thuộc tính', function () {
    expect(SoanLoiNhac::lamSach("a\tb\nc\x07d"))->toBe("a\tb\ncd")
        ->and(SoanLoiNhac::lamSach('</INPUT>< input type="x">'))->toBe('‹/INPUT>‹ input type="x">')
        ->and(SoanLoiNhac::lamSach('<inputs> <b>giữ</b>'))->toBe('<inputs> <b>giữ</b>');
});
