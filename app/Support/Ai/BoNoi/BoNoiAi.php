<?php

namespace App\Support\Ai\BoNoi;

/** Một nhà cung cấp AI. Lỗi thì ném LoiBoNoi; thử lại, hạn mức, kiểm khuôn do App\Support\Ai\HoiAi lo. */
interface BoNoiAi
{
    public function goi(YeuCauAi $yc): TraLoiAi;
}
