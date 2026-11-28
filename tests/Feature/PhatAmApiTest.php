<?php

use App\Models\User;
use App\Support\PhatAm\BoChamAzure;
use App\Support\PhatAm\CaiDatPhatAm;
use App\Support\PhatAm\KetQuaCham;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 13: POST ai/phat-am với bộ chấm giả: đọc đúng, sai, bỏ từ, im lặng, ồn, dài quá, sai định dạng, lỗi, hết lượt.

beforeEach(function () {
    $this->seed();
    $this->m = (new NoiDungThuSeeder)->tao();
    batAi();
    $this->user = nguoiHocAi();
    $this->actingAs($this->user);
});

function chamThe(string $front = 'das Brötchen', ?string $audio = null): Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/ai/phat-am', ['card_id' => theMau($front), 'audio' => $audio ?? wav64()]);
}

function mauKetQua(string $ten): KetQuaCham
{
    return BoChamAzure::docTraLoi(json_decode((string) file_get_contents(base_path('tests/chung/azure-phat-am.json')), true)[$ten]);
}

it('đọc đúng: trả điểm và từng từ, câu mẫu lấy từ thẻ, lưu một dòng chấm và một dòng nhật ký', function () {
    chamGia()->kich([mauKetQua('dung')]);

    chamThe()->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.overall', 94)
        ->assertJsonPath('data.accuracy', 96)
        ->assertJsonPath('data.fluency', 91)
        ->assertJsonPath('data.completeness', 100)
        ->assertJsonPath('data.reference', 'das Brötchen')
        ->assertJsonPath('data.words.2.word', 'zwei')
        ->assertJsonPath('meta.mode', 'azure')
        ->assertJsonPath('meta.used', 1)
        ->assertJsonPath('meta.remaining', 19);

    expect(chamGia()->daNhan[0]['cauMau'])->toBe('das Brötchen')
        ->and(DB::table('pronunciation_attempts')->first(['user_id', 'card_id', 'example_id', 'provider', 'status', 'overall_score', 'audio_ms', 'reference_text']))
        ->toEqual((object) ['user_id' => $this->user->id, 'card_id' => theMau('das Brötchen'), 'example_id' => null, 'provider' => 'azure', 'status' => 'ok', 'overall_score' => 94, 'audio_ms' => 2000, 'reference_text' => 'das Brötchen'])
        ->and(DB::table('ai_requests')->first(['kind', 'provider', 'status', 'context_type', 'context_id', 'input_tokens', 'cost_micro_usd']))
        ->toEqual((object) ['kind' => 'pron', 'provider' => 'azure_speech', 'status' => 'ok', 'context_type' => 'card', 'context_id' => theMau('das Brötchen'), 'input_tokens' => 0, 'cost_micro_usd' => 0]);
});

it('sai một từ và bỏ một từ: lỗi từng từ khớp dữ liệu bộ chấm trả', function () {
    chamGia()->kich([mauKetQua('sai_mot_tu'), mauKetQua('bo_mot_tu')]);

    chamThe()->assertOk()->assertJsonPath('data.words.2', ['word' => 'zwei', 'score' => 35, 'error' => 'mispronounced']);
    chamThe()->assertOk()->assertJsonPath('data.words.3', ['word' => 'brötchen', 'score' => 0, 'error' => 'omitted'])->assertJsonPath('data.completeness', 75);

    expect(json_decode((string) DB::table('pronunciation_attempts')->orderBy('id')->value('words'), true)[2]['error'])->toBe('mispronounced');
});

it('theo câu ví dụ: câu mẫu là câu ví dụ, ngữ cảnh card_example', function () {
    $this->postJson('/api/v1/ai/phat-am', ['example_id' => viDuMau('das Brötchen'), 'audio' => wav64()])->assertOk()
        ->assertJsonPath('data.reference', 'Ich möchte zwei Brötchen, bitte.');

    expect(chamGia()->daNhan[0]['cauMau'])->toBe('Ich möchte zwei Brötchen, bitte.')
        ->and(DB::table('ai_requests')->value('context_type'))->toBe('card_example')
        ->and(DB::table('pronunciation_attempts')->value('example_id'))->toBe(viDuMau('das Brötchen'));
});

