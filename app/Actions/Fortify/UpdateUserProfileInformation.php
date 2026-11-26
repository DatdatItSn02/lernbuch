<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Đổi tên, email. Đổi email thì phải xác minh lại email mới.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $input['email'] = Str::lower(trim((string) ($input['email'] ?? '')));

        Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users')->ignore($user->id)],
        ])->validateWithBag('updateProfileInformation');

        if ($input['email'] !== $user->email) {
            $user->forceFill([
                'name' => trim($input['name']),
                'email' => $input['email'],
                'email_verified_at' => null,
            ])->save();

            $user->sendEmailVerificationNotification();

            return;
        }

        $user->forceFill(['name' => trim($input['name'])])->save();
    }
}
