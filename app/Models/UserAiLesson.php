<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Bài học AI một người đã soạn; nội dung nằm ở ai_cache theo cache_hash (mục lesson không hết hạn). */
class UserAiLesson extends Model
{
    protected $table = 'user_ai_lessons';

    protected $dateFormat = 'U';

    public $timestamps = false;

    protected $guarded = ['id'];
}
