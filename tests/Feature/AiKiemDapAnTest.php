<?php

use App\Models\User;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 10 (phần Kiểm đáp án khác mẫu): theo câu bài tập điền từ, sửa lỗi, hoặc theo thẻ đang luyện gõ.

beforeEach(function () {
    $this->seed();
    $this->m = (new NoiDungThuSeeder)->tao()->taoLuyenTap();
    batAi();
    $this->actingAs(nguoiHocAi());
});

it('câu điền từ: gửi đáp án mẫu, đề đã bỏ dấu tô đậm, câu trả lời; nhật ký ngữ cảnh exercise', function () {
    $this->postJson('/api/v1/ai/kiem-dap-an', ['exercise_id' => $this->m->cau['lb3:4'], 'text' => 'eine'])->assertOk()
        ->assertJsonStructure(['data' => ['ok', 'why', 'better'], 'meta']);

    expect(aiGia()->daNhan[0]->nguoiDung)->toBe('Đáp án mẫu: die. Câu hỏi: Ich lese ____ Zeitung.. Người học trả lời: <input>eine</input>. Có đúng không?')
        ->and(DB::table('ai_requests')->where('kind', 'answer')->first(['context_type', 'context_id']))
        ->toEqual((object) ['context_type' => 'exercise', 'context_id' => $this->m->cau['lb3:4']]);
});

it('câu sửa lỗi cũng dùng được; theo thẻ: đáp án mẫu là mặt trước, câu hỏi là nghĩa', function () {
    $this->postJson('/api/v1/ai/kiem-dap-an', ['exercise_id' => $this->m->cau['dx:g1:1A:2'], 'text' => 'Den Frau heißt Anna.'])->assertOk();
    $this->postJson('/api/v1/ai/kiem-dap-an', ['card_id' => theMau('das Brötchen'), 'text' => 'die Semmel'])->assertOk();

    expect(aiGia()->daNhan[1]->nguoiDung)->toContain('Đáp án mẫu: das Brötchen. Câu hỏi: Viết từ tiếng Đức có nghĩa: bánh mì nhỏ (ổ tròn).')
        ->and(DB::table('ai_requests')->where('context_type', 'card')->value('context_id'))->toBe(theMau('das Brötchen'));
});

it('trả lời đã khớp mẫu thì báo ai_dap_an_khop_mau, không gọi AI, không ghi nhật ký', function () {
    $this->postJson('/api/v1/ai/kiem-dap-an', ['exercise_id' => $this->m->cau['lb3:4'], 'text' => 'die'])->assertUnprocessable()->assertJsonPath('code', 'ai_dap_an_khop_mau');
    $this->postJson('/api/v1/ai/kiem-dap-an', ['card_id' => theMau('das Brötchen'), 'text' => 'das Broetchen'])->assertUnprocessable()->assertJsonPath('code', 'ai_dap_an_khop_mau');

    expect(aiGia()->daNhan)->toBe([])->and(DB::table('ai_requests')->count())->toBe(0);
});

it('dạng sắp xếp thì ai_khong_ap_dung; câu nháp, câu không có, thẻ riêng của người khác thì 404', function () {
    $this->postJson('/api/v1/ai/kiem-dap-an', ['exercise_id' => $this->m->cau['dx:g1:1A:3'], 'text' => 'Im Garten spielt das Kind'])->assertUnprocessable()->assertJsonPath('code', 'ai_khong_ap_dung');
    $this->postJson('/api/v1/ai/kiem-dap-an', ['exercise_id' => $this->m->cau['lb3:nhap'], 'text' => 'abc'])->assertNotFound();
    $this->postJson('/api/v1/ai/kiem-dap-an', ['exercise_id' => 999999, 'text' => 'abc'])->assertNotFound();

    $m = new NoiDungThuSeeder;
    $m->chuDe = $this->m->chuDe;
    $m->the = $this->m->the;
    $m->themThe('t-banh.v', 'der Hefezopf', 'noun', 'die Hefezöpfe', 2, 2, 2, ['vi' => 'bánh mì bện'], [], 'der', chu: User::factory()->create()->id);
    $this->postJson('/api/v1/ai/kiem-dap-an', ['card_id' => theMau('der Hefezopf'), 'text' => 'Zopf'])->assertNotFound();

    expect(aiGia()->daNhan)->toBe([]);
});

it('thiếu cả hai mã, có cả hai mã, mã là chữ thì 422', function (array $than) {
    $this->postJson('/api/v1/ai/kiem-dap-an', $than + ['text' => 'eine'])->assertUnprocessable();
    expect(aiGia()->daNhan)->toBe([]);
})->with([
    'thiếu' => [[]],
    'cả hai' => [['exercise_id' => 1, 'card_id' => 1]],
    'chữ' => [['card_id' => 'abc']],
]);
