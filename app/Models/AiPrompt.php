<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Prompt AI theo phiên bản (ai_prompts). Mỗi loại có nhiều phiên bản; bản đang dùng là bản is_active có version lớn nhất.
 *
 * @property int $id
 * @property string $kind
 * @property int $version
 * @property string $model
 * @property string $system_prompt
 * @property string $user_template
 * @property int $max_tokens
 * @property string $temperature
 * @property bool $is_active
 */
class AiPrompt extends Model
{
    protected $table = 'ai_prompts';

    protected $dateFormat = 'U';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['version' => 'integer', 'max_tokens' => 'integer', 'is_active' => 'boolean'];
    }

    public static function dangDung(string $loai): ?self
    {
        return self::query()->where('kind', $loai)->where('is_active', 1)->whereNull('deleted_at')->orderByDesc('version')->first();
    }
}
