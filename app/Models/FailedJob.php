<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Job lỗi (failed_jobs của Laravel), chỉ đọc; chạy lại hay bỏ qua ở trang quản trị Job lỗi.
 *
 * @property int $id
 * @property string $uuid
 * @property string $queue
 * @property string $payload
 * @property string $exception
 */
class FailedJob extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['failed_at' => 'datetime'];
    }

    /** Tên job trong payload (vd App\Jobs\ThuHangDoi). */
    public function tenJob(): string
    {
        $payload = json_decode($this->payload, true);

        return is_array($payload) ? (string) ($payload['displayName'] ?? '') : '';
    }

    /** Dòng đầu của lỗi. */
    public function dongLoi(): string
    {
        return strtok($this->exception, "\n") ?: '';
    }
}
