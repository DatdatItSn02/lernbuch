<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use App\Support\KhoaHoc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cài đặt học và giao diện, lưu theo tài khoản để máy nào cũng giống nhau. Gửi khoá nào thì đổi khoá đó. */
class CaiDatController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => UserSetting::cua($this->nguoiDung($request))]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);

        $hopLe = $request->validate([
            'new_per_session' => ['sometimes', 'required', 'integer', Rule::in([0, 10, 20, 30, 50, 100])],
            'daily_goal' => ['sometimes', 'required', 'integer', 'between:5,500'],
            'theme' => ['sometimes', 'required', Rule::in(['light', 'dark'])],
            'font_size' => ['sometimes', 'required', Rule::in(['sm', 'md', 'lg'])],
            'season' => ['sometimes', 'required', Rule::in(['auto', 'spring', 'summer', 'autumn', 'winter'])],
            'meaning_langs' => ['sometimes', 'present', 'array', 'max:10'],
            'meaning_langs.*' => ['string', 'distinct', Rule::in(KhoaHoc::ngonNguNghia(KhoaHoc::hienTai($user)))],
        ]);

        if (array_key_exists('meaning_langs', $hopLe)) {
            $hopLe['meaning_langs'] = array_values($hopLe['meaning_langs']);
        }

        UserSetting::luu($user, $hopLe);

        return response()->json(['data' => UserSetting::cua($user)]);
    }
}
