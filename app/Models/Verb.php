<?php

namespace App\Models;

use App\Support\GhiNhatKy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Động từ và bảng chia (verbs). Luyện chia động từ chấm theo các dạng ở đây (máy chủ tính lại đáp án), nên sửa là có hiệu lực ngay.
 *
 * @property int $id
 * @property string $infinitive
 * @property int|null $level
 * @property array<string, string>|null $meanings
 * @property array<string, list<string>>|null $conjugation
 * @property string $status
 */
class Verb extends Model
{
    use GhiNhatKy, SoftDeletes;

    /** Bảng chia: Präsens, Präteritum, Perfekt (6 ngôi), mệnh lệnh (3 dạng). */
    public const BANG = ['pres' => 6, 'prat' => 6, 'perf' => 6, 'imp' => 3];

    protected $dateFormat = 'U';

    protected $fillable = ['level', 'is_separable', 'prefix', 'auxiliary', 'is_irregular', 'form_du', 'form_er', 'preterite', 'perfect', 'meanings', 'conjugation', 'status'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['meanings' => 'array', 'conjugation' => 'array', 'is_separable' => 'boolean', 'is_irregular' => 'boolean', 'level' => 'integer'];
    }
}
