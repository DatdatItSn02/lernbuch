<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// App học React: mọi đường dẫn dưới /app trả cùng một khung, React lo phần còn lại.
Route::view('/app/{path?}', 'app')->where('path', '.*')->name('app');
