<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Nhịp mỗi phút: lịch (schedule:run) đưa job này vào hàng đợi, worker (queue:work) chạy nó thì ghi nhịp của phút đó.
 * Đủ nhịp nghĩa là cả lịch lẫn hàng đợi đang chạy đều; kiểm bằng `php artisan app:kiem-nhip`.
 */
class GhiNhip implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $now = now()->getTimestamp();
        Cache::put(self::key($now), $now, now()->addDay());
    }

    /** Khoá của nhịp trong phút chứa thời điểm $timestamp. */
    public static function key(int $timestamp): string
    {
        return 'nhip:'.date('YmdHi', $timestamp);
    }
}
