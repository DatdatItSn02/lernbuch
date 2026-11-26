<?php

// Câu báo lỗi kiểm dữ liệu. Quy tắc chưa dịch ở đây thì Laravel dùng bản tiếng Anh (APP_FALLBACK_LOCALE).

return [
    'accepted' => 'Cần đồng ý :attribute.',
    'array' => ':Attribute không đúng dạng.',
    'boolean' => ':Attribute chỉ nhận đúng hoặc sai.',
    'confirmed' => 'Hai lần nhập :attribute không khớp.',
    'current_password' => 'Mật khẩu không đúng.',
    'date' => ':Attribute không phải ngày hợp lệ.',
    'email' => ':Attribute không đúng dạng email.',
    'exists' => ':Attribute không có trong danh sách.',
    'in' => ':Attribute không hợp lệ.',
    'integer' => ':Attribute phải là số nguyên.',
    'json' => ':Attribute không đúng dạng JSON.',
    'max' => [
        'array' => ':Attribute có nhiều nhất :max mục.',
        'numeric' => ':Attribute không được lớn hơn :max.',
        'string' => ':Attribute dài nhất :max ký tự.',
    ],
    'min' => [
        'array' => ':Attribute cần ít nhất :min mục.',
        'numeric' => ':Attribute ít nhất là :min.',
        'string' => ':Attribute cần ít nhất :min ký tự.',
    ],
    'numeric' => ':Attribute phải là số.',
    'password' => [
        'letters' => ':Attribute cần có ít nhất một chữ cái.',
        'mixed' => ':Attribute cần có cả chữ hoa và chữ thường.',
        'numbers' => ':Attribute cần có ít nhất một chữ số.',
        'symbols' => ':Attribute cần có ít nhất một ký hiệu.',
        'uncompromised' => ':Attribute này đã bị lộ trong một vụ rò rỉ dữ liệu. Chọn mật khẩu khác.',
    ],
    'required' => 'Cần nhập :attribute.',
    'string' => ':Attribute phải là chữ.',
    'timezone' => ':Attribute không phải múi giờ hợp lệ.',
    'unique' => ':Attribute này đã được dùng.',
    'url' => ':Attribute không đúng dạng địa chỉ web.',

    'attributes' => [
        'name' => 'tên',
        'email' => 'email',
        'password' => 'mật khẩu',
        'password_confirmation' => 'mật khẩu nhập lại',
        'current_password' => 'mật khẩu hiện tại',
        'token' => 'mã đặt lại',
    ],
];
