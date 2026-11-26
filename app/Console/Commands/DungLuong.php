<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DungLuong extends Command
{
    protected $signature = 'app:dung-luong {--ket-noi= : Tên kết nối database (bỏ trống là kết nối chính)}';

    protected $description = 'In dung lượng database (dữ liệu và chỉ mục của mọi bảng), để theo dõi giới hạn 1 GB của gói alwaysdata Free';

    public function handle(): int
    {
        $ketNoi = $this->option('ket-noi');

        try {
            $kq = DB::connection(is_string($ketNoi) && $ketNoi !== '' ? $ketNoi : null)->selectOne(
                'select count(*) as so_bang, coalesce(sum(data_length + index_length), 0) as so_byte from information_schema.tables where table_schema = database()'
            );
        } catch (Throwable) {
            // Không in nội dung lỗi: log deploy được lưu lại, không nên có tên máy chủ hay tài khoản database
            $this->error('Database: không đo được (không kết nối được hoặc không đọc được information_schema)');

            return self::FAILURE;
        }

        $this->line(sprintf('Database: %.1fM (%d bảng)', $kq->so_byte / 1048576, $kq->so_bang));

        return self::SUCCESS;
    }
}
