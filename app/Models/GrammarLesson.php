<?php

namespace App\Models;

use App\Support\GhiNhatKy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bài ngữ pháp (grammar_lessons): nội dung là danh sách khối (blocks) như app học vẽ (components/KhoiNguPhap.tsx).
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property int|null $level
 * @property int|null $chapter_id
 * @property list<array<string, mixed>> $blocks
 * @property string $status
 * @property int $version
 */
class GrammarLesson extends Model
{
    use GhiNhatKy, SoftDeletes;

    /** Loại khối app học vẽ được (t của mỗi khối). */
    public const LOAI_KHOI = ['h2', 'h3', 'p', 'li', 'tip', 'recap', 'note', 'kv', 'fm', 'tipl', 'ex', 'tbl'];

    protected $dateFormat = 'U';

    protected $fillable = ['title', 'summary', 'level', 'chapter_id', 'blocks', 'status', 'sort', 'source_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['blocks' => 'array', 'level' => 'integer', 'version' => 'integer', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (GrammarLesson $bai): void {
            $bai->version = $bai->version + 1;
        });
    }

    /**
     * Kiểm nội dung bài sửa ở trang quản trị: JSON là danh sách khối, mỗi khối có loại (t) app học vẽ được.
     * Trả về lời báo lỗi, hoặc null khi hợp lệ.
     */
    public static function kiemKhoi(string $json): ?string
    {
        $khoi = json_decode($json, true);

        if (! is_array($khoi) || json_last_error() !== JSON_ERROR_NONE) {
            return 'Nội dung không phải JSON hợp lệ.';
        }

        if ($khoi === [] || ! array_is_list($khoi)) {
            return 'Nội dung phải là danh sách khối, ít nhất một khối.';
        }

        foreach ($khoi as $i => $k) {
            $loai = is_array($k) ? ($k['t'] ?? null) : null;

            if (! is_string($loai) || ! in_array($loai, self::LOAI_KHOI, true)) {
                return 'Khối thứ '.($i + 1).' có loại không hợp lệ (t phải là một trong: '.implode(', ', self::LOAI_KHOI).').';
            }
        }

        return null;
    }

    /** @return BelongsTo<GrammarChapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(GrammarChapter::class, 'chapter_id');
    }
}
