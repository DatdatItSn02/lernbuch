<?php

namespace App\Support\Ai\BoNoi;

/** Một lần gọi AI. $khuon: JSON Schema của kết quả, hoặc null. $thamSo: chỉ AI giả đọc (để trả lời đúng từ đang hỏi). */
final readonly class YeuCauAi
{
    /**
     * @param  array<string, mixed>|null  $khuon
     * @param  array<string, string>  $thamSo
     */
    public function __construct(
        public string $loai,
        public string $model,
        public string $heThong,
        public string $nguoiDung,
        public int $maxTokens,
        public float $nhietDo,
        public ?array $khuon,
        public float $choGiay,
        public array $thamSo = [],
    ) {}

    /** Cùng yêu cầu, thời gian chờ khác (lần thử lại chỉ còn phần thời gian còn lại). */
    public function voiCho(float $choGiay): self
    {
        return new self($this->loai, $this->model, $this->heThong, $this->nguoiDung, $this->maxTokens, $this->nhietDo, $this->khuon, $choGiay, $this->thamSo);
    }
}
