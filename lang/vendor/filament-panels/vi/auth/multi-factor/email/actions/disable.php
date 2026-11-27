<?php

// Bản dịch tiếng Việt còn thiếu của Filament 5.12 (gói chưa dịch các khoá này): Laravel gộp file này với bản dịch của gói.
// tests/Feature/BanDichFilamentTest.php kiểm mọi khoá tiếng Anh của Filament đều có tiếng Việt.

return [
    'modal' => [
        'form' => [
            'code' => [
                'actions' => [
                    'resend' => [
                        'notifications' => [
                            'throttled' => [
                                'title' => 'Gửi lại quá nhiều lần. Hãy đợi một lúc rồi mới xin mã khác.',
                            ],
                        ],
                    ],
                ],
                'messages' => [
                    'rate_limited' => 'Thử quá nhiều lần. Hãy thử lại sau.',
                ],
            ],
        ],
    ],
];
