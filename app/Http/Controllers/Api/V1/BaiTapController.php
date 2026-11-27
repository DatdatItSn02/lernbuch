<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\LuyenTap\BaiTap;
use App\Support\LuyenTap\DienCau;
use App\Support\NoiDung;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Bài tập: các bộ bài kèm tiến độ, phiên làm bài, ghi kết quả; Điền câu (câu có chỗ trống của thẻ Từ vựng). */
class BaiTapController extends Controller
{
    /** Slug bộ bài (từ khoá Lernbuch, ":" thành "-"), vd dx-g1-1A, pr-1.2. */
    private const MAU_SLUG = '/^[A-Za-z0-9.\-]{1,160}$/';

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => BaiTap::danhSach($this->nguoiDung($request))]);
    }

    public function phien(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate([
            'bo' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'cap' => ['sometimes', 'nullable', 'string', 'max:20'],
            'on' => ['sometimes', 'boolean'],
            'n' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'thu_tu' => ['sometimes', 'boolean'],
        ]);
        $bo = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($hopLe['bo'] ?? ''))), fn (string $s) => $s !== '')));

        foreach ($bo as $s) {
            if (preg_match(self::MAU_SLUG, $s) !== 1) {
                throw ValidationException::withMessages(['bo' => __('validation.regex', ['attribute' => 'bo'])]);
            }
        }

        $cau = BaiTap::phien($user, $bo, NoiDung::docCap($hopLe['cap'] ?? null), (bool) ($hopLe['on'] ?? false), (int) ($hopLe['n'] ?? 15), (bool) ($hopLe['thu_tu'] ?? false));

        return response()->json(['data' => $cau]);
    }

    public function ketQua(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate([
            // ULID: 26 chữ Crockford base32 viết hoa
            'uid' => ['required', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
            'mode' => ['required', 'string', Rule::in(BaiTap::LOAI)],
            'grading' => ['sometimes', 'string', Rule::in(BaiTap::CACH_CHAM)],
            'set' => ['sometimes', 'nullable', 'string', 'regex:'.self::MAU_SLUG],
            'started_at_ms' => ['required', 'integer', 'min:1000000000000', 'max:99999999999999'],
            'finished_at_ms' => ['required', 'integer', 'min:1000000000000', 'max:99999999999999'],
            'items' => ['required', 'array', 'min:1', 'max:'.BaiTap::CAU_TOI_DA],
            'items.*' => ['required', 'array'],
            'items.*.id' => ['required_unless:mode,conjugate', 'integer', 'min:1'],
            'items.*.sig' => ['required_if:mode,conjugate', 'string', 'max:200'],
            'items.*.answer' => ['present', 'nullable', 'string', 'max:500'],
        ]);

        $items = array_map(fn (array $i) => [
            ...(isset($i['id']) ? ['id' => (int) $i['id']] : []),
            ...(isset($i['sig']) ? ['sig' => (string) $i['sig']] : []),
            'answer' => (string) ($i['answer'] ?? ''),
        ], array_values($hopLe['items']));

        return response()->json(['data' => BaiTap::ghiKetQua($user, [
            'uid' => (string) $hopLe['uid'],
            'mode' => (string) $hopLe['mode'],
            'grading' => (string) ($hopLe['grading'] ?? 'instant'),
            'set' => isset($hopLe['set']) ? (string) $hopLe['set'] : null,
            'started_at_ms' => (int) $hopLe['started_at_ms'],
            'finished_at_ms' => (int) $hopLe['finished_at_ms'],
            'items' => $items,
        ])]);
    }

    public function dienCau(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate([
            'cap' => ['sometimes', 'nullable', 'string', 'max:20'],
            'n' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        return response()->json(['data' => DienCau::phien($user, NoiDung::docCap($hopLe['cap'] ?? null), (int) ($hopLe['n'] ?? 15))]);
    }
}
