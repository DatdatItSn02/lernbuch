<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\LuyenTap\DongTu;
use App\Support\NoiDung;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Bảng động từ (tìm, lọc cấp, động từ tách; mỗi trang 80 động từ) và câu luyện chia động từ. */
class DongTuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->nguoiDung($request);
        $hopLe = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'cap' => ['sometimes', 'nullable', 'string', 'max:20'],
            'tach' => ['sometimes', 'boolean'],
            'trang' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);
        $trang = (int) ($hopLe['trang'] ?? 1);
        $kq = DongTu::danhSach((string) ($hopLe['q'] ?? ''), NoiDung::docCap($hopLe['cap'] ?? null), (bool) ($hopLe['tach'] ?? false), $trang);

        return response()->json([
            'data' => $kq['verbs'],
            'meta' => ['page' => $trang, 'per_page' => DongTu::MOI_TRANG, 'total' => $kq['total'], 'pages' => (int) ceil($kq['total'] / DongTu::MOI_TRANG)],
        ]);
    }

    public function luyen(Request $request): JsonResponse
    {
        $this->nguoiDung($request);
        $hopLe = $request->validate([
            'cap' => ['sometimes', 'nullable', 'string', 'max:20'],
            'tach' => ['sometimes', 'boolean'],
            'n' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        return response()->json(['data' => DongTu::luyen(NoiDung::docCap($hopLe['cap'] ?? null), (bool) ($hopLe['tach'] ?? false), (int) ($hopLe['n'] ?? 12))]);
    }
}
