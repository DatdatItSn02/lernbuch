<?php

namespace App\Models;

use App\Models\Concerns\GhiNhatKy;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Vai trò của nhân viên (super_admin, admin, content_editor…), danh sách khởi tạo trong seed.sql.
 * Thời gian lưu dạng Unix timestamp như các bảng khác.
 *
 * @property int $id
 * @property string $name
 * @property string|null $label
 * @property string|null $description
 */
class Role extends SpatieRole
{
    use GhiNhatKy;

    /** Có mọi quyền qua Gate::before; không sửa quyền, không xoá. */
    public const CAO_NHAT = 'super_admin';

    /** Vai trò của người học, không phải nhân viên: không gán ở trang quản trị. */
    public const NGUOI_HOC = 'learner';

    protected $dateFormat = 'U';

    /** Tên hiện trong trang quản trị: nhãn tiếng Việt, chưa có thì mã. */
    public function tenHien(): string
    {
        return $this->label ?: $this->name;
    }
}
