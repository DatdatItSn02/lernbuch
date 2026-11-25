<?php

// File sinh tự động bởi tools/schema/gen_migrations.py từ database/sql/schema.sql.
// Không sửa tay: muốn đổi cấu trúc thì viết migration mới.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_courses', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('course_id');
            $table->string('goal', 20)->nullable()->charset('ascii')->collation('ascii_bin')->comment('study_abroad, vocational, work, exam, travel, other');
            $table->unsignedTinyInteger('level_goal')->nullable();
            $table->unsignedTinyInteger('placement_level')->nullable();
            $table->unsignedInteger('current_topic_id')->nullable();
            $table->unsignedInteger('enrolled_at');
            $table->unsignedInteger('last_studied_at')->nullable();
            $table->primary(['user_id', 'course_id']);
            $table->index(['course_id'], 'ix_user_courses_course');
            $table->index(['current_topic_id'], 'ix_user_courses_topic');
            $table->comment('Khóa học người dùng đang học');
        });

        Schema::create('card_progress', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('card_id');
            $table->unsignedTinyInteger('box')->default(0)->comment('0 Mới, 1 Đang học, 2 Quen, 3 Khá, 4 Thuộc, 5 Thành thạo');
            $table->unsignedInteger('due_at')->nullable();
            $table->unsignedSmallInteger('reps')->default(0);
            $table->unsignedSmallInteger('lapses')->default(0);
            $table->unsignedTinyInteger('last_rating')->nullable()->comment('0 Quên, 1 Khó, 2 Tốt, 3 Dễ');
            $table->float('stability', 24)->nullable()->comment('dự phòng cho FSRS');
            $table->float('difficulty', 24)->nullable()->comment('dự phòng cho FSRS');
            $table->unsignedInteger('first_seen_at')->nullable();
            $table->unsignedInteger('last_seen_at')->nullable();
            $table->primary(['user_id', 'card_id']);
            $table->index(['user_id', 'box'], 'ix_progress_box');
            $table->index(['card_id'], 'ix_progress_card');
            $table->index(['user_id', 'due_at'], 'ix_progress_due');
            $table->comment('Tiến độ từng thẻ của từng người (SRS 6 mức)');
        });

        Schema::create('review_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('id', true);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('card_id');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->char('client_uid', 26)->nullable()->charset('ascii')->collation('ascii_bin')->comment('ULID do máy tạo, chống gửi trùng');
            $table->string('mode', 12)->charset('ascii')->collation('ascii_bin')->comment('flashcard, quiz, listening, typing, match, mixed, sentence');
            $table->unsignedTinyInteger('rating')->comment('0 Quên, 1 Khó, 2 Tốt, 3 Dễ');
            $table->unsignedTinyInteger('box_before');
            $table->unsignedTinyInteger('box_after');
            $table->unsignedSmallInteger('coins')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('reviewed_at');
            $table->primary(['id', 'reviewed_at']);
            $table->index(['card_id', 'reviewed_at'], 'ix_review_card');
            $table->index(['session_id'], 'ix_review_session');
            $table->index(['user_id', 'reviewed_at'], 'ix_review_user');
            $table->unique(['user_id', 'client_uid', 'reviewed_at'], 'uq_review_client');
            $table->comment('Nhật ký từng lượt chấm thẻ');
        });
        DB::statement('ALTER TABLE review_logs PARTITION BY RANGE (reviewed_at) (
  PARTITION p2026 VALUES LESS THAN (1798761600),
  PARTITION p2027 VALUES LESS THAN (1830297600),
  PARTITION p2028 VALUES LESS THAN (1861920000),
  PARTITION pmax  VALUES LESS THAN MAXVALUE
)');

        Schema::create('study_sessions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('course_id')->nullable();
            $table->unsignedInteger('topic_id')->nullable();
            $table->string('mode', 12)->charset('ascii')->collation('ascii_bin');
            $table->unsignedSmallInteger('planned_new')->default(0);
            $table->unsignedSmallInteger('planned_review')->default(0);
            $table->unsignedSmallInteger('done_new')->default(0);
            $table->unsignedSmallInteger('done_review')->default(0);
            $table->unsignedSmallInteger('leveled_up')->default(0);
            $table->json('ratings')->nullable()->comment('số lượt theo mức chấm, vd {"0":2,"1":3,"2":10,"3":1}');
            $table->unsignedInteger('coins')->default(0);
            $table->string('client', 10)->nullable()->charset('ascii')->collation('ascii_bin')->comment('web, pwa, ios, android');
            $table->unsignedInteger('started_at');
            $table->unsignedInteger('ended_at')->nullable();
            $table->index(['course_id'], 'ix_study_sessions_course');
            $table->index(['topic_id'], 'ix_study_sessions_topic');
            $table->index(['user_id', 'started_at'], 'ix_study_sessions_user');
            $table->comment('Phiên học, dùng cho bảng tổng kết cuối phiên');
        });

        Schema::create('user_daily_stats', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('day_start')->comment('mốc 00:00 theo múi giờ người dùng (Unix)');
            $table->unsignedSmallInteger('reviews')->default(0);
            $table->unsignedSmallInteger('new_cards')->default(0);
            $table->unsignedSmallInteger('correct')->default(0);
            $table->unsignedSmallInteger('exercises')->default(0);
            $table->unsignedSmallInteger('ai_calls')->default(0);
            $table->unsignedInteger('coins')->default(0);
            $table->unsignedInteger('study_seconds')->default(0);
            $table->boolean('goal_met')->default(0);
            $table->boolean('streak_frozen')->default(0);
            $table->primary(['user_id', 'day_start']);
            $table->index(['day_start'], 'ix_daily_day');
            $table->comment('Số liệu mỗi ngày: chuỗi ngày, biểu đồ 14 ngày, xếp hạng tuần');
        });

        Schema::create('user_stats', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('coins_total')->default(0)->comment('tổng xu đã nhận');
            $table->bigInteger('coins_balance')->default(0)->comment('xu còn tiêu được');
            $table->unsignedInteger('streak_current')->default(0);
            $table->unsignedInteger('streak_longest')->default(0);
            $table->unsignedInteger('streak_last_day')->nullable();
            $table->unsignedTinyInteger('freezes_available')->default(0);
            $table->unsignedInteger('cards_seen')->default(0);
            $table->unsignedInteger('cards_mastered')->default(0);
            $table->unsignedInteger('reviews_total')->default(0);
            $table->unsignedInteger('updated_at')->nullable();
            $table->primary(['user_id']);
            $table->index(['coins_total'], 'ix_user_stats_coins');
            $table->comment('Số liệu tổng của từng người');
        });

        Schema::create('coin_transactions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->integer('amount')->comment('dương = nhận, âm = tiêu');
            $table->string('reason', 30)->charset('ascii')->collation('ascii_bin')->comment('study, exercise, achievement, referral, streak_freeze, admin_adjust');
            $table->string('ref_type', 40)->nullable()->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->bigInteger('balance_after');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('created_at');
            $table->index(['created_by'], 'ix_coin_tx_created_by');
            $table->index(['ref_type', 'ref_id'], 'ix_coin_tx_ref');
            $table->index(['user_id', 'created_at'], 'ix_coin_tx_user');
            $table->comment('Sổ xu: cộng theo phiên, tiêu, điều chỉnh');
        });

        Schema::create('user_lesson_progress', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('lesson_id');
            $table->string('status', 12)->default('read')->charset('ascii')->collation('ascii_bin')->comment('read, completed');
            $table->unsignedInteger('read_at')->nullable();
            $table->unsignedInteger('completed_at')->nullable();
            $table->primary(['user_id', 'lesson_id']);
            $table->index(['lesson_id'], 'ix_ulp_lesson');
            $table->comment('Bài ngữ pháp đã đọc');
        });

        Schema::create('exercise_runs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('set_id')->nullable();
            $table->string('mode', 20)->charset('ascii')->collation('ascii_bin')->comment('set, quick, cloze, conjugate, write, plural, wrong_review');
            $table->string('grading', 10)->default('instant')->charset('ascii')->collation('ascii_bin')->comment('instant = chấm từng câu, sheet = chấm cả phiếu');
            $table->unsignedSmallInteger('total')->default(0);
            $table->unsignedSmallInteger('correct')->default(0);
            $table->unsignedInteger('coins')->default(0);
            $table->unsignedInteger('started_at');
            $table->unsignedInteger('finished_at')->nullable();
            $table->index(['set_id'], 'ix_exruns_set');
            $table->index(['user_id', 'started_at'], 'ix_exruns_user');
            $table->comment('Lượt làm bài tập');
        });

        Schema::create('user_exercise_stats', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('exercise_id');
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('wrong_count')->default(0);
            $table->boolean('last_correct')->nullable();
            $table->string('last_answer', 500)->nullable();
            $table->unsignedInteger('last_at')->nullable();
            $table->primary(['user_id', 'exercise_id']);
            $table->index(['exercise_id'], 'ix_ues_exercise');
            $table->index(['user_id', 'last_correct', 'last_at'], 'ix_ues_wrong');
            $table->comment('Đúng/sai từng câu, dùng cho Ôn câu sai');
        });

        Schema::create('writing_submissions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('card_id')->nullable();
            $table->string('kind', 20)->default('sentence')->charset('ascii')->collation('ascii_bin')->comment('sentence, free_ask, exam_writing');
            $table->text('content');
            $table->string('verdict', 10)->nullable()->charset('ascii')->collation('ascii_bin')->comment('dung, gan_dung, sai');
            $table->json('result')->nullable();
            $table->unsignedBigInteger('ai_request_id')->nullable();
            $table->unsignedInteger('created_at');
            $table->index(['ai_request_id'], 'ix_writing_ai');
            $table->index(['card_id'], 'ix_writing_card');
            $table->index(['user_id', 'created_at'], 'ix_writing_user');
            $table->comment('Câu người học tự viết và kết quả AI chấm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writing_submissions');
        Schema::dropIfExists('user_exercise_stats');
        Schema::dropIfExists('exercise_runs');
        Schema::dropIfExists('user_lesson_progress');
        Schema::dropIfExists('coin_transactions');
        Schema::dropIfExists('user_stats');
        Schema::dropIfExists('user_daily_stats');
        Schema::dropIfExists('study_sessions');
        Schema::dropIfExists('review_logs');
        Schema::dropIfExists('card_progress');
        Schema::dropIfExists('user_courses');
    }
};
