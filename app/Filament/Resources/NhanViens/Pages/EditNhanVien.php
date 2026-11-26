<?php

namespace App\Filament\Resources\NhanViens\Pages;

use App\Filament\Resources\NhanViens\NhanVienResource;
use App\Models\User;
use App\Support\NhatKy;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Spatie\Permission\PermissionRegistrar;

/** Sửa nhân viên: tên, mật khẩu mới, khoá/mở, vai trò. Đổi vai trò thì ghi nhật ký vai trò trước và sau. */
class EditNhanVien extends EditRecord
{
    protected static string $resource = NhanVienResource::class;

    /** @var list<string> */
    private array $vaiTroTruoc = [];

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->fill(Arr::only($data, ['name', 'password']));

        if (array_key_exists('status', $data) && $record->getKey() !== auth()->id()) {
            $record->forceFill(['status' => $data['status']]);
        }

        $record->save();

        return $record;
    }

    protected function beforeSave(): void
    {
        $this->vaiTroTruoc = $this->vaiTro();
    }

    protected function afterSave(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $sau = $this->vaiTro();

        if ($sau !== $this->vaiTroTruoc) {
            NhatKy::ghi('user.roles', $this->getRecord(), ['roles' => $this->vaiTroTruoc], ['roles' => $sau]);
        }
    }

    /** @return list<string> */
    private function vaiTro(): array
    {
        $user = $this->getRecord();

        /** @var list<string> $ten */
        $ten = $user instanceof User ? $user->roles()->orderBy('name')->pluck('name')->all() : [];

        return $ten;
    }
}
