<?php

namespace App\Console\Commands;

use App\Jobs\GhiNhip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class KiemNhip extends Command
{
    protected $signature = 'app:kiem-nhip {--phut=10 : Số phút nhìn lại} {--toi-thieu=9 : Số nhịp tối thiểu để đạt}';

    protected $description = 'Đếm nhịp mỗi phút do lịch và hàng đợi ghi; thiếu nhịp thì báo lỗi';

    public function handle(): int
    {
        $phut = max(1, (int) $this->option('phut'));
        $toiThieu = max(0, (int) $this->option('toi-thieu'));
        $now = now()->getTimestamp();

        $co = 0;
        for ($i = 0; $i < $phut; $i++) {
            if (Cache::has(GhiNhip::key($now - $i * 60))) {
                $co++;
            }
        }

        $this->line("Nhịp trong {$phut} phút qua: {$co}/{$phut} (cần ít nhất {$toiThieu}).");

        if ($co < $toiThieu) {
            $this->error('Thiếu nhịp: lịch (schedule:run mỗi phút) hoặc hàng đợi (queue:work) không chạy đều.');

            return self::FAILURE;
        }

        $this->info('Đạt: lịch và hàng đợi chạy đều.');

        return self::SUCCESS;
    }
}
