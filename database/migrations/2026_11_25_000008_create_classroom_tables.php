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
        Schema::create('organizations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('name', 200);
            $table->string('slug', 120)->charset('ascii')->collation('ascii_bin');
            $table->string('tax_code', 20)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_email', 191)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->unsignedInteger('seats')->default(0);
            $table->unsignedInteger('seats_used')->default(0);
            $table->string('status', 20)->default('active')->charset('ascii')->collation('ascii_bin');
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['created_by'], 'ix_org_created_by');
            $table->index(['deleted_by'], 'ix_org_deleted_by');
            $table->index(['updated_by'], 'ix_org_updated_by');
            $table->unique(['slug'], 'uq_org_slug');
            $table->comment('Trung tâm, trường (khách B2B)');
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 20)->charset('ascii')->collation('ascii_bin')->comment('owner, admin, teacher, student');
            $table->unsignedInteger('joined_at');
            $table->primary(['organization_id', 'user_id']);
            $table->index(['user_id'], 'ix_org_members_user');
            $table->comment('Thành viên trung tâm');
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedInteger('course_id');
            $table->string('name', 160);
            $table->string('join_code', 12)->charset('ascii')->collation('ascii_bin');
            $table->unsignedInteger('starts_on')->nullable();
            $table->unsignedInteger('ends_on')->nullable();
            $table->string('status', 20)->default('active')->charset('ascii')->collation('ascii_bin');
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['course_id'], 'ix_classrooms_course');
            $table->index(['created_by'], 'ix_classrooms_created_by');
            $table->index(['deleted_by'], 'ix_classrooms_deleted_by');
            $table->index(['organization_id'], 'ix_classrooms_org');
            $table->index(['teacher_id'], 'ix_classrooms_teacher');
            $table->index(['updated_by'], 'ix_classrooms_updated_by');
            $table->unique(['join_code'], 'uq_classrooms_code');
            $table->comment('Lớp học');
        });

        Schema::create('classroom_members', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('classroom_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 20)->default('student')->charset('ascii')->collation('ascii_bin')->comment('teacher, student');
            $table->unsignedInteger('joined_at');
            $table->primary(['classroom_id', 'user_id']);
            $table->index(['user_id'], 'ix_classroom_members_user');
            $table->comment('Học viên trong lớp');
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('classroom_id');
            $table->string('title', 200);
            $table->string('target_type', 30)->charset('ascii')->collation('ascii_bin')->comment('topic, exercise_set, grammar_lesson, deck');
            $table->unsignedBigInteger('target_id');
            $table->unsignedInteger('due_at')->nullable();
            $table->unsignedTinyInteger('min_score')->nullable()->comment('% tối thiểu để đạt');
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['classroom_id', 'due_at'], 'ix_assignments_class');
            $table->index(['created_by'], 'ix_assignments_created_by');
            $table->index(['deleted_by'], 'ix_assignments_deleted_by');
            $table->index(['target_type', 'target_id'], 'ix_assignments_target');
            $table->index(['updated_by'], 'ix_assignments_updated_by');
            $table->comment('Bài giao cho lớp');
        });

        Schema::create('assignment_progress', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status', 20)->default('todo')->charset('ascii')->collation('ascii_bin')->comment('todo, in_progress, done');
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedInteger('completed_at')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->primary(['assignment_id', 'user_id']);
            $table->index(['user_id'], 'ix_assign_progress_user');
            $table->comment('Tiến độ bài giao của từng học viên');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_progress');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('classroom_members');
        Schema::dropIfExists('classrooms');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
    }
};
