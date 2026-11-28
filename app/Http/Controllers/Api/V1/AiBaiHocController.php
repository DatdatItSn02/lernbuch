<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Ai\BaiHocAi;
use App\Support\Ai\ChuNhap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Bài học AI: soạn (qua tám chặng), xem lại, ghi điểm, xoá. Xem, ghi điểm, xoá bài của mình không qua cổng AI. */
class AiBaiHocController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => BaiHocAi::danhSach($this->nguoiDung($request))]);
    }

    public function store(Request $request): JsonResponse
    {
        $hopLe = $request->validate([
            'topic' => ChuNhap::luat(60),
            'level' => ['required', 'string', Rule::in(BaiHocAi::CAP)],
            'words' => ['sometimes', 'array', 'max:'.BaiHocAi::TOI_DA_TU],
            'words.*' => ['required', 'string', 'max:40', 'distinct', 'regex:/^[\p{L}\p{M}][\p{L}\p{M} \'\-]*$/u'],
        ]);

        /** @var list<string> $tu */
        $tu = array_values($hopLe['words'] ?? []);

        return response()->json(BaiHocAi::soan($this->nguoiDung($request), ChuNhap::sach((string) $hopLe['topic']), (string) $hopLe['level'], $tu));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(BaiHocAi::xem($this->nguoiDung($request), $id));
    }

    public function ketQua(Request $request, int $id): JsonResponse
    {
        $hopLe = $request->validate(['score' => ['required', 'integer', 'between:0,100']]);

        return response()->json(['data' => BaiHocAi::ghiDiem($this->nguoiDung($request), $id, (int) $hopLe['score'])]);
    }

    public function destroy(Request $request, int $id): Response
    {
        BaiHocAi::xoa($this->nguoiDung($request), $id);

        return response()->noContent();
    }
}
