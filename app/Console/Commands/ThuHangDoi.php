<?php

namespace App\Console\Commands;

use App\Jobs\ViecLoiThu;
use App\Jobs\ViecThu;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ThuHangDoi extends Command
{
    protected $signature = 'app:thu-hang-doi {--kiem= : Mã lần thử cần xem kết quả}';

    protected $description = 'Đưa một job thử và một job cố ý lỗi vào hàng đợi, rồi kiểm kết quả sau khi worker chạy';

    public function handle(): int
    {
        $ma = (string) $this->option('kiem');

        if ($ma === '') {
            $ma = Str::lower(Str::random(8));
            ViecThu::dispatch($ma);
            ViecLoiThu::dispatch($ma);
            $this->info("Đã đưa 2 job vào hàng đợi, mã {$ma}.");
            $this->line("Chờ worker chạy (hoặc chạy: php artisan queue:work --stop-when-empty), rồi: php artisan app:thu-hang-doi --kiem={$ma}");

            return self::SUCCESS;
        }

        $xong = Cache::get('viec-thu:'.$ma) === 'xong';
        $lan = (int) Cache::get('viec-loi-thu:'.$ma, 0);
        $loi = DB::table('failed_jobs')
            ->where('payload', 'like', '%ViecLoiThu%')
            ->where('exception', 'like', '%(mã '.$ma.')%')
            ->exists();

        $this->line('Job thử: '.($xong ? 'chạy xong' : 'chưa chạy'));
        $this->line("Job cố ý lỗi: đã thử {$lan} lần, ".($loi ? 'đã vào failed_jobs' : 'chưa vào failed_jobs'));

        if ($xong && $lan === 3 && $loi) {
            $this->info('Đạt: hàng đợi chạy job, job lỗi vào failed_jobs sau 3 lần thử.');

            return self::SUCCESS;
        }

        $this->error('Chưa đạt: worker chưa chạy hết hoặc số lần thử khác 3.');

        return self::FAILURE;
    }
}
