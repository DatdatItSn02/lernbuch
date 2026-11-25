<?php

use App\Jobs\GhiNhip;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nhịp mỗi phút đi qua hàng đợi: đủ nhịp nghĩa là cả lịch (schedule:run) và worker (queue:work) đang chạy.
Schedule::job(new GhiNhip)->everyMinute()->name('ghi-nhip');
