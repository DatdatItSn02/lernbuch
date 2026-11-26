<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

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
