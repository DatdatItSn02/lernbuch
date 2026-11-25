<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/kiem-chu', 'kiem-chu')->name('kiem-chu');

// App học React: mọi đường dẫn dưới /app trả cùng một khung, React lo phần còn lại.
Route::view('/app/{path?}', 'app')->where('path', '.*')->name('app');
