<?php

namespace App\Models;

use App\Support\GhiNhatKy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Câu ví dụ của thẻ (card_examples), tối đa 4 câu, sort 0 là câu chính (Đặt câu, Hỗn hợp dùng câu này).
 *
 * @property int $id
 * @property int $card_id
 * @property int $sort
 * @property string $sentence
 * @property array<string, string>|null $translations
 */
class CardExample extends Model
{
    use GhiNhatKy;

    public const TOI_DA = 4;

    protected $dateFormat = 'U';

    protected $fillable = ['card_id', 'sort', 'sentence', 'translations', 'is_manual', 'source_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort' => 'integer', 'translations' => 'array', 'is_manual' => 'boolean'];
    }

    /**
     * Bảng chỉ có updated_by.
     *
     * @return list<string>
     */
    public static function cotNguoiSua(): array
    {
        return ['updated_by'];
    }

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card_id');
    }
}
