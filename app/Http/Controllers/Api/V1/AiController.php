<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Ai\HoiAi;
use App\Support\KhoaHoc;
use App\Support\NoiDung;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use stdClass;

/** Các chức năng AI của app học. Mỗi lượt đi qua tám chặng của HoiAi; lỗi của từng chặng thành JSON ở LoiApi. */
class AiController extends Controller
{
    /** Giải thích một từ trên thẻ: nghĩa, cách dùng, ví dụ, lỗi người Việt hay gặp. Nội dung lấy từ thẻ, không có ô nhập. */
    public function giaiThichTu(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate(['card_id' => ['required', 'integer', 'min:1']]);
        $the = NoiDung::cacThe($user)->where('cards.id', (int) $hopLe['card_id'])->first() ?? abort(404);
        $id = (int) $the->id;
        $tu = (string) $the->front;

        return response()->json(HoiAi::hoi($user, 'word', [
            'word' => $tu,
            'meaning' => NoiDung::nghia([$id])[$id]['vi'] ?? '?',
            'level' => self::trinhDo($user, $the),
        ], $tu, 'card', $id));
    }

    /** Trình độ ghi trong prompt: trình độ người học tự chọn, chưa chọn thì cấp của thẻ, không có thì A1. */
    private static function trinhDo(User $user, stdClass $the): string
    {
        $tuChon = KhoaHoc::choApi($user, KhoaHoc::hienTai($user))['level'] ?? null;
        $cap = $tuChon ?? ($the->level !== null ? (int) $the->level : null);

        return NoiDung::CAP[$cap ?? 1] ?? 'A1';
    }
}
