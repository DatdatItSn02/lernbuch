<?php

use App\Http\Controllers\Api\V1\CaiDatController;
use App\Http\Controllers\Api\V1\PhienController;
use App\Http\Controllers\Api\V1\ToiController;
use Illuminate\Support\Facades\Route;

// API cho app học. Cần đăng nhập (cookie phiên của app cùng tên miền, hoặc token của app di động sau này)
// và email đã xác minh. Người dùng ra ngoài bằng mã ULID (public_id), không lộ id số.
Route::prefix('v1')->middleware(['auth:sanctum', 'da-xac-minh', 'ngon-ngu'])->group(function () {
    Route::get('toi', [ToiController::class, 'show']);
    Route::put('toi/ho-so', [ToiController::class, 'capNhatHoSo']);

    Route::get('toi/cai-dat', [CaiDatController::class, 'show']);
    Route::put('toi/cai-dat', [CaiDatController::class, 'update']);

    Route::get('toi/phien', [PhienController::class, 'index']);
    // Cần mật khẩu: chặn đoán mật khẩu bằng giới hạn riêng, chặt hơn giới hạn chung của API.
    Route::post('toi/phien/dang-xuat-may-khac', [PhienController::class, 'dangXuatMayKhac'])->middleware('throttle:6,1');
});
