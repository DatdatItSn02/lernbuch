<?php

use App\Support\Ai\BoNhoAi;
use App\Support\Ai\CaiDatAi;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 9: cache dùng chung. Trúng cache thì không gọi nhà cung cấp, không trừ lượt.

beforeEach(function () {
    $this->seed();
    $this->m = (new NoiDungThuSeeder)->tao();
    batAi();
});

it('hỏi lại cùng thẻ: chỉ gọi AI một lần, lần hai lấy từ cache, không trừ lượt, đếm số lần dùng', function () {
    $this->actingAs(nguoiHocAi());

    $mot = giaiThich(theMau('das Brötchen'))->assertOk()->assertJsonPath('meta.cached', false)->json('data');
    giaiThich(theMau('das Brötchen'))->assertOk()->assertJsonPath('meta.cached', true)->assertJsonPath('meta.used', 1)->assertJsonPath('data', $mot);

    expect(aiGia()->daNhan)->toHaveCount(1)
        ->and((int) DB::table('ai_cache')->value('hits'))->toBe(1)
        ->and(DB::table('ai_requests')->orderBy('id')->pluck('cache_hit')->map(fn ($v) => (int) $v)->all())->toBe([0, 1]);
});

it('người thứ hai cùng trình độ lấy từ cache; khác trình độ thì gọi lại', function () {
    $this->actingAs(nguoiHocAi());
    giaiThich(theMau('das Brötchen'))->assertOk();

    $this->actingAs(nguoiHocAi());
    giaiThich(theMau('das Brötchen'))->assertOk()->assertJsonPath('meta.cached', true);

    $this->actingAs(nguoiHocAi());
    $this->putJson('/api/v1/toi/ho-so', ['level' => 2])->assertOk();
    giaiThich(theMau('das Brötchen'))->assertOk()->assertJsonPath('meta.cached', false);

    expect(aiGia()->daNhan)->toHaveCount(2);
});

it('khoá cache: giống nhau khi chỉ khác khoảng trắng hay dạng Unicode; khác nhau khi khác hoa thường', function () {
    $khoa = fn (string $tu) => BoNhoAi::khoa('word', 1, 'm', ['word' => $tu, 'meaning' => 'ăn', 'level' => 'A1']);

    expect($khoa('  essen '))->toBe($khoa('essen'))
        ->and($khoa("Bro\u{0308}tchen"))->toBe($khoa('Brötchen'))
        ->and($khoa("das  \n Brot"))->toBe($khoa('das Brot'))
        ->and($khoa('Essen'))->not->toBe($khoa('essen'))
        ->and(BoNhoAi::khoa('word', 1, 'm', ['meaning' => 'ăn', 'word' => 'x']))->toBe(BoNhoAi::khoa('word', 1, 'm', ['word' => 'x', 'meaning' => 'ăn']))
        ->and(BoNhoAi::khoa('word', 2, 'm', ['word' => 'x']))->not->toBe(BoNhoAi::khoa('word', 1, 'm', ['word' => 'x']))
        ->and(BoNhoAi::khoa('word', 1, 'n', ['word' => 'x']))->not->toBe(BoNhoAi::khoa('word', 1, 'm', ['word' => 'x']));
});

it('prompt lên phiên bản 2 hay đổi model thì không dùng kết quả cũ', function () {
    $this->actingAs(nguoiHocAi());
    giaiThich(theMau('das Brötchen'))->assertOk();

    $cu = (array) DB::table('ai_prompts')->where('kind', 'word')->first();
    unset($cu['id']);
    DB::table('ai_prompts')->insert(['version' => 2] + $cu);
    giaiThich(theMau('das Brötchen'))->assertOk()->assertJsonPath('meta.cached', false);
    expect(DB::table('ai_requests')->latest('id')->value('prompt_id'))->toBe(DB::table('ai_prompts')->where('kind', 'word')->where('version', 2)->value('id'));

    CaiDatAi::luu(['default_model' => 'gemini-2.5-flash-lite'], null);
    giaiThich(theMau('das Brötchen'))->assertOk()->assertJsonPath('meta.cached', false);
    expect(aiGia()->daNhan)->toHaveCount(3)->and(aiGia()->daNhan[2]->model)->toBe('gemini-2.5-flash-lite');
});

it('sau 91 ngày mục cache hết hạn: gọi lại và ghi đè', function () {
    $this->actingAs(nguoiHocAi());
    giaiThich(theMau('das Brötchen'))->assertOk();
    giaiThich(theMau('das Brötchen'))->assertOk()->assertJsonPath('meta.cached', true);

    $this->travel(91)->days();
    giaiThich(theMau('das Brötchen'))->assertOk()->assertJsonPath('meta.cached', false);

    $dong = DB::table('ai_cache')->sole();
    expect((int) $dong->hits)->toBe(0)
        ->and((int) $dong->created_at)->toBe(now()->getTimestamp())
        ->and((int) $dong->expires_at)->toBe(now()->getTimestamp() + 90 * 86400);
});

it('kết quả hỏng không được lưu; bốn loại chứa chữ riêng của người học không bao giờ lưu', function () {
    $this->actingAs(nguoiHocAi());
    aiGia()->kich(['không phải json']);
    giaiThich(theMau('das Brötchen'))->assertStatus(502);

    expect(DB::table('ai_cache')->count())->toBe(0);
    foreach (['translate', 'grammar', 'chat', 'ask'] as $loai) {
        expect(BoNhoAi::duocLuu($loai))->toBeFalse();
    }
    foreach (['lookup', 'word', 'doc', 'lesson', 'answer', 'sentence'] as $loai) {
        expect(BoNhoAi::duocLuu($loai))->toBeTrue();
    }
});

it('qua API: Dịch, Sửa ngữ pháp, Trò chuyện, câu hỏi tự do gọi hai lần là hai lượt thật; sáu loại còn lại lần hai lấy từ cache', function (string $ten) {
    $this->m->taoLuyenTap();
    $user = nguoiHocAi();
    $this->actingAs($user);
    $d = duongAi($ten, $user);

    $this->postJson($d['url'], $d['than'])->assertOk()->assertJsonPath('meta.cached', false);
    $lan2 = $this->postJson($d['url'], $d['than'])->assertOk();

    $khongLuu = in_array($d['loai'], ['translate', 'grammar', 'chat', 'ask'], true);
    expect($lan2->json('meta.cached'))->toBe(! $khongLuu)
        ->and(aiGia()->daNhan)->toHaveCount($khongLuu ? 2 : 1)
        ->and(DB::table('ai_cache')->where('kind', $d['loai'])->count())->toBe($khongLuu ? 0 : 1);
})->with(DUONG_AI);
