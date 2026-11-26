<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {--email= : Email đăng nhập} {--name= : Tên hiện trong trang quản trị}';

    protected $description = 'Tạo tài khoản nhân viên để đăng nhập trang quản trị /admin';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: text('Email', required: true));
        $name = (string) ($this->option('name') ?: text('Tên', default: 'Quản trị', required: true));
        $password = password('Mật khẩu (ít nhất 12 ký tự)', required: true);

        $validator = Validator::make(compact('email', 'name', 'password'), [
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['is_staff' => true, 'status' => 'active', 'email_verified_at' => now()])->save();
        // Vai trò có sẵn trong seed.sql (deploy nạp); database chưa nạp thì tạo luôn vai trò này
        $user->assignRole(Role::findOrCreate(Role::CAO_NHAT, 'web'));

        $this->info("Đã tạo tài khoản quản trị {$user->email} (vai trò Quản trị cao nhất).");

        return self::SUCCESS;
    }
}
