<?php

use App\Support\Ai\DoanNguPhap;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 10 (phần Hỏi AI trong bài ngữ pháp): giải thích một phần của bài, hoặc hỏi câu của mình (300 ký tự).

beforeEach(function () {
    $this->seed();
    $this->m = (new NoiDungThuSeeder)->tao()->taoLuyenTap();
    batAi();
    $this->actingAs(nguoiHocAi());
});

it('giải thích phần 1 (Bảng mạo từ): chữ gửi đi có bảng và phần tóm tắt, không có phần Ví dụ', function () {
    $this->postJson('/api/v1/ai/ngu-phap/giai-thich', ['slug' => 'g-A1-22', 'section' => 1])->assertOk()
        ->assertJsonStructure(['data' => ['summary', 'points', 'examples'], 'meta' => ['remaining']]);

    $gui = aiGia()->daNhan[0]->nguoiDung;
    expect($gui)->toContain('Bảng mạo từ')
        ->toContain(' | giống đực | giống cái | giống trung')
        ->toContain('Nominativ | der | die | das')
        ->toContain('Học danh từ luôn kèm mạo từ')
        ->not->toContain('Das Brot ist frisch')
        ->and(DB::table('ai_requests')->where('kind', 'doc')->where('context_type', 'grammar_lesson')->value('context_id'))->toBe($this->m->bai['g-A1-22']);
});

it('phần 0 (Ví dụ) có câu ví dụ kèm nghĩa; bài không có h2 thì phần 0 là cả bài, có cặp k: x', function () {
    $this->postJson('/api/v1/ai/ngu-phap/giai-thich', ['slug' => 'g-A1-22', 'section' => 0])->assertOk();
    $this->postJson('/api/v1/ai/ngu-phap/giai-thich', ['slug' => 'g-A1-23', 'section' => 0])->assertOk();

    expect(aiGia()->daNhan[0]->nguoiDung)->toContain('Das Brot ist frisch. (Bánh mì còn tươi.)')->not->toContain('Nominativ')
        ->and(aiGia()->daNhan[1]->nguoiDung)->toContain('-e: der Tisch → die Tische')->toContain('das Brötchen → die Brötchen');
});

it('phần ngoài phạm vi, số âm, chữ thì 422; bài nháp và bài không có thì 404; không gọi AI', function () {
    $this->postJson('/api/v1/ai/ngu-phap/giai-thich', ['slug' => 'g-A1-22', 'section' => 2])->assertUnprocessable()->assertJsonValidationErrors('section');
    $this->postJson('/api/v1/ai/ngu-phap/giai-thich', ['slug' => 'g-A1-23', 'section' => 1])->assertUnprocessable();
    $this->postJson('/api/v1/ai/ngu-phap/giai-thich', ['slug' => 'g-A1-22', 'section' => -1])->assertUnprocessable();
    $this->postJson('/api/v1/ai/ngu-phap/giai-thich', ['slug' => 'g-A1-22', 'section' => 'a'])->assertUnprocessable();
    $this->postJson('/api/v1/ai/ngu-phap/giai-thich', ['slug' => 'g-A1-99', 'section' => 0])->assertNotFound();
    $this->postJson('/api/v1/ai/ngu-phap/giai-thich', ['slug' => 'khong-co', 'section' => 0])->assertNotFound();
    $this->postJson('/api/v1/ai/ngu-phap/hoi', ['slug' => 'khong-co', 'question' => 'Vì sao?'])->assertNotFound();

    expect(aiGia()->daNhan)->toBe([]);
});

it('cắt chữ của phần còn 1.500 ký tự, bỏ dấu ** tô đậm', function () {
    $dai = [['t' => 'h2', 'x' => 'Dài'], ['t' => 'p', 'x' => '**đậm** '.str_repeat('ab ', 1000)]];

    $chu = DoanNguPhap::chu($dai, 0);
    expect(mb_strlen($chu))->toBe(1500)
        ->and($chu)->toStartWith("Dài\nđậm ab")
        ->and(DoanNguPhap::chu([], 0))->toBeNull()
        ->and(DoanNguPhap::chu([['t' => 'h2', 'x' => 'A']], 1))->toBeNull();
});

it('hỏi câu của mình: chữ gửi kèm tên bài; hỏi lại cùng phần giải thích thì lấy từ cache, câu hỏi tự do thì không', function () {
    $this->postJson('/api/v1/ai/ngu-phap/hoi', ['slug' => 'g-A1-23', 'question' => 'Khi nào thêm -n?'])->assertOk()
        ->assertJsonStructure(['data' => ['answer', 'examples']]);
    $this->postJson('/api/v1/ai/ngu-phap/hoi', ['slug' => 'g-A1-23', 'question' => 'Khi nào thêm -n?'])->assertOk()->assertJsonPath('meta.cached', false);
    $this->postJson('/api/v1/ai/ngu-phap/giai-thich', ['slug' => 'g-A1-23', 'section' => 0])->assertOk()->assertJsonPath('meta.cached', false);
    $this->postJson('/api/v1/ai/ngu-phap/giai-thich', ['slug' => 'g-A1-23', 'section' => 0])->assertOk()->assertJsonPath('meta.cached', true);

    expect(aiGia()->daNhan)->toHaveCount(3)
        ->and(aiGia()->daNhan[0]->nguoiDung)->toContain('<input>Bài ngữ pháp «Plural  ·  Số nhiều». Khi nào thêm -n?</input>')
        ->and(DB::table('ai_requests')->where('kind', 'ask')->value('input_excerpt'))->toBe('Khi nào thêm -n?');
});

it('câu hỏi rỗng, quá 300 ký tự, toàn ký hiệu thì 422', function (mixed $hoi) {
    $this->postJson('/api/v1/ai/ngu-phap/hoi', ['slug' => 'g-A1-23', 'question' => $hoi])->assertUnprocessable()->assertJsonValidationErrors('question');
    expect(aiGia()->daNhan)->toBe([]);
})->with(['', '   ', '?!… 123 🙂', str_repeat('a', 301)]);
