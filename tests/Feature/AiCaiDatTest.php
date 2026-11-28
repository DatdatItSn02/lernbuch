<?php

use App\Filament\Pages\TrangCaiDatAi;
use App\Models\User;
use App\Support\Ai\CaiDatAi;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// Trang Cài đặt AI (mục tự kiểm 7 và 17): cần quyền ai.manage, lưu có hiệu lực ngay, ghi nhật ký, không bao giờ hiện khoá.

beforeEach(fn () => $this->seed());

/** @return array<string, mixed> giá trị form hợp lệ */
function formAiMau(array $doi = []): array
{
    return $doi + [
        'enabled' => true, 'audience' => 'testers', 'provider' => 'gemini', 'default_model' => 'gemini-2.5-flash-lite',
        'rpm_cap' => 12, 'rpd_cap' => 1200, 'max_concurrent' => 2, 'daily_spend_cap_usd' => 5.5, 'trial_daily_cap' => 30,
        'price_in' => 0.1, 'price_out' => 0.4,
    ];
}

it('chưa có gì trong database thì dùng mặc định: AI tắt, chỉ danh sách thử', function () {
    DB::table('settings')->where('name', 'like', 'ai.%')->delete();

    expect(CaiDatAi::tatCa())->toBe(CaiDatAi::MAC_DINH);
});

it('chỉ người có quyền ai.manage vào được trang', function () {
    $this->actingAs(User::factory()->create())->get('/admin/cai-dat-ai')->assertForbidden();
    $this->actingAs(nhanVien('content_editor'))->get('/admin/cai-dat-ai')->assertForbidden();
    $this->actingAs(nhanVien('support'))->get('/admin/cai-dat-ai')->assertForbidden();
    $this->actingAs(nhanVien('admin'))->get('/admin/cai-dat-ai')->assertOk()->assertSee('Bật AI')->assertSee('Hạn mức giai đoạn thử');
});

it('lưu: có hiệu lực ngay, ghi nhật ký những ô đã đổi; lưu y như cũ thì không ghi thêm', function () {
    $this->actingAs($nguoi = nhanVien('admin'));

    Livewire::test(TrangCaiDatAi::class)
        ->assertFormSet(['enabled' => false, 'audience' => 'testers', 'default_model' => 'gemini-3.1-flash-lite', 'price_in' => 0.25])
        ->fillForm(formAiMau())
        ->call('save')
        ->assertHasNoFormErrors();

    $cd = CaiDatAi::tatCa();
    expect($cd['enabled'])->toBeTrue()
        ->and($cd['default_model'])->toBe('gemini-2.5-flash-lite')
        ->and($cd['rpm_cap'])->toBe(12)
        ->and($cd['daily_spend_cap_usd'])->toBe(5.5)
        ->and($cd['price_usd_per_mtok'])->toBe(['in' => 0.1, 'out' => 0.4])
        ->and(DB::table('settings')->where('name', 'ai.enabled')->value('updated_by'))->toBe($nguoi->id);
    $dong = dongNhatKy('setting.update');
    expect($dong['actor_id'])->toBe($nguoi->id)
        ->and(json_decode($dong['changes'], true)['before'])->toMatchArray(['ai.enabled' => false, 'ai.rpm_cap' => 10])
        ->and(json_decode($dong['changes'], true)['after'])->toMatchArray(['ai.enabled' => true, 'ai.rpm_cap' => 12])
        ->and(json_decode($dong['changes'], true)['after'])->not->toHaveKey('ai.audience');

    Livewire::test(TrangCaiDatAi::class)->assertFormSet(['rpm_cap' => 12, 'daily_spend_cap_usd' => 5.5])->call('save')->assertHasNoFormErrors();
    expect(DB::table('audit_logs')->where('action', 'setting.update')->count())->toBe(1);
});

it('chi phí số nguyên lưu lại y như cũ không bị coi là đổi', function () {
    $this->actingAs(nhanVien('admin'));
    Livewire::test(TrangCaiDatAi::class)->fillForm(['daily_spend_cap_usd' => '20'])->call('save')->assertHasNoFormErrors();

    expect(DB::table('audit_logs')->where('action', 'setting.update')->count())->toBe(0);
});

it('giá trị sai báo lỗi đúng ô, không lưu gì', function (string $o, mixed $giaTri) {
    $this->actingAs(nhanVien('admin'));
    $truoc = DB::table('settings')->where('name', 'like', 'ai.%')->orderBy('name')->get()->all();

    Livewire::test(TrangCaiDatAi::class)->fillForm(formAiMau([$o => $giaTri]))->call('save')->assertHasFormErrors([$o]);

    expect(DB::table('settings')->where('name', 'like', 'ai.%')->orderBy('name')->get()->all())->toEqual($truoc);
})->with([
    'lượt mỗi phút là 0' => ['rpm_cap', 0],
    'lượt mỗi ngày âm' => ['rpd_cap', -5],
    'lượt cùng lúc là chữ' => ['max_concurrent', 'ba'],
    'model rỗng' => ['default_model', ''],
    'model có dấu cách' => ['default_model', 'gemini 3'],
    'nhà cung cấp lạ' => ['provider', 'claude'],
    'ai được dùng lạ' => ['audience', 'tat-ca'],
    'chi phí quá trần' => ['daily_spend_cap_usd', 5000],
    'hạn mức thử quá 500' => ['trial_daily_cap', 501],
    'giá âm' => ['price_in', -1],
]);

it('giá trị hỏng trong bảng settings thì bỏ, dùng mặc định cho ô đó', function () {
    DB::table('settings')->upsert([
        ['name' => 'ai.rpm_cap', 'value' => '"nhiều"', 'updated_at' => time()],
        ['name' => 'ai.audience', 'value' => '"tat-ca"', 'updated_at' => time()],
        ['name' => 'ai.price_usd_per_mtok', 'value' => '{"in":-1,"out":1}', 'updated_at' => time()],
        ['name' => 'ai.max_concurrent', 'value' => '5', 'updated_at' => time()],
    ], ['name'], ['value']);

    $cd = CaiDatAi::tatCa();
    expect($cd['rpm_cap'])->toBe(10)
        ->and($cd['audience'])->toBe('testers')
        ->and($cd['price_usd_per_mtok'])->toBe(['in' => 0.25, 'out' => 1.5])
        ->and($cd['max_concurrent'])->toBe(5);
});

it('CaiDatAi::luu bỏ qua ô lạ, giá trị sai và tuổi tối thiểu', function () {
    CaiDatAi::luu(['rpm_cap' => 0, 'khong_co' => 1, 'min_age' => 16, 'trial_daily_cap' => 7], null);

    expect(CaiDatAi::tatCa()['rpm_cap'])->toBe(10)
        ->and(CaiDatAi::tatCa()['trial_daily_cap'])->toBe(7)
        ->and(CaiDatAi::tatCa()['min_age'])->toBe(18)
        ->and(DB::table('settings')->where('name', 'ai.khong_co')->exists())->toBeFalse();
});

it('trang không bao giờ chứa khoá AI, chỉ báo đã có hay chưa có', function () {
    $this->actingAs(nhanVien('admin'));

    config(['ai.gemini.khoa' => null]);
    $this->get('/admin/cai-dat-ai')->assertOk()->assertSee('Chưa có');

    config(['ai.gemini.khoa' => 'khoa-bi-mat-tren-may-chu', 'ai.bo_noi' => null]);
    $this->get('/admin/cai-dat-ai')->assertOk()->assertSee('Đã có')->assertDontSee('khoa-bi-mat-tren-may-chu');
});
