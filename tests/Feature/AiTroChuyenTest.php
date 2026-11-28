<?php

use App\Models\User;
use App\Support\Ai\BoNoi\LoiBoNoi;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 11 (trò chuyện riêng tư) và phần xoá cuộc trò chuyện của mục 8.

beforeEach(function () {
    $this->seed();
    batAi();
    $this->user = nguoiHocAi();
    $this->actingAs($this->user);
});

/** Thêm $n tin xen kẽ người học, AI vào cuộc (thẳng vào database). */
function themTin(string $ma, int $n, string $tienTo = 'tin'): void
{
    $id = (int) DB::table('ai_conversations')->where('public_id', $ma)->value('id');

    for ($i = 1; $i <= $n; $i++) {
        DB::table('ai_messages')->insert(['conversation_id' => $id, 'role' => $i % 2 === 1 ? 'user' : 'assistant', 'content' => "{$tienTo} {$i}.", 'created_at' => 1000 + $i]);
    }

    DB::table('ai_conversations')->where('id', $id)->update(['message_count' => $n]);
}

it('mở cuộc theo tình huống, gửi tin: lưu hai tin đúng vai, đúng request_id, cập nhật số tin, tiêu đề', function () {
    $c = $this->postJson('/api/v1/ai/tro-chuyen', ['scenario' => 'restaurant'])->assertCreated()->json('data');
    expect($c['scenario'])->toBe('restaurant')->and($c['message_count'])->toBe(0)->and($c['title'])->toBeNull();

    $r = $this->postJson("/api/v1/ai/tro-chuyen/{$c['id']}/tin", ['text' => 'Guten Tag, ich möchte ein Wasser.'])->assertOk()
        ->assertJsonPath('conversation.message_count', 2)
        ->assertJsonPath('conversation.title', 'Guten Tag, ich möchte ein Wasser.')
        ->assertJsonPath('messages.0.role', 'user')
        ->assertJsonPath('messages.0.extra', null)
        ->assertJsonPath('messages.1.role', 'assistant')
        ->assertJsonPath('messages.1.content', 'Sehr gut! Und was möchten Sie trinken?')
        ->assertJsonPath('messages.1.extra.hint', 'Ich möchte einen Kaffee, bitte.');

    expect($r->json('messages.1.extra.errors.0.right'))->toBe('Ich heiße')
        ->and(aiGia()->daNhan[0]->nguoiDung)->toContain('Tình huống: gọi món ở nhà hàng.')->toContain('Các lượt trước: (chưa có) Câu mới của người học: Guten Tag')
        ->and(DB::table('ai_messages')->where('role', 'assistant')->value('request_id'))->toBe(DB::table('ai_requests')->where('kind', 'chat')->value('id'));

    $this->getJson("/api/v1/ai/tro-chuyen/{$c['id']}")->assertOk()->assertJsonCount(2, 'data.messages');
});

it('gửi hai tin liền nhau trong cùng cuộc: lượt hai nhận lượt một trong lịch sử, đủ bốn tin', function () {
    $ma = cuocTroChuyen($this->user);

    $this->postJson("/api/v1/ai/tro-chuyen/{$ma}/tin", ['text' => 'Guten Tag, ich heißen Lan.'])->assertOk();
    $this->postJson("/api/v1/ai/tro-chuyen/{$ma}/tin", ['text' => 'Ich möchte ein Wasser.'])->assertOk()
        ->assertJsonPath('conversation.message_count', 4)
        ->assertJsonPath('messages.0.content', 'Ich möchte ein Wasser.');

    expect(aiGia()->daNhan[1]->nguoiDung)->toContain("Người học: Guten Tag, ich heißen Lan.\nAI: Sehr gut!")
        ->and(DB::table('ai_messages')->count())->toBe(4);
});

it('cuộc dài 40 tin: AI chỉ nhận tin 31 đến 40, cũ trước, không có tin 30; tin dài bị cắt 300 ký tự', function () {
    $ma = cuocTroChuyen($this->user);
    themTin($ma, 40);
    DB::table('ai_messages')->where('content', 'tin 35.')->update(['content' => str_repeat('x', 400)]);

    $this->postJson("/api/v1/ai/tro-chuyen/{$ma}/tin", ['text' => 'Noch etwas?'])->assertOk();

    $gui = aiGia()->daNhan[0]->nguoiDung;
    expect($gui)->toContain("Người học: tin 31.\nAI: tin 32.")->toContain('AI: tin 40.')
        ->not->toContain('tin 30.')->not->toContain('tin 1.')
        ->toContain(str_repeat('x', 300))->not->toContain(str_repeat('x', 301))
        ->and(strpos($gui, 'tin 31.'))->toBeLessThan(strpos($gui, 'tin 40.'));
});

it('lịch sử không có tin của cuộc khác, kể cả cuộc khác của chính mình', function () {
    $a = cuocTroChuyen($this->user);
    $b = cuocTroChuyen($this->user);
    themTin($b, 4, 'bí mật');

    $this->postJson("/api/v1/ai/tro-chuyen/{$a}/tin", ['text' => 'Hallo'])->assertOk();
    expect(aiGia()->daNhan[0]->nguoiDung)->not->toContain('bí mật');
});

