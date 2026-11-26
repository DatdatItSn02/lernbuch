<?php

// Chuẩn bị đa ngôn ngữ (G1): bài ngữ pháp và bộ bài tập ghi rõ ngôn ngữ giải thích, để sau này có bản
// giải thích bằng tiếng Anh, tiếng Trung cùng slug với bản tiếng Việt. Bản hiện có đều giải thích bằng tiếng Việt.
// schema.sql đã sửa theo: cột base_lang ngay sau lang, khoá duy nhất và chỉ mục duyệt bài có thêm base_lang.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array{unique: string, browse: string, browse_cols: list<string>}> */
    private array $bang = [
        'grammar_lessons' => ['unique' => 'uq_grammar_lessons_slug', 'browse' => 'ix_glessons_browse', 'browse_cols' => ['level', 'chapter_id', 'sort']],
        'exercise_sets' => ['unique' => 'uq_exercise_sets_slug', 'browse' => 'ix_exsets_browse', 'browse_cols' => ['section', 'level', 'sort']],
    ];

    public function up(): void
    {
        foreach ($this->bang as $ten => $k) {
            Schema::table($ten, function (Blueprint $table) use ($k) {
                $table->string('base_lang', 8)->default('vi')->charset('ascii')->collation('ascii_bin')->comment('ngôn ngữ giải thích')->after('lang');
                $table->dropUnique($k['unique']);
                $table->dropIndex($k['browse']);
            });
            Schema::table($ten, function (Blueprint $table) use ($k) {
                $table->unique(['lang', 'base_lang', 'slug'], $k['unique']);
                $table->index(['lang', 'base_lang', ...$k['browse_cols']], $k['browse']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->bang as $ten => $k) {
            Schema::table($ten, function (Blueprint $table) use ($k) {
                $table->dropUnique($k['unique']);
                $table->dropIndex($k['browse']);
            });
            Schema::table($ten, function (Blueprint $table) use ($k) {
                $table->dropColumn('base_lang');
                $table->unique(['lang', 'slug'], $k['unique']);
                $table->index(['lang', ...$k['browse_cols']], $k['browse']);
            });
        }
    }
};
