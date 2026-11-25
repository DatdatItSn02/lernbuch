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
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('kind', 20)->charset('ascii')->collation('ascii_bin')->comment('translate, lookup, lesson, chat, grammar, sentence, answer, word, doc, ask');
            $table->unsignedSmallInteger('version');
            $table->string('model', 60)->charset('ascii')->collation('ascii_bin')->comment('vd gemini-3.1-flash-lite');
            $table->text('system_prompt');
            $table->text('user_template');
            $table->unsignedSmallInteger('max_tokens')->default(800);
            $table->decimal('temperature', 3, 2)->default(0.20);
            $table->boolean('is_active')->default(0);
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['kind', 'is_active'], 'ix_ai_prompts_active');
            $table->index(['created_by'], 'ix_ai_prompts_created_by');
            $table->index(['deleted_by'], 'ix_ai_prompts_deleted_by');
            $table->index(['updated_by'], 'ix_ai_prompts_updated_by');
            $table->unique(['kind', 'version'], 'uq_ai_prompts');
            $table->comment('Prompt AI theo phiên bản, sửa trong admin');
        });

        Schema::create('ai_cache', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->char('hash', 64)->charset('ascii')->collation('ascii_bin')->comment('sha256(loại|phiên bản prompt|model|đầu vào chuẩn hoá)');
            $table->string('kind', 20)->charset('ascii')->collation('ascii_bin');
            $table->json('response');
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('last_hit_at')->nullable();
            $table->unsignedInteger('expires_at')->nullable();
            $table->primary(['hash']);
            $table->index(['expires_at'], 'ix_ai_cache_expires');
            $table->index(['kind', 'created_at'], 'ix_ai_cache_kind');
            $table->comment('Kết quả AI dùng chung cho mọi người; kind=lesson không hết hạn');
        });

        Schema::create('ai_requests', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('kind', 20)->charset('ascii')->collation('ascii_bin')->comment('như ai_prompts.kind, thêm pron');
            $table->string('provider', 20)->default('gemini')->charset('ascii')->collation('ascii_bin')->comment('gemini, azure_speech, …');
            $table->unsignedInteger('prompt_id')->nullable();
            $table->string('model', 60)->nullable()->charset('ascii')->collation('ascii_bin');
            $table->string('context_type', 30)->nullable()->charset('ascii')->collation('ascii_bin')->comment('card, exercise, grammar_lesson, verb');
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('input_excerpt', 500)->nullable()->comment('xoá sau 30 ngày');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cost_micro_usd')->default(0)->comment('chi phí USD × 1.000.000');
            $table->boolean('cache_hit')->default(0);
            $table->string('status', 12)->charset('ascii')->collation('ascii_bin')->comment('ok, error, quota, blocked');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('created_at');
            $table->index(['context_type', 'context_id'], 'ix_ai_req_context');
            $table->index(['created_at'], 'ix_ai_req_created');
            $table->index(['prompt_id'], 'ix_ai_req_prompt');
            $table->index(['user_id', 'created_at'], 'ix_ai_req_user');
            $table->comment('Nhật ký gọi AI: hạn mức và chi phí');
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->char('public_id', 26)->charset('ascii')->collation('ascii_bin')->comment('ULID');
            $table->unsignedBigInteger('user_id');
            $table->string('scenario', 40)->default('free')->charset('ascii')->collation('ascii_bin')->comment('free, restaurant, visa_interview, workplace…');
            $table->string('level', 4)->nullable()->charset('ascii')->collation('ascii_bin')->comment('A1…C1');
            $table->string('context_type', 30)->nullable()->charset('ascii')->collation('ascii_bin')->comment('card, grammar_lesson, exercise… khi mở từ nút Hỏi AI');
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('title', 160)->nullable();
            $table->unsignedSmallInteger('message_count')->default(0);
            $table->unsignedInteger('last_message_at')->nullable();
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at')->nullable();
            $table->index(['context_type', 'context_id'], 'ix_ai_conv_context');
            $table->index(['user_id', 'last_message_at'], 'ix_ai_conv_user');
            $table->unique(['public_id'], 'uq_ai_conv_public');
            $table->comment('Trò chuyện AI; người dùng xoá là xoá hẳn');
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversation_id');
            $table->string('role', 10)->charset('ascii')->collation('ascii_bin')->comment('user, assistant');
            $table->text('content');
            $table->json('corrections')->nullable()->comment('lỗi AI chỉ ra trong câu người học');
            $table->unsignedBigInteger('request_id')->nullable()->comment('ai_requests.id của lượt trả lời');
            $table->unsignedInteger('created_at');
            $table->index(['conversation_id', 'id'], 'ix_ai_msg_conv');
            $table->index(['request_id'], 'ix_ai_msg_request');
            $table->comment('Tin nhắn trong trò chuyện AI');
        });

        Schema::create('user_ai_lessons', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->char('cache_hash', 64)->charset('ascii')->collation('ascii_bin')->comment('ai_cache.hash chứa nội dung bài');
            $table->unsignedInteger('topic_id')->nullable();
            $table->string('level', 4)->nullable()->charset('ascii')->collation('ascii_bin');
            $table->string('title', 160);
            $table->unsignedTinyInteger('score')->nullable()->comment('0–100 phần câu hỏi');
            $table->unsignedInteger('completed_at')->nullable();
            $table->unsignedInteger('created_at');
            $table->index(['cache_hash'], 'ix_ual_cache');
            $table->index(['topic_id'], 'ix_ual_topic');
            $table->index(['user_id', 'created_at'], 'ix_ual_user');
            $table->unique(['user_id', 'cache_hash'], 'uq_ual_user_lesson');
            $table->comment('Bài học AI của từng người');
        });

        Schema::create('pronunciation_attempts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('card_id')->nullable();
            $table->unsignedBigInteger('example_id')->nullable();
            $table->string('lang', 10)->default('de-DE')->charset('ascii')->collation('ascii_bin');
            $table->string('reference_text', 600)->comment('câu hoặc từ phải đọc');
            $table->string('provider', 12)->charset('ascii')->collation('ascii_bin')->comment('azure, browser');
            $table->string('status', 12)->charset('ascii')->collation('ascii_bin')->comment('ok, empty, error, quota');
            $table->unsignedTinyInteger('overall_score')->nullable()->comment('0–100');
            $table->unsignedTinyInteger('accuracy_score')->nullable();
            $table->unsignedTinyInteger('fluency_score')->nullable();
            $table->unsignedTinyInteger('completeness_score')->nullable();
            $table->json('words')->nullable()->comment('điểm và lỗi từng từ, từng âm');
            $table->unsignedInteger('audio_ms')->nullable()->comment('độ dài ghi âm; không lưu file');
            $table->unsignedInteger('created_at');
            $table->index(['card_id'], 'ix_pron_card');
            $table->index(['example_id'], 'ix_pron_example');
            $table->index(['user_id', 'created_at'], 'ix_pron_user');
            $table->comment('Lượt chấm phát âm; không giữ file ghi âm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pronunciation_attempts');
        Schema::dropIfExists('user_ai_lessons');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_requests');
        Schema::dropIfExists('ai_cache');
        Schema::dropIfExists('ai_prompts');
    }
};
