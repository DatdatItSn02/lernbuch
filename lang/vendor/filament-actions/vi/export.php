<?php

// Bản dịch tiếng Việt còn thiếu của Filament 5.12 (gói chưa dịch các khoá này): Laravel gộp file này với bản dịch của gói.
// tests/Feature/BanDichFilamentTest.php kiểm mọi khoá tiếng Anh của Filament đều có tiếng Việt.

return [
    'modal' => [
        'form' => [
            'columns' => [
                'actions' => [
                    'select_all' => [
                        'label' => 'Chọn tất cả',
                    ],
                    'deselect_all' => [
                        'label' => 'Bỏ chọn tất cả',
                    ],
                ],
            ],
        ],
    ],
    'notifications' => [
        'no_columns' => [
            'title' => 'Chưa chọn cột nào',
            'body' => 'Hãy chọn ít nhất một cột để xuất.',
        ],
    ],
];
