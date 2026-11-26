<?php

use App\Filament\Resources\NhanViens\Pages\CreateNhanVien;
use App\Filament\Resources\NhanViens\Pages\EditNhanVien;
use App\Filament\Resources\VaiTros\Pages\EditVaiTro;
use App\Models\ContentSource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// Trang quản trị (mục tự kiểm 10 của G1): người học bị từ chối; nhân viên thấy theo vai trò; mọi lần sửa ghi nhật ký.

function nhanVien(string $vaiTro, array $doi = []): User
{
    $user = User::factory()->create($doi);
    $user->forceFill(['is_staff' => true])->save();
    $user->assignRole($vaiTro);

    return $user;
}

/** @return array<string, mixed> */
function dongNhatKy(string $viec): array
{
    $dong = DB::table('audit_logs')->where('action', $viec)->latest('id')->first();
    expect($dong)->not->toBeNull("không có nhật ký {$viec}");

    return (array) $dong;
}

beforeEach(fn () => $this->seed());

it('người học (không phải nhân viên) vào /admin thì bị từ chối 403; nhân viên bị khoá cũng vậy', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    $this->actingAs(nhanVien('admin', ['status' => 'suspended']))->get('/admin')->assertForbidden();
    $this->actingAs(nhanVien('admin'))->get('/admin')->assertOk();
});

it('chỉ người có quyền phân quyền mới thấy trang Vai trò và Nhân viên', function () {
    $this->actingAs(nhanVien('content_editor'));
    $this->get('/admin/vai-tro')->assertForbidden();
    $this->get('/admin/nhan-vien')->assertForbidden();
    // admin có hầu hết quyền nhưng không có roles.manage (seed.sql)
    $this->actingAs(nhanVien('admin'))->get('/admin/vai-tro')->assertForbidden();

    $this->actingAs(nhanVien('super_admin'));
    $this->get('/admin/vai-tro')->assertOk()->assertSee('Biên tập viên')->assertSee('Quản trị cao nhất');
    $this->get('/admin/nhan-vien')->assertOk();
});

it('sửa quyền của vai trò: có hiệu lực ngay, ghi nhật ký quyền trước và sau', function () {
    $nguoiSua = nhanVien('super_admin');
    $bienTap = nhanVien('content_editor');
    $vaiTro = Role::findByName('content_editor');
    expect($bienTap->can('content.publish'))->toBeFalse();

    $quyen = Permission::whereIn('name', ['content.edit', 'content.publish', 'reports.handle'])->pluck('id')->all();
    $this->actingAs($nguoiSua);
    Livewire::test(EditVaiTro::class, ['record' => $vaiTro->getKey()])
        ->fillForm(['label' => 'Biên tập viên', 'permissions' => $quyen])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($bienTap->fresh()->can('content.publish'))->toBeTrue();
    $dong = dongNhatKy('role.permissions');
    expect($dong['actor_id'])->toBe($nguoiSua->id)
        ->and($dong['subject_id'])->toBe($vaiTro->id)
        ->and(json_decode($dong['changes'], true))->toBe([
            'before' => ['permissions' => ['content.edit', 'reports.handle']],
            'after' => ['permissions' => ['content.edit', 'content.publish', 'reports.handle']],
        ]);
});

it('không sửa được vai trò Quản trị cao nhất (đã có mọi quyền)', function () {
    $this->actingAs(nhanVien('super_admin'));
    $this->get('/admin/vai-tro/'.Role::findByName('super_admin')->getKey().'/edit')->assertForbidden();
});

