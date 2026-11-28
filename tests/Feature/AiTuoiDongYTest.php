<?php

use App\Filament\Resources\NguoiHocs\Pages\ViewNguoiHoc;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// Mục tự kiểm 6: AI chỉ cho người từ 18 tuổi đã đồng ý. Ngày sinh khai một lần; đủ tuổi tính theo ngày ở múi giờ người học.

beforeEach(function () {
    $this->seed();
    (new NoiDungThuSeeder)->tao();
    batAi();
});

function trangThaiAiCuaToi(): array
{
    return test()->getJson('/api/v1/toi')->assertOk()->json('data.ai');
}

it('chưa khai ngày sinh: ai_can_ngay_sinh; khai hợp lệ thì lưu 00:00 UTC; khai lần hai thì 409', function () {
    $this->actingAs($user = nguoiHocAi(['birth_date' => null]));

    giaiThich(theMau('das Brötchen'))->assertForbidden()->assertJson(['code' => 'ai_can_ngay_sinh']);
    expect(trangThaiAiCuaToi())->toBe(['state' => 'need_birth_date', 'min_age' => 18]);

    $this->putJson('/api/v1/toi/ngay-sinh', ['birth_date' => '1990-05-17'])->assertOk()->assertJsonPath('data.ai.state', 'ready');
    expect($user->fresh()->birth_date)->toBe(gmmktime(0, 0, 0, 5, 17, 1990));

    $this->putJson('/api/v1/toi/ngay-sinh', ['birth_date' => '1991-01-01'])->assertStatus(409)->assertJson(['code' => 'ngay_sinh_da_khai']);
    expect($user->fresh()->birth_date)->toBe(gmmktime(0, 0, 0, 5, 17, 1990));
});

it('ngày sinh trước 1970 lưu số âm', function () {
    $this->actingAs($user = nguoiHocAi(['birth_date' => null]));

    $this->putJson('/api/v1/toi/ngay-sinh', ['birth_date' => '1950-12-31'])->assertOk();
    expect($user->fresh()->birth_date)->toBe(gmmktime(0, 0, 0, 12, 31, 1950))->toBeLessThan(0);
});

it('ngày sinh sai: 422, không lưu', function (mixed $ngay) {
    $this->actingAs($user = nguoiHocAi(['birth_date' => null]));

    $this->putJson('/api/v1/toi/ngay-sinh', ['birth_date' => $ngay])->assertUnprocessable()->assertJsonValidationErrors('birth_date');
    expect($user->fresh()->birth_date)->toBeNull();
})->with([
    'ngày mai' => [fn () => CarbonImmutable::now('Asia/Ho_Chi_Minh')->addDay()->format('Y-m-d')],
    'trước 1900' => ['1899-12-31'],
    'sai dạng' => ['17/05/1990'],
    'ngày không có thật' => ['2001-02-31'],
    'trống' => [''],
    'không phải chữ' => [19900517],
]);

it('thiếu một ngày mới đủ 18: underage; đúng sinh nhật 18: dùng được', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-10 12:00', 'Asia/Ho_Chi_Minh'));

    $this->actingAs(nguoiHocAi(['birth_date' => gmmktime(0, 0, 0, 3, 11, 2008)]));
    expect(trangThaiAiCuaToi())->toBe(['state' => 'underage', 'min_age' => 18]);
    giaiThich(theMau('das Brötchen'))->assertForbidden()->assertJson(['code' => 'ai_chua_du_tuoi', 'message' => 'AI chỉ dành cho người từ 18 tuổi.']);

    $this->actingAs(nguoiHocAi(['birth_date' => gmmktime(0, 0, 0, 3, 10, 2008)]));
    giaiThich(theMau('das Brötchen'))->assertOk();
});

it('sinh 29/2/2008: ngày 28/2/2026 chưa đủ 18, ngày 1/3/2026 đủ', function () {
    $this->travelTo(CarbonImmutable::parse('2026-02-28 20:00', 'Asia/Ho_Chi_Minh'));
    $this->actingAs(nguoiHocAi(['birth_date' => gmmktime(0, 0, 0, 2, 29, 2008)]));
    expect(trangThaiAiCuaToi()['state'])->toBe('underage');

    $this->travelTo(CarbonImmutable::parse('2026-03-01 08:00', 'Asia/Ho_Chi_Minh'));
    expect(trangThaiAiCuaToi()['state'])->toBe('ready');
});

it('đủ tuổi tính theo ngày ở múi giờ người học (Việt Nam đã sang ngày mới, UTC chưa)', function () {
    // 18:00 UTC ngày 9/3 là 1:00 sáng ngày 10/3 ở Việt Nam
    $this->travelTo(CarbonImmutable::parse('2026-03-09 18:00', 'UTC'));
    $this->actingAs(nguoiHocAi(['timezone' => 'Asia/Ho_Chi_Minh', 'birth_date' => gmmktime(0, 0, 0, 3, 10, 2008)]));
    expect(trangThaiAiCuaToi()['state'])->toBe('ready');

    $this->actingAs(nguoiHocAi(['timezone' => 'Europe/Berlin', 'birth_date' => gmmktime(0, 0, 0, 3, 10, 2008)]));
    expect(trangThaiAiCuaToi()['state'])->toBe('underage');
});

