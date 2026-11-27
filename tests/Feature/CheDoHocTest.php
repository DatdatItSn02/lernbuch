<?php

use App\Models\User;
use App\Support\Hoc\PhienHoc;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Các chế độ ngoài Flashcard: Quiz, Ghép cặp, Hỗn hợp có đáp án nhiễu (như Lernbuch); Đặt câu chỉ lấy thẻ có câu ví dụ.

beforeEach(function () {
    $this->seed();
    $this->m = (new NoiDungThuSeeder)->tao();
    $this->travelTo(lucHoc('2026-11-27 09:00:00'));
});

it('Quiz: mỗi thẻ có 6 đáp án nhiễu; cùng loại từ, cùng chủ đề đứng trước; không trùng thẻ, mặt trước hay nghĩa', function () {
    // Thẻ riêng của người khác và thẻ nháp không bao giờ thành đáp án nhiễu
    $m = new NoiDungThuSeeder;
    $m->chuDe = $this->m->chuDe;
    $m->the = $this->m->the;
    $m->themThe('t-banh.v', 'der Hefezopf', 'noun', 'die Hefezöpfe', 2, 2, 2, ['vi' => 'bánh mì bện'], [], 'der', chu: User::factory()->create()->id);
    $this->actingAs(User::factory()->create());

    $the = collect($this->postJson('/api/v1/hoc/phien', ['mode' => 'quiz'])->assertCreated()->json('data.cards'))->keyBy('front');
    expect($the)->toHaveCount(10)
        ->and(DB::table('study_sessions')->value('mode'))->toBe('quiz');

    foreach ($the as $front => $c) {
        $chon = collect($c['options']);
        expect($chon)->toHaveCount(6)
            ->and($chon->pluck('id')->unique())->toHaveCount(6)
            ->and($chon->pluck('id'))->not->toContain($c['id'])
            ->and($chon->pluck('front'))->not->toContain($front)->not->toContain('der Hefezopf')->not->toContain('der Teig')
            ->and($chon->pluck('meanings.vi'))->not->toContain($c['meanings']['vi']);
    }

    // "null" có 21 số cùng loại từ trong chủ đề: đáp án nhiễu đều là số đếm
    expect(collect($the['null']['options'])->pluck('front')->diff(array_column(NoiDungThuSeeder::SO_DEM, 0)))->toBeEmpty();
    // "die Zahl" là danh từ duy nhất của chủ đề: 3 danh từ của chủ đề Bánh mì đứng trước, sau đó mới tới loại từ khác
    expect(collect($the['die Zahl']['options'])->take(3)->pluck('front')->sort()->values()->all())->toBe(['das Brot', 'das Brötchen', 'das Mehl'])
        ->and($the['die Zahl']['options'][0])->toHaveKeys(['id', 'front', 'article', 'word', 'gender', 'meanings'])
        ->and(collect($the['die Zahl']['options'])->firstWhere('front', 'das Brot'))->toMatchArray(['article' => 'das', 'word' => 'Brot', 'gender' => 'n']);
    // "zahlen": động từ cùng chủ đề (zählen) trước, động từ chủ đề khác (backen) sau
    expect(collect($the['zahlen']['options'])->take(2)->pluck('front')->all())->toBe(['zählen', 'backen']);
});

it('chỉ Quiz, Ghép cặp, Hỗn hợp kèm đáp án nhiễu', function (string $cheDo, bool $co) {
    $this->actingAs(User::factory()->create());

    $the = $this->postJson('/api/v1/hoc/phien', ['mode' => $cheDo])->assertCreated()->json('data.cards.0');
    $co ? expect($the['options'])->toHaveCount(PhienHoc::SO_NHIEU) : expect($the)->not->toHaveKey('options');
})->with([
    'Flashcard' => ['flashcard', false],
    'Quiz' => ['quiz', true],
    'Listening' => ['listening', false],
    'Typing' => ['typing', false],
    'Ghép cặp' => ['match', true],
    'Hỗn hợp' => ['mixed', true],
    'Đặt câu' => ['sentence', false],
]);