it('tạo nhân viên: mật khẩu dưới 12 ký tự bị từ chối; đủ thì tạo tài khoản đã xác minh, có vai trò, ghi nhật ký', function () {
    $nguoiTao = nhanVien('super_admin');
    $this->actingAs($nguoiTao);
    $hoTro = Role::findByName('support')->getKey();

    Livewire::test(CreateNhanVien::class)
        ->fillForm(['name' => 'Hà', 'email' => 'ha@lernbuch.test', 'password' => 'ngan-qua-11', 'roles' => [$hoTro]])
        ->call('create')
        ->assertHasFormErrors(['password' => 'min']);
    expect(User::where('email', 'ha@lernbuch.test')->exists())->toBeFalse();

    Livewire::test(CreateNhanVien::class)
        ->fillForm(['name' => 'Hà', 'email' => 'Ha@Lernbuch.test', 'password' => 'du-muoi-hai-ky-tu', 'roles' => [$hoTro]])
        ->call('create')
        ->assertHasNoFormErrors();

    $ha = User::where('email', 'ha@lernbuch.test')->firstOrFail();
    expect($ha->is_staff)->toBeTrue()
        ->and($ha->hasVerifiedEmail())->toBeTrue()
        ->and($ha->getRoleNames()->all())->toBe(['support'])
        ->and($ha->created_by)->toBe($nguoiTao->id);
    expect(dongNhatKy('user.create')['subject_id'])->toBe($ha->id);
    expect(json_decode(dongNhatKy('user.roles')['changes'], true)['after'])->toBe(['roles' => ['support']]);
    expect(dongNhatKy('user.create')['changes'])->not->toContain('password');
});

it('sửa nhân viên: khoá tài khoản, đổi vai trò được ghi nhật ký; không tự khoá chính mình', function () {
    $nguoiSua = nhanVien('super_admin');
    $ha = nhanVien('support');
    $this->actingAs($nguoiSua);

    Livewire::test(EditNhanVien::class, ['record' => $ha->getKey()])
        ->fillForm(['status' => 'suspended', 'roles' => [Role::findByName('accountant')->getKey()]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($ha->fresh()->status)->toBe('suspended')->and($ha->fresh()->getRoleNames()->all())->toBe(['accountant']);
    expect(json_decode(dongNhatKy('user.update')['changes'], true))->toBe(['before' => ['status' => 'active'], 'after' => ['status' => 'suspended']]);
    expect(json_decode(dongNhatKy('user.roles')['changes'], true))->toBe(['before' => ['roles' => ['support']], 'after' => ['roles' => ['accountant']]]);

    Livewire::test(EditNhanVien::class, ['record' => $nguoiSua->getKey()])->fillForm(['status' => 'suspended'])->call('save');
    expect($nguoiSua->fresh()->status)->toBe('active');
});

it('nhân viên sửa nội dung thì ghi người sửa và nhật ký trước/sau; người học tự sửa hồ sơ thì không ghi nhật ký', function () {
    $bienTap = nhanVien('content_editor');
    $nguon = ContentSource::where('code', 'tatoeba')->firstOrFail();
    $this->actingAs($bienTap);

    $nguon->update(['notes' => 'Ghi công từng câu, có link về câu gốc.']);

    $dong = dongNhatKy('content_source.update');
    expect($dong['actor_id'])->toBe($bienTap->id)
        ->and($dong['subject_type'])->toBe(ContentSource::class)
        ->and(json_decode($dong['changes'], true))->toBe([
            'before' => ['notes' => 'Ghi công từng câu.'],
            'after' => ['notes' => 'Ghi công từng câu, có link về câu gốc.'],
        ]);
    expect($nguon->fresh()->updated_by)->toBe($bienTap->id);

    $nguoiHoc = User::factory()->create();
    $this->actingAs($nguoiHoc)->putJson('/api/v1/toi/ho-so', ['name' => 'Tên mới'])->assertOk();
    expect(DB::table('audit_logs')->where('actor_id', $nguoiHoc->id)->exists())->toBeFalse();
});

it('đăng nhập trang quản trị: sai 5 lần thì lần thứ 6 bị chặn tạm, kể cả khi nhập đúng', function () {
    nhanVien('super_admin', ['email' => 'qt@lernbuch.test', 'password' => 'dung-mat-khau-dai']);

    for ($i = 0; $i < 5; $i++) {
        Livewire::test(Login::class)
            ->fillForm(['email' => 'qt@lernbuch.test', 'password' => 'sai-mat-khau-'.$i])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
    }

    Livewire::test(Login::class)
        ->fillForm(['email' => 'qt@lernbuch.test', 'password' => 'dung-mat-khau-dai'])
        ->call('authenticate')
        ->assertNotified();
    $this->assertGuest();
});
