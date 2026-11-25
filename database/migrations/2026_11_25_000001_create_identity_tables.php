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
        Schema::create('users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->char('public_id', 26)->charset('ascii')->collation('ascii_bin')->comment('ULID dùng trong URL và API');
            $table->string('email', 191);
            $table->unsignedInteger('email_verified_at')->nullable();
            $table->string('password', 255)->nullable()->comment('NULL nếu chỉ đăng nhập Google');
            $table->string('name', 100);
            $table->string('username', 32)->nullable()->comment('tên hiện trên bảng xếp hạng');
            $table->string('avatar_path', 255)->nullable();
            $table->string('ui_locale', 8)->default('vi')->charset('ascii')->collation('ascii_bin');
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh')->charset('ascii')->collation('ascii_bin');
            $table->integer('birth_date')->nullable()->comment('ngày sinh lúc 00:00 UTC; INT có dấu vì có thể trước 1970; AI chỉ mở từ 18 tuổi');
            $table->string('status', 20)->default('active')->charset('ascii')->collation('ascii_bin')->comment('active, suspended, pending_deletion');
            $table->boolean('is_staff')->default(0)->comment('1 = nhân viên, bắt buộc 2FA');
            $table->text('two_factor_secret')->nullable()->comment('mã hoá ở ứng dụng');
            $table->text('two_factor_recovery_codes')->nullable()->comment('mã hoá ở ứng dụng');
            $table->unsignedInteger('two_factor_confirmed_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->string('referral_code', 16)->nullable()->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('referred_by')->nullable();
            $table->unsignedInteger('last_login_at')->nullable();
            $table->unsignedInteger('last_active_at')->nullable();
            $table->unsignedInteger('deletion_requested_at')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['created_at'], 'ix_users_created_at');
            $table->index(['created_by'], 'ix_users_created_by');
            $table->index(['deleted_by'], 'ix_users_deleted_by');
            $table->index(['referred_by'], 'ix_users_referred_by');
            $table->index(['status'], 'ix_users_status');
            $table->index(['updated_by'], 'ix_users_updated_by');
            $table->unique(['email'], 'uq_users_email');
            $table->unique(['public_id'], 'uq_users_public_id');
            $table->unique(['referral_code'], 'uq_users_referral_code');
            $table->unique(['username'], 'uq_users_username');
            $table->comment('Tài khoản: học viên và nhân viên');
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('current_course_id')->nullable();
            $table->unsignedSmallInteger('new_per_session')->default(20)->comment('0 = tất cả');
            $table->unsignedSmallInteger('daily_goal')->default(20)->comment('số thẻ mỗi ngày');
            $table->char('reminder_time', 5)->nullable()->charset('ascii')->collation('ascii_bin')->comment('HH:MM theo giờ người dùng');
            $table->json('reminder_channels')->nullable()->comment('["email","push"]');
            $table->json('prefs')->nullable()->comment('mặt trước thẻ, tự phát âm, hiện ví dụ, AI tự giải thích, ngôn ngữ nghĩa, mùa, sáng/tối, cỡ chữ');
            $table->unsignedInteger('updated_at')->nullable();
            $table->primary(['user_id']);
            $table->index(['current_course_id'], 'ix_user_settings_course');
            $table->comment('Cài đặt học và giao diện, đồng bộ giữa các máy');
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 20)->charset('ascii')->collation('ascii_bin')->comment('google, facebook, apple');
            $table->string('provider_user_id', 191)->charset('ascii')->collation('ascii_bin');
            $table->string('email', 191)->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->index(['user_id'], 'ix_social_user');
            $table->unique(['provider', 'provider_user_id'], 'uq_social_provider_uid');
            $table->comment('Đăng nhập qua Google, Facebook, Apple');
        });

        Schema::create('user_devices', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->char('device_uid', 26)->charset('ascii')->collation('ascii_bin')->comment('ULID lưu trên máy');
            $table->string('name', 120)->nullable()->comment('vd Chrome trên Windows');
            $table->string('platform', 20)->nullable()->charset('ascii')->collation('ascii_bin')->comment('web, pwa, ios, android');
            $table->binary('last_ip', 16)->nullable();
            $table->unsignedInteger('last_seen_at')->nullable();
            $table->unsignedInteger('revoked_at')->nullable();
            $table->unsignedInteger('created_at');
            $table->index(['user_id', 'last_seen_at'], 'ix_user_devices_seen');
            $table->unique(['user_id', 'device_uid'], 'uq_user_devices');
            $table->comment('Thiết bị đang đăng nhập: giới hạn số máy, đăng xuất từ xa');
        });

        Schema::create('login_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email', 191)->nullable()->comment('ghi khi đăng nhập sai');
            $table->string('event', 20)->charset('ascii')->collation('ascii_bin')->comment('login, logout, failed, two_factor_failed, password_reset, locked');
            $table->binary('ip', 16)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedInteger('created_at');
            $table->index(['created_at'], 'ix_login_events_created');
            $table->index(['user_id', 'created_at'], 'ix_login_events_user');
            $table->comment('Lịch sử đăng nhập, giữ 90 ngày');
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('kind', 30)->charset('ascii')->collation('ascii_bin')->comment('terms, privacy, marketing_email, analytics_cookies, ai_processing');
            $table->string('document_version', 20);
            $table->boolean('granted');
            $table->string('source', 20)->default('web')->charset('ascii')->collation('ascii_bin')->comment('web, app, admin');
            $table->binary('ip', 16)->nullable();
            $table->unsignedInteger('created_at');
            $table->index(['user_id', 'kind', 'created_at'], 'ix_consents_user_kind');
            $table->comment('Bằng chứng đồng ý và rút đồng ý xử lý dữ liệu cá nhân');
        });

        Schema::create('data_requests', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('kind', 10)->charset('ascii')->collation('ascii_bin')->comment('export, delete');
            $table->string('status', 20)->default('pending')->charset('ascii')->collation('ascii_bin')->comment('pending, processing, done, rejected, canceled');
            $table->string('file_path', 255)->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('requested_at');
            $table->unsignedInteger('due_at')->nullable();
            $table->unsignedInteger('completed_at')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->index(['handled_by'], 'ix_data_requests_handler');
            $table->index(['status', 'due_at'], 'ix_data_requests_status');
            $table->index(['user_id'], 'ix_data_requests_user');
            $table->comment('Yêu cầu tải dữ liệu hoặc xoá tài khoản');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('name', 125);
            $table->string('guard_name', 125);
            $table->string('label', 160)->nullable()->comment('tên tiếng Việt hiển thị');
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->index(['created_by'], 'ix_permissions_created_by');
            $table->index(['updated_by'], 'ix_permissions_updated_by');
            $table->unique(['name', 'guard_name'], 'uq_permissions_name_guard');
            $table->comment('Quyền');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('name', 125);
            $table->string('guard_name', 125);
            $table->string('label', 160)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->index(['created_by'], 'ix_roles_created_by');
            $table->index(['updated_by'], 'ix_roles_updated_by');
            $table->unique(['name', 'guard_name'], 'uq_roles_name_guard');
            $table->comment('Vai trò');
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type', 125);
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
            $table->index(['model_id', 'model_type'], 'ix_mhp_model');
            $table->comment('Quyền gán thẳng cho người dùng');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('role_id');
            $table->string('model_type', 125);
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
            $table->index(['model_id', 'model_type'], 'ix_mhr_model');
            $table->comment('Vai trò của người dùng');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
            $table->index(['role_id'], 'ix_rhp_role');
            $table->comment('Quyền của vai trò');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 60)->charset('ascii')->collation('ascii_bin')->comment('vd user.suspend, order.refund, card.update, impersonate.start');
            $table->string('subject_type', 60)->nullable()->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('changes')->nullable()->comment('{"before":{},"after":{}}');
            $table->binary('ip', 16)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedInteger('created_at');
            $table->index(['action', 'created_at'], 'ix_audit_action');
            $table->index(['actor_id', 'created_at'], 'ix_audit_actor');
            $table->index(['subject_type', 'subject_id'], 'ix_audit_subject');
            $table->comment('Nhật ký thao tác của nhân viên, chỉ thêm không sửa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('data_requests');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('login_events');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('users');
    }
};
