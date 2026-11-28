<?php

namespace App\Support\Ai\BoNoi;

/** Chữ AI trả về (chưa kiểm khuôn) và số token để tính chi phí. */
final readonly class TraLoiAi
{
    public function __construct(
        public string $chu,
        public int $tokenVao,
        public int $tokenRa,
        public int $tokenCache = 0,
    ) {}
}
