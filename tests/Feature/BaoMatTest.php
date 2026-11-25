<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

it('lỗi máy chủ chỉ hiện trang lỗi chung, không lộ nội dung lỗi hay đường dẫn', function () {
    config(['app.debug' => false]);
    Route::get('/_thu-loi-may-chu', fn () => throw new RuntimeException('Bí mật nội bộ ở '.base_path()));

    $this->get('/_thu-loi-may-chu')
        ->assertStatus(500)
        ->assertDontSee('Bí mật nội bộ')
        ->assertDontSee('RuntimeException')
        ->assertDontSee(base_path(), false);
});

it('đúng email và mật khẩu thì đăng nhập được trang quản trị', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $user = User::factory()->staff()->create();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(Filament::auth()->id())->toBe($user->id);
});

it('nhập sai mật khẩu 5 lần thì bị chặn tạm thời, kể cả lần sau đúng mật khẩu', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $user = User::factory()->staff()->create();
    $page = Livewire::test(Login::class);

    foreach (range(1, 5) as $lan) {
        $page->fillForm(['email' => $user->email, 'password' => 'sai-mat-khau-'.$lan])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
    }

    $page->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertNotified();

    expect(Filament::auth()->check())->toBeFalse();
});
