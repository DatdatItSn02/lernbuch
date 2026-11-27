<?php

namespace App\Models;

use App\Support\GhiNhatKy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Bộ bài tập (exercise_sets): theo cấp (level), Ôn tập (review), Số nhiều (plural); câu ở bảng exercises.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $section
 * @property int|null $level
 * @property string $status
 */
class ExerciseSet extends Model
{
    use GhiNhatKy, SoftDeletes;

    public const PHAN = ['level' => 'Theo cấp', 'review' => 'Ôn tập', 'plural' => 'Số nhiều'];

    protected $dateFormat = 'U';

    protected $fillable = ['title', 'description', 'group_name', 'level', 'status', 'sort'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['level' => 'integer', 'item_count' => 'integer', 'sort' => 'integer'];
    }

    /** @return HasMany<Exercise, $this> */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class, 'set_id');
    }

    /** @return BelongsTo<GrammarLesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(GrammarLesson::class, 'grammar_lesson_id');
    }

    /** Đếm lại số câu đã xuất bản (cột item_count) sau khi sửa câu. */
    public static function demLaiCau(int $id): void
    {
        $n = Exercise::query()->where('set_id', $id)->where('status', 'published')->count();
        DB::table('exercise_sets')->where('id', $id)->update(['item_count' => $n]);
    }
}
