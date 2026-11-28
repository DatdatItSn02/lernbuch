<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Câu người học tự viết (dạng Viết câu) và kết quả AI chấm. */
class WritingSubmission extends Model
{
    protected $table = 'writing_submissions';

    protected $dateFormat = 'U';

    public $timestamps = false;

    protected $guarded = ['id'];
}
