<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\LuyenTap\NguPhap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ngữ pháp: danh sách bài theo cấp và chương, nội dung một bài. Chỉ đọc. */
class NguPhapController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->nguoiDung($request);

        return response()->json(['data' => NguPhap::danhSach()]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $this->nguoiDung($request);

        return response()->json(['data' => NguPhap::bai($slug) ?? abort(404)]);
    }
}
