<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 10 (phần Bài học AI): soạn, lưu theo người, dùng chung cache, xem lại, ghi điểm, xoá.

beforeEach(function () {
    $this->seed();
    batAi();
    $this->user = nguoiHocAi();
    $this->actingAs($this->user);
});

function soanBai(array $doi = []): Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/ai/bai-hoc', $doi + ['topic' => 'Ăn uống', 'level' => 'A2', 'words' => ['das Brot', 'trinken']]);
}

it('soạn bài: chủ đề và từ nằm trong thẻ input; lưu một dòng của người này; trả nội dung và bài đã lưu', function () {
    $r = soanBai()->assertOk()
        ->assertJsonPath('data.title', 'Bài học AI giả: Ăn uống')
        ->assertJsonPath('lesson.title', 'Bài học AI giả: Ăn uống')
        ->assertJsonPath('lesson.level', 'A2')
        ->assertJsonPath('lesson.score', null);

    expect(aiGia()->daNhan[0]->nguoiDung)->toContain('<input>Chủ đề: Ăn uống. Từ: das Brot, trinken</input>')->toStartWith('Soạn bài ngắn cấp A2:')
        ->and(DB::table('user_ai_lessons')->where('user_id', $this->user->id)->count())->toBe(1)
        ->and(DB::table('user_ai_lessons')->value('cache_hash'))->toBe(DB::table('ai_cache')->where('kind', 'lesson')->value('hash'))
        ->and($r->json('lesson.id'))->toBe((int) DB::table('user_ai_lessons')->value('id'));
});

it('không có từ thì để AI tự chọn; cùng đầu vào lần hai lấy từ cache, không thêm dòng', function () {
    soanBai(['words' => []])->assertOk();
    soanBai(['words' => []])->assertOk()->assertJsonPath('meta.cached', true);

    expect(aiGia()->daNhan)->toHaveCount(1)
        ->and(aiGia()->daNhan[0]->nguoiDung)->toContain('Từ: (tự chọn từ phù hợp)')
        ->and(DB::table('user_ai_lessons')->count())->toBe(1);
});

it('hai người cùng đầu vào dùng chung cache nhưng mỗi người một dòng', function () {
    soanBai()->assertOk();
    $this->actingAs(nguoiHocAi());
    soanBai()->assertOk()->assertJsonPath('meta.cached', true);

    expect(aiGia()->daNhan)->toHaveCount(1)
        ->and(DB::table('user_ai_lessons')->count())->toBe(2);
});

it('11 từ, từ có ký hiệu, từ trùng, trình độ lạ, chủ đề toàn ký hiệu: 422, không gọi AI', function (array $doi) {
    soanBai($doi)->assertUnprocessable();
    expect(aiGia()->daNhan)->toBe([]);
})->with([
    '11 từ' => [['words' => array_map(fn ($i) => 'Wort'.chr(96 + $i), range(1, 11))]],
    'ký hiệu' => [['words' => ['<b>x</b>']]],
    'trùng' => [['words' => ['Brot', 'Brot']]],
    'trình độ lạ' => [['level' => 'C2']],
    'chủ đề ký hiệu' => [['topic' => '!!! 123']],
    'từ quá dài' => [['words' => [str_repeat('a', 41)]]],
]);

it('xem lại, ghi điểm; danh sách chỉ có bài của mình, mới trước', function () {
    $id = soanBai()->json('lesson.id');
    soanBai(['topic' => 'Mua sắm']);
    $this->actingAs(nguoiHocAi());
    soanBai(['topic' => 'Đi lại']);
    $this->actingAs($this->user);

    $this->getJson("/api/v1/ai/bai-hoc/{$id}")->assertOk()->assertJsonPath('data.questions.0.answer', 1)->assertJsonPath('lesson.id', $id);
    $this->putJson("/api/v1/ai/bai-hoc/{$id}/ket-qua", ['score' => 80])->assertOk()->assertJsonPath('data.score', 80);
    expect(DB::table('user_ai_lessons')->where('id', $id)->value('completed_at'))->not->toBeNull();

    $ds = $this->getJson('/api/v1/ai/bai-hoc')->assertOk()->json('data');
    expect(array_column($ds, 'title'))->toEqualCanonicalizing(['Bài học AI giả: Ăn uống', 'Bài học AI giả: Mua sắm'])->and($ds)->toHaveCount(2);
});

it('điểm ngoài 0 đến 100 thì 422; bài của người khác 404 ở đọc, ghi điểm, xoá', function () {
    $id = soanBai()->json('lesson.id');
    $this->putJson("/api/v1/ai/bai-hoc/{$id}/ket-qua", ['score' => 101])->assertUnprocessable();
    $this->putJson("/api/v1/ai/bai-hoc/{$id}/ket-qua", ['score' => -1])->assertUnprocessable();

    $this->actingAs(User::factory()->create());
    $this->getJson("/api/v1/ai/bai-hoc/{$id}")->assertNotFound();
    $this->putJson("/api/v1/ai/bai-hoc/{$id}/ket-qua", ['score' => 50])->assertNotFound();
    $this->deleteJson("/api/v1/ai/bai-hoc/{$id}")->assertNotFound();

    expect(DB::table('user_ai_lessons')->count())->toBe(1);
});

it('sau 91 ngày và sau app:ai-don bài vẫn đọc được; xoá bài không xoá mục cache', function () {
    $id = soanBai()->json('lesson.id');
    $this->travel(91)->days();
    Artisan::call('app:ai-don');

    $this->getJson("/api/v1/ai/bai-hoc/{$id}")->assertOk()->assertJsonPath('data.title', 'Bài học AI giả: Ăn uống');
    $this->deleteJson("/api/v1/ai/bai-hoc/{$id}")->assertNoContent();

    expect(DB::table('user_ai_lessons')->count())->toBe(0)
        ->and(DB::table('ai_cache')->where('kind', 'lesson')->count())->toBe(1);
    $this->getJson("/api/v1/ai/bai-hoc/{$id}")->assertNotFound();
});
