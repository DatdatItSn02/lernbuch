<?php

// G2 tuần 2: ba prompt chat, lesson, lookup có bản 2 (App\Support\Ai\PromptBan2). seed.sql chỉ thêm dòng chưa có nên
// database đã nạp seed cũ cần migration này. Chỉ thêm dữ liệu, không đổi cấu trúc.

use App\Support\Ai\PromptBan2;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PromptBan2::nangCap();
    }

    public function down(): void
    {
        // Giữ nguyên: quay lại bản trước làm ở trang quản trị Prompt
    }
};
