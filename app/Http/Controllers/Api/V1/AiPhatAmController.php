<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PhatAm\ChamPhatAm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Chấm phát âm: Azure (máy chủ gửi file ghi âm) hoặc bản dự phòng (trình duyệt tự nhận giọng, máy chủ so chữ). */
class AiPhatAmController extends Controller
{
    public function cham(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate([
            'card_id' => ['required_without:example_id', 'prohibits:example_id', 'integer', 'min:1'],
            'example_id' => ['required_without:card_id', 'integer', 'min:1'],
            // Giới hạn độ dài nằm trong Wav::doc (phat_am_qua_lon), kiểm trước khi giải mã
            'audio' => ['required', 'string'],
        ]);

        return response()->json(ChamPhatAm::cham(
            $user,
            isset($hopLe['card_id']) ? (int) $hopLe['card_id'] : null,
            isset($hopLe['example_id']) ? (int) $hopLe['example_id'] : null,
            (string) $hopLe['audio'],
        ));
    }

    public function duPhong(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate([
            'card_id' => ['required_without:example_id', 'prohibits:example_id', 'integer', 'min:1'],
            'example_id' => ['required_without:card_id', 'integer', 'min:1'],
            'heard' => ['present', 'nullable', 'string', 'max:600'],
        ]);

        return response()->json(ChamPhatAm::duPhong(
            $user,
            isset($hopLe['card_id']) ? (int) $hopLe['card_id'] : null,
            isset($hopLe['example_id']) ? (int) $hopLe['example_id'] : null,
            (string) ($hopLe['heard'] ?? ''),
        ));
    }
}
