<?php

use App\Support\Ai\BoNoi\BoNoiGia;
use App\Support\Ai\BoNoi\YeuCauAi;
use App\Support\Ai\KhuonAi;
use App\Support\Ai\SoanLoiNhac;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 2: kết quả AI sai khuôn bị bỏ. Phần đường ống (không hiện, không lưu cache, không trừ lượt) ở cuối file.

function ketQuaWordMau(array $doi = []): array
{
    return array_merge([
        'meaning' => 'ăn',
        'usage' => 'Dùng khi nói về bữa ăn.',
        'examples' => [['de' => 'Wir essen um sieben.', 'vi' => 'Chúng tôi ăn lúc bảy giờ.']],
        'common_mistakes' => ['Nhầm essen với Essen (danh từ).'],
    ], $doi);
}

it('kết quả đúng khuôn được nhận và giữ nguyên chữ', function () {
    expect(KhuonAi::doc('word', json_encode(ketQuaWordMau(), JSON_UNESCAPED_UNICODE)))->toBe(ketQuaWordMau());
});

it('các dạng hỏng bị bỏ', function (string $chu) {
    expect(KhuonAi::doc('word', $chu))->toBeNull();
})->with([
    'chữ thường' => ['Essen nghĩa là ăn.'],
    'JSON thiếu trường' => [json_encode(['meaning' => 'ăn', 'usage' => '', 'examples' => []])],
    'sai kiểu' => [json_encode(ketQuaWordMau(['examples' => 'Wir essen.']))],
    'chuỗi quá dài' => [json_encode(ketQuaWordMau(['meaning' => str_repeat('a', 601)]))],
    'bị cắt giữa chừng' => [substr((string) json_encode(ketQuaWordMau()), 0, 40)],
    'rỗng' => [''],
    'JSON là mảng' => [json_encode([ketQuaWordMau()])],
    'chuỗi bắt buộc rỗng' => [json_encode(ketQuaWordMau(['meaning' => '   ']))],
    'phần tử ví dụ thiếu bản dịch' => [json_encode(ketQuaWordMau(['examples' => [['de' => 'Wir essen.']]]))],
    'lỗi hay gặp không phải chuỗi' => [json_encode(ketQuaWordMau(['common_mistakes' => [1]]))],
]);

it('chấp nhận rào ```json bọc ngoài, bỏ khoá lạ, cắt danh sách dài, giữ nguyên thẻ HTML như chữ', function () {
    $kq = ketQuaWordMau([
        'thua' => 'bỏ',
        'examples' => array_fill(0, 8, ['de' => 'Wir essen.', 'vi' => 'Chúng tôi ăn.', 'them' => 'x']),
        'common_mistakes' => ['<b>x</b><script>alert(1)</script>'],
        'usage' => '',
    ]);
    $doc = KhuonAi::doc('word', "```json\n".json_encode($kq, JSON_UNESCAPED_UNICODE)."\n```");

    expect($doc)->not->toBeNull()
        ->and(array_keys($doc))->toBe(['meaning', 'usage', 'examples', 'common_mistakes'])
        ->and($doc['examples'])->toHaveCount(5)
        ->and($doc['examples'][0])->toBe(['de' => 'Wir essen.', 'vi' => 'Chúng tôi ăn.'])
        ->and($doc['common_mistakes'])->toBe(['<b>x</b><script>alert(1)</script>'])
        ->and($doc['usage'])->toBe('');
});

it('loại không có khuôn thì không nhận gì', function () {
    expect(KhuonAi::doc('pron', json_encode(['answer' => 'x'])))->toBeNull()
        ->and(KhuonAi::jsonSchema('pron'))->toBeNull();
});

it('JSON Schema của word chỉ dùng type, properties, required, items', function () {
    $s = KhuonAi::jsonSchema('word');

    expect($s['type'])->toBe('object')
        ->and($s['required'])->toBe(['meaning', 'usage', 'examples', 'common_mistakes'])
        ->and($s['properties']['examples'])->toBe(['type' => 'array', 'items' => [
            'type' => 'object', 'properties' => ['de' => ['type' => 'string'], 'vi' => ['type' => 'string']], 'required' => ['de', 'vi'],
        ]])
        ->and($s['properties']['common_mistakes'])->toBe(['type' => 'array', 'items' => ['type' => 'string']]);
    expect(json_encode($s))->not->toContain('maxLength')->not->toContain('maxItems');
});

// Phần đường ống: kết quả hỏng không hiện, không lưu cache, không trừ lượt; nhật ký vẫn ghi token và chi phí.

it('đường ống: mỗi dạng hỏng trả ai_tra_loi_hong, không lưu cache, không trừ lượt, nhật ký error có chi phí', function () {
    $this->seed();
    (new NoiDungThuSeeder)->tao();
    batAi();
    $this->actingAs(nguoiHocAi());
    $hong = ['Essen nghĩa là ăn.', '{"meaning":"ăn"}', '[1,2]', '', '{"meaning":', json_encode(ketQuaWordMau(['meaning' => str_repeat('a', 601)]))];
    aiGia()->kich($hong);

    foreach ($hong as $chu) {
        giaiThich(theMau('das Brötchen'))->assertStatus(502)->assertJson(['code' => 'ai_tra_loi_hong', 'message' => 'AI trả lời chưa đúng mẫu, thử lại.'])
            ->assertJsonMissingPath('data');
    }

    expect(DB::table('ai_cache')->count())->toBe(0)
        ->and(DB::table('ai_requests')->where('status', 'error')->where('cost_micro_usd', 1125)->count())->toBe(6)
        ->and($this->getJson('/api/v1/toi')->json('data.ai.used'))->toBe(0);
});

