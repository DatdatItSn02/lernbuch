<?php

use App\Support\Ai\CaiDatAi;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 6 và 7, phần "cả 10 chức năng": công tắc, danh sách thử, tuổi, đồng ý chặn ở mọi đường gọi AI, trước khi gọi.

beforeEach(function () {
    $this->seed();
    (new NoiDungThuSeeder)->tao()->taoLuyenTap();
    batAi();
});

it('mỗi đường × năm ca: đúng mã lỗi, không gọi AI', function (string $ten) {
    $ca = [
        'ai_tam_tat' => [fn () => nguoiHocAi(), 503, fn () => CaiDatAi::luu(['enabled' => false], null)],
        'ai_chua_mo' => [fn () => nguoiHocAi(thu: false), 403, null],
        'ai_can_ngay_sinh' => [fn () => nguoiHocAi(['birth_date' => null]), 403, null],
        'ai_chua_du_tuoi' => [fn () => nguoiHocAi(['birth_date' => now()->subYears(17)->startOfDay()->getTimestamp()]), 403, null],
        'ai_can_dong_y' => [fn () => nguoiHocAi(dongY: false), 403, null],
    ];

    foreach ($ca as $ma => [$tao, $http, $truoc]) {
        batAi();
        $user = $tao();
        $d = duongAi($ten, $user);

        if ($truoc !== null) {
            $truoc();
        }

        $this->actingAs($user)->postJson($d['url'], $d['than'])->assertStatus($http)->assertJsonPath('code', $ma);
    }

    expect(aiGia()->daNhan)->toBe([]);
})->with(DUONG_AI);

it('tắt công tắc giữa hai lượt thì lượt sau bị chặn ở mọi đường; bật lại thì dùng tiếp', function (string $ten) {
    $user = nguoiHocAi();
    $d = duongAi($ten, $user);
    $this->actingAs($user)->postJson($d['url'], $d['than'])->assertOk();

    CaiDatAi::luu(['enabled' => false], null);
    $d = duongAi($ten, $user);
    $this->postJson($d['url'], $d['than'])->assertStatus(503)->assertJsonPath('code', 'ai_tam_tat');

    batAi();
    $this->postJson($d['url'], $d['than'])->assertOk();

    expect(DB::table('ai_requests')->where('status', 'ok')->count())->toBeGreaterThanOrEqual(1);
})->with(DUONG_AI);

it('chấm phát âm và bản dự phòng × năm ca cổng: đúng mã, không gọi bộ chấm, không ghi gì', function (string $duong) {
    $ca = [
        'ai_tam_tat' => [fn () => nguoiHocAi(), 503, fn () => CaiDatAi::luu(['enabled' => false], null)],
        'ai_chua_mo' => [fn () => nguoiHocAi(thu: false), 403, null],
        'ai_can_ngay_sinh' => [fn () => nguoiHocAi(['birth_date' => null]), 403, null],
        'ai_chua_du_tuoi' => [fn () => nguoiHocAi(['birth_date' => now()->subYears(17)->startOfDay()->getTimestamp()]), 403, null],
        'ai_can_dong_y' => [fn () => nguoiHocAi(dongY: false), 403, null],
    ];
    $than = $duong === 'ai/phat-am' ? ['audio' => wav64()] : ['heard' => 'das brötchen'];

    foreach ($ca as $ma => [$tao, $http, $truoc]) {
        batAi();
        $user = $tao();

        if ($truoc !== null) {
            $truoc();
        }

        $this->actingAs($user)->postJson('/api/v1/'.$duong, ['card_id' => theMau('das Brötchen')] + $than)->assertStatus($http)->assertJsonPath('code', $ma);
    }

    expect(chamGia()->daNhan)->toBe([])->and(DB::table('pronunciation_attempts')->count())->toBe(0);
})->with(['ai/phat-am', 'ai/phat-am/du-phong']);
