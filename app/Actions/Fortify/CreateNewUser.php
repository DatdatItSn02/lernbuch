<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /** Mỗi địa chỉ IP tạo được tối đa chừng này tài khoản mỗi giờ (chống đăng ký rác). */
    public const GIOI_HAN_MOI_GIO = 5;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $key = 'dang-ky:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::GIOI_HAN_MOI_GIO)) {
            throw ValidationException::withMessages([
                'email' => __('Mạng này đã tạo quá nhiều tài khoản. Thử lại sau :phut phút.', [
                    'phut' => max(1, (int) ceil(RateLimiter::availableIn($key) / 60)),
                ]),
            ]);
        }

        $input['email'] = Str::lower(trim((string) ($input['email'] ?? '')));

        Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ])->validate();

        RateLimiter::hit($key, 3600);

        return User::create([
            'name' => trim($input['name']),
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
