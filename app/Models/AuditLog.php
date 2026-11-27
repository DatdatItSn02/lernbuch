<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nhật ký thao tác (audit_logs), chỉ đọc: dòng mới do App\Support\NhatKy ghi; không sửa, không xoá.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array{before?: array<string, mixed>, after?: array<string, mixed>}|null $changes
 * @property string|null $ip
 * @property int $created_at
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $dateFormat = 'U';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    /** Địa chỉ IP dạng chữ (cột lưu dạng nhị phân). */
    public function ipChu(): ?string
    {
        $ip = $this->getRawOriginal('ip');

        return is_string($ip) && $ip !== '' ? (@inet_ntop($ip) ?: null) : null;
    }
}
