<?php

namespace App\Models;

use App\Support\GhiNhatKy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Chủ đề 3 tầng (topics): nhóm lớn, chủ đề vừa, chủ đề nhỏ (thẻ thuộc đúng 1 chủ đề nhỏ). Sửa ở trang quản trị Chủ đề.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property int $depth
 * @property string|null $collection
 * @property string $code
 * @property string $slug
 * @property string $name_vi
 * @property string|null $name_en
 * @property string $status
 */
class Topic extends Model
{
    use GhiNhatKy, SoftDeletes;

    public const TANG = [1 => 'Nhóm lớn', 2 => 'Chủ đề vừa', 3 => 'Chủ đề nhỏ'];

    protected $dateFormat = 'U';

    protected $fillable = ['name_vi', 'name_en', 'icon', 'sort', 'status'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['depth' => 'integer', 'sort' => 'integer', 'card_count' => 'integer'];
    }

    /** @return BelongsTo<Topic, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'parent_id');
    }

    /** @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'topic_id');
    }

    /** Đếm lại số thẻ đã xuất bản của chủ đề (cột card_count) sau khi sửa thẻ. */
    public static function demLaiThe(?int $id): void
    {
        if ($id === null) {
            return;
        }

        $n = Card::query()->where('topic_id', $id)->where('status', 'published')->whereNull('owner_user_id')->count();
        // Số đếm sẵn, không phải sửa của người: không đổi updated_at, không ghi nhật ký
        DB::table('topics')->where('id', $id)->update(['card_count' => $n]);
    }
}
