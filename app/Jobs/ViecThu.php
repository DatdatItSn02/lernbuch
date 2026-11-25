<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Job thử hàng đợi: chạy xong thì ghi "xong" theo mã lần thử (xem app:thu-hang-doi). */
class ViecThu implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $ma) {}

    public function handle(): void
    {
        Cache::put('viec-thu:'.$this->ma, 'xong', now()->addDay());
    }
}
