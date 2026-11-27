<?php

use App\Filament\Resources\NguoiHocs\NguoiHocResource;
use App\Filament\Resources\NguoiHocs\Pages\ListNguoiHocs;
use App\Filament\Resources\NguoiHocs\Pages\ViewNguoiHoc;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\Hoc\LapLich;
use App\Support\QuanTri\KhoaTaiKhoan;
use App\Support\QuanTri\TienDoNguoiHoc;
use Database\Seeders\NoiDungThuSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// Trang quản trị Người học (tuần 5 G1, mục tự kiểm 10): cần quyền xem người dùng; tìm, xem tiến độ; khoá thì người học
// bị đăng xuất mọi máy, API trả 403, không đăng nhập lại được; mở khoá thì vào lại được. Mỗi lần khoá, mở ghi nhật ký.

beforeEach(fn () => $this->seed());

it('cần quyền xem người dùng; chỉ liệt kê người học (không có nhân viên), tìm theo email; không có trang tạo, sửa', function () {
    $lan = User::factory()->create(['name' => 'Lan', 'email' => 'lan@vidu.test']);
    $minh = User::factory()->create(['name' => 'Minh', 'email' => 'minh@vidu.test']);

    $this->actingAs(nhanVien('accountant'))->get('/admin/nguoi-hoc')->assertForbidden();
    $this->actingAs(nhanVien('content_editor'))->get('/admin/nguoi-hoc')->assertForbidden();

    $hoTro = nhanVien('support');
    $this->actingAs($hoTro);
    $this->get('/admin/nguoi-hoc')->assertOk()->assertSee('Người học');
    Livewire::test(ListNguoiHocs::class)
        ->assertCanSeeTableRecords([$lan, $minh])
        ->assertCanNotSeeTableRecords([$hoTro])
        ->searchTable('lan@vidu')
        ->assertCanSeeTableRecords([$lan])
        ->assertCanNotSeeTableRecords([$minh]);

    // Đường dẫn dùng mã ULID; nhân viên không mở được qua trang người học; không có trang sửa
    $this->get('/admin/nguoi-hoc/'.$lan->public_id)->assertOk()->assertSee('lan@vidu.test');
    $this->get('/admin/nguoi-hoc/'.$lan->id)->assertNotFound();
    $this->get('/admin/nguoi-hoc/'.$hoTro->public_id)->assertNotFound();
    $this->get('/admin/nguoi-hoc/'.$lan->public_id.'/edit')->assertNotFound();
});

it('xem tiến độ: cùng số liệu với trang Hôm nay của người học (thẻ theo mức, đã ôn hôm nay, chuỗi, xu, phiên học)', function () {
    $m = (new NoiDungThuSeeder)->tao();
    $hoc = User::factory()->create();
    $now = now()->getTimestamp();
    $homNay = LapLich::dauNgay($now, $hoc->timezone);

    foreach (['zahlen' => 1, 'zählen' => 1, 'die Zahl' => 2] as $front => $box) {
        DB::table('card_progress')->insert(['user_id' => $hoc->id, 'card_id' => $m->the[$front], 'box' => $box, 'due_at' => $now + 86400 * 3, 'reps' => 1]);
    }
    DB::table('user_daily_stats')->insert(['user_id' => $hoc->id, 'day_start' => $homNay, 'reviews' => 2, 'new_cards' => 3, 'coins' => 25]);
    DB::table('user_stats')->insert(['user_id' => $hoc->id, 'coins_total' => 140, 'coins_balance' => 140, 'streak_current' => 4, 'streak_longest' => 9, 'streak_last_day' => $homNay]);
    DB::table('study_sessions')->insert(['user_id' => $hoc->id, 'mode' => 'quiz', 'started_at' => $now - 600]);

    $t = TienDoNguoiHoc::cua($hoc);
    expect($t['da_hoc'])->toBe(3)
        ->and(array_slice($t['levels'], 1))->toBe([2, 1, 0, 0, 0])
        ->and($t['reviewed_today'])->toBe(5)
        ->and($t['streak'])->toBe(4)->and($t['streak_longest'])->toBe(9)
        ->and($t['coins'])->toBe(140)->and($t['coins_today'])->toBe(25)
        ->and($t['phien'])->toBe(1)->and($t['phien_cuoi'])->toBe($now - 600)
        ->and(end($t['ngay']))->toBe(['date' => date('Y-m-d', $homNay + 43200), 'count' => 5])
        ->and(TienDoNguoiHoc::theoMuc($t['levels']))->toContain('Đang học 2 · Quen 1')
        ->and(TienDoNguoiHoc::theoMuc($t['levels']))->not->toContain('Khá');

    $this->actingAs(nhanVien('support'));
    Livewire::test(ViewNguoiHoc::class, ['record' => $hoc->public_id])
        ->assertSee('Thẻ theo mức')
        ->assertSee('Đang học 2 · Quen 1')
        ->assertSee('5 / 20 thẻ')
        ->assertSee('4 ngày (dài nhất 9)')
        ->assertSee('140 (hôm nay 25)');
});

