<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Đăng nhập như mặc định của Fortify (đếm lần sai, chặn tạm vẫn do Fortify lo), thêm: tài khoản bị khoá thì báo rõ
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where('email', (string) $request->input(Fortify::username()))->first();

            if ($user === null || $user->password === null || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
                return null;
            }

            if ($user->status !== 'active') {
                throw ValidationException::withMessages([Fortify::username() => __('auth.bi_khoa')]);
            }

            return $user;
        });

        Fortify::loginView(fn () => view('tai-khoan.dang-nhap'));
        Fortify::registerView(fn () => view('tai-khoan.dang-ky'));
        Fortify::requestPasswordResetLinkView(fn () => view('tai-khoan.quen-mat-khau'));
        Fortify::resetPasswordView(fn (Request $request) => view('tai-khoan.dat-lai-mat-khau', [
            'token' => $request->route('token'),
            'email' => $request->query('email'),
        ]));
        Fortify::verifyEmailView(fn () => view('tai-khoan.xac-minh-email'));
        Fortify::confirmPasswordView(fn () => view('tai-khoan.xac-nhan-mat-khau'));
    }
}
