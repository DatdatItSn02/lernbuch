<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class DungLuong extends Command
{
    protected $signature = 'app:dung-luong {--ket-noi= : Tên kết nối database (bỏ trống là kết nối chính)}';

    protected $description = 'In dung lượng database (dữ liệu và chỉ mục của mọi bảng) và số nội dung đã xuất bản, để theo dõi giới hạn 1 GB của gói alwaysdata Free';

    public function handle(): int
    {
        $ketNoi = $this->option('ket-noi');
        $db = DB::connection(is_string($ketNoi) && $ketNoi !== '' ? $ketNoi : null);

        try {
            $kq = $db->selectOne(
                'select count(*) as so_bang, coalesce(sum(data_length + index_length), 0) as so_byte from information_schema.tables where table_schema = database()'
            );
        } catch (Throwable) {
            // Không in nội dung lỗi: log deploy được lưu lại, không nên có tên máy chủ hay tài khoản database
            $this->error('Database: không đo được (không kết nối được hoặc không đọc được information_schema)');

            return self::FAILURE;
        }

        $this->line(sprintf('Database: %.1fM (%d bảng)', $kq->so_byte / 1048576, $kq->so_bang));

        try {
            $this->line(self::noiDung($db));
        } catch (Throwable) {
            $this->line('Nội dung: không đếm được');
        }

        return self::SUCCESS;
    }

    /**
     * Nội dung đã xuất bản của hệ thống (không tính thẻ riêng của người học) và số file audio, để đối chiếu với số liệu
     * nhập Lernbuch (docs/nhap-lernbuch-cu.md) và theo dõi dung lượng (tools/tu-kiem đọc dòng này trong log deploy).
     */
    public static function noiDung(ConnectionInterface $db): string
    {
        $dem = fn (string $bang) => $db->table($bang)->whereNull('deleted_at')->where('status', 'published');

        return sprintf(
            'Nội dung: %d thẻ, %d chủ đề nhỏ, %d bài ngữ pháp, %d câu bài tập, %d động từ, %d file audio',
            $dem('cards')->whereNull('owner_user_id')->count(),
            $dem('topics')->where('depth', 3)->count(),
            $dem('grammar_lessons')->count(),
            $dem('exercises')->count(),
            $dem('verbs')->count(),
            $db->table('media_assets')->where('kind', 'audio')->count(),
        );
    }
}
