<?php

// Nhập bài tập Lernbuch (G1 tuần 2): bộ bài tập có nhóm hiện trên trang (vd "Chương 36 · Pronomen"),
// câu bài tập có lời dặn riêng, cờ phân biệt hoa thường và kho từ để chọn. schema.sql đã sửa theo.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_sets', function (Blueprint $table) {
            $table->string('group_name', 160)->nullable()->comment('nhóm hiện trên trang, vd Chương 36 · Pronomen, Bài 1 · Person & Familie')->after('description');
        });
        Schema::table('exercises', function (Blueprint $table) {
            $table->json('extra')->nullable()->comment('lời dặn (Viết số nhiều…), phân biệt hoa thường, kho từ để chọn')->after('translations');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_sets', function (Blueprint $table) {
            $table->dropColumn('group_name');
        });
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('extra');
        });
    }
};