it('Đặt câu: chỉ thẻ có câu ví dụ, cả ôn và mới; không đổi chủ đề đang học; Học tiếp đếm theo chế độ', function () {
    $this->actingAs($u = User::factory()->create());
    // Hôm qua đã học das Brötchen (có câu) và das Mehl (không có câu): hôm nay cả hai đến hạn
    $this->travelTo(lucHoc('2026-11-26 09:00:00'));
    $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [
        luot($this->m->the['das Brötchen'], 1, lucHoc('2026-11-26 09:00:01')),
        luot($this->m->the['das Mehl'], 1, lucHoc('2026-11-26 09:00:05')),
    ]])->assertOk();
    $this->travelTo(lucHoc('2026-11-27 09:00:00'));

    // Chủ đề đang học (Số đếm) không có thẻ nào có câu: từ mới lấy ở chủ đề kế có câu (das Brot)
    $r = $this->postJson('/api/v1/hoc/phien', ['mode' => 'sentence'])->assertCreated();
    expect($r->json('data.counts'))->toBe(['review' => 1, 'new' => 1])
        ->and(array_column($r->json('data.cards'), 'front'))->toBe(['das Brötchen', 'das Brot'])
        ->and(collect($r->json('data.cards'))->pluck('example.sentence')->all())->toBe(['Ich möchte zwei Brötchen, bitte.', 'Das Brot ist noch frisch.'])
        ->and(DB::table('user_courses')->where('user_id', $u->id)->value('current_topic_id'))->toBeNull()
        ->and(PhienHoc::chuDeDangHoc($u)?->slug)->toBe('so-dem-tu-vung')
        ->and($this->getJson('/api/v1/hoc/con-lai?che_do=sentence')->assertOk()->json('data'))->toBe(['review' => 1, 'new' => 1])
        ->and($this->getJson('/api/v1/hoc/con-lai')->json('data'))->toBe(['review' => 2, 'new' => 10]);

    // Flashcard vẫn học chủ đề đang học, kể cả thẻ không có câu
    expect(array_column($this->postJson('/api/v1/hoc/phien')->json('data.cards'), 'front'))->toContain('das Mehl')->toContain('zahlen');
});

it('Đặt câu trong một chủ đề không có câu nào thì phiên rỗng; chế độ lạ ở Học tiếp thì 422', function () {
    $this->actingAs(User::factory()->create());

    expect($this->postJson('/api/v1/hoc/phien', ['mode' => 'sentence', 'topic' => 'so-dem-tu-vung'])->assertCreated()->json('data'))
        ->toMatchArray(['counts' => ['review' => 0, 'new' => 0], 'cards' => []]);
    $this->getJson('/api/v1/hoc/con-lai?che_do=hack')->assertStatus(422)->assertJsonValidationErrors('che_do');
});

it('lượt chấm ở các chế độ được xu theo chế độ: Hỗn hợp 20, Listening 15, Khó được nửa', function () {
    $this->actingAs($u = User::factory()->create());
    $phien = $this->postJson('/api/v1/hoc/phien', ['mode' => 'mixed'])->json('data.id');

    $r = $this->postJson('/api/v1/hoc/luot-cham', ['reviews' => [
        luot($this->m->the['zahlen'], 2, lucHoc('2026-11-27 09:00:10'), $phien, cheDo: 'mixed'),
        luot($this->m->the['zählen'], 1, lucHoc('2026-11-27 09:00:20'), $phien, cheDo: 'listening'),
        luot($this->m->the['die Zahl'], 0, lucHoc('2026-11-27 09:00:30'), $phien, cheDo: 'sentence'),
    ]])->assertOk();

    expect($r->json('data.coins'))->toBe(20 + 7)
        ->and(DB::table('review_logs')->where('user_id', $u->id)->orderBy('reviewed_at')->pluck('mode')->all())->toBe(['mixed', 'listening', 'sentence']);
});
