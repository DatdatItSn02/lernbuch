<?php

// Bạn chốt 27/11 (kế hoạch G1): 9 nguồn nội dung của Lernbuch cũ có quyền dùng để bán. seed.sql chỉ thêm dòng mới,
// không sửa dòng đã có, nên database đã nạp seed cũ (commercial_use = unknown) cần migration này để đổi theo.
// Về sau đổi quyền dùng ở trang quản trị nguồn; lệnh app:nhap-lernbuch đọc cột này mỗi lần chạy.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NGUON = ['lernbuch_xlsx', 'vocab_trainer', 'cum_tu_mau_cau', 'collins_3000', 'english_everyday', 'german_slang', 'illustrated_dict', 'grammar_doc'];

    public function up(): void
    {
        DB::table('content_sources')->whereIn('code', self::NGUON)->where('commercial_use', 'unknown')
            ->update(['commercial_use' => 'allowed', 'notes' => 'Bạn chốt 27/11: có quyền dùng để bán.', 'updated_at' => time()]);
    }

    public function down(): void
    {
        DB::table('content_sources')->whereIn('code', self::NGUON)->where('notes', 'Bạn chốt 27/11: có quyền dùng để bán.')
            ->update(['commercial_use' => 'unknown', 'updated_at' => time()]);
    }
};
