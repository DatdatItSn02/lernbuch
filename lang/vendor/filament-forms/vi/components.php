<?php

// Bản dịch tiếng Việt còn thiếu của Filament 5.12 (gói chưa dịch các khoá này): Laravel gộp file này với bản dịch của gói.
// tests/Feature/BanDichFilamentTest.php kiểm mọi khoá tiếng Anh của Filament đều có tiếng Việt.

return [
    'builder' => [
        'reordering' => [
            'item' => 'Khối',
            'position' => ':label, vị trí :position trên :count',
            'moved' => 'Đã chuyển :label tới vị trí :position trên :count',
        ],
        'block_picker' => [
            'no_search_results_message' => 'Không có khối nào khớp với từ tìm.',
            'search_prompt' => 'Tìm khối',
        ],
    ],
    'checkbox_list' => [
        'required_description' => 'Chọn ít nhất một mục.',
    ],
    'color_picker' => [
        'panel_label' => 'Bảng chọn màu',
    ],
    'date_time_picker' => [
        'month_select' => [
            'label' => 'Tháng',
        ],
        'year_input' => [
            'label' => 'Năm',
        ],
        'hour_input' => [
            'label' => 'Giờ',
        ],
        'minute_input' => [
            'label' => 'Phút',
        ],
        'second_input' => [
            'label' => 'Giây',
        ],
    ],
    'file_upload' => [
        'placeholder' => 'Chưa có file nào',
        'actions' => [
            'download' => [
                'label' => 'Tải xuống',
            ],
            'open' => [
                'label' => 'Mở ở thẻ mới',
            ],
        ],
        'editor' => [
            'label' => 'Trình sửa ảnh',
        ],
    ],
    'key_value' => [
        'actions' => [
            'move_down' => [
                'label' => 'Chuyển xuống',
            ],
            'move_up' => [
                'label' => 'Chuyển lên',
            ],
        ],
        'columns' => [
            'actions' => [
                'label' => 'Thao tác',
            ],
            'reorder' => [
                'label' => 'Sắp xếp lại',
            ],
        ],
        'reordering' => [
            'row' => 'Dòng',
            'position' => ':label, vị trí :position trên :count',
            'moved' => 'Đã chuyển :label tới vị trí :position trên :count',
        ],
    ],
    'repeater' => [
        'reordering' => [
            'item' => 'Mục',
            'position' => ':label, vị trí :position trên :count',
            'moved' => 'Đã chuyển :label tới vị trí :position trên :count',
        ],
        'columns' => [
            'actions' => [
                'label' => 'Thao tác',
            ],
            'reorder' => [
                'label' => 'Sắp xếp lại',
            ],
        ],
    ],
    'rich_editor' => [
        'actions' => [
            'close_panel' => [
                'label' => 'Đóng bảng',
            ],
        ],
        'custom_blocks' => [
            'actions' => [
                'delete' => [
                    'label' => 'Xoá khối',
                ],
                'edit' => [
                    'label' => 'Sửa khối',
                ],
            ],
            'no_search_results_message' => 'Không có khối nào khớp với từ tìm.',
            'search_label' => 'Tìm khối',
            'search_prompt' => 'Tìm khối',
        ],
        'toolbar' => [
            'label' => 'Thanh công cụ soạn thảo',
        ],
    ],
    'select' => [
        'actions' => [
            'clear' => [
                'label' => 'Bỏ chọn',
            ],
            'remove_option' => [
                'label' => 'Bỏ :label',
            ],
        ],
        'error_message' => 'Không tải được các lựa chọn. Hãy thử lại.',
        'required_description' => 'Chọn một mục.',
        'search_label' => 'Tìm',
    ],
    'tags_input' => [
        'tag_added' => 'Đã thêm: :tag',
        'tag_removed' => 'Đã bỏ: :tag',
    ],
    'toggle_buttons' => [
        'required_description' => 'Chọn ít nhất một mục.',
    ],
];
