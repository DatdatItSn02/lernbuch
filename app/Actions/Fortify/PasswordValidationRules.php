<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Mật khẩu người học: ít nhất 8 ký tự, có chữ và số (Password::defaults trong AppServiceProvider),
     * tối đa 72 byte vì bcrypt bỏ phần sau.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', 'max:72', Password::default(), 'confirmed'];
    }
}
