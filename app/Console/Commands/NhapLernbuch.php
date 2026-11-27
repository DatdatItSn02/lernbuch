<?php

namespace App\Console\Commands;

use App\Support\NhapLernbuch\BaoCaoNhap;
use App\Support\NhapLernbuch\BoNhapLernbuch;
use App\Support\NhapLernbuch\NguonLernbuch;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class NhapLernbuch extends Command
{
    protected $signature = 'app:nhap-lernbuch
        {nguon : lernweb.zip, thư mục lernweb (hoặc content), hoặc file seed.db / app.db}
        {--ghi : ghi vào database (không có thì chỉ chạy thử, in báo cáo)}
        {--json= : ghi thêm báo cáo dạng JSON ra file này}';

    protected $description = 'Nhập nội dung Lernbuch cũ: thẻ, chủ đề, ngữ pháp, bài tập, động từ (chỉ nguồn có quyền dùng để bán)';

    /** Tên hiện của từng phần trong báo cáo. */
    private const PHAN = [
        'the' => 'Thẻ', 'chu_de_nhom' => 'Chủ đề: nhóm lớn', 'chu_de_vua' => 'Chủ đề: chủ đề vừa', 'chu_de_nho' => 'Chủ đề: chủ đề nhỏ',
        'bai_ngu_phap' => 'Bài ngữ pháp', 'chuong_ngu_phap' => 'Chương ngữ pháp', 'bo_bai_tap' => 'Bộ bài tập', 'cau_bai_tap' => 'Câu bài tập',
        'dong_tu' => 'Động từ', 'chu_de' => 'Chủ đề (mọi tầng)', 'nghia' => 'Nghĩa (vi, en, zh)', 'cau_vi_du' => 'Câu ví dụ', 'dang_tu' => 'Dạng từ để tra',
    ];

    public function handle(): int
    {
        try {
            $bc = (new BoNhapLernbuch(new NguonLernbuch((string) $this->argument('nguon'))))->chay((bool) $this->option('ghi'));
        } catch (QueryException $e) {
            $this->error(self::loiDatabase($e));

            if ($this->option('ghi')) {
                $this->line('Phần đã ghi trước lỗi vẫn giữ; sửa lỗi rồi chạy lại (chạy lại không nhân đôi).');
            }

            return 3;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 2;
        }

        $this->inBaoCao($bc);

        if (is_string($json = $this->option('json')) && $json !== '') {
            file_put_contents($json, json_encode($bc->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        }

        return $bc->coLoi() ? self::FAILURE : self::SUCCESS;
    }

    /** Lỗi database in gọn: thông báo gốc của Laravel kèm cả câu SQL và dữ liệu (hàng trăm KB với một lô bài tập). */
    public static function loiDatabase(QueryException $e): string
    {
        $bang = preg_match('/^\s*(?:insert\s+(?:ignore\s+)?into|update|delete\s+from)\s+`?(\w+)`?/i', $e->getSql(), $m) === 1 ? " khi ghi vào bảng {$m[1]}" : '';

        return "Lỗi database{$bang}: ".Str::limit(($e->getPrevious() ?? $e)->getMessage(), 300);
    }

    private function inBaoCao(BaoCaoNhap $bc): void
    {
        $so = fn (int $n) => number_format($n, 0, ',', '.');
        $this->line("Nguồn: {$bc->nguon}".($bc->phienBan !== null ? " (dữ liệu bản {$bc->phienBan})" : ''));

        $tenLoai = DB::table('card_collections')->pluck('name_vi', 'code')->all();
        $this->line('Thẻ đang dùng sẽ nhập: '.$so($bc->so['the'] ?? 0).($bc->theTat > 0 ? ' (bỏ '.$so($bc->theTat).' thẻ đã tắt ở Lernbuch cũ)' : ''));
        $this->line('  theo loại: '.implode(' · ', array_map(fn ($k, $n) => ($tenLoai[$k] ?? $k).' '.$so($n), array_keys($bc->theoLoai), $bc->theoLoai)));
        $this->line('  theo nguồn: '.implode(' · ', array_map(fn ($k, $n) => "{$k} ".$so($n), array_keys($bc->theoNguon), $bc->theoNguon)));
        ksort($bc->theoCap);
        $this->line('  theo cấp: '.implode(' · ', array_map(fn ($k, $n) => ($k === 'chua_xep' ? 'chưa xếp cấp' : $k).' '.$so($n), array_keys($bc->theoCap), $bc->theoCap)));

        foreach (['chu_de_nhom', 'chu_de_vua', 'chu_de_nho', 'bai_ngu_phap', 'chuong_ngu_phap', 'bo_bai_tap', 'cau_bai_tap', 'dong_tu'] as $k) {
            $this->line(self::PHAN[$k].': '.$so($bc->so[$k] ?? 0));
        }

        if ($bc->boViNguon !== []) {
            $this->warn('Bỏ vì nguồn chưa có quyền dùng để bán (chỉ đếm, không ghi): '.implode(' · ', array_map(fn ($k, $n) => "{$k} ".$so($n), array_keys($bc->boViNguon), $bc->boViNguon)));
        }

        foreach ($bc->canhBao as $c) {
            $this->warn("Cảnh báo: {$c}");
        }

        if ($bc->coLoi()) {
            $this->error('Lỗi dữ liệu: '.$so(count($bc->loi)).'. Không ghi gì vào database; sửa nguồn rồi chạy lại.');

            foreach (array_slice($bc->loi, 0, 50) as $l) {
                $this->line("  - {$l}");
            }

            if (count($bc->loi) > 50) {
                $this->line('  … và '.$so(count($bc->loi) - 50).' lỗi nữa (xem đủ bằng --json).');
            }

            return;
        }

        if (! $bc->daGhiVao) {
            $this->info('Chạy thử xong, chưa ghi gì. Thêm --ghi để ghi vào database.');

            return;
        }

        $this->info('Đã ghi vào database:');

        foreach ($bc->daGhi as $k => $g) {
            $this->line('  '.(self::PHAN[$k] ?? $k).': mới '.$so($g['moi']).', cập nhật '.$so($g['cap_nhat']).($g['xoa'] > 0 ? ', bỏ '.$so($g['xoa']) : ''));
        }

        foreach (['nghia', 'cau_vi_du', 'dang_tu'] as $k) {
            $this->line('  '.self::PHAN[$k].': '.$so($bc->so[$k] ?? 0));
        }
    }
}