// G2 tuần 2: khuôn của chín loại còn lại và bốn kiểu trường mới

/** Mẫu đúng khuôn của AI giả cho một loại, đã giải mã. */
function mauGia(string $loai): array
{
    $thamSo = array_fill_keys(SoanLoiNhac::THAM_SO[$loai], 'Haus') + ['level' => 'A2'];

    return json_decode(BoNoiGia::mau(new YeuCauAi($loai, 'm', 'h', 'n', 500, 0.2, null, 20.0, $thamSo)), true);
}

it('mẫu của AI giả qua khuôn ở cả 10 loại', function () {
    expect(array_keys(KhuonAi::KHUON))->toEqualCanonicalizing(array_keys(SoanLoiNhac::THAM_SO));

    foreach (array_keys(KhuonAi::KHUON) as $loai) {
        expect(KhuonAi::doc($loai, json_encode(mauGia($loai), JSON_UNESCAPED_UNICODE)))->not->toBeNull($loai);
    }
});

it('kiểu logic chỉ nhận true, false; số nguyên trong khoảng; chọn trong danh sách', function (string $loai, string $truong, mixed $giaTri) {
    $kq = mauGia($loai);
    $kq[$truong] = $giaTri;

    expect(KhuonAi::doc($loai, json_encode($kq)))->toBeNull();
})->with([
    'logic là chuỗi' => ['answer', 'ok', 'true'],
    'logic là số' => ['sentence', 'word_ok', 1],
    'chọn ngoài danh sách' => ['sentence', 'verdict', 'dung_mot_nua'],
    'chọn sai kiểu' => ['lookup', 'gender', 1],
    'giống lạ' => ['lookup', 'gender', 'x'],
    'danh sách thiếu phần tử tối thiểu' => ['lookup', 'meanings_vi', []],
    'bài đọc rỗng' => ['lesson', 'text', []],
]);

it('chọn được rỗng nếu danh sách cho phép, có cắt khoảng trắng; logic false vẫn là giá trị đúng', function () {
    $lookup = mauGia('lookup');
    $lookup['gender'] = ' f ';
    $answer = mauGia('answer');
    $answer['ok'] = false;
    $tra = mauGia('lookup');
    $tra['gender'] = '';

    expect(KhuonAi::doc('lookup', json_encode($lookup))['gender'])->toBe('f')
        ->and(KhuonAi::doc('lookup', json_encode($tra))['gender'])->toBe('')
        ->and(KhuonAi::doc('answer', json_encode($answer))['ok'])->toBeFalse();
});

it('số đáp án của câu hỏi bài học: số thực, chuỗi số, ngoài khoảng, trỏ ra ngoài lựa chọn đều hỏng', function (mixed $dapAn) {
    $kq = mauGia('lesson');
    $kq['questions'][0]['answer'] = $dapAn;

    expect(KhuonAi::doc('lesson', json_encode($kq)))->toBeNull();
})->with([1.5, '1', -1, 4, 3]);

it('câu hỏi bài học có ít hơn 2 lựa chọn thì hỏng; đáp án 0 là đúng', function () {
    $kq = mauGia('lesson');
    $kq['questions'][0]['answer'] = 0;
    $mot = $kq;
    $mot['questions'][0]['choices'] = ['Kaffee'];

    expect(KhuonAi::doc('lesson', json_encode($kq)))->not->toBeNull()
        ->and(KhuonAi::doc('lesson', json_encode($mot)))->toBeNull();
});

it('JSON Schema của 10 loại: boolean, integer, enum đúng chỗ; giống của từ không gửi enum vì có chuỗi rỗng', function () {
    foreach (array_keys(KhuonAi::KHUON) as $loai) {
        expect(KhuonAi::jsonSchema($loai)['type'])->toBe('object', $loai);
    }

    $cau = KhuonAi::jsonSchema('sentence')['properties'];
    $bai = KhuonAi::jsonSchema('lesson')['properties'];

    expect($cau['word_ok'])->toBe(['type' => 'boolean'])
        ->and($cau['verdict'])->toBe(['type' => 'string', 'enum' => ['dung', 'gan_dung', 'sai']])
        ->and(KhuonAi::jsonSchema('answer')['properties']['ok'])->toBe(['type' => 'boolean'])
        ->and($bai['questions']['items']['properties']['answer'])->toBe(['type' => 'integer'])
        ->and(KhuonAi::jsonSchema('lookup')['properties']['gender'])->toBe(['type' => 'string'])
        ->and(KhuonAi::jsonSchema('lookup')['properties']['forms']['items']['required'])->toBe(['label', 'form']);
});

it('loại có trong danh sách không gửi khuôn thì jsonSchema trả null, khuôn vẫn kiểm', function () {
    expect(KhuonAi::khongGuiKhuon())->toBeArray();

    foreach (KhuonAi::khongGuiKhuon() as $loai) {
        expect(KhuonAi::jsonSchema($loai))->toBeNull()
            ->and(KhuonAi::doc($loai, json_encode(mauGia($loai))))->not->toBeNull();
    }
});
