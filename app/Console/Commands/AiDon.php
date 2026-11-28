<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Dọn dữ liệu AI mỗi ngày: xoá trắng đoạn trích câu hỏi quá 30 ngày (giữ dòng để đếm chi phí), xoá cache AI đã hết hạn. */
class AiDon extends Command
{
    public const GIU_TRICH_NGAY = 30;

    protected $signature = 'app:ai-don';

    protected $description = 'Xoá đoạn trích câu hỏi AI quá 30 ngày và cache AI đã hết hạn';

    public function handle(): int
    {
        $bay = now()->getTimestamp();
        $trich = DB::table('ai_requests')->where('created_at', '<', $bay - self::GIU_TRICH_NGAY * 86400)->whereNotNull('input_excerpt')
            ->update(['input_excerpt' => null]);
        $cache = DB::table('ai_cache')->whereNotNull('expires_at')->where('expires_at', '<=', $bay)->delete();

        $this->info("Đã xoá {$trich} đoạn trích câu hỏi và {$cache} mục cache AI hết hạn.");

        return self::SUCCESS;
    }
}
