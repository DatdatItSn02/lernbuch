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
        Schema::create('content_reports', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('target_type', 30)->charset('ascii')->collation('ascii_bin')->comment('card, card_example, exercise, grammar_lesson, verb, audio, image');
            $table->unsignedBigInteger('target_id');
            $table->string('reason', 30)->charset('ascii')->collation('ascii_bin')->comment('wrong_meaning, wrong_image, typo, audio, grammar, other');
            $table->string('message', 1000)->nullable();
            $table->string('status', 20)->default('open')->charset('ascii')->collation('ascii_bin')->comment('open, accepted, fixed, rejected');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('resolution', 500)->nullable();
            $table->unsignedInteger('handled_at')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->index(['assigned_to'], 'ix_reports_assignee');
            $table->index(['created_by'], 'ix_reports_created_by');
            $table->index(['status', 'created_at'], 'ix_reports_status');
            $table->index(['target_type', 'target_id'], 'ix_reports_target');
            $table->index(['updated_by'], 'ix_reports_updated_by');
            $table->index(['user_id'], 'ix_reports_user');
            $table->comment('Báo lỗi nội dung từ người học');
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email', 191)->nullable();
            $table->string('subject', 200);
            $table->string('category', 30)->nullable()->charset('ascii')->collation('ascii_bin')->comment('payment, account, content, bug, other');
            $table->string('status', 20)->default('open')->charset('ascii')->collation('ascii_bin')->comment('open, pending, solved, closed');
            $table->unsignedTinyInteger('priority')->default(2);
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedInteger('last_reply_at')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['assigned_to'], 'ix_tickets_assignee');
            $table->index(['created_by'], 'ix_tickets_created_by');
            $table->index(['deleted_by'], 'ix_tickets_deleted_by');
            $table->index(['status', 'priority'], 'ix_tickets_status');
            $table->index(['updated_by'], 'ix_tickets_updated_by');
            $table->index(['user_id'], 'ix_tickets_user');
            $table->comment('Yêu cầu hỗ trợ');
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->boolean('is_staff')->default(0);
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->unsignedInteger('created_at');
            $table->index(['author_id'], 'ix_support_msg_author');
            $table->index(['ticket_id', 'created_at'], 'ix_support_msg_ticket');
            $table->comment('Tin nhắn trong yêu cầu hỗ trợ');
        });

        Schema::create('content_revisions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('target_type', 30)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('target_id');
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('change_note', 255)->nullable();
            $table->string('status', 20)->charset('ascii')->collation('ascii_bin')->comment('draft, in_review, approved, rejected, published');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('reviewed_at')->nullable();
            $table->index(['author_id'], 'ix_revisions_author');
            $table->index(['reviewer_id'], 'ix_revisions_reviewer');
            $table->index(['status', 'created_at'], 'ix_revisions_status');
            $table->unique(['target_type', 'target_id', 'version'], 'uq_revisions');
            $table->comment('Lịch sử phiên bản nội dung và quy trình duyệt');
        });

        Schema::create('content_imports', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('file_name', 255);
            $table->string('kind', 20)->charset('ascii')->collation('ascii_bin')->comment('cards, exercises, grammar, verbs, lernbuch_db');
            $table->string('status', 20)->default('queued')->charset('ascii')->collation('ascii_bin')->comment('queued, running, done, failed');
            $table->boolean('dry_run')->default(1);
            $table->json('stats')->nullable()->comment('số dòng thêm, sửa, bỏ qua');
            $table->json('errors')->nullable();
            $table->unsignedInteger('started_at')->nullable();
            $table->unsignedInteger('finished_at')->nullable();
            $table->unsignedInteger('created_at');
            $table->index(['status'], 'ix_imports_status');
            $table->index(['user_id'], 'ix_imports_user');
            $table->comment('Lượt nhập Excel, CSV hoặc DB Lernbuch cũ');
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('slug', 120)->charset('ascii')->collation('ascii_bin');
            $table->string('kind', 20)->charset('ascii')->collation('ascii_bin')->comment('legal, help, landing');
            $table->string('title', 200);
            $table->mediumText('body');
            $table->string('version', 20)->default('1');
            $table->string('status', 20)->default('draft')->charset('ascii')->collation('ascii_bin');
            $table->unsignedInteger('published_at')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['created_by'], 'ix_pages_created_by');
            $table->index(['deleted_by'], 'ix_pages_deleted_by');
            $table->index(['updated_by'], 'ix_pages_updated_by');
            $table->unique(['slug'], 'uq_pages_slug');
            $table->comment('Trang nội dung: điều khoản, quyền riêng tư, trợ giúp');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->string('name', 100)->charset('ascii')->collation('ascii_bin');
            $table->json('value')->nullable();
            $table->boolean('is_secret')->default(0)->comment('1 = giá trị đã mã hoá');
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->primary(['name']);
            $table->index(['updated_by'], 'ix_settings_updated_by');
            $table->comment('Cấu hình hệ thống sửa trong admin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('content_imports');
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('content_reports');
    }
};
