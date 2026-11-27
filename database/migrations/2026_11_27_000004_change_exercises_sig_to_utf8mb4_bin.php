<?php

// Sig câu bài tập Số nhiều của Lernbuch có chữ Đức (vd "pl:e:Föhn", 203 câu): cột ascii không nhận
// (MySQL 9.7 báo lỗi 3988, MariaDB ở chế độ strict báo 1366). Đổi sang utf8mb4_bin như dạng từ:
// giữ nguyên văn, so khớp chính xác ("Föhn" khác "Fohn"). schema.sql đã sửa theo.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('sig', 64)->charset('utf8mb4')->collation('utf8mb4_bin')
                ->comment('khoá ổn định, giữ nguyên văn từ Lernbuch, có thể có chữ Đức (vd pl:e:Föhn)')->change();
        });
    }

    public function down(): void
    {
        // Đã có sig chữ Đức thì database từ chối đổi lại (chế độ strict), không làm hỏng khoá
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('sig', 64)->charset('ascii')->collation('ascii_bin')->comment('khoá ổn định, giữ từ Lernbuch')->change();
        });
    }
};
