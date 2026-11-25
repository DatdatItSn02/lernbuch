<?php

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
        ->and(Hash::check('mat-khau-dai-123', (string) $user->password))->toBeTrue();
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
