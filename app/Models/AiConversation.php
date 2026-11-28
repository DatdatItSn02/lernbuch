<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cuộc trò chuyện AI của một người học (xoá là xoá hẳn cả tin nhắn). */
class AiConversation extends Model
{
    protected $table = 'ai_conversations';

    protected $dateFormat = 'U';

    protected $guarded = ['id'];
}
