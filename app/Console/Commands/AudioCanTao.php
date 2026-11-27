<?php

namespace App\Console\Commands;

use App\Support\AmThanh;
use App\Support\NoiDung;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Danh sách chữ cần tạo audio (workflow audio gọi qua SSH, rồi đọc bằng Piper): mặt trước của mọi thẻ hệ thống đang hiện,
 * mỗi dòng một JSON {"text": "..."}, bỏ chữ trùng và chữ đã có file của giọng này. Số liệu in ra stderr để không lẫn vào danh sách.
 */
class AudioCanTao extends Command
{
    protected $signature = 'app:audio-can-tao {--giong= : Giọng Piper (mặc định: giọng đang dùng, AUDIO_GIONG)} {--gioi-han=0 : Chỉ lấy chừng này chữ (0 là tất cả)}';

    protected $description = 'In danh sách chữ chưa có audio (JSON mỗi dòng) để workflow audio tạo bằng Piper';

    public function handle(): int
    {
        $giong = (string) ($this->option('giong') ?: AmThanh::giong());
        $gioiHan = (int) $this->option('gioi-han');

        if (preg_match(AmThanh::MAU_GIONG, $giong) !== 1) {
            $this->components->error("Tên giọng '{$giong}' không đúng dạng của Piper, vd de_DE-thorsten-medium.");

            return self::FAILURE;
        }

        $daCo = DB::table('media_assets')->where('kind', AmThanh::LOAI)->where('voice', $giong)->pluck('hash')->flip();
        $da = [];
        $tong = 0;

        // Thẻ hệ thống đã xuất bản (thẻ riêng của người dùng không tạo audio ở G1)
        $q = DB::table('cards')->where('lang', NoiDung::LANG)->where('status', 'published')->whereNull('deleted_at')->whereNull('owner_user_id')
            ->orderBy('id')->select(['id', 'front']);

        foreach ($q->lazyById(2000) as $t) {
            $chu = AmThanh::chuCanDoc((string) $t->front);
            $ma = AmThanh::hash($giong, $chu);

            if ($chu === '' || isset($da[$ma]) || isset($daCo[$ma])) {
                continue;
            }

            $da[$ma] = true;
            // In nguyên văn: chữ có dấu < > không bị hiểu là thẻ định dạng của console
            $this->output->writeln(json_encode(['text' => $chu], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

            if ($gioiHan > 0 && ++$tong >= $gioiHan) {
                break;
            }
        }

        fwrite(STDERR, sprintf("Giọng %s: %d chữ cần tạo, %d đã có file.\n", $giong, count($da), count($daCo)));

        return self::SUCCESS;
    }
}
