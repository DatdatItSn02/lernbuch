<?php

use App\Http\Controllers\Api\V1\AiBaiHocController;
use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AiTroChuyenController;
use App\Http\Controllers\Api\V1\BaiTapController;
use App\Http\Controllers\Api\V1\CaiDatController;
use App\Http\Controllers\Api\V1\DongTuController;
use App\Http\Controllers\Api\V1\HocController;
use App\Http\Controllers\Api\V1\NguPhapController;
use App\Http\Controllers\Api\V1\NoiDungController;
use App\Http\Controllers\Api\V1\PhienController;
use App\Http\Controllers\Api\V1\SoTayController;
use App\Http\Controllers\Api\V1\ToiController;
use App\Http\Controllers\Api\V1\TraTuController;
use Illuminate\Support\Facades\Route;

// API cho app học. Cần đăng nhập (cookie phiên của app cùng tên miền, hoặc token của app di động sau này)
// và email đã xác minh; tài khoản bị khoá thì bị đăng xuất. Người dùng ra ngoài bằng mã ULID (public_id), không lộ id số.
Route::prefix('v1')->middleware(['khach-xem-thu', 'auth:sanctum', 'con-hoat-dong', 'da-xac-minh', 'ngon-ngu'])->group(function () {
    Route::get('toi', [ToiController::class, 'show']);
    Route::put('toi/ho-so', [ToiController::class, 'capNhatHoSo']);
    // AI: khai ngày sinh (một lần), đồng ý hay rút đồng ý gửi nội dung cho nhà cung cấp AI
    Route::put('toi/ngay-sinh', [ToiController::class, 'ngaySinh']);
    Route::put('toi/dong-y-ai', [ToiController::class, 'dongYAi']);

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
    // Tải một chủ đề để học khi mất mạng (app lưu trên máy)
    Route::get('hoc/tai/{slug}', [HocController::class, 'taiChuDe'])->where('slug', '[a-z0-9-]{1,160}');
    Route::post('hoc/luot-cham', [HocController::class, 'luotCham']);

    // Luyện tập: ngữ pháp, bài tập (kết quả máy chủ chấm lại, chống gửi trùng theo mã), Điền câu, động từ
    Route::get('ngu-phap', [NguPhapController::class, 'index']);
    Route::get('ngu-phap/{slug}', [NguPhapController::class, 'show'])->where('slug', '[A-Za-z0-9-]{1,160}');
    Route::get('bai-tap', [BaiTapController::class, 'index']);
    Route::get('bai-tap/phien', [BaiTapController::class, 'phien']);
    Route::post('bai-tap/ket-qua', [BaiTapController::class, 'ketQua']);
    Route::get('bai-tap/dien-cau', [BaiTapController::class, 'dienCau']);
    Route::get('bai-tap/viet-cau', [BaiTapController::class, 'vietCau']);
    Route::get('dong-tu', [DongTuController::class, 'index']);
    Route::get('dong-tu/luyen', [DongTuController::class, 'luyen']);

    // Tra từ
    Route::get('tra-tu', [TraTuController::class, 'tim']);
    Route::get('tra-chu', [TraTuController::class, 'traChu']);
    Route::get('tra-tu/lich-su', [TraTuController::class, 'lichSu']);
    Route::post('tra-tu/lich-su', [TraTuController::class, 'ghiLichSu']);
    Route::delete('tra-tu/lich-su', [TraTuController::class, 'xoaHetLichSu']);
    Route::delete('tra-tu/lich-su/{id}', [TraTuController::class, 'xoaLichSu'])->whereNumber('id');

    // AI: mọi lượt đi qua tám chặng (App\Support\Ai\HoiAi); khoá AI chỉ nằm trên máy chủ
    Route::post('ai/giai-thich-tu', [AiController::class, 'giaiThichTu']);
    Route::post('ai/dich', [AiController::class, 'dich']);
    Route::post('ai/sua-ngu-phap', [AiController::class, 'suaNguPhap']);
    Route::post('ai/tra-tu', [AiController::class, 'traTu']);
    Route::post('ai/ngu-phap/giai-thich', [AiController::class, 'giaiThichNguPhap']);
    Route::post('ai/ngu-phap/hoi', [AiController::class, 'hoiNguPhap']);
    Route::post('ai/kiem-dap-an', [AiController::class, 'kiemDapAn']);
    Route::post('ai/viet-cau', [AiController::class, 'vietCau']);
    Route::get('ai/tro-chuyen', [AiTroChuyenController::class, 'index']);
    Route::post('ai/tro-chuyen', [AiTroChuyenController::class, 'store']);
    Route::get('ai/tro-chuyen/{ma}', [AiTroChuyenController::class, 'show'])->where('ma', '[0-9A-HJKMNP-TV-Z]{26}');
    Route::post('ai/tro-chuyen/{ma}/tin', [AiTroChuyenController::class, 'gui'])->where('ma', '[0-9A-HJKMNP-TV-Z]{26}');
    Route::delete('ai/tro-chuyen/{ma}', [AiTroChuyenController::class, 'destroy'])->where('ma', '[0-9A-HJKMNP-TV-Z]{26}');
    Route::get('ai/bai-hoc', [AiBaiHocController::class, 'index']);
    Route::post('ai/bai-hoc', [AiBaiHocController::class, 'store']);
    Route::get('ai/bai-hoc/{id}', [AiBaiHocController::class, 'show'])->whereNumber('id');
    Route::put('ai/bai-hoc/{id}/ket-qua', [AiBaiHocController::class, 'ketQua'])->whereNumber('id');
    Route::delete('ai/bai-hoc/{id}', [AiBaiHocController::class, 'destroy'])->whereNumber('id');
});
