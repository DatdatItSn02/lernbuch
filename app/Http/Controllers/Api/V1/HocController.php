<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Hoc\CaiDatHoc;
use App\Support\Hoc\GhiLuotCham;
use App\Support\Hoc\HomNay;
use App\Support\Hoc\PhienHoc;
use App\Support\NoiDung;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use stdClass;

/**
 * Học và đồng bộ: Hôm nay, mở phiên học, nhận lượt chấm theo lô (máy gửi lại khi có mạng), phiên tới còn bao nhiêu thẻ.
 * Mọi số liệu là của người đang đăng nhập; mã phiên của người khác thì 404.
 */
class HocController extends Controller
{
    /** Số lượt tối đa trong một lô. */
    public const LO_TOI_DA = 200;

    public function homNay(Request $request): JsonResponse
    {
        return response()->json(['data' => HomNay::cua($this->nguoiDung($request))]);
    }

    public function taoPhien(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate([
            'topic' => ['sometimes', 'nullable', 'string', 'max:160', 'regex:/^[a-z0-9-]+$/'],
            'review_only' => ['sometimes', 'boolean'],
            'mode' => ['sometimes', Rule::in(CaiDatHoc::CHE_DO)],
        ]);

        $phien = PhienHoc::tao($user, self::chuDe($hopLe['topic'] ?? null), (bool) ($hopLe['review_only'] ?? false), (string) ($hopLe['mode'] ?? 'flashcard'));

        return response()->json(['data' => $phien], 201);
    }

    public function conLai(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate([
            'chu_de' => ['sometimes', 'nullable', 'string', 'max:160', 'regex:/^[a-z0-9-]+$/'],
            'on' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => PhienHoc::conLai($user, self::chuDe($hopLe['chu_de'] ?? null), (bool) ($hopLe['on'] ?? false))]);
    }

    public function luotCham(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate([
            'reviews' => ['required', 'array', 'min:1', 'max:'.self::LO_TOI_DA],
            'reviews.*' => ['required', 'array'],
            // ULID: 26 chữ Crockford base32 viết hoa
            'reviews.*.uid' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
            'reviews.*.card_id' => ['required', 'integer', 'min:1'],
            'reviews.*.session_id' => ['present', 'nullable', 'integer', 'min:1'],
            'reviews.*.mode' => ['required', 'string', Rule::in(CaiDatHoc::CHE_DO)],
            'reviews.*.rating' => ['required', 'integer', 'between:0,3'],
            'reviews.*.reviewed_at_ms' => ['required', 'integer', 'min:1000000000000', 'max:99999999999999'],
            'reviews.*.duration_ms' => ['present', 'nullable', 'integer', 'between:0,86400000'],
        ]);

        $luot = array_map(fn (array $l) => [
            'uid' => (string) $l['uid'],
            'card_id' => (int) $l['card_id'],
            'session_id' => $l['session_id'] !== null ? (int) $l['session_id'] : null,
            'mode' => (string) $l['mode'],
            'rating' => (int) $l['rating'],
            'reviewed_at_ms' => (int) $l['reviewed_at_ms'],
            'duration_ms' => $l['duration_ms'] !== null ? (int) $l['duration_ms'] : null,
        ], array_values($hopLe['reviews']));

        return response()->json(['data' => GhiLuotCham::ghi($user, $luot)]);
    }

    private static function chuDe(?string $slug): ?stdClass
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return NoiDung::cacChuDe()->where('slug', $slug)->where('depth', 3)->first() ?? abort(404);
    }
}
