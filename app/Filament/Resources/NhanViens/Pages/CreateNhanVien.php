<?php

namespace App\Filament\Resources\NhanViens\Pages;

use App\Filament\Resources\NhanViens\NhanVienResource;
use App\Models\User;
use App\Support\NhatKy;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/** Tạo tài khoản nhân viên: luôn là nhân viên, đang làm, không cần xác minh email (người quản trị tạo). */
class CreateNhanVien extends CreateRecord
{
    protected static string $resource = NhanVienResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        $user = new User(Arr::only($data, ['name', 'email', 'password']));
        $user->email = Str::lower(trim((string) $user->email));
        $user->forceFill(['is_staff' => true, 'status' => 'active', 'email_verified_at' => now()])->save();

        return $user;
    }

    protected function afterCreate(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $this->getRecord();

        if ($user instanceof User) {
            NhatKy::ghi('user.roles', $user, ['roles' => []], ['roles' => $user->getRoleNames()->sort()->values()->all()]);
        }
    }
}
