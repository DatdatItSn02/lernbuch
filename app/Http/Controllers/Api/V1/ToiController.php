<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\KhoaHoc;
use App\Support\NgonNgu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Người đang đăng nhập: hồ sơ, khoá học đang học (mục tiêu, trình độ tự chọn) và cài đặt. */
class ToiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->duLieu($this->nguoiDung($request))]);
    }

    public function capNhatHoSo(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $khoa = KhoaHoc::hienTai($user);

        $hopLe = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all_with_bc'],
            'ui_locale' => ['sometimes', 'required', 'string', Rule::in(NgonNgu::giaoDien())],
            'goal' => ['sometimes', 'nullable', 'string', Rule::in(KhoaHoc::MUC_TIEU)],
            'level' => ['sometimes', 'nullable', 'integer', 'between:'.($khoa->level_min ?? 1).','.($khoa->level_max ?? 4)],
        ]);

        $mucTieu = array_intersect_key($hopLe, array_flip(['goal', 'level']));

        if ($mucTieu !== [] && $khoa === null) {
            throw ValidationException::withMessages([array_key_first($mucTieu) => __('api.chua_co_khoa_hoc')]);
        }

        $user->fill(array_intersect_key($hopLe, array_flip(['name', 'timezone', 'ui_locale'])))->save();

        if ($khoa !== null && $mucTieu !== []) {
            $thayDoi = [];

            if (array_key_exists('goal', $mucTieu)) {
                $thayDoi['goal'] = $mucTieu['goal'];
            }

            if (array_key_exists('level', $mucTieu)) {
                $thayDoi['placement_level'] = $mucTieu['level'];
            }

            KhoaHoc::luuMucTieu($user, $khoa, $thayDoi);
        }

        return response()->json(['data' => $this->duLieu($user)]);
    }

    /** @return array<string, mixed> */
    private function duLieu(User $user): array
    {
        return [
            'id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'ui_locale' => NgonNgu::chon($user->ui_locale),
            'timezone' => $user->timezone,
            'created_at' => $user->created_at?->getTimestamp(),
            'course' => KhoaHoc::choApi($user, KhoaHoc::hienTai($user)),
            'settings' => UserSetting::cua($user),
        ];
    }
}
