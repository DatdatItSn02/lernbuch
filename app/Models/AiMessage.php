<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Một tin trong cuộc trò chuyện AI; tin của AI có cột corrections chứa nghĩa tiếng Việt, gợi ý và lỗi đã sửa. */
class AiMessage extends Model
{
    protected $table = 'ai_messages';

    protected $dateFormat = 'U';

    public $timestamps = false;

    protected $guarded = ['id'];
}
