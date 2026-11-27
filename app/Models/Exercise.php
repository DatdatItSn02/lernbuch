<?php

namespace App\Models;

use App\Support\GhiNhatKy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Câu bài tập (exercises): điền từ (fill), trắc nghiệm (mc), sắp xếp (order), sửa lỗi (fix). Máy chủ chấm lại từ answers,
 * nên sửa đáp án ở đây có hiệu lực ngay cho lần làm sau.
 *
 * @property int $id
 * @property int $set_id
 * @property string $sig
 * @property string $kind
 * @property string $prompt
 * @property list<string> $answers
 * @property list<string>|null $choices
 * @property string $status
 */
class Exercise extends Model
{
    use GhiNhatKy, SoftDeletes;

    public const DANG = ['fill' => 'Điền từ', 'mc' => 'Trắc nghiệm', 'order' => 'Sắp xếp', 'fix' => 'Sửa lỗi'];

    protected $dateFormat = 'U';

    protected $fillable = ['prompt', 'answers', 'choices', 'hint', 'explanation', 'status', 'sort'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['answers' => 'array', 'choices' => 'array', 'translations' => 'array', 'extra' => 'array', 'sort' => 'integer'];
    }

    /** @return BelongsTo<ExerciseSet, $this> */
    public function set(): BelongsTo
    {
        return $this->belongsTo(ExerciseSet::class, 'set_id');
    }
}
