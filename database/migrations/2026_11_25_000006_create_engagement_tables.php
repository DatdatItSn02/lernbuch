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
        Schema::create('achievements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->smallIncrements('id');
            $table->string('code', 40)->charset('ascii')->collation('ascii_bin');
            $table->string('name_vi', 120);
            $table->string('description_vi', 255)->nullable();
            $table->string('icon', 40)->nullable()->charset('ascii')->collation('ascii_bin');
            $table->json('rule')->comment('vd {"type":"streak","value":7}');
            $table->unsignedInteger('coins_reward')->default(0);
            $table->boolean('is_active')->default(1);
            $table->smallInteger('sort')->default(0);
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['created_by'], 'ix_achievements_created_by');
            $table->index(['deleted_by'], 'ix_achievements_deleted_by');
            $table->index(['updated_by'], 'ix_achievements_updated_by');
            $table->unique(['code'], 'uq_achievements_code');
            $table->comment('Huy hiệu');
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('achievement_id');
            $table->unsignedInteger('earned_at');
            $table->primary(['user_id', 'achievement_id']);
            $table->index(['achievement_id'], 'ix_user_ach_achievement');
            $table->comment('Huy hiệu đã nhận');
        });

        Schema::create('leaderboard_weeks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedInteger('week_start')->comment('00:00 thứ Hai theo giờ Việt Nam');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('score');
            $table->unsignedInteger('rank_no');
            $table->primary(['week_start', 'user_id']);
            $table->index(['week_start', 'rank_no'], 'ix_lb_rank');
            $table->index(['user_id'], 'ix_lb_user');
            $table->comment('Chốt bảng xếp hạng mỗi tuần');
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->char('endpoint_hash', 64)->charset('ascii')->collation('ascii_bin')->comment('sha256 của endpoint');
            $table->text('endpoint');
            $table->string('public_key', 255)->nullable();
            $table->string('auth_token', 255)->nullable();
            $table->string('content_encoding', 20)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->index(['user_id'], 'ix_push_user');
            $table->unique(['endpoint_hash'], 'uq_push_endpoint');
            $table->comment('Đăng ký nhận web push');
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('level', 10)->default('info')->charset('ascii')->collation('ascii_bin')->comment('info, warning');
            $table->json('audience')->nullable()->comment('vd {"tier":[1]}');
            $table->unsignedInteger('starts_at')->nullable();
            $table->unsignedInteger('ends_at')->nullable();
            $table->boolean('is_active')->default(1);
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['is_active', 'starts_at', 'ends_at'], 'ix_announcements_active');
            $table->index(['created_by'], 'ix_announcements_created_by');
            $table->index(['deleted_by'], 'ix_announcements_deleted_by');
            $table->index(['updated_by'], 'ix_announcements_updated_by');
            $table->comment('Thông báo chung (banner)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('leaderboard_weeks');
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
    }
};
