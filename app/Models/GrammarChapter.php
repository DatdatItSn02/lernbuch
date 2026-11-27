<?php

namespace App\Models;

use App\Support\GhiNhatKy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Chương ngữ pháp (grammar_chapters), mã dạng "A1-3".
 *
 * @property int $id
 * @property string $code
 * @property string $name_vi
 */
class GrammarChapter extends Model
{
    use GhiNhatKy, SoftDeletes;

    protected $dateFormat = 'U';

    protected $fillable = ['name_vi', 'name_en', 'sort'];
}
