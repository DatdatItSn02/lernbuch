<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nhật ký gọi AI (ai_requests): một dòng mỗi lượt, kể cả lượt bị chặn (quota, blocked) và lượt lấy từ cache.
 * Dùng để đếm hạn mức, trần hệ thống và chi phí. Đoạn trích câu hỏi bị xoá sau 30 ngày (lệnh app:ai-don).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $kind
 * @property string $status
 * @property int $cost_micro_usd
 * @property bool $cache_hit
 */
class AiRequest extends Model
{
    protected $table = 'ai_requests';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer', 'cached_tokens' => 'integer', 'output_tokens' => 'integer', 'cost_micro_usd' => 'integer',
            'cache_hit' => 'boolean', 'latency_ms' => 'integer', 'created_at' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
