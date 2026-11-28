<?php

use App\Support\Ai\KhuonAi;
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

it('loại chưa có khuôn thì không nhận gì', function () {
    expect(KhuonAi::doc('chat', json_encode(['reply_de' => 'Hallo'])))->toBeNull()
        ->and(KhuonAi::jsonSchema('chat'))->toBeNull();
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
