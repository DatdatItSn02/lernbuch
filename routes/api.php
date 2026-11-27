<?php

use App\Http\Controllers\Api\V1\CaiDatController;
use App\Http\Controllers\Api\V1\HocController;
use App\Http\Controllers\Api\V1\NoiDungController;
use App\Http\Controllers\Api\V1\PhienController;
use App\Http\Controllers\Api\V1\SoTayController;
use App\Http\Controllers\Api\V1\ToiController;
use App\Http\Controllers\Api\V1\TraTuController;
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

    // Nội dung học (chỉ đọc)
    Route::get('bo-the', [NoiDungController::class, 'boThe']);
    Route::get('chu-de', [NoiDungController::class, 'chuDe']);
    Route::get('chu-de/{slug}', [NoiDungController::class, 'moChuDe'])->where('slug', '[a-z0-9-]{1,160}');
    Route::get('the/{id}', [NoiDungController::class, 'the'])->whereNumber('id');

    // Sổ tay: sao và ghi chú riêng, luôn của người đang đăng nhập
    Route::get('so-tay', [SoTayController::class, 'index']);
    Route::put('the/{id}/so-tay', [SoTayController::class, 'update'])->whereNumber('id');

    // Học: Hôm nay, phiên học, lượt chấm gửi theo lô (hàng chờ trên máy gửi lại khi có mạng)
    Route::get('hom-nay', [HocController::class, 'homNay']);
    Route::post('hoc/phien', [HocController::class, 'taoPhien']);
    Route::get('hoc/con-lai', [HocController::class, 'conLai']);
    Route::post('hoc/luot-cham', [HocController::class, 'luotCham']);

    // Tra từ
    Route::get('tra-tu', [TraTuController::class, 'tim']);
    Route::get('tra-chu', [TraTuController::class, 'traChu']);
    Route::get('tra-tu/lich-su', [TraTuController::class, 'lichSu']);
    Route::post('tra-tu/lich-su', [TraTuController::class, 'ghiLichSu']);
    Route::delete('tra-tu/lich-su', [TraTuController::class, 'xoaHetLichSu']);
    Route::delete('tra-tu/lich-su/{id}', [TraTuController::class, 'xoaLichSu'])->whereNumber('id');
});