it('cuộc của người khác, mã không có, mã sai dạng: 404 ở đọc, gửi, xoá; không gọi AI, không đổi gì', function () {
    $khac = cuocTroChuyen(User::factory()->create());
    themTin($khac, 2);
    $khong = '01JDZZZZZZZZZZZZZZZZZZZZZZ';

    foreach ([$khac, $khong] as $ma) {
        $this->getJson("/api/v1/ai/tro-chuyen/{$ma}")->assertNotFound();
        $this->postJson("/api/v1/ai/tro-chuyen/{$ma}/tin", ['text' => 'Hallo'])->assertNotFound();
        $this->deleteJson("/api/v1/ai/tro-chuyen/{$ma}")->assertNotFound();
    }

    $this->getJson('/api/v1/ai/tro-chuyen/abc')->assertNotFound();
    $this->postJson('/api/v1/ai/tro-chuyen/'.strtolower($khac).'/tin', ['text' => 'Hallo'])->assertNotFound();

    expect(aiGia()->daNhan)->toBe([])
        ->and(DB::table('ai_messages')->count())->toBe(2)
        ->and(DB::table('ai_conversations')->count())->toBe(1);
});

it('AI lỗi hay trả sai khuôn thì không lưu tin nào', function () {
    $ma = cuocTroChuyen($this->user);
    aiGia()->kich([new LoiBoNoi(LoiBoNoi::TAM_THOI, 'x'), new LoiBoNoi(LoiBoNoi::TAM_THOI, 'x'), 'không phải JSON']);

    $this->postJson("/api/v1/ai/tro-chuyen/{$ma}/tin", ['text' => 'Hallo'])->assertStatus(503);
    $this->postJson("/api/v1/ai/tro-chuyen/{$ma}/tin", ['text' => 'Hallo'])->assertStatus(502);

    expect(DB::table('ai_messages')->count())->toBe(0)
        ->and(DB::table('ai_conversations')->where('public_id', $ma)->value('message_count'))->toBe(0);
});

it('cuộc thứ 51 bị từ chối; cuộc đã đủ 200 tin không gửi thêm được', function () {
    for ($i = 0; $i < 50; $i++) {
        cuocTroChuyen($this->user);
    }

    $this->postJson('/api/v1/ai/tro-chuyen', ['scenario' => 'free'])->assertUnprocessable()->assertJsonPath('code', 'ai_qua_nhieu_cuoc');

    $ma = (string) DB::table('ai_conversations')->value('public_id');
    DB::table('ai_conversations')->where('public_id', $ma)->update(['message_count' => 199]);
    $this->postJson("/api/v1/ai/tro-chuyen/{$ma}/tin", ['text' => 'Hallo'])->assertUnprocessable()->assertJsonPath('code', 'ai_cuoc_day');

    expect(aiGia()->daNhan)->toBe([]);
});

it('tình huống lạ hay thiếu thì 422; danh sách chỉ có cuộc của mình, mới trước, tối đa 30', function () {
    $this->postJson('/api/v1/ai/tro-chuyen', ['scenario' => 'party'])->assertUnprocessable();
    $this->postJson('/api/v1/ai/tro-chuyen', [])->assertUnprocessable();

    cuocTroChuyen(User::factory()->create());
    $cu = cuocTroChuyen($this->user);
    DB::table('ai_conversations')->where('public_id', $cu)->update(['created_at' => 100]);
    $moi = cuocTroChuyen($this->user);

    $ds = $this->getJson('/api/v1/ai/tro-chuyen')->assertOk()->json('data');
    expect(array_column($ds, 'id'))->toBe([$moi, $cu]);

    for ($i = 0; $i < 35; $i++) {
        cuocTroChuyen($this->user);
    }
    $this->getJson('/api/v1/ai/tro-chuyen')->assertJsonCount(30, 'data');
});

it('xoá cuộc: hết dòng ở hai bảng, đoạn trích các lượt đó thành rỗng, lượt của cuộc khác giữ nguyên', function () {
    $a = cuocTroChuyen($this->user);
    $b = cuocTroChuyen($this->user);
    $this->postJson("/api/v1/ai/tro-chuyen/{$a}/tin", ['text' => 'Ich heiße Lan.'])->assertOk();
    $this->postJson("/api/v1/ai/tro-chuyen/{$b}/tin", ['text' => 'Ich wohne in Hanoi.'])->assertOk();

    $this->deleteJson("/api/v1/ai/tro-chuyen/{$a}")->assertNoContent();

    $idB = (int) DB::table('ai_conversations')->where('public_id', $b)->value('id');
    expect(DB::table('ai_conversations')->pluck('public_id')->all())->toBe([$b])
        ->and(DB::table('ai_messages')->where('conversation_id', '<>', $idB)->count())->toBe(0)
        ->and(DB::table('ai_requests')->where('kind', 'chat')->whereNull('input_excerpt')->count())->toBe(1)
        ->and(DB::table('ai_requests')->where('context_id', $idB)->value('input_excerpt'))->toBe('Ich wohne in Hanoi.');
    $this->getJson("/api/v1/ai/tro-chuyen/{$a}")->assertNotFound();
});

it('người đã rút đồng ý vẫn đọc, xoá được cuộc của mình; mở cuộc mới, gửi tin thì bị chặn', function () {
    $ma = cuocTroChuyen($this->user);
    themTin($ma, 2);
    $this->putJson('/api/v1/toi/dong-y-ai', ['granted' => false, 'version' => '2026-11'])->assertOk();

    $this->getJson('/api/v1/ai/tro-chuyen')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/ai/tro-chuyen/{$ma}")->assertOk()->assertJsonCount(2, 'data.messages');
    $this->postJson("/api/v1/ai/tro-chuyen/{$ma}/tin", ['text' => 'Hallo'])->assertForbidden()->assertJsonPath('code', 'ai_can_dong_y');
    $this->postJson('/api/v1/ai/tro-chuyen', ['scenario' => 'free'])->assertForbidden()->assertJsonPath('code', 'ai_can_dong_y');
    $this->deleteJson("/api/v1/ai/tro-chuyen/{$ma}")->assertNoContent();

    expect(aiGia()->daNhan)->toBe([]);
});
