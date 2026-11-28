<?php

use App\Models\User;
use App\Support\Hoc\LapLich;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Chức năng AI đầu tiên chạy trọn tám chặng: Giải thích từ trên thẻ (POST /api/v1/ai/giai-thich-tu).

beforeEach(function () {
    $this->seed();
    batAi();
});

it('trả đúng khuôn và số lượt; nhật ký ghi loại, thẻ, token và chi phí quy đổi', function () {
    (new NoiDungThuSeeder)->tao();
    $user = nguoiHocAi();
    $id = theMau('das Brötchen');

    $r = $this->actingAs($user)->postJson('/api/v1/ai/giai-thich-tu', ['card_id' => $id])->assertOk();

    expect(array_keys($r->json('data')))->toBe(['meaning', 'usage', 'examples', 'common_mistakes'])
        ->and($r->json('data.meaning'))->toContain('das Brötchen')
        ->and($r->json('meta'))->toBe([
            'cached' => false, 'limit' => 50, 'used' => 1, 'remaining' => 49,
            'reset_at' => LapLich::dauNgayMai(now()->getTimestamp(), $user->timezone),
        ]);

    $dong = DB::table('ai_requests')->sole();
    expect($dong->user_id)->toBe($user->id)
        ->and($dong->kind)->toBe('word')
        ->and($dong->provider)->toBe('fake')
        ->and($dong->context_type)->toBe('card')
        ->and((int) $dong->context_id)->toBe($id)
        ->and($dong->status)->toBe('ok')
        ->and((int) $dong->cache_hit)->toBe(0)
        ->and((int) $dong->input_tokens)->toBe(1500)
        ->and((int) $dong->output_tokens)->toBe(500)
        ->and((int) $dong->cost_micro_usd)->toBe(1125)
        ->and($dong->input_excerpt)->toBe('das Brötchen')
        ->and($dong->model)->toBe('gemini-3.1-flash-lite')
        ->and($dong->prompt_id)->toBe(DB::table('ai_prompts')->where('kind', 'word')->value('id'));
});

it('AI nhận đúng từ và nghĩa tiếng Việt của thẻ, gói trong prompt đang dùng', function () {
    (new NoiDungThuSeeder)->tao();
    $this->actingAs(nguoiHocAi());

    giaiThich(theMau('das Brötchen'))->assertOk();

    $yc = aiGia()->daNhan[0];
    expect($yc->loai)->toBe('word')
        ->and($yc->thamSo)->toBe(['word' => 'das Brötchen', 'meaning' => 'bánh mì nhỏ (ổ tròn)', 'level' => 'A1'])
        ->and($yc->nguoiDung)->toBe('Giải thích từ das Brötchen (bánh mì nhỏ (ổ tròn)); nêu lỗi người Việt hay mắc.')
        ->and($yc->heThong)->toContain('trình độ người học A1')
        ->and($yc->maxTokens)->toBe(700)
        ->and($yc->khuon)->not->toBeNull();
});

it('trình độ trong prompt: trình độ tự chọn, chưa chọn thì cấp của thẻ, không có thì A1', function () {
    (new NoiDungThuSeeder)->tao();
    $tuChon = nguoiHocAi();
    $this->actingAs($tuChon)->putJson('/api/v1/toi/ho-so', ['level' => 3])->assertOk();
    giaiThich(theMau('das Brötchen'))->assertOk();

    $this->actingAs(nguoiHocAi());
    giaiThich(theMau('das Mehl'))->assertOk();
    giaiThich(theMau('süß'))->assertOk();

    expect(array_map(fn ($yc) => $yc->thamSo['level'], aiGia()->daNhan))->toBe(['B1', 'A2', 'A1']);
});

it('thẻ không có, thẻ nháp, thẻ riêng của người khác: 404 và không gọi AI', function () {
    $khac = User::factory()->create();
    (new NoiDungThuSeeder)->tao($khac->id);
    $this->actingAs(nguoiHocAi());

    giaiThich(999999)->assertNotFound()->assertJson(['code' => 'khong_thay']);
    giaiThich(theMau('der Teig'))->assertNotFound();
    giaiThich(theMau('der Hefezopf'))->assertNotFound();

    expect(aiGia()->daNhan)->toBe([])->and(DB::table('ai_requests')->count())->toBe(0);
});

it('mã thẻ thiếu hay sai dạng: 422; chưa đăng nhập: 401', function () {
    $this->postJson('/api/v1/ai/giai-thich-tu', ['card_id' => 1])->assertUnauthorized()->assertJson(['code' => 'chua_dang_nhap']);

    $this->actingAs(nguoiHocAi());
    $this->postJson('/api/v1/ai/giai-thich-tu', [])->assertUnprocessable()->assertJsonValidationErrors('card_id');
    $this->postJson('/api/v1/ai/giai-thich-tu', ['card_id' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('card_id');
    $this->postJson('/api/v1/ai/giai-thich-tu', ['card_id' => 0])->assertUnprocessable();

    expect(aiGia()->daNhan)->toBe([]);
});

it('GET /toi có khối ai: sẵn sàng, hạn mức, đã dùng, giờ có lượt mới', function () {
    (new NoiDungThuSeeder)->tao();
    $user = nguoiHocAi();
    $this->actingAs($user);
    giaiThich(theMau('das Brötchen'))->assertOk();

    $this->getJson('/api/v1/toi')->assertOk()->assertJsonPath('data.ai', [
        'state' => 'ready', 'consent_version' => '2026-11', 'limit' => 50, 'used' => 1,
        'reset_at' => LapLich::dauNgayMai(now()->getTimestamp(), $user->timezone),
    ]);
});
