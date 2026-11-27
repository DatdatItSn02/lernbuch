<?php

// Bản dịch tiếng Việt còn thiếu của Filament 5.12 (gói chưa dịch các khoá này): Laravel gộp file này với bản dịch của gói.
// tests/Feature/BanDichFilamentTest.php kiểm mọi khoá tiếng Anh của Filament đều có tiếng Việt.

return [
    'column_manager' => [
        'position' => 'Vị trí :position trên :count.',
        'position_in_group' => 'Vị trí :position trên :count trong :group.',
        'reordered' => 'Đã chuyển :column tới vị trí :position trên :count.',
        'reordered_deferred' => 'Đã chuyển :column tới vị trí :position trên :count. Bấm áp dụng để lưu thay đổi.',
        'reordered_in_group' => 'Đã chuyển :column tới vị trí :position trên :count trong :group.',
        'reordered_in_group_deferred' => 'Đã chuyển :column tới vị trí :position trên :count trong :group. Bấm áp dụng để lưu thay đổi.',
        'actions' => [
            'move_up' => [
                'label' => 'Chuyển :column lên',
            ],
            'move_down' => [
                'label' => 'Chuyển :column xuống',
            ],
            'reorder' => [
                'label' => 'Sắp xếp lại cột',
            ],
        ],
    ],
    'columns' => [
        'editable' => [
            'saving' => 'Đang lưu thay đổi…',
            'saved' => 'Đã lưu thay đổi.',
            'failure' => 'Chưa xác nhận được việc lưu. Tải lại trang rồi mới thử lại.',
            'unavailable' => 'Mục này không còn sửa được.',
        ],
        'icon' => [
            'boolean' => [
                'true' => 'Có',
                'false' => 'Không',
            ],
        ],
        'select' => [
            'loading_message' => 'Đang tải…',
            'no_options_message' => 'Không có lựa chọn nào.',
            'no_search_results_message' => 'Không có lựa chọn nào khớp với từ tìm.',
            'placeholder' => 'Chọn một mục',
            'searching_message' => 'Đang tìm…',
            'search_prompt' => 'Gõ để tìm',
        ],
    ],
    'actions' => [
        'reorder_record' => [
            'label' => 'Sắp xếp lại mục :key',
        ],
        'move_record_up' => [
            'label' => 'Chuyển :label lên',
        ],
        'move_record_down' => [
            'label' => 'Chuyển :label xuống',
        ],
        'toggle_record_content' => [
            'label' => 'Mở hoặc thu gọn mục :key',
        ],
    ],
    'loading' => 'Đang tải…',
    'reordering' => [
        'position' => ':label, vị trí :position trên :count.',
        'moved' => 'Đã chuyển :label tới vị trí :position trên :count.',
    ],
    'result_count' => '{0} Không có kết quả|[1,*] :count kết quả',
];
