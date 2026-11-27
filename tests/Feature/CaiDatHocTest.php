<?php

use App\Filament\Pages\TrangCaiDatHoc;
use App\Models\User;
use App\Support\Hoc\CaiDatHoc;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// Ngưỡng chấm tối thiểu theo chế độ (mục tự kiểm 7): sửa trong trang quản trị, cần quyền settings.manage, ghi nhật ký.

function nhanVienHoc(string $vaiTro): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_staff' => true])->save();
    $user->assignRole($vaiTro);

    return $user;
}

beforeEach(fn () => $this->seed());

it('chưa sửa gì thì dùng ngưỡng mặc định', function () {
    expect(CaiDatHoc::nguong())->toBe(CaiDatHoc::NGUONG_MAC_DINH);
});

it('chỉ người có quyền cấu hình hệ thống mới vào được trang Cài đặt học', function () {
    $this->actingAs(User::factory()->create())->get('/admin/cai-dat-hoc')->assertForbidden();
    $this->actingAs(nhanVienHoc('content_editor'))->get('/admin/cai-dat-hoc')->assertForbidden();
    // admin có hầu hết quyền nhưng không có settings.manage (seed.sql)
    $this->actingAs(nhanVienHoc('admin'))->get('/admin/cai-dat-hoc')->assertForbidden();
    $this->actingAs(nhanVienHoc('super_admin'))->get('/admin/cai-dat-hoc')->assertOk()->assertSee('Ngưỡng chấm tối thiểu')->assertSee('Flashcard');
});

it('lưu ngưỡng: có hiệu lực ngay, ghi nhật ký trước và sau', function () {
    $this->actingAs($nguoi = nhanVienHoc('super_admin'));
    $moi = [...CaiDatHoc::NGUONG_MAC_DINH, 'flashcard' => 500, 'sentence' => 0];

    Livewire::test(TrangCaiDatHoc::class)
        ->assertFormSet(['nguong' => CaiDatHoc::NGUONG_MAC_DINH])
        ->fillForm(['nguong' => $moi])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(CaiDatHoc::nguong())->toBe($moi)
        ->and(DB::table('settings')->where('name', CaiDatHoc::TEN_NGUONG)->value('updated_by'))->toBe($nguoi->id);
    $dong = DB::table('audit_logs')->where('action', 'setting.update')->latest('id')->first();
    expect($dong->actor_id)->toBe($nguoi->id)
        ->and(json_decode($dong->changes, true))->toEqual([
            'before' => [CaiDatHoc::TEN_NGUONG => CaiDatHoc::NGUONG_MAC_DINH],
            'after' => [CaiDatHoc::TEN_NGUONG => $moi],
        ]);

    // Lưu lại y như cũ: không ghi thêm nhật ký
    Livewire::test(TrangCaiDatHoc::class)->assertFormSet(['nguong' => $moi])->call('save')->assertHasNoFormErrors();
    expect(DB::table('audit_logs')->where('action', 'setting.update')->count())->toBe(1);
});

it('ngưỡng âm, quá 10 giây hay không phải số thì báo lỗi đúng ô, không lưu gì', function (mixed $giaTri) {
    $this->actingAs(nhanVienHoc('super_admin'));

    Livewire::test(TrangCaiDatHoc::class)
        ->fillForm(['nguong' => [...CaiDatHoc::NGUONG_MAC_DINH, 'quiz' => $giaTri]])
        ->call('save')
        ->assertHasFormErrors(['nguong.quiz']);

    expect(DB::table('settings')->where('name', CaiDatHoc::TEN_NGUONG)->exists())->toBeFalse();
})->with(['âm' => [-1], 'quá 10 giây' => [10001], 'chữ' => ['nhanh'], 'trống' => [null]]);

it('giá trị lưu hỏng trong bảng settings thì bỏ, dùng mặc định cho ô đó', function () {
    DB::table('settings')->insert(['name' => CaiDatHoc::TEN_NGUONG, 'value' => json_encode(['flashcard' => 'x', 'quiz' => 999999, 'match' => 300, 'khac' => 5]), 'updated_at' => time()]);

    expect(CaiDatHoc::nguong())->toBe([...CaiDatHoc::NGUONG_MAC_DINH, 'match' => 300]);

    DB::table('settings')->where('name', CaiDatHoc::TEN_NGUONG)->update(['value' => '"hỏng"']);
    expect(CaiDatHoc::nguong())->toBe(CaiDatHoc::NGUONG_MAC_DINH);
});

it('xu mỗi lượt: Tốt, Dễ đủ xu của chế độ; Khó một nửa làm tròn xuống; Quên không có', function () {
    expect(CaiDatHoc::xu('flashcard', 2))->toBe(5)->and(CaiDatHoc::xu('flashcard', 3))->toBe(5)
        ->and(CaiDatHoc::xu('flashcard', 1))->toBe(2)->and(CaiDatHoc::xu('listening', 1))->toBe(7)
        ->and(CaiDatHoc::xu('flashcard', 0))->toBe(0)->and(CaiDatHoc::xu('khong-co', 2))->toBe(0);
});

it('xu theo chế độ khớp bảng dùng chung với app (tests/chung/xu-che-do.json), đủ 7 chế độ học và Điền câu', function () {
    $chung = json_decode((string) file_get_contents(base_path('tests/chung/xu-che-do.json')), true)['xu'];

    expect(CaiDatHoc::XU)->toBe($chung)
        ->and(CaiDatHoc::CHE_DO_CHAM)->toBe([...CaiDatHoc::CHE_DO, 'cloze'])
        ->and(array_keys(CaiDatHoc::XU))->toBe(CaiDatHoc::CHE_DO_CHAM)
        ->and(array_keys(CaiDatHoc::NGUONG_MAC_DINH))->toBe(CaiDatHoc::CHE_DO_CHAM)
        ->and(array_keys(TrangCaiDatHoc::TEN_CHE_DO))->toBe(CaiDatHoc::CHE_DO_CHAM);
});
