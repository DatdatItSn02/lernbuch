<?php

namespace App\Support\Ai;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Chữ người học gửi AI phải có ít nhất một chữ cái: toàn số, dấu câu, ký hiệu, emoji thì không gửi. */
final class CoChu implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match('/\p{L}/u', $value) !== 1) {
            $fail(__('api.ai_can_co_chu'));
        }
    }
}
