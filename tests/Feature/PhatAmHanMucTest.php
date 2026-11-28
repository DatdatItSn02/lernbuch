<?php

use App\Support\Ai\CaiDatAi;
use App\Support\PhatAm\CaiDatPhatAm;
use App\Support\PhatAm\CheDoPhatAm;
use App\Support\PhatAm\HanMucPhatAm;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;

// Mục tự kiểm 13: cách chấm (Azure, dự phòng, tắt), giây đã dùng trong tháng, lượt mỗi ngày của từng người.

beforeEach(function () {
    $this->seed();
    (new NoiDungThuSeeder)->tao();
    batAi();
});

function dongPhatAm(int $userId, array $doi = []): void
{
    DB::table('pronunciation_attempts')->insert($doi + [
        'user_id' => $userId, 'reference_text' => 'das Brötchen', 'provider' => 'azure', 'status' => 'ok', 'audio_ms' => 2000, 'created_at' => now()->getTimestamp(),
    ]);
}

function coKhoaAzure(): void
{
    config(['ai.phat_am.bo_cham' => null, 'ai.azure.khoa' => 'khoa-thu', 'ai.azure.vung' => 'westeurope']);
}

it('cách chấm: tắt; bộ chấm giả; có khoá; thiếu khoá; thiếu vùng; chọn chỉ trình duyệt', function () {
    expect(CheDoPhatAm::cua())->toBe('azure');

    CaiDatPhatAm::luu(['provider' => 'off'], null);
    expect(CheDoPhatAm::cua())->toBe('off');

    CaiDatPhatAm::luu(['provider' => 'azure'], null);
    coKhoaAzure();
    expect(CheDoPhatAm::cua())->toBe('azure');

    config(['ai.azure.khoa' => '  ']);
    expect(CheDoPhatAm::cua())->toBe('browser');

    config(['ai.azure.khoa' => 'khoa-thu', 'ai.azure.vung' => '']);
    expect(CheDoPhatAm::cua())->toBe('browser');

    config(['ai.azure.goc' => 'https://ten.cognitiveservices.azure.com/stt/speech/recognition/conversation/cognitiveservices/v1']);
    expect(CheDoPhatAm::cua())->toBe('azure');

    CaiDatPhatAm::luu(['provider' => 'browser'], null);
    expect(CheDoPhatAm::cua())->toBe('browser');
});

it('tháng này đã dùng tới ngưỡng thì tự chuyển sang bản dự phòng', function () {
    coKhoaAzure();
    $user = nguoiHocAi();
    dongPhatAm($user->id, ['audio_ms' => 16_199_000]);
    expect(HanMucPhatAm::giayThangNay())->toBe(16199)->and(CheDoPhatAm::cua())->toBe('azure');

    dongPhatAm($user->id, ['audio_ms' => 1000]);
    expect(CheDoPhatAm::cua())->toBe('browser');

    CaiDatPhatAm::luu(['switch_at_seconds' => 17000], null);
    expect(CheDoPhatAm::cua())->toBe('azure');
});

it('giây của tháng: không tính tháng trước, bản dự phòng, dòng không gửi đi; làm tròn lên', function () {
    $user = nguoiHocAi();
    $this->travelTo(now('UTC')->setDate(2026, 12, 15)->setTime(10, 0));
    dongPhatAm($user->id, ['audio_ms' => 1500]);
    dongPhatAm($user->id, ['audio_ms' => 9000, 'created_at' => now('UTC')->setDate(2026, 11, 30)->setTime(23, 59, 59)->getTimestamp()]);
    dongPhatAm($user->id, ['audio_ms' => 9000, 'provider' => 'browser']);
    dongPhatAm($user->id, ['audio_ms' => null, 'status' => 'empty']);

    expect(HanMucPhatAm::giayThangNay())->toBe(2);
});

it('lượt mỗi ngày: chỉ đếm Azure chấm được; giai đoạn thử lấy số nhỏ hơn; qua nửa đêm của người học thì có lượt mới', function () {
    $user = nguoiHocAi(['timezone' => 'Asia/Ho_Chi_Minh']);
    $this->travelTo(lucHoc('2026-12-01 23:30'));
    dongPhatAm($user->id);
    dongPhatAm($user->id, ['status' => 'empty']);
    dongPhatAm($user->id, ['status' => 'error']);
    dongPhatAm($user->id, ['provider' => 'browser']);

    $tt = HanMucPhatAm::trangThai($user);
    expect($tt['used'])->toBe(1)->and($tt['limit'])->toBe(20)
        ->and($tt['reset_at'])->toBe(lucHoc('2026-12-02 00:00')->getTimestamp());

    CaiDatPhatAm::luu(['trial_daily_cap' => 5], null);
    expect(HanMucPhatAm::hanMuc($user))->toBe(5);

    CaiDatAi::luu(['audience' => 'adults'], null);
    expect(HanMucPhatAm::hanMuc($user))->toBe(20);

    $this->travelTo(lucHoc('2026-12-02 00:10'));
    expect(HanMucPhatAm::trangThai($user)['used'])->toBe(0);
});

it('GET /toi: khối pron chỉ có khi AI sẵn sàng', function () {
    $this->actingAs(nguoiHocAi())->getJson('/api/v1/toi')->assertOk()
        ->assertJsonPath('data.ai.state', 'ready')
        ->assertJsonPath('data.ai.pron.mode', 'azure')
        ->assertJsonPath('data.ai.pron.limit', 20)
        ->assertJsonPath('data.ai.pron.used', 0)
        ->assertJsonPath('data.ai.pron.max_seconds', 15);

    $this->actingAs(nguoiHocAi(dongY: false))->getJson('/api/v1/toi')->assertOk()
        ->assertJsonPath('data.ai.state', 'need_consent')
        ->assertJsonMissingPath('data.ai.pron');
});

it('cài đặt sai bị bỏ qua: ngoài khoảng, sai kiểu, ngưỡng lớn hơn giây miễn phí', function () {
    DB::table('settings')->upsert([
        ['name' => 'pron.max_seconds', 'value' => '31', 'updated_at' => time()],
        ['name' => 'pron.trial_daily_cap', 'value' => '"5"', 'updated_at' => time()],
    ], ['name'], ['value']);
    CaiDatPhatAm::luu(['monthly_free_seconds' => 1000, 'switch_at_seconds' => 2000], null);

    $cd = CaiDatPhatAm::tatCa();
    expect($cd['max_seconds'])->toBe(15)->and($cd['trial_daily_cap'])->toBe(20)
        ->and($cd['monthly_free_seconds'])->toBe(1000)->and($cd['switch_at_seconds'])->toBe(1000);
});

it('bộ chấm giả không được dùng ở production', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['ai.azure.khoa' => null]);

    expect(CheDoPhatAm::cua())->toBe('browser');
});
