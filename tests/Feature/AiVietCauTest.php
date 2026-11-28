<?php

use App\Models\User;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 10 (phần Viết câu): chọn thẻ để đặt câu, AI chấm, mọi lượt chấm được lưu.

beforeEach(function () {
    $this->seed();
    $this->m = (new NoiDungThuSeeder)->tao();
    batAi();
    $this->user = nguoiHocAi();
    $this->actingAs($this->user);
});

it('chấm câu: gửi từ bắt buộc và nghĩa; lưu writing_submissions đủ cột; trả saved', function () {
    $brot = theMau('das Brot');
    $r = $this->postJson('/api/v1/ai/viet-cau', ['card_id' => $brot, 'text' => 'Ich esse jeden Tag Brot.'])->assertOk()
        ->assertJsonPath('data.verdict', 'gan_dung')
        ->assertJsonPath('data.word_ok', true);

    $dong = DB::table('writing_submissions')->first();
    expect(aiGia()->daNhan[0]->nguoiDung)->toBe('Chấm câu. Từ bắt buộc: das Brot. Ý tiếng Việt: bánh mì. <input>Ich esse jeden Tag Brot.</input>')
        ->and($r->json('saved.id'))->toBe((int) $dong->id)
        ->and((int) $dong->user_id)->toBe($this->user->id)
        ->and((int) $dong->card_id)->toBe($brot)
        ->and($dong->kind)->toBe('sentence')
        ->and($dong->content)->toBe('Ich esse jeden Tag Brot.')
        ->and($dong->verdict)->toBe('gan_dung')
        ->and(json_decode($dong->result, true)['corrected'])->toContain('Ich esse')
        ->and((int) $dong->ai_request_id)->toBe((int) DB::table('ai_requests')->value('id'));
});

it('lượt lấy từ cache cũng được lưu, kèm mã dòng nhật ký của lượt đó', function () {
    $than = ['card_id' => theMau('das Brot'), 'text' => 'Das Brot ist frisch.'];
    $this->postJson('/api/v1/ai/viet-cau', $than)->assertOk();
    $this->postJson('/api/v1/ai/viet-cau', $than)->assertOk()->assertJsonPath('meta.cached', true);

    expect(aiGia()->daNhan)->toHaveCount(1)
        ->and(DB::table('writing_submissions')->count())->toBe(2)
        ->and(DB::table('writing_submissions')->pluck('ai_request_id')->unique())->toHaveCount(2);
});

it('thẻ không xem được (nháp, của người khác, không có) thì 404, không gọi AI, không lưu', function () {
    $m = new NoiDungThuSeeder;
    $m->chuDe = $this->m->chuDe;
    $m->the = $this->m->the;
    $m->themThe('t-banh.v', 'der Hefezopf', 'noun', 'die Hefezöpfe', 2, 2, 2, ['vi' => 'bánh mì bện'], [], 'der', chu: User::factory()->create()->id);

    $this->postJson('/api/v1/ai/viet-cau', ['card_id' => theMau('der Hefezopf'), 'text' => 'Der Hefezopf ist lecker.'])->assertNotFound();
    $this->postJson('/api/v1/ai/viet-cau', ['card_id' => 999999, 'text' => 'Hallo.'])->assertNotFound();

    expect(aiGia()->daNhan)->toBe([])->and(DB::table('writing_submissions')->count())->toBe(0);
});

it('GET bai-tap/viet-cau: mặc định 5 thẻ từ vựng; n ngoài khoảng bị kéo về 3 đến 10; ưu tiên thẻ đã học', function () {
    $this->getJson('/api/v1/bai-tap/viet-cau')->assertOk()->assertJsonCount(5, 'data');
    $this->getJson('/api/v1/bai-tap/viet-cau?n=1')->assertOk()->assertJsonCount(3, 'data');
    expect(count($this->getJson('/api/v1/bai-tap/viet-cau?n=999')->json('data')))->toBeLessThanOrEqual(10);
    $this->getJson('/api/v1/bai-tap/viet-cau?n=abc')->assertUnprocessable();

    DB::table('card_progress')->insert(['user_id' => $this->user->id, 'card_id' => theMau('das Mehl'), 'box' => 2]);
    expect($this->getJson('/api/v1/bai-tap/viet-cau?n=3')->json('data.0.front'))->toBe('das Mehl');
});
