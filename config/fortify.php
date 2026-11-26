<?php

use Laravel\Fortify\Features;

// Fortify lo đăng ký, đăng nhập, xác minh email, quên mật khẩu, đổi mật khẩu cho người học.
// Trang là Blade (resources/views/tai-khoan), đường dẫn tiếng Việt. Trang quản trị /admin đăng nhập riêng qua Filament.
// Khoá không có ở đây lấy theo mặc định của Fortify (vendor/laravel/fortify/config/fortify.php).

return [
    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/app/hom-nay',
    'prefix' => '',
    'domain' => null,

    // login null: Fortify tự đếm lần đăng nhập sai (5 lần mỗi phút theo email và IP),
    // quá thì báo lỗi ngay trên form và phát sự kiện Lockout. Gửi lại email xác minh: 6 lần mỗi phút.
    'limiters' => [
        'login' => null,
        'verification' => '6,1',
    ],

    'paths' => [
        'login' => 'dang-nhap',
        'logout' => 'dang-xuat',
        'password' => [
            'request' => 'quen-mat-khau',
            'email' => 'quen-mat-khau',
            'reset' => 'dat-lai-mat-khau/{token}',
            'update' => 'dat-lai-mat-khau',
            'confirm' => 'xac-nhan-mat-khau',
            'confirmation' => 'xac-nhan-mat-khau/trang-thai',
        ],
        'register' => 'dang-ky',
        'verification' => [
            'notice' => 'xac-minh-email',
            'verify' => 'xac-minh-email/{id}/{hash}',
            'send' => 'xac-minh-email/gui-lai',
        ],
        'user-profile-information' => [
            'update' => 'tai-khoan/thong-tin',
        ],
        'user-password' => [
            'update' => 'tai-khoan/mat-khau',
        ],
    ],

    'redirects' => [
        'login' => null,
        'logout' => '/',
        'password-confirmation' => null,
        'register' => null,
        'email-verification' => null,
        'password-reset' => null,
    ],

    'views' => true,

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
    ],
];