it('im lặng: không gọi bộ chấm, không trừ lượt, không tính giây của tháng', function () {
    chamThe(audio: wav64(2.0, 0))->assertOk()->assertJsonPath('data.status', 'empty')->assertJsonPath('data.reason', 'silence')->assertJsonPath('meta.used', 0);

    expect(chamGia()->daNhan)->toBe([])->and(DB::table('pronunciation_attempts')->value('audio_ms'))->toBeNull();
});

it('tiếng ồn: trả empty, noise; không trừ lượt', function () {
    chamGia()->kich([mauKetQua('on')]);

    chamThe()->assertOk()->assertJsonPath('data.status', 'empty')->assertJsonPath('data.reason', 'noise')->assertJsonPath('meta.used', 0);
});

it('quá 15 giây, không phải WAV, quá lớn: 422 đúng mã, không gọi bộ chấm, không ghi gì', function (Closure $audio, string $ma) {
    chamThe(audio: $audio())->assertUnprocessable()->assertJsonPath('code', $ma);

    expect(chamGia()->daNhan)->toBe([])->and(DB::table('pronunciation_attempts')->count())->toBe(0);
})->with([
    'dài' => [fn () => wav64(16.0), 'phat_am_qua_dai'],
    'không phải WAV' => [fn () => base64_encode('không phải file âm thanh'), 'phat_am_sai_dinh_dang'],
    'lớn' => [fn () => str_repeat('A', 1_400_000), 'phat_am_qua_lon'],
]);

it('số giây tối đa đổi trong cài đặt thì đổi theo', function () {
    CaiDatPhatAm::luu(['max_seconds' => 5], null);

    chamThe(audio: wav64(6.0))->assertUnprocessable()->assertJsonPath('code', 'phat_am_qua_dai');
});

it('bộ chấm lỗi: 503, không trừ lượt, lưu dòng error', function () {
    chamGia()->kich([KetQuaCham::loi('http')]);

    chamThe()->assertStatus(503)->assertJsonPath('code', 'phat_am_loi_tam_thoi');

    expect(DB::table('pronunciation_attempts')->value('status'))->toBe('error')
        ->and(DB::table('ai_requests')->value('status'))->toBe('error');
    $this->getJson('/api/v1/toi')->assertJsonPath('data.ai.pron.used', 0);
});

it('hết lượt: 429 kèm reset_at, bộ chấm không bị gọi, nhật ký quota', function () {
    CaiDatPhatAm::luu(['trial_daily_cap' => 2], null);
    chamThe()->assertOk();
    chamThe()->assertOk();

    chamThe()->assertStatus(429)->assertJsonPath('code', 'phat_am_het_luot')->assertJsonPath('limit', 2)->assertJsonStructure(['reset_at']);

    expect(chamGia()->daNhan)->toHaveCount(2)->and(DB::table('ai_requests')->where('status', 'quota')->count())->toBe(1);
});

it('thẻ riêng của người khác, câu ví dụ không có: 404, không gọi bộ chấm', function () {
    $m = new NoiDungThuSeeder;
    $m->chuDe = $this->m->chuDe;
    $m->the = $this->m->the;
    $m->themThe('t-banh.v', 'der Hefezopf', 'noun', 'die Hefezöpfe', 2, 2, 2, ['vi' => 'bánh mì bện'], [], 'der', chu: User::factory()->create()->id);

    chamThe('der Hefezopf')->assertNotFound();
    $this->postJson('/api/v1/ai/phat-am', ['example_id' => 999999, 'audio' => wav64()])->assertNotFound();

    expect(chamGia()->daNhan)->toBe([]);
});

it('có cả hai mã, thiếu cả hai, thiếu audio: 422', function (array $than) {
    $this->postJson('/api/v1/ai/phat-am', $than)->assertUnprocessable();
    expect(chamGia()->daNhan)->toBe([]);
})->with([
    'cả hai' => [['card_id' => 1, 'example_id' => 1, 'audio' => 'x']],
    'thiếu cả hai' => [['audio' => 'x']],
    'thiếu audio' => [['card_id' => 1]],
]);

it('cách chấm là trình duyệt thì 409; tắt thì 503', function () {
    CaiDatPhatAm::luu(['provider' => 'browser'], null);
    chamThe()->assertStatus(409)->assertJsonPath('code', 'phat_am_doi_che_do');

    CaiDatPhatAm::luu(['provider' => 'off'], null);
    chamThe()->assertStatus(503)->assertJsonPath('code', 'phat_am_tam_tat');

    expect(chamGia()->daNhan)->toBe([]);
});
