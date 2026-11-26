<?php

namespace App\Filament\Resources\VaiTros\Pages;

use App\Filament\Resources\VaiTros\VaiTroResource;
use App\Models\Role;
use App\Support\NhatKy;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

/** Sửa quyền của một vai trò: ghi nhật ký quyền trước và sau, xoá bộ nhớ đệm quyền để có hiệu lực ngay. */
class EditVaiTro extends EditRecord
{
    protected static string $resource = VaiTroResource::class;

    /** @var list<string> */
    private array $quyenTruoc = [];

    protected function beforeSave(): void
    {
        $this->quyenTruoc = $this->quyen();
    }

    protected function afterSave(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $sau = $this->quyen();

        if ($sau !== $this->quyenTruoc) {
            NhatKy::ghi('role.permissions', $this->getRecord(), ['permissions' => $this->quyenTruoc], ['permissions' => $sau]);
        }
    }

    /** @return list<string> */
    private function quyen(): array
    {
        $vaiTro = $this->getRecord();

        /** @var list<string> $ten */
        $ten = $vaiTro instanceof Role ? $vaiTro->permissions()->orderBy('name')->pluck('name')->all() : [];

        return $ten;
    }
}
