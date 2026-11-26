<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Quyền (users.view, content.edit, roles.manage…), danh sách khởi tạo trong seed.sql. Thời gian lưu dạng Unix timestamp.
 *
 * @property int $id
 * @property string $name
 * @property string|null $label
 */
class Permission extends SpatiePermission
{
    protected $dateFormat = 'U';
}
