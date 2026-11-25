<?php

// File sinh tự động bởi tools/schema/gen_migrations.py từ database/sql/schema.sql.
// Không sửa tay: muốn đổi cấu trúc thì viết migration mới.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->string('email', 255);
            $table->string('token', 255);
            $table->timestamp('created_at')->nullable();
            $table->primary(['email']);
            $table->comment('Laravel: mã đặt lại mật khẩu');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->string('id', 255);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');
            $table->primary(['id']);
            $table->index(['last_activity'], 'sessions_last_activity_index');
            $table->index(['user_id'], 'sessions_user_id_index');
            $table->comment('Laravel: phiên đăng nhập (dự phòng)');
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->string('key', 255);
            $table->mediumText('value');
            $table->integer('expiration');
            $table->primary(['key']);
            $table->comment('Laravel: cache (dự phòng)');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->string('key', 255);
            $table->string('owner', 255);
            $table->integer('expiration');
            $table->primary(['key']);
            $table->comment('Laravel: khoá cache (dự phòng)');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('queue', 255);
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
            $table->index(['queue'], 'jobs_queue_index');
            $table->comment('Laravel: hàng đợi (dự phòng)');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->string('id', 255);
            $table->string('name', 255);
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
            $table->primary(['id']);
            $table->comment('Laravel: lô job');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('uuid', 255);
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
            $table->unique(['uuid'], 'failed_jobs_uuid_unique');
            $table->comment('Laravel: job lỗi');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('tokenable_type', 255);
            $table->unsignedBigInteger('tokenable_id');
            $table->text('name');
            $table->string('token', 64);
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['expires_at'], 'personal_access_tokens_expires_at_index');
            $table->index(['tokenable_type', 'tokenable_id'], 'personal_access_tokens_tokenable_type_tokenable_id_index');
            $table->unique(['token'], 'personal_access_tokens_token_unique');
            $table->comment('Sanctum: token cho app di động');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->char('id', 36);
            $table->string('type', 255);
            $table->string('notifiable_type', 255);
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->primary(['id']);
            $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
            $table->comment('Laravel: thông báo trong app (xoá sau 6 tháng)');
        });

        Schema::create('features', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('scope', 255);
            $table->text('value');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['name', 'scope'], 'features_name_scope_unique');
            $table->comment('Pennant: bật/tắt tính năng theo nhóm người dùng');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
    }
};