it('khoá: đăng xuất mọi máy, API trả 403, trang app về đăng nhập, không đăng nhập lại được; mở khoá thì vào lại; ghi nhật ký có lý do', function () {
    $hoc = User::factory()->create(['email' => 'hoc@vidu.test']);
    DB::table('sessions')->insert(['id' => 'phien-may-khac', 'user_id' => $hoc->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
    $hoTro = nhanVien('support');
    $this->actingAs($hoTro);

    Livewire::test(ViewNguoiHoc::class, ['record' => $hoc->public_id])
        ->assertActionVisible('khoa')
        ->assertActionHidden('mo-khoa')
        ->callAction('khoa', data: ['ly_do' => 'Gửi thư rác'])
        ->assertHasNoActionErrors()
        ->assertNotified('Đã khoá tài khoản');

    expect($hoc->fresh()->status)->toBe(KhoaTaiKhoan::BI_KHOA)
        ->and($hoc->fresh()->updated_by)->toBe($hoTro->id)
        ->and(DB::table('sessions')->where('user_id', $hoc->id)->exists())->toBeFalse();
    $dong = dongNhatKy('user.suspend');
    expect($dong['actor_id'])->toBe($hoTro->id)->and($dong['subject_id'])->toBe($hoc->id)
        ->and(json_decode($dong['changes'], true))->toEqual(['before' => ['status' => 'active'], 'after' => ['status' => 'suspended', 'reason' => 'Gửi thư rác']]);
    // Một dòng nhật ký cho việc khoá, không thêm dòng user.update
    expect(DB::table('audit_logs')->where('action', 'user.update')->where('subject_id', $hoc->id)->exists())->toBeFalse();

    // Còn phiên trên máy (đăng nhập từ trước khi khoá): API trả 403 và đăng xuất phiên đó
    auth()->guard('web')->logout();
    $this->actingAs($hoc->fresh())->getJson('/api/v1/hom-nay')->assertForbidden()->assertJson(['code' => 'tai_khoan_bi_khoa']);
    $this->assertGuest('web');
    // Gọi API xong thì guard mặc định của lần test này là sanctum: trang web đăng nhập lại bằng guard web như trình duyệt
    $this->actingAs($hoc->fresh(), 'web')->get('/app/hom-nay')->assertRedirect('/dang-nhap')->assertSessionHasErrors(['email' => __('auth.bi_khoa')]);
    $this->assertGuest('web');

    // Đăng nhập lại: đúng mật khẩu thì báo bị khoá; sai mật khẩu thì báo như mọi lần sai (không lộ là tài khoản bị khoá)
    $this->from('/dang-nhap')->post('/dang-nhap', ['email' => 'hoc@vidu.test', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.bi_khoa')]);
    $this->assertGuest('web');
    $this->from('/dang-nhap')->post('/dang-nhap', ['email' => 'hoc@vidu.test', 'password' => 'sai-mat-khau'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    // Mở khoá: đăng nhập lại được
    $this->actingAs($hoTro);
    Livewire::test(ViewNguoiHoc::class, ['record' => $hoc->public_id])
        ->assertActionHidden('khoa')
        ->assertActionVisible('mo-khoa')
        ->callAction('mo-khoa')
        ->assertNotified('Đã mở khoá tài khoản');
    expect($hoc->fresh()->status)->toBe(KhoaTaiKhoan::DANG_HOC)
        ->and(json_decode(dongNhatKy('user.unsuspend')['changes'], true))->toEqual(['before' => ['status' => 'suspended'], 'after' => ['status' => 'active']]);

    auth()->guard('web')->logout();
    $this->post('/dang-nhap', ['email' => 'hoc@vidu.test', 'password' => 'password'])->assertRedirect('/app/hom-nay');
    $this->assertAuthenticatedAs($hoc->fresh());
    // Yêu cầu thật nào cũng dựng guard mới; trong test guard sanctum còn nhớ người dùng (bị khoá) của lần gọi API trước
    auth()->guard('sanctum')->forgetUser();
    $this->getJson('/api/v1/hom-nay')->assertOk();
});

it('chỉ người có quyền quản lý người dùng mới khoá được; khoá hai lần không ghi thêm; không khoá nhân viên ở đây', function () {
    $hoc = User::factory()->create();
    $chiXem = nhanVien('accountant');
    $chiXem->givePermissionTo('users.view');
    $this->actingAs($chiXem);

    Livewire::test(ViewNguoiHoc::class, ['record' => $hoc->public_id])
        ->assertSee($hoc->email)
        ->assertActionHidden('khoa')
        ->assertActionHidden('mo-khoa');

    $this->actingAs(nhanVien('admin'));
    KhoaTaiKhoan::khoa($hoc, 'lần một');
    KhoaTaiKhoan::khoa($hoc->fresh(), 'lần hai');
    expect(DB::table('audit_logs')->where('action', 'user.suspend')->count())->toBe(1);

    expect(fn () => KhoaTaiKhoan::khoa(nhanVien('support')))->toThrow(InvalidArgumentException::class);
});

it('nút Lọc của bảng có tên đọc được kèm số bộ lọc đang dùng, như số hiện trên nút (WCAG 2.5.3)', function () {
    expect(AdminPanelProvider::nhanNutLoc(2))->toBe('Lọc, đang dùng 2');

    $this->actingAs(nhanVien('support'));
    $this->get('/admin/nguoi-hoc')->assertOk()->assertSee('aria-label="Lọc, đang dùng 0"', false);
    Livewire::test(ListNguoiHocs::class)
        ->filterTable('status', KhoaTaiKhoan::BI_KHOA)
        ->assertSeeHtml('aria-label="Lọc, đang dùng 1"')
        ->filterTable('email_verified_at', true)
        ->assertSeeHtml('aria-label="Lọc, đang dùng 2"')
        ->assertDontSeeHtml('aria-label="Lọc"');
});

it('danh sách mở trang người học bằng mã ULID (không bằng id); nhãn tiếng Việt chỉ viết hoa chữ đầu', function () {
    $lan = User::factory()->create(['name' => 'Lan', 'email' => 'lan@vidu.test']);
    $this->actingAs(nhanVien('super_admin'));

    $trang = $this->get('/admin/nguoi-hoc')->assertOk()->assertSee('Nguồn và giấy phép')->assertDontSee('Nguồn Và Giấy Phép')->getContent();
    expect($trang)->toContain('/admin/nguoi-hoc/'.$lan->public_id.'"')
        ->not->toContain('/admin/nguoi-hoc/'.$lan->id.'"')
        ->and(NguoiHocResource::getUrl('view', ['record' => $lan]))->toEndWith('/admin/nguoi-hoc/'.$lan->public_id);
    $this->get(NguoiHocResource::getUrl('view', ['record' => $lan]))->assertOk()->assertSee('lan@vidu.test');
});
