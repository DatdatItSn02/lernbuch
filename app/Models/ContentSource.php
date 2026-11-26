<?php

namespace App\Models;

use App\Models\Concerns\GhiNhatKy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nguồn nội dung và giấy phép (content_sources): commercial_use cho biết có được dùng để bán không.
 * Danh sách khởi tạo trong seed.sql; trang quản trị nguồn làm ở tuần 5.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $license
 * @property string $commercial_use
 */
class ContentSource extends Model
{
    use GhiNhatKy, SoftDeletes;

    /** Giá trị của commercial_use được dùng nội dung để bán. */
    public const DUOC_BAN = ['allowed', 'licensed'];

    protected $dateFormat = 'U';

    protected $fillable = ['code', 'name', 'license', 'commercial_use', 'attribution', 'url', 'notes'];
}
