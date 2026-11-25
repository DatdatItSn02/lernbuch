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
        Schema::create('languages', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->string('code', 8)->charset('ascii')->collation('ascii_bin')->comment('de, en, zh, vi, ja…');
            $table->string('name_vi', 60);
            $table->string('native_name', 60);
            $table->string('tts_voice', 60)->nullable()->charset('ascii')->collation('ascii_bin')->comment('vd de-DE-KatjaNeural');
            $table->string('special_chars', 60)->nullable()->comment('nút gõ nhanh, vd äöüßÄÖÜ');
            $table->boolean('is_target')->default(0)->comment('1 = có khóa học');
            $table->boolean('is_meaning')->default(0)->comment('1 = dùng làm nghĩa');
            $table->boolean('is_ui')->default(0)->comment('1 = ngôn ngữ giao diện');
            $table->smallInteger('sort')->default(0);
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->primary(['code']);
            $table->index(['created_by'], 'ix_languages_created_by');
            $table->index(['deleted_by'], 'ix_languages_deleted_by');
            $table->index(['updated_by'], 'ix_languages_updated_by');
            $table->comment('Ngôn ngữ');
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('slug', 80)->charset('ascii')->collation('ascii_bin');
            $table->string('target_lang', 8)->charset('ascii')->collation('ascii_bin');
            $table->string('base_lang', 8)->charset('ascii')->collation('ascii_bin')->comment('ngôn ngữ giải thích');
            $table->json('meaning_langs')->comment('["vi","en","zh"]');
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('cover_path', 255)->nullable();
            $table->unsignedTinyInteger('level_min')->default(1);
            $table->unsignedTinyInteger('level_max')->default(4);
            $table->string('status', 20)->default('draft')->charset('ascii')->collation('ascii_bin')->comment('draft, published, archived');
            $table->smallInteger('sort')->default(0);
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['base_lang'], 'ix_courses_base_lang');
            $table->index(['created_by'], 'ix_courses_created_by');
            $table->index(['deleted_by'], 'ix_courses_deleted_by');
            $table->index(['updated_by'], 'ix_courses_updated_by');
            $table->unique(['target_lang', 'base_lang'], 'uq_courses_pair');
            $table->unique(['slug'], 'uq_courses_slug');
            $table->comment('Khóa học = ngôn ngữ đích + ngôn ngữ giải thích');
        });

        Schema::create('card_collections', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->string('code', 16)->charset('ascii')->collation('ascii_bin')->comment('vocab, phrase, comm, pattern, verbprep, sepverb, name');
            $table->string('name_vi', 60);
            $table->string('name_en', 60)->nullable();
            $table->boolean('single_word_only')->default(0)->comment('1 = chỉ nhận từ đơn');
            $table->smallInteger('sort')->default(0);
            $table->primary(['code']);
            $table->comment('Loại thẻ');
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('lang', 8)->charset('ascii')->collation('ascii_bin');
            $table->unsignedInteger('parent_id')->nullable();
            $table->unsignedTinyInteger('depth')->comment('1 nhóm lớn, 2 chủ đề vừa, 3 chủ đề nhỏ');
            $table->string('collection', 16)->nullable()->charset('ascii')->collation('ascii_bin')->comment('chủ đề nhỏ thuộc đúng 1 loại thẻ');
            $table->string('code', 16)->charset('ascii')->collation('ascii_bin')->comment('giữ mã cũ, vd f0001');
            $table->string('slug', 160)->charset('ascii')->collation('ascii_bin');
            $table->string('name_vi', 160);
            $table->string('name_en', 160)->nullable();
            $table->string('icon', 40)->nullable()->charset('ascii')->collation('ascii_bin')->comment('mã OpenMoji hoặc p-<tên>');
            $table->integer('sort')->default(0);
            $table->unsignedInteger('card_count')->default(0)->comment('đếm sẵn thẻ đã xuất bản');
            $table->unsignedTinyInteger('access_tier')->default(2)->comment('0 khách, 1 Free, 2 Premium');
            $table->string('status', 20)->default('published')->charset('ascii')->collation('ascii_bin');
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['lang', 'collection', 'depth', 'status', 'sort'], 'ix_topics_browse');
            $table->index(['created_by'], 'ix_topics_created_by');
            $table->index(['deleted_by'], 'ix_topics_deleted_by');
            $table->index(['parent_id', 'sort'], 'ix_topics_parent');
            $table->index(['updated_by'], 'ix_topics_updated_by');
            $table->unique(['lang', 'code'], 'uq_topics_lang_code');
            $table->unique(['lang', 'slug'], 'uq_topics_lang_slug');
            $table->comment('Chủ đề 3 tầng');
        });

        Schema::create('content_sources', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->smallIncrements('id');
            $table->string('code', 40)->charset('ascii')->collation('ascii_bin');
            $table->string('name', 160);
            $table->string('license', 60)->nullable()->comment('vd CC BY 2.0 FR, CC BY-SA 4.0, tự soạn, chưa rõ');
            $table->string('commercial_use', 20)->default('unknown')->charset('ascii')->collation('ascii_bin')->comment('allowed, licensed, forbidden, unknown; ghi để truy lại; nguồn chưa có quyền chỉ lấy dữ kiện, không chép nguyên văn');
            $table->text('attribution')->nullable();
            $table->string('url', 255)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['commercial_use'], 'ix_sources_commercial');
            $table->index(['created_by'], 'ix_sources_created_by');
            $table->index(['deleted_by'], 'ix_sources_deleted_by');
            $table->index(['updated_by'], 'ix_sources_updated_by');
            $table->unique(['code'], 'uq_sources_code');
            $table->comment('Nguồn nội dung và giấy phép');
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('kind', 10)->charset('ascii')->collation('ascii_bin')->comment('audio, image');
            $table->char('hash', 40)->charset('ascii')->collation('ascii_bin')->comment('audio: sha1(giọng|tốc độ|câu); hình: sha1 nội dung');
            $table->string('disk', 20)->default('r2')->charset('ascii')->collation('ascii_bin');
            $table->string('path', 255);
            $table->string('mime', 60)->charset('ascii')->collation('ascii_bin');
            $table->unsignedInteger('bytes')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('voice', 60)->nullable()->charset('ascii')->collation('ascii_bin');
            $table->string('speed', 10)->nullable()->charset('ascii')->collation('ascii_bin')->comment('normal, slow');
            $table->string('text_content', 600)->nullable()->comment('câu đã đọc');
            $table->unsignedSmallInteger('source_id')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->index(['source_id'], 'ix_media_source');
            $table->unique(['kind', 'hash'], 'uq_media_kind_hash');
            $table->comment('Sổ đăng ký file audio và hình trên kho file');
        });

        Schema::create('cards', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('lang', 8)->charset('ascii')->collation('ascii_bin');
            $table->string('collection', 16)->charset('ascii')->collation('ascii_bin');
            $table->string('card_key', 64)->charset('ascii')->collation('ascii_bin')->comment('khoá ổn định, giữ từ Lernbuch');
            $table->unsignedBigInteger('owner_user_id')->nullable()->comment('NULL = nội dung hệ thống; có giá trị = thẻ riêng của người dùng');
            $table->unsignedInteger('topic_id')->nullable()->comment('đúng 1 chủ đề nhỏ');
            $table->string('front', 500);
            $table->string('article', 10)->nullable()->comment('der, die, das');
            $table->char('gender', 1)->nullable()->charset('ascii')->collation('ascii_bin')->comment('m, f, n, p (tô màu)');
            $table->string('pos', 16)->nullable()->charset('ascii')->collation('ascii_bin')->comment('loại từ');
            $table->string('forms', 255)->nullable()->comment('số nhiều hoặc dạng chia');
            $table->unsignedTinyInteger('level')->nullable()->comment('1–6 = A1–C2; NULL = chưa xếp');
            $table->unsignedTinyInteger('level_min')->nullable();
            $table->unsignedTinyInteger('level_max')->nullable();
            $table->string('lektion', 20)->nullable()->comment('bài trong giáo trình');
            $table->string('image', 40)->nullable()->charset('ascii')->collation('ascii_bin')->comment('mã OpenMoji hoặc p-<tên>');
            $table->unsignedTinyInteger('image_quality')->default(0)->comment('0 chưa có, 1 nhóm nghĩa, 2 khớp nghĩa, 3 tự vẽ');
            $table->text('note')->nullable();
            $table->text('tip')->nullable();
            $table->string('cloze', 500)->nullable();
            $table->string('cloze_answer', 120)->nullable();
            $table->string('source_topic', 160)->nullable()->comment('chủ đề gốc từ nguồn');
            $table->json('extra')->nullable()->comment('dữ liệu riêng ngôn ngữ: giới từ đi kèm, Kasus, tách được…');
            $table->string('search_text', 1500)->nullable()->comment('không dấu: từ + nghĩa mọi thứ tiếng + dạng');
            $table->string('status', 20)->default('published')->charset('ascii')->collation('ascii_bin')->comment('draft, in_review, published, archived');
            $table->unsignedSmallInteger('source_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('published_at')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['created_by'], 'ix_cards_created_by');
            $table->index(['deleted_by'], 'ix_cards_deleted_by');
            $table->index(['lang', 'collection', 'level', 'status'], 'ix_cards_level');
            $table->index(['owner_user_id'], 'ix_cards_owner');
            $table->index(['lang', 'pos'], 'ix_cards_pos');
            $table->index(['source_id'], 'ix_cards_source');
            $table->index(['topic_id', 'status'], 'ix_cards_topic');
            $table->index(['updated_by'], 'ix_cards_updated_by');
            $table->unique(['lang', 'collection', 'card_key'], 'uq_cards_key');
            $table->comment('Thẻ học: từ, cụm, câu giao tiếp, mẫu câu, động từ, tên riêng');
        });

        Schema::create('card_translations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('card_id');
            $table->string('lang', 8)->charset('ascii')->collation('ascii_bin')->comment('vi, en, zh…');
            $table->string('meaning', 500);
            $table->string('note', 500)->nullable();
            $table->boolean('is_verified')->default(0)->comment('1 = người đã duyệt');
            $table->unsignedSmallInteger('source_id')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->primary(['card_id', 'lang']);
            $table->index(['lang', 'is_verified'], 'ix_card_tr_lang');
            $table->index(['source_id'], 'ix_card_tr_source');
            $table->index(['updated_by'], 'ix_card_tr_updated_by');
            $table->comment('Nghĩa của thẻ theo từng thứ tiếng');
        });

        Schema::create('card_examples', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('card_id');
            $table->unsignedTinyInteger('sort')->default(0)->comment('0 = câu chính');
            $table->string('sentence', 600);
            $table->json('translations')->nullable()->comment('{"vi":"…","en":"…","zh":"…"}');
            $table->boolean('is_manual')->default(0)->comment('1 = câu viết tay cho từ khó');
            $table->unsignedSmallInteger('source_id')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->index(['source_id'], 'ix_card_examples_source');
            $table->index(['updated_by'], 'ix_card_examples_updated_by');
            $table->unique(['card_id', 'sort'], 'uq_card_examples_sort');
            $table->comment('Câu ví dụ, tối đa 4 câu mỗi thẻ');
        });

        Schema::create('card_relations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('card_id');
            $table->unsignedBigInteger('related_id');
            $table->string('kind', 20)->charset('ascii')->collation('ascii_bin')->comment('similar, synonym, antonym, family, confusable');
            $table->decimal('score', 4, 3)->nullable();
            $table->primary(['card_id', 'kind', 'related_id']);
            $table->index(['related_id'], 'ix_card_rel_related');
            $table->comment('Từ tương tự, đồng nghĩa, trái nghĩa, cùng họ, dễ nhầm');
        });

        Schema::create('word_forms', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('lang', 8)->charset('ascii')->collation('ascii_bin');
            $table->string('form', 120)->charset('utf8mb4')->collation('utf8mb4_bin')->comment('viết thường, so khớp chính xác');
            $table->unsignedBigInteger('card_id');
            $table->string('kind', 20)->charset('ascii')->collation('ascii_bin')->comment('lemma, plural, conj, comparative…');
            $table->index(['lang', 'form'], 'ix_word_forms_lookup');
            $table->unique(['card_id', 'kind', 'form'], 'uq_word_forms');
            $table->comment('Dạng từ để tra chữ bấm trong câu (số nhiều, dạng chia)');
        });

        Schema::create('decks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('owner_type', 20)->charset('ascii')->collation('ascii_bin')->comment('user, classroom, system');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('lang', 8)->charset('ascii')->collation('ascii_bin');
            $table->string('name', 160);
            $table->string('description', 500)->nullable();
            $table->string('visibility', 20)->default('private')->charset('ascii')->collation('ascii_bin')->comment('private, unlisted, public');
            $table->unsignedInteger('card_count')->default(0);
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['created_by'], 'ix_decks_created_by');
            $table->index(['deleted_by'], 'ix_decks_deleted_by');
            $table->index(['owner_type', 'owner_id'], 'ix_decks_owner');
            $table->index(['updated_by'], 'ix_decks_updated_by');
            $table->comment('Bộ thẻ tự tạo của người dùng hoặc lớp học');
        });

        Schema::create('deck_cards', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('deck_id');
            $table->unsignedBigInteger('card_id');
            $table->integer('sort')->default(0);
            $table->unsignedInteger('added_at');
            $table->primary(['deck_id', 'card_id']);
            $table->index(['card_id'], 'ix_deck_cards_card');
            $table->comment('Thẻ trong bộ thẻ tự tạo');
        });

        Schema::create('user_card_notes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('card_id');
            $table->boolean('is_starred')->default(0);
            $table->text('note')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->primary(['user_id', 'card_id']);
            $table->index(['card_id'], 'ix_ucn_card');
            $table->index(['user_id', 'is_starred'], 'ix_ucn_starred');
            $table->comment('Thẻ đánh dấu sao và ghi chú riêng');
        });

        Schema::create('search_history', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('term', 120);
            $table->string('lang', 8)->nullable()->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('card_id')->nullable()->comment('thẻ đã mở từ kết quả');
            $table->unsignedInteger('created_at');
            $table->index(['card_id'], 'ix_search_card');
            $table->index(['user_id', 'created_at'], 'ix_search_user');
            $table->comment('Lịch sử tra từ, giữ 100 mục gần nhất');
        });

        Schema::create('grammar_chapters', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->smallIncrements('id');
            $table->string('lang', 8)->charset('ascii')->collation('ascii_bin');
            $table->string('code', 40)->charset('ascii')->collation('ascii_bin');
            $table->string('name_vi', 120);
            $table->string('name_en', 120)->nullable();
            $table->smallInteger('sort')->default(0);
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['created_by'], 'ix_gchap_created_by');
            $table->index(['deleted_by'], 'ix_gchap_deleted_by');
            $table->index(['updated_by'], 'ix_gchap_updated_by');
            $table->unique(['lang', 'code'], 'uq_grammar_chapters');
            $table->comment('Chương ngữ pháp');
        });

        Schema::create('grammar_lessons', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('lang', 8)->charset('ascii')->collation('ascii_bin');
            $table->unsignedSmallInteger('chapter_id')->nullable();
            $table->string('slug', 160)->charset('ascii')->collation('ascii_bin');
            $table->unsignedTinyInteger('level')->nullable()->comment('1–4 = A1–B2; NULL = chuyên đề');
            $table->string('title', 200);
            $table->string('summary', 500)->nullable();
            $table->json('blocks')->comment('khối: tiêu đề, đoạn, danh sách, bảng, công thức, mẹo, ghi chú, tóm tắt, ví dụ');
            $table->unsignedTinyInteger('access_tier')->default(2)->comment('0 khách, 1 Free, 2 Premium');
            $table->boolean('is_public')->default(0)->comment('1 = hiện công khai cho SEO');
            $table->string('status', 20)->default('published')->charset('ascii')->collation('ascii_bin');
            $table->unsignedSmallInteger('source_id')->nullable();
            $table->integer('sort')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('published_at')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['lang', 'level', 'chapter_id', 'sort'], 'ix_glessons_browse');
            $table->index(['chapter_id'], 'ix_glessons_chapter');
            $table->index(['created_by'], 'ix_glessons_created_by');
            $table->index(['deleted_by'], 'ix_glessons_deleted_by');
            $table->index(['source_id'], 'ix_glessons_source');
            $table->index(['updated_by'], 'ix_glessons_updated_by');
            $table->unique(['lang', 'slug'], 'uq_grammar_lessons_slug');
            $table->comment('Bài ngữ pháp');
        });

        Schema::create('exercise_sets', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('lang', 8)->charset('ascii')->collation('ascii_bin');
            $table->string('slug', 160)->charset('ascii')->collation('ascii_bin');
            $table->string('title', 200);
            $table->string('section', 20)->charset('ascii')->collation('ascii_bin')->comment('level (theo cấp), review (Ôn tập), plural (Số nhiều)');
            $table->unsignedTinyInteger('level')->nullable();
            $table->unsignedInteger('grammar_lesson_id')->nullable();
            $table->unsignedInteger('topic_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('item_count')->default(0);
            $table->unsignedTinyInteger('access_tier')->default(2);
            $table->string('status', 20)->default('published')->charset('ascii')->collation('ascii_bin');
            $table->unsignedSmallInteger('source_id')->nullable();
            $table->integer('sort')->default(0);
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['lang', 'section', 'level', 'sort'], 'ix_exsets_browse');
            $table->index(['created_by'], 'ix_exsets_created_by');
            $table->index(['deleted_by'], 'ix_exsets_deleted_by');
            $table->index(['grammar_lesson_id'], 'ix_exsets_lesson');
            $table->index(['source_id'], 'ix_exsets_source');
            $table->index(['topic_id'], 'ix_exsets_topic');
            $table->index(['updated_by'], 'ix_exsets_updated_by');
            $table->unique(['lang', 'slug'], 'uq_exercise_sets_slug');
            $table->comment('Bộ bài tập');
        });

        Schema::create('exercises', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->unsignedInteger('set_id');
            $table->string('sig', 64)->charset('ascii')->collation('ascii_bin')->comment('khoá ổn định, giữ từ Lernbuch');
            $table->string('kind', 10)->charset('ascii')->collation('ascii_bin')->comment('fill, mc, order, fix');
            $table->text('prompt');
            $table->json('answers')->comment('các đáp án được chấp nhận');
            $table->json('choices')->nullable()->comment('lựa chọn (mc) hoặc mảnh câu (order)');
            $table->string('hint', 500)->nullable();
            $table->text('explanation')->nullable();
            $table->json('translations')->nullable();
            $table->integer('sort')->default(0);
            $table->string('status', 20)->default('published')->charset('ascii')->collation('ascii_bin');
            $table->unsignedSmallInteger('source_id')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['created_by'], 'ix_exercises_created_by');
            $table->index(['deleted_by'], 'ix_exercises_deleted_by');
            $table->index(['set_id', 'sort'], 'ix_exercises_set');
            $table->index(['source_id'], 'ix_exercises_source');
            $table->index(['updated_by'], 'ix_exercises_updated_by');
            $table->unique(['sig'], 'uq_exercises_sig');
            $table->comment('Câu bài tập: điền từ, trắc nghiệm, sắp xếp, sửa lỗi');
        });

        Schema::create('verbs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('lang', 8)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('card_id')->nullable()->comment('thẻ Từ vựng tương ứng');
            $table->string('infinitive', 80)->charset('utf8mb4')->collation('utf8mb4_bin');
            $table->unsignedTinyInteger('level')->nullable();
            $table->boolean('is_separable')->default(0);
            $table->string('prefix', 20)->nullable();
            $table->string('auxiliary', 10)->nullable()->comment('haben, sein');
            $table->boolean('is_irregular')->default(0);
            $table->string('form_du', 80)->nullable();
            $table->string('form_er', 80)->nullable();
            $table->string('preterite', 80)->nullable();
            $table->string('perfect', 120)->nullable();
            $table->json('meanings')->nullable();
            $table->json('conjugation')->nullable()->comment('bảng Präsens, Präteritum, Perfekt, Imperativ…');
            $table->unsignedTinyInteger('access_tier')->default(1);
            $table->string('status', 20)->default('published')->charset('ascii')->collation('ascii_bin');
            $table->unsignedSmallInteger('source_id')->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['card_id'], 'ix_verbs_card');
            $table->index(['created_by'], 'ix_verbs_created_by');
            $table->index(['deleted_by'], 'ix_verbs_deleted_by');
            $table->index(['lang', 'level'], 'ix_verbs_level');
            $table->index(['source_id'], 'ix_verbs_source');
            $table->index(['updated_by'], 'ix_verbs_updated_by');
            $table->unique(['lang', 'infinitive'], 'uq_verbs');
            $table->comment('Động từ và bảng chia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verbs');
        Schema::dropIfExists('exercises');
        Schema::dropIfExists('exercise_sets');
        Schema::dropIfExists('grammar_lessons');
        Schema::dropIfExists('grammar_chapters');
        Schema::dropIfExists('search_history');
        Schema::dropIfExists('user_card_notes');
        Schema::dropIfExists('deck_cards');
        Schema::dropIfExists('decks');
        Schema::dropIfExists('word_forms');
        Schema::dropIfExists('card_relations');
        Schema::dropIfExists('card_examples');
        Schema::dropIfExists('card_translations');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('content_sources');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('card_collections');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('languages');
    }
};
