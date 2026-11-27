<?php

namespace App\Models;

use App\Support\GhiNhatKy;
use App\Support\NoiDung;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Thẻ học (cards). Nghĩa theo từng thứ tiếng ở card_translations (khoá chính ghép card_id + lang, đọc ghi bằng DB),
 * câu ví dụ ở card_examples. Sửa ở trang quản trị Thẻ: mỗi lần lưu tính lại chữ để tìm (search_text) và số thẻ của chủ đề.
 *
 * @property int $id
 * @property string $collection
 * @property string $card_key
 * @property int|null $owner_user_id
 * @property int|null $topic_id
 * @property string $front
 * @property string|null $article
 * @property string|null $pos
 * @property string|null $forms
 * @property int|null $level
 * @property string $status
 * @property int|null $source_id
 * @property int $version
 */
class Card extends Model
{
    use GhiNhatKy, SoftDeletes;

    public const TRANG_THAI = ['published' => 'Đã xuất bản', 'draft' => 'Nháp', 'in_review' => 'Chờ duyệt', 'archived' => 'Đã ẩn'];

    /** Thứ tiếng của nghĩa (app học hiện vi, en, zh). */
    public const THU_TIENG = ['vi' => 'Tiếng Việt', 'en' => 'Tiếng Anh', 'zh' => 'Tiếng Trung'];

    protected $dateFormat = 'U';

    /** Giống để tô màu: m, f, n, p (chỉ có số nhiều). */
    public const GIONG = ['m' => 'giống đực (der)', 'f' => 'giống cái (die)', 'n' => 'giống trung (das)', 'p' => 'chỉ có số nhiều'];

    protected $fillable = ['front', 'article', 'gender', 'pos', 'forms', 'level', 'level_min', 'level_max', 'tip', 'note', 'cloze', 'cloze_answer', 'status', 'source_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['level' => 'integer', 'level_min' => 'integer', 'level_max' => 'integer', 'version' => 'integer', 'extra' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (Card $card): void {
            $card->version = $card->version + 1;
        });
    }

    /** @return BelongsTo<Topic, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    /** @return BelongsTo<ContentSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ContentSource::class, 'source_id');
    }

    /** @return HasMany<CardExample, $this> */
    public function examples(): HasMany
    {
        return $this->hasMany(CardExample::class, 'card_id')->orderBy('sort');
    }

    /** @return array<string, string> thứ tiếng → nghĩa */
    public function nghia(): array
    {
        /** @var array<string, string> $ds */
        $ds = DB::table('card_translations')->where('card_id', $this->id)->pluck('meaning', 'lang')->all();

        return $ds;
    }

    /** Tính lại chữ để tìm từ mặt trước, dạng và nghĩa hiện có (như khi nhập). */
    public function tinhChuTim(): void
    {
        DB::table('cards')->where('id', $this->id)->update(['search_text' => NoiDung::chuTim($this->front, $this->forms, $this->nghia())]);
    }
}
