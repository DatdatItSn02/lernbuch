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

    /** Câu trả lời mẫu đúng khuôn của loại, có chứa từ đang hỏi. */
    public static function mau(YeuCauAi $yc): string
    {
        $tu = $yc->thamSo['word'] ?? 'Wort';
        $kq = match ($yc->loai) {
            'word' => [
                'meaning' => "Nghĩa của {$tu} (AI giả).",
                'usage' => "Cách dùng {$tu} trong câu hằng ngày.",
                'examples' => [['de' => "Ich lerne das Wort {$tu}.", 'vi' => "Tôi học từ {$tu}."]],
                'common_mistakes' => ["Người Việt hay quên mạo từ khi dùng {$tu}."],
            ],
            default => ['answer' => 'Câu trả lời mẫu của AI giả.'],
        };

        return (string) json_encode($kq, JSON_UNESCAPED_UNICODE);
    }
}
