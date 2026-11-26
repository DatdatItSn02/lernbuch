<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\NoiDung;
use App\Support\TraTu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Tra từ (gõ 4 thứ tiếng, không cần dấu), tra một chữ bấm trong câu, và lịch sử tra của chính người học. */
class TraTuController extends Controller
{
    /** Giữ bao nhiêu mục lịch sử mỗi người (như ghi chú của bảng search_history). */
    public const LICH_SU_GIU = 100;

    public const LICH_SU_HIEN = 20;

    public function tim(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate(['q' => ['sometimes', 'nullable', 'string', 'max:120']]);
        $kq = TraTu::tim($user, (string) ($hopLe['q'] ?? ''));

        return response()->json(['data' => $kq['cards'], 'meta' => ['total' => $kq['total'], 'limit' => TraTu::KET_QUA_TOI_DA]]);
    }

    public function traChu(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate(['chu' => ['required', 'string', 'max:120']]);

        return response()->json(['data' => TraTu::traChu($user, (string) $hopLe['chu'])]);
    }

    /** 20 lần tra gần nhất, kèm thẻ đã mở (nếu thẻ còn xem được). */
    public function lichSu(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $ds = DB::table('search_history')->where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::LICH_SU_HIEN)->get(['id', 'term', 'card_id', 'created_at']);
        $the = NoiDung::cacThe($user)->whereIn('cards.id', $ds->pluck('card_id')->filter()->unique()->values()->all())
            ->get(['cards.id', 'cards.front'])->keyBy('id');

        return response()->json(['data' => $ds->map(fn ($d) => [
            'id' => (int) $d->id,
            'term' => (string) $d->term,
            'card' => $d->card_id !== null && isset($the[$d->card_id]) ? ['id' => (int) $d->card_id, 'front' => (string) $the[$d->card_id]->front] : null,
            'searched_at' => (int) $d->created_at,
        ])->values()]);
    }

    /** Ghi một lần tra (khi người học mở thẻ từ kết quả, hoặc bấm Enter). Lần tra giống lần ngay trước (không kể hoa thường) thì chỉ đổi giờ và cách viết. */
    public function ghiLichSu(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate([
            'term' => ['required', 'string', 'max:120'],
            'card_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);
        $theId = isset($hopLe['card_id']) ? (int) $hopLe['card_id'] : null;

        if ($theId !== null && ! NoiDung::cacThe($user)->where('cards.id', $theId)->exists()) {
            throw ValidationException::withMessages(['card_id' => __('validation.exists', ['attribute' => 'card_id'])]);
        }

        $bay = time();
        $truoc = DB::table('search_history')->where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')->first();

        if ($truoc !== null && mb_strtolower((string) $truoc->term) === mb_strtolower((string) $hopLe['term']) && (int) ($truoc->card_id ?? 0) === (int) ($theId ?? 0)) {
            DB::table('search_history')->where('id', $truoc->id)->update(['term' => (string) $hopLe['term'], 'created_at' => $bay]);
            $id = (int) $truoc->id;
        } else {
            $id = (int) DB::table('search_history')->insertGetId([
                'user_id' => $user->id, 'term' => (string) $hopLe['term'], 'lang' => NoiDung::LANG, 'card_id' => $theId, 'created_at' => $bay,
            ]);
        }

        // Chỉ giữ 100 mục gần nhất
        $giu = DB::table('search_history')->where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')->limit(self::LICH_SU_GIU)->pluck('id')->all();
        DB::table('search_history')->where('user_id', $user->id)->whereNotIn('id', $giu)->delete();

        return response()->json(['data' => ['id' => $id]], 201);
    }

    /** Xoá một mục lịch sử của chính mình; mục của người khác thì như không có (404). */
    public function xoaLichSu(Request $request, string $id): Response
    {
        $user = $this->nguoiDung($request);

        if (DB::table('search_history')->where('user_id', $user->id)->where('id', (int) $id)->delete() === 0) {
            abort(404);
        }

        return response()->noContent();
    }

    public function xoaHetLichSu(Request $request): Response
    {
        DB::table('search_history')->where('user_id', $this->nguoiDung($request)->id)->delete();

        return response()->noContent();
    }
}
