<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    | Mã bản đang chạy: file RELEASE do workflow deploy ghi vào thư mục bản phát hành;
    | máy dev dùng APP_RELEASE trong .env (mặc định "dev").
    */

    'release' => env('APP_RELEASE') ?? (is_file(base_path('RELEASE')) ? trim((string) file_get_contents(base_path('RELEASE'))) : 'dev'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    // Tiếng Việt là ngôn ngữ gốc của Lernbuch: chữ thiếu bản dịch ở ngôn ngữ khác thì hiện tiếng Việt, không hiện mã khoá.
    // Ngôn ngữ dự phòng cố định (không lấy từ .env) để máy nào cũng giống nhau.
    'locale' => env('APP_LOCALE', 'vi'),

    'fallback_locale' => 'vi',

    // Ngôn ngữ giao diện đã có bản dịch (lang/<mã>/, lang/<mã>.json và từ điển của app học).
    // Người dùng chọn ngôn ngữ khác danh sách này thì vẫn thấy tiếng Việt. Thêm 'en', 'zh' khi đã dịch xong.
    'ngon_ngu_giao_dien' => ['vi'],

    // Số lần gọi API mỗi phút cho một người (chưa đăng nhập thì theo IP). Test trình duyệt cho nhiều test chạy song song
    // trên cùng một tài khoản thử nên đặt cao hơn qua API_MOI_PHUT.
    'api_moi_phut' => (int) env('API_MOI_PHUT', 120),

    // Chế độ xem thử (tạm thời): app học mở không cần đăng nhập, các trang đăng nhập, đăng ký tắt. Xem App\Http\Middleware\KhachXemThu.
    'xem_thu' => (bool) env('XEM_THU', false),

    // Audio đọc từ tiếng Đức tạo sẵn bằng Piper (workflow audio, tools/audio): giọng đang dùng và địa chỉ thư mục audio.
    // Trên alwaysdata thư mục audio dùng chung nối vào public/audio khi deploy; chuyển sang kho file khác (R2) chỉ cần
    // chép file và đổi AUDIO_URL.
    'audio' => [
        'giong' => env('AUDIO_GIONG', 'de_DE-thorsten-medium'),
        'url' => env('AUDIO_URL', '/audio'),
    ],

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache", "array"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
