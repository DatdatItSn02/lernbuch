<?php

namespace App\Support\Ai;

use RuntimeException;

/** Một lượt hỏi AI không qua được một chặng. LoiApi đổi thành {"code", "message", ...phần thêm} với mã HTTP tương ứng. */
final class TuChoiAi extends RuntimeException
{
    /** @param array<string, mixed> $them */
    public function __construct(public readonly string $ma, public readonly int $trangThai, public readonly array $them = [])
    {
        parent::__construct($ma);
    }
}
