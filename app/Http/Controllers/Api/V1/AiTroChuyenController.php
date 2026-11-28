<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Ai\ChuNhap;
use App\Support\Ai\TroChuyenAi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Trò chuyện AI. Đọc, xoá cuộc của mình không qua cổng AI (người đã rút đồng ý vẫn xem, xoá được); mở cuộc, gửi tin thì qua. */
class AiTroChuyenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => TroChuyenAi::danhSach($this->nguoiDung($request))]);
    }

    public function store(Request $request): JsonResponse
    {
        $hopLe = $request->validate(['scenario' => ['required', 'string', Rule::in(array_keys(TroChuyenAi::TINH_HUONG))]]);

        return response()->json(['data' => TroChuyenAi::tao($this->nguoiDung($request), (string) $hopLe['scenario'])], 201);
    }

    public function show(Request $request, string $ma): JsonResponse
    {
        return response()->json(['data' => TroChuyenAi::xem($this->nguoiDung($request), $ma)]);
    }

    public function gui(Request $request, string $ma): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $chu = ChuNhap::sach((string) $request->validate(['text' => ChuNhap::luat(300)])['text']);

        return response()->json(TroChuyenAi::gui($user, $ma, $chu));
    }

    public function destroy(Request $request, string $ma): Response
    {
        TroChuyenAi::xoa($this->nguoiDung($request), $ma);

        return response()->noContent();
    }
}
