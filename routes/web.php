<?php

use App\Http\Controllers\ServiceWorkerController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/kiem-chu', 'kiem-chu')->name('kiem-chu');

// App học React: mọi đường dẫn dưới /app trả cùng một khung, React lo phần còn lại.
// Cần đăng nhập và đã xác minh email; chưa thì về /dang-nhap hoặc /xac-minh-email (Fortify).
// Chế độ xem thử (XEM_THU=true): người chưa đăng nhập vào thẳng bằng tài khoản khách dùng chung.
Route::view('/app/{path?}', 'app')->where('path', '.*')->middleware(['khach-xem-thu', 'auth', 'con-hoat-dong', 'verified'])->name('app');

// Service worker (PWA): cài được, mở app và học tiếp khi mất mạng. Không cần đăng nhập (trình duyệt tự hỏi bản mới).
Route::get('/sw.js', ServiceWorkerController::class)->name('sw');
