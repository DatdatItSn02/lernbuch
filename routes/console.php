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

// AI: xoá đoạn trích câu hỏi quá 30 ngày và cache AI đã hết hạn.
Schedule::command('app:ai-don')->dailyAt('03:17')->name('ai-don');