it('chưa đồng ý: ai_can_dong_y; đồng ý ghi một dòng consents đủ cột; rút thì khoá ngay; đồng ý lại thì mở', function () {
    $this->actingAs($user = nguoiHocAi([], true, false));

    giaiThich(theMau('das Brötchen'))->assertForbidden()->assertJson(['code' => 'ai_can_dong_y']);
    expect(trangThaiAiCuaToi())->toBe(['state' => 'need_consent', 'min_age' => 18, 'consent_version' => '2026-11']);

    $this->putJson('/api/v1/toi/dong-y-ai', ['granted' => true, 'version' => '2026-11'])->assertOk()->assertJsonPath('data.ai.state', 'ready');
    $dong = DB::table('consents')->sole();
    expect($dong->user_id)->toBe($user->id)
        ->and($dong->kind)->toBe('ai_processing')
        ->and($dong->document_version)->toBe('2026-11')
        ->and((int) $dong->granted)->toBe(1)
        ->and($dong->source)->toBe('app')
        ->and($dong->ip)->not->toBeNull()
        ->and((int) $dong->created_at)->toBeGreaterThan(0);
    giaiThich(theMau('das Brötchen'))->assertOk();

    $this->travel(1)->seconds();
    $this->putJson('/api/v1/toi/dong-y-ai', ['granted' => false, 'version' => '2026-11'])->assertOk()->assertJsonPath('data.ai.state', 'need_consent');
    giaiThich(theMau('das Mehl'))->assertForbidden()->assertJson(['code' => 'ai_can_dong_y']);
    expect(DB::table('consents')->pluck('granted')->map(fn ($v) => (int) $v)->all())->toBe([1, 0]);

    $this->travel(1)->seconds();
    $this->putJson('/api/v1/toi/dong-y-ai', ['granted' => true, 'version' => '2026-11'])->assertOk();
    giaiThich(theMau('das Mehl'))->assertOk();
});

it('đồng ý bản lời đồng ý cũ coi như chưa đồng ý; gửi sai phiên bản hay thiếu ô thì 422', function () {
    $this->actingAs($user = nguoiHocAi([], true, false));
    DB::table('consents')->insert(['user_id' => $user->id, 'kind' => 'ai_processing', 'document_version' => '2025-01', 'granted' => 1, 'source' => 'app', 'created_at' => now()->getTimestamp()]);

    expect(trangThaiAiCuaToi()['state'])->toBe('need_consent');
    $this->putJson('/api/v1/toi/dong-y-ai', ['granted' => true, 'version' => '2025-01'])->assertUnprocessable()->assertJsonValidationErrors('version');
    $this->putJson('/api/v1/toi/dong-y-ai', ['version' => '2026-11'])->assertUnprocessable()->assertJsonValidationErrors('granted');
    expect(DB::table('consents')->count())->toBe(1);
});

it('ô Tuổi tối thiểu đặt 16 trong database: vẫn dùng 18', function () {
    DB::table('settings')->upsert([['name' => 'ai.min_age', 'value' => '16', 'updated_at' => time()]], ['name'], ['value']);
    $this->travelTo(CarbonImmutable::parse('2026-03-10 12:00', 'Asia/Ho_Chi_Minh'));
    $this->actingAs(nguoiHocAi(['birth_date' => gmmktime(0, 0, 0, 1, 1, 2009)]));

    expect(trangThaiAiCuaToi())->toBe(['state' => 'underage', 'min_age' => 18]);
});

it('trang người học: có users.manage thì sửa được ngày sinh, có nhật ký kèm lý do; thiếu quyền thì không thấy nút', function () {
    $hoc = User::factory()->create(['birth_date' => gmmktime(0, 0, 0, 3, 11, 2010)]);

    $this->actingAs($nguoi = nhanVien('support'));
    Livewire::test(ViewNguoiHoc::class, ['record' => $hoc->public_id])
        ->assertSee('11/03/2010')
        ->assertActionVisible('sua-ngay-sinh')
        ->callAction('sua-ngay-sinh', data: ['ngay_sinh' => '2030-01-01', 'ly_do' => 'x'])
        ->assertHasActionErrors(['ngay_sinh']);
    expect($hoc->fresh()->birth_date)->toBe(gmmktime(0, 0, 0, 3, 11, 2010));

    Livewire::test(ViewNguoiHoc::class, ['record' => $hoc->public_id])
        ->callAction('sua-ngay-sinh', data: ['ngay_sinh' => '2000-03-11', 'ly_do' => 'Người học gửi giấy tờ'])
        ->assertHasNoActionErrors();

    expect($hoc->fresh()->birth_date)->toBe(gmmktime(0, 0, 0, 3, 11, 2000));
    $dong = dongNhatKy('user.ngay_sinh');
    expect($dong['actor_id'])->toBe($nguoi->id)
        ->and(json_decode($dong['changes'], true))->toBe(['before' => ['birth_date' => '2010-03-11'], 'after' => ['birth_date' => '2000-03-11', 'ly_do' => 'Người học gửi giấy tờ']]);

    // xem được người học (users.view) nhưng không có users.manage
    $chiXem = nhanVien('content_editor');
    $chiXem->givePermissionTo('users.view');
    $this->actingAs($chiXem);
    Livewire::test(ViewNguoiHoc::class, ['record' => $hoc->public_id])->assertActionHidden('sua-ngay-sinh');
});
