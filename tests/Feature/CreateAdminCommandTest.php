<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('tạo tài khoản quản trị bằng lệnh', function () {
    $this->artisan('app:create-admin', ['--email' => 'admin@example.com', '--name' => 'Quản trị'])
        ->expectsQuestion('Mật khẩu (ít nhất 12 ký tự)', 'mat-khau-dai-123')
        ->assertSuccessful();

    $user = User::where('email', 'admin@example.com')->firstOrFail();

    expect($user->is_staff)->toBeTrue()
        ->and($user->status)->toBe('active')
        ->and(strlen($user->public_id))->toBe(26)
        ->and(Hash::check('mat-khau-dai-123', (string) $user->password))->toBeTrue()
        ->and($user->hasRole('super_admin'))->toBeTrue();
});

it('đã nạp seed.sql thì gán đúng vai trò có sẵn, không tạo vai trò trùng', function () {
    $this->seed();
    $this->artisan('app:create-admin', ['--email' => 'admin@example.com', '--name' => 'Quản trị'])
        ->expectsQuestion('Mật khẩu (ít nhất 12 ký tự)', 'mat-khau-dai-123')
        ->assertSuccessful();

    expect(Role::where('name', 'super_admin')->count())->toBe(1)
        ->and(User::where('email', 'admin@example.com')->firstOrFail()->can('settings.manage'))->toBeTrue();
});

it('từ chối email đã có, kể cả khác hoa thường', function () {
    User::factory()->create(['email' => 'admin@example.com']);

    $this->artisan('app:create-admin', ['--email' => 'ADMIN@example.com', '--name' => 'Trùng'])
        ->expectsQuestion('Mật khẩu (ít nhất 12 ký tự)', 'mat-khau-dai-123')
        ->assertFailed();

    expect(User::count())->toBe(1);
});

it('từ chối mật khẩu ngắn hơn 12 ký tự', function () {
    $this->artisan('app:create-admin', ['--email' => 'admin@example.com', '--name' => 'Quản trị'])
        ->expectsQuestion('Mật khẩu (ít nhất 12 ký tự)', 'ngan')
        ->assertFailed();

    expect(User::count())->toBe(0);
});
