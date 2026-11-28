<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Một lượt chấm phát âm (pronunciation_attempts): điểm, lỗi từng từ, độ dài ghi âm. Không bao giờ chứa âm thanh.
 * provider: azure (máy chủ gửi Azure chấm) hoặc browser (bản dự phòng, trình duyệt tự nhận giọng).
 */
class PronunciationAttempt extends Model
{
    protected $table = 'pronunciation_attempts';

    protected $dateFormat = 'U';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['words' => 'array', 'created_at' => 'integer', 'audio_ms' => 'integer'];
    }
}
