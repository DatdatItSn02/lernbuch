<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('ghi rồi đọc chữ có dấu qua database giữ nguyên', function (string $text) {
    $user = User::factory()->create(['name' => $text]);

    expect($user->fresh()?->name)->toBe($text);
})->with(['Brötchen', 'Straße', 'tiếng Việt', 'ẞ ắ ặ ộ ữ đ']);

it('kết nối sai bảng mã thì chữ có dấu đọc ra bị hỏng, nên test bắt được', function () {
    $user = User::factory()->create(['name' => 'tiếng Việt']);

    DB::statement('SET NAMES latin1');
    $broken = DB::table('users')->where('id', $user->id)->value('name');
    DB::statement('SET NAMES utf8mb4');

    expect($broken)->not->toBe('tiếng Việt')
        ->and(DB::table('users')->where('id', $user->id)->value('name'))->toBe('tiếng Việt');
});

it('kết nối database dùng utf8mb4', function () {
    expect(DB::connection()->getConfig('charset'))->toBe('utf8mb4');
});
