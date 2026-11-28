<?php

namespace App\Support\Ai\BoNoi;

use Closure;
use Throwable;

/**
 * AI giả cho test tự động và máy dev: trả lời theo kịch bản xếp hàng, hết hàng thì trả mẫu đúng khuôn của loại.
 * Đăng ký là singleton trong container (mỗi test, mỗi request có một bản mới): lấy bằng app(BoNoiGia::class).
 * Không bao giờ chạy ở production.
 */
final class BoNoiGia implements BoNoiAi
{
    /** @var list<string|TraLoiAi|Throwable|Closure> */
    private array $hang = [];

    /** @var list<YeuCauAi> mọi yêu cầu đã nhận, theo thứ tự */
    public array $daNhan = [];

    /**
     * Xếp thêm các lần trả lời: chuỗi (chữ AI trả), TraLoiAi, Throwable (ném ra), hoặc Closure(YeuCauAi) trả một trong ba thứ đó.
     *
     * @param  list<string|TraLoiAi|Throwable|Closure>  $kichBan
     */
    public function kich(array $kichBan): self
    {
        array_push($this->hang, ...$kichBan);

        return $this;
    }

    public function datLai(): void
    {
        $this->hang = [];
        $this->daNhan = [];
    }

    public function goi(YeuCauAi $yc): TraLoiAi
    {
        if (app()->isProduction()) {
            throw new LoiBoNoi(LoiBoNoi::CAU_HINH, 'AI giả không chạy ở production');
        }

        $this->daNhan[] = $yc;
        $buoc = array_shift($this->hang);

        if ($buoc instanceof Closure) {
            $buoc = $buoc($yc);
        }

        if ($buoc instanceof Throwable) {
            throw $buoc;
        }

        if ($buoc instanceof TraLoiAi) {
            return $buoc;
        }

        return new TraLoiAi(is_string($buoc) ? $buoc : self::mau($yc), 1500, 500);
    }

    /** Câu trả lời mẫu đúng khuôn của loại, có chứa chữ đầu vào (từ, câu, chủ đề) để test nhận ra. */
    public static function mau(YeuCauAi $yc): string
    {
        $tu = $yc->thamSo['word'] ?? 'Wort';
        $chu = $yc->thamSo['text'] ?? 'Hallo';
        $vd = [['de' => "Ich lerne das Wort {$tu}.", 'vi' => "Tôi học từ {$tu}."]];
        $kq = match ($yc->loai) {
            'word' => [
                'meaning' => "Nghĩa của {$tu} (AI giả).",
                'usage' => "Cách dùng {$tu} trong câu hằng ngày.",
                'examples' => $vd,
                'common_mistakes' => ["Người Việt hay quên mạo từ khi dùng {$tu}."],
            ],
            'translate' => [
                'translation' => "Bản dịch (AI giả): {$chu}",
                'literal' => "Dịch sát chữ: {$chu}",
                'words' => [['de' => 'Hallo', 'vi' => 'xin chào', 'note' => '']],
                'grammar' => 'Động từ đứng ở vị trí thứ hai.',
                'natural' => '',
            ],
            'lookup' => [
                'lemma' => $chu,
                'pos' => 'Nomen',
                'gender' => 'n',
                'plural' => '',
                'forms' => [['label' => 'Genitiv', 'form' => "des {$chu}s"]],
                'meanings_vi' => ["Nghĩa của {$chu} (AI giả)"],
                'examples' => [['de' => "Das ist {$chu}.", 'vi' => "Đây là {$chu}."]],
                'collocations' => [],
                'level' => 'B1',
            ],
            'lesson' => [
                'title' => 'Bài học AI giả: '.($yc->thamSo['topic'] ?? 'Chủ đề'),
                'level' => $yc->thamSo['level'] ?? 'A1',
                'text' => [['de' => 'Ich trinke Kaffee.', 'vi' => 'Tôi uống cà phê.'], ['de' => 'Das Brot ist frisch.', 'vi' => 'Bánh mì còn mới.']],
                'new_words' => [['de' => 'der Kaffee', 'vi' => 'cà phê'], ['de' => 'das Brot', 'vi' => 'bánh mì']],
                'questions' => [['q' => 'Tôi uống gì?', 'choices' => ['Tee', 'Kaffee', 'Wasser'], 'answer' => 1]],
                'exercise' => [['de' => 'Ich ____ Kaffee.', 'answer' => 'trinke']],
            ],
            'chat' => [
                'reply_de' => 'Sehr gut! Und was möchten Sie trinken?',
                'reply_vi' => 'Rất tốt! Bạn muốn uống gì? (AI giả)',
                'corrections' => [['wrong' => 'Ich heißen', 'right' => 'Ich heiße', 'why' => 'Ngôi ich chia heiße.']],
                'next_hint' => 'Ich möchte einen Kaffee, bitte.',
            ],
            'grammar' => [
                'corrected' => "Bản đã sửa (AI giả): {$chu}",
                'errors' => [['wrong' => 'gegangt', 'right' => 'gegangen', 'why' => 'gehen là động từ bất quy tắc.']],
                'natural' => '',
                'level' => 'A2',
            ],
            'sentence' => [
                'verdict' => 'gan_dung',
                'word_ok' => true,
                'corrected' => "Câu đã sửa (AI giả): {$chu}",
                'errors' => [['wrong' => 'ein Termin', 'right' => 'einen Termin', 'why' => 'Akkusativ giống đực.']],
                'explain' => "Câu có dùng {$tu}, còn một lỗi nhỏ.",
                'use' => '',
                'tip' => '',
            ],
            'answer' => ['ok' => true, 'why' => "Câu \"{$chu}\" cũng đúng (AI giả).", 'better' => ''],
            'doc' => [
                'summary' => 'Tóm tắt đoạn ngữ pháp (AI giả).',
                'points' => ['Động từ chia đứng ở vị trí thứ hai.'],
                'examples' => [['de' => 'Heute lerne ich Deutsch.', 'vi' => 'Hôm nay tôi học tiếng Đức.']],
            ],
            'ask' => ['answer' => 'Câu trả lời mẫu của AI giả.', 'examples' => $vd],
            default => ['answer' => 'Câu trả lời mẫu của AI giả.'],
        };

        return (string) json_encode($kq, JSON_UNESCAPED_UNICODE);
    }
}
