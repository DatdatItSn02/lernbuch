<?php

// Gọi AI trên máy chủ: bộ nối, khoá (chỉ nằm trong .env, không bao giờ ra trình duyệt), thời gian chờ.
// Cài đặt sửa trong trang quản trị (bật tắt, model, hạn mức, trần) nằm ở bảng settings, đọc bằng App\Support\Ai\CaiDatAi.
return [
    // fake | gemini | openai_compatible; trống thì theo ô Nhà cung cấp trong trang Cài đặt AI
    'bo_noi' => env('AI_DRIVER'),
    // Giây chờ tối đa cho cả lượt, tính cả lần thử lại
    'cho_toi_da' => 20,
    'gemini' => [
        'khoa' => env('GEMINI_API_KEY'),
        'goc' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],
    'openai' => [
        'khoa' => env('AI_OPENAI_API_KEY'),
        'goc' => env('AI_OPENAI_BASE_URL'),
    ],
];
