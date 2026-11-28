<?php

use App\Support\Ai\PromptBan2;
use Illuminate\Support\Facades\DB;

// G2 tuần 2: bản 2 của ba prompt chat, lesson, lookup (seed mới ghi thẳng; database cũ nâng bằng migration).

beforeEach(fn () => $this->seed());

it('database mới có đúng 10 prompt đang dùng, ba loại khớp chữ bản 2', function () {
    expect(DB::table('ai_prompts')->count())->toBe(10)
        ->and(DB::table('ai_prompts')->where('is_active', 1)->count())->toBe(10);

    foreach (PromptBan2::MAU as $loai => $m) {
        $p = DB::table('ai_prompts')->where('kind', $loai)->first();
        expect($p->user_template)->toBe($m['mau'], $loai)
            ->and($p->system_prompt)->toBe(PromptBan2::heThong($loai), $loai)
            ->and((int) $p->max_tokens)->toBe($m['max_tokens'], $loai);
    }

    expect(PromptBan2::nangCap())->toBe(0)
        ->and(DB::table('ai_prompts')->count())->toBe(10);
});

it('database còn chữ cũ: thêm bản 2 đang dùng, tắt bản 1; chạy lại không thêm dòng', function () {
    DB::table('ai_prompts')->where('kind', 'chat')->update(['user_template' => 'Tình huống: {scenario}. Người học nói: <input>{text}</input>', 'temperature' => 0.6]);

    expect(PromptBan2::nangCap())->toBe(1);

    $chat = DB::table('ai_prompts')->where('kind', 'chat')->orderBy('version')->get();
    expect($chat)->toHaveCount(2)
        ->and((int) $chat[0]->is_active)->toBe(0)
        ->and((int) $chat[1]->is_active)->toBe(1)
        ->and((int) $chat[1]->version)->toBe(2)
        ->and($chat[1]->user_template)->toBe(PromptBan2::MAU['chat']['mau'])
        ->and((float) $chat[1]->temperature)->toBe(0.6)
        ->and($chat[1]->model)->toBe($chat[0]->model);

    expect(PromptBan2::nangCap())->toBe(0)
        ->and(DB::table('ai_prompts')->where('kind', 'chat')->count())->toBe(2);
});

it('đã có phiên bản 2 (sửa ở trang quản trị) thì không đụng; chưa có dòng nào thì không thêm', function () {
    DB::table('ai_prompts')->where('kind', 'lesson')->update(['user_template' => 'cũ {level} {topic} {words}']);
    DB::table('ai_prompts')->insert(['kind' => 'lesson', 'version' => 2, 'model' => 'm', 'system_prompt' => 's {level} JSON', 'user_template' => 'của quản trị {level} <input>{topic} {words}</input>', 'max_tokens' => 900, 'temperature' => 0.3, 'is_active' => 1, 'created_at' => 1]);
    DB::table('ai_prompts')->where('kind', 'lookup')->delete();

    expect(PromptBan2::nangCap())->toBe(0)
        ->and(DB::table('ai_prompts')->where('kind', 'lesson')->where('is_active', 1)->value('user_template'))->toBe('của quản trị {level} <input>{topic} {words}</input>')
        ->and(DB::table('ai_prompts')->where('kind', 'lookup')->count())->toBe(0);
});
