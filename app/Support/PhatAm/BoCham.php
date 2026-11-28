<?php

namespace App\Support\PhatAm;

/** Bộ chấm phát âm: nhận file WAV (16 kHz, một kênh) và câu mẫu tiếng Đức, trả kết quả. Không ném lỗi: lỗi nằm trong KetQuaCham. */
interface BoCham
{
    public function cham(string $wav, string $cauMau): KetQuaCham;
}
