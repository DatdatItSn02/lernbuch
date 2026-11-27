<?php

// Bài tập: đếm số lần đúng liên tiếp của từng câu (từ 2 lần là đã thuộc, như Lernbuch); lượt làm bài có mã chống gửi trùng
// và lý do không cộng xu; nhật ký lượt chấm thẻ có thêm chế độ Điền câu (cloze). schema.sql đã sửa theo.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_exercise_stats', function (Blueprint $table) {
            $table->unsignedTinyInteger('correct_streak')->default(0)->after('wrong_count')
                ->comment('số lần đúng liên tiếp; từ 2 lần là đã thuộc');
        });

        Schema::table('exercise_runs', function (Blueprint $table) {
            $table->char('client_uid', 26)->charset('ascii')->collation('ascii_bin')->nullable()->after('user_id')
                ->comment('ULID do máy tạo, chống gửi trùng');
            $table->string('no_coin_reason', 20)->charset('ascii')->collation('ascii_bin')->nullable()->after('coins')
                ->comment('lý do không cộng xu: too_fast (trung bình dưới 1 giây mỗi câu)');
            $table->unique(['user_id', 'client_uid'], 'uq_exruns_client');
        });

        Schema::table('review_logs', function (Blueprint $table) {
            $table->string('mode', 12)->charset('ascii')->collation('ascii_bin')
                ->comment('flashcard, quiz, listening, typing, match, mixed, sentence, cloze (Điền câu)')->change();
        });
    }

    public function down(): void
    {
        Schema::table('review_logs', function (Blueprint $table) {
            $table->string('mode', 12)->charset('ascii')->collation('ascii_bin')
                ->comment('flashcard, quiz, listening, typing, match, mixed, sentence')->change();
        });

        Schema::table('exercise_runs', function (Blueprint $table) {
            $table->dropUnique('uq_exruns_client');
            $table->dropColumn(['client_uid', 'no_coin_reason']);
        });

        Schema::table('user_exercise_stats', function (Blueprint $table) {
            $table->dropColumn('correct_streak');
        });
    }
};
