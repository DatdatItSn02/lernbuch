<?php

use Illuminate\Database\Migrations\Migration;

// Tự kiểm G0 mục 8: migration cố ý hỏng, chỉ hỏng trên máy chủ thật (production) để CI vẫn xanh và deploy chạy tới bước migrate.
// Gỡ file này ngay sau lần thử.
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Migration cố ý hỏng để thử mục 8: deploy phải dừng, bản đang chạy giữ nguyên.');
        }
    }

    public function down(): void {}
};
