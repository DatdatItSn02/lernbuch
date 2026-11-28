<?php

use App\Support\Ai\BoNoi\BoNoiGia;
use App\Support\Ai\BoNoi\YeuCauAi;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 10: mười đường gọi AI (chín chức năng chữ và Giải thích từ). Mỗi đường: đúng khuôn, ô rỗng, quá giới hạn,
// toàn ký hiệu, dán 10.000 ký tự. Mọi ca bị từ chối ở bước kiểm dữ liệu không gọi AI, không ghi nhật ký.

beforeEach(function () {
    $this->seed();
    (new NoiDungThuSeeder)->tao()->taoLuyenTap();
    batAi();
    $this->user = nguoiHocAi();
    $this->actingAs($this->user);
});

it('thân hợp lệ: trả đúng khuôn của loại, meta, nhật ký đúng loại', function (string $ten) {
    $d = duongAi($ten, $this->user);

    $r = $this->postJson($d['url'], $d['than'])->assertOk()->assertJsonStructure(['data', 'meta' => ['cached', 'limit', 'used', 'remaining', 'reset_at']]);

    expect(array_keys($r->json('data')))->toBe(array_keys(App\Support\Ai\KhuonAi::KHUON[$d['loai']]))
        ->and(aiGia()->daNhan)->toHaveCount(1)
        ->and(aiGia()->daNhan[0]->loai)->toBe($d['loai'])
        ->and(DB::table('ai_requests')->where('status', 'ok')->value('kind'))->toBe($d['loai'])
        ->and($r->json('request_id'))->toBeNull()
        ->and($r->json('hash'))->toBeNull();
})->with(DUONG_AI);

it('ô chữ rỗng, toàn dấu cách, toàn ký hiệu, quá giới hạn một ký tự, dán 10.000 ký tự: 422, không gọi AI, không ghi nhật ký', function (string $ten) {
    $d = duongAi($ten, $this->user);

    if ($d['o'] === null) {
        expect(true)->toBeTrue();

        return;
    }

    foreach (['', '     ', '?!… 123 🙂 --', str_repeat('a', $d['toiDa'] + 1), str_repeat('Hallo ', 1667)] as $chu) {
        $this->postJson($d['url'], [$d['o'] => $chu] + $d['than'])->assertUnprocessable()->assertJsonValidationErrors($d['o']);
    }

    expect(aiGia()->daNhan)->toBe([])
        ->and(DB::table('ai_requests')->count())->toBe(0);
})->with(DUONG_AI);

it('đúng giới hạn ký tự thì qua', function (string $ten) {
    $d = duongAi($ten, $this->user);

    if ($d['o'] === null) {
        expect(true)->toBeTrue();

        return;
    }

    $this->postJson($d['url'], [$d['o'] => str_repeat('a', $d['toiDa'])] + $d['than'])->assertOk();
    expect(aiGia()->daNhan)->toHaveCount(1);
})->with(DUONG_AI);

it('chuỗi có thẻ HTML trong kết quả được trả nguyên văn', function (string $ten) {
    $d = duongAi($ten, $this->user);
    aiGia()->kich([fn (YeuCauAi $yc) => str_replace('AI giả', '<script>alert(1)</script><b>x</b>', BoNoiGia::mau($yc))]);

    $r = $this->postJson($d['url'], $d['than'])->assertOk();

    expect(json_encode($r->json('data'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->toContain('<script>alert(1)</script><b>x</b>');
})->with(DUONG_AI);
