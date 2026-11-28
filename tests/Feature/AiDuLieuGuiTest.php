<?php

use App\Models\User;
use App\Support\Ai\HoiAi;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// Mục tự kiểm 8: không gửi tên, email, mã tài khoản, IP cho nhà cung cấp; đoạn trích trong nhật ký ngắn và bị xoá sau 30 ngày.

beforeEach(function () {
    $this->seed();
    (new NoiDungThuSeeder)->tao();
    batAi();
});

function nguoiDeNhan(): User
{
    return nguoiHocAi(['name' => 'Nguyễn Thị Rất Dễ Nhận', 'email' => 'rat-de-nhan@example.com']);
}

it('AI giả nhận gì: không có tên, email, mã tài khoản, địa chỉ IP', function () {
    $user = nguoiDeNhan();
    $this->actingAs($user);
    giaiThich(theMau('das Brötchen'))->assertOk();

    $yc = aiGia()->daNhan[0];
    $tatCa = json_encode([$yc->heThong, $yc->nguoiDung, $yc->thamSo, $yc->khuon], JSON_UNESCAPED_UNICODE);
    foreach (['Nguyễn Thị Rất Dễ Nhận', 'Rất Dễ Nhận', 'rat-de-nhan', $user->public_id, '127.0.0.1', (string) $user->id.'|'] as $rieng) {
        expect($tatCa)->not->toContain($rieng);
    }
});

it('thân gửi Gemini chỉ có ba khoá và không có thông tin riêng của người học', function () {
    $user = nguoiDeNhan();
    $this->actingAs($user);
    config(['ai.bo_noi' => 'gemini', 'ai.gemini.khoa' => 'khoa-thu']);
    $chu = json_encode(['meaning' => 'bánh mì nhỏ', 'usage' => '', 'examples' => [], 'common_mistakes' => []]);
    Http::preventStrayRequests();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => $chu]]], 'finishReason' => 'STOP']]])]);

    giaiThich(theMau('das Brötchen'))->assertOk()->assertJsonPath('data.meaning', 'bánh mì nhỏ');

    Http::assertSent(function (Request $r) use ($user) {
        $than = $r->body();

        return array_keys($r->data()) === ['systemInstruction', 'contents', 'generationConfig']
            && ! str_contains($than, 'rat-de-nhan') && ! str_contains($than, 'Nhận') && ! str_contains($than, $user->public_id)
            && ! str_contains($r->url(), 'rat-de-nhan');
    });
});

it('đoạn trích trong nhật ký tối đa 500 ký tự, không cắt giữa một ký tự', function () {
    $user = nguoiHocAi();
    HoiAi::hoi($user, 'word', ['word' => 'essen', 'meaning' => 'ăn', 'level' => 'A1'], str_repeat('ẵ', 700));

    $trich = (string) DB::table('ai_requests')->value('input_excerpt');
    expect(mb_strlen($trich))->toBe(500)
        ->and(mb_check_encoding($trich, 'UTF-8'))->toBeTrue()
        ->and($trich)->toBe(str_repeat('ẵ', 500));
});

it('app:ai-don: xoá trắng đoạn trích quá 30 ngày (giữ dòng), xoá cache hết hạn, giữ bài học AI và mục còn hạn', function () {
    $bay = now()->getTimestamp();
    dongAi(null, 1, ['input_excerpt' => 'câu cũ', 'created_at' => $bay - 31 * 86400]);
    dongAi(null, 1, ['input_excerpt' => 'câu mới', 'created_at' => $bay - 86400]);
    $cache = fn (string $hash, string $loai, ?int $het) => DB::table('ai_cache')->insert([
        'hash' => str_pad($hash, 64, '0'), 'kind' => $loai, 'response' => '{}', 'hits' => 0, 'created_at' => $bay, 'expires_at' => $het,
    ]);
    $cache('a', 'word', $bay - 1);
    $cache('b', 'word', $bay + 86400);
    $cache('c', 'lesson', null);

    $this->artisan('app:ai-don')->assertSuccessful()->expectsOutputToContain('Đã xoá 1 đoạn trích câu hỏi và 1 mục cache AI hết hạn.');

    expect(DB::table('ai_requests')->orderBy('id')->pluck('input_excerpt')->all())->toBe([null, 'câu mới'])
        ->and(DB::table('ai_cache')->orderBy('hash')->pluck('kind')->all())->toBe(['word', 'lesson']);
});

it('app:ai-don có trong lịch chạy mỗi ngày', function () {
    $this->artisan('schedule:list')->assertSuccessful()->expectsOutputToContain('app:ai-don');
});

it('cả 10 đường gọi AI: chữ gửi đi không có tên, email, mã tài khoản của người học', function (string $ten) {
    (new NoiDungThuSeeder)->taoLuyenTap();
    $user = nguoiDeNhan();
    $this->actingAs($user);
    $d = duongAi($ten, $user);

    $this->postJson($d['url'], $d['than'])->assertOk();

    $yc = aiGia()->daNhan[0];
    $chu = $yc->heThong.$yc->nguoiDung.json_encode($yc->thamSo, JSON_UNESCAPED_UNICODE);
    expect($chu)->not->toContain('Rất Dễ Nhận')->not->toContain('rat-de-nhan')->not->toContain($user->public_id)->not->toContain((string) $user->id.'@');
})->with(DUONG_AI);
