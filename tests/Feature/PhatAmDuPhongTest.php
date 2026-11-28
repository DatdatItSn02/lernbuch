<?php

use App\Support\PhatAm\CaiDatPhatAm;
use App\Support\PhatAm\SoChu;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 14: bản dự phòng: trình duyệt nhận giọng, máy chủ so chữ với câu mẫu; không trừ lượt, không gửi gì đi.

beforeEach(function () {
    $this->seed();
    (new NoiDungThuSeeder)->tao();
    batAi();
    CaiDatPhatAm::luu(['provider' => 'browser'], null);
    $this->actingAs(nguoiHocAi());
});

function heard(array $tu): array
{
    return array_map(fn (array $t) => $t['word'].($t['heard'] ? '+' : '-'), $tu);
}

it('so chữ: hoa thường, dấu câu, ß và ss, giữ ä ö ü', function () {
    expect(heard(SoChu::so('Ich möchte zwei Brötchen, bitte.', 'ich MÖCHTE zwei brötchen bitte')))->toBe(['Ich+', 'möchte+', 'zwei+', 'Brötchen+', 'bitte+'])
        ->and(heard(SoChu::so('Die Straße ist groß.', 'die strasse ist gross')))->toBe(['Die+', 'Straße+', 'ist+', 'groß+'])
        ->and(heard(SoChu::so('schön', 'schon')))->toBe(['schön-']);
});

it('so chữ: từ sai, từ thừa, từ lặp đi đúng thứ tự, chuỗi rỗng', function () {
    expect(heard(SoChu::so('Ich möchte zwei Brötchen', 'ich möchte drei brötchen')))->toBe(['Ich+', 'möchte+', 'zwei-', 'Brötchen+'])
        ->and(heard(SoChu::so('das Brötchen', 'äh das das brötchen ja')))->toBe(['das+', 'Brötchen+'])
        ->and(heard(SoChu::so('das das Brot', 'das brot')))->toBe(['das+', 'das-', 'Brot-'])
        ->and(heard(SoChu::so('das Brot', '')))->toBe(['das-', 'Brot-'])
        ->and(SoChu::rong(' ,. '))->toBeTrue();
});

it('lưu dòng browser không có điểm; không ghi ai_requests; không trừ lượt', function () {
    $this->postJson('/api/v1/ai/phat-am/du-phong', ['card_id' => theMau('das Brötchen'), 'heard' => 'das brötchen'])->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.words', [['word' => 'das', 'heard' => true], ['word' => 'Brötchen', 'heard' => true]])
        ->assertJsonPath('meta.mode', 'browser');

    expect(DB::table('pronunciation_attempts')->first(['provider', 'status', 'overall_score', 'audio_ms']))->toEqual((object) ['provider' => 'browser', 'status' => 'ok', 'overall_score' => null, 'audio_ms' => null])
        ->and(DB::table('ai_requests')->count())->toBe(0)
        ->and(chamGia()->daNhan)->toBe([]);
    $this->getJson('/api/v1/toi')->assertJsonPath('data.ai.pron.used', 0)->assertJsonPath('data.ai.pron.mode', 'browser');
});

it('không nghe được gì thì status empty; theo câu ví dụ', function () {
    $this->postJson('/api/v1/ai/phat-am/du-phong', ['example_id' => viDuMau('das Brötchen'), 'heard' => ''])->assertOk()
        ->assertJsonPath('data.status', 'empty')
        ->assertJsonPath('data.reference', 'Ich möchte zwei Brötchen, bitte.');
});

it('cách chấm là Azure thì 409; tắt thì 503; chữ quá 600 ký tự thì 422', function () {
    $than = ['card_id' => theMau('das Brötchen'), 'heard' => 'das brötchen'];
    $this->postJson('/api/v1/ai/phat-am/du-phong', ['heard' => str_repeat('a', 601)] + $than)->assertUnprocessable();

    CaiDatPhatAm::luu(['provider' => 'azure'], null);
    $this->postJson('/api/v1/ai/phat-am/du-phong', $than)->assertStatus(409)->assertJsonPath('code', 'phat_am_doi_che_do');

    CaiDatPhatAm::luu(['provider' => 'off'], null);
    $this->postJson('/api/v1/ai/phat-am/du-phong', $than)->assertStatus(503);

    expect(DB::table('pronunciation_attempts')->count())->toBe(0);
});
