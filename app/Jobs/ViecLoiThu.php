<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/** Job cố ý lỗi: đếm số lần chạy rồi ném lỗi; sau đúng 3 lần thử phải nằm trong failed_jobs. */
class ViecLoiThu implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 0;

    public function __construct(public string $ma) {}

    public function handle(): void
    {
        Cache::increment('viec-loi-thu:'.$this->ma);

        throw new RuntimeException('Lỗi cố ý để thử hàng đợi (mã '.$this->ma.'): job này phải vào failed_jobs sau 3 lần thử.');
    }
}
