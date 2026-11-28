<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\KhachXemThu;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\Ai\DongYAi;
use App\Support\Ai\TrangThaiAi;
use App\Support\KhoaHoc;
use App\Support\LoiApi;
use App\Support\NgonNgu;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
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
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all'],
            'ui_locale' => ['sometimes', 'required', 'string', Rule::in(NgonNgu::giaoDien())],
            'goal' => ['sometimes', 'nullable', 'string', Rule::in(KhoaHoc::MUC_TIEU)],
            'level' => ['sometimes', 'nullable', 'integer', 'between:'.($khoa->level_min ?? 1).','.($khoa->level_max ?? 4)],
        ]);

        $mucTieu = array_intersect_key($hopLe, array_flip(['goal', 'level']));

        if ($mucTieu !== [] && $khoa === null) {
            throw ValidationException::withMessages([array_key_first($mucTieu) => __('api.chua_co_khoa_hoc')]);
        }

        // Khách xem thử dùng chung một tài khoản: không đổi tên, múi giờ, ngôn ngữ của tài khoản đó
        if (! KhachXemThu::la($user)) {
            $user->fill(array_intersect_key($hopLe, array_flip(['name', 'timezone', 'ui_locale'])))->save();
        }

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

    /**
     * Khai ngày sinh để dùng AI (chỉ một lần; cần sửa thì nhân viên sửa trong trang quản trị, có nhật ký).
     * Lưu INT: 00:00 UTC của ngày sinh (có thể âm). Không ở tương lai theo ngày của người học, không trước 1900.
     */
    public function ngaySinh(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);

        if ($user->getAttribute('birth_date') !== null) {
            return LoiApi::json('ngay_sinh_da_khai', 409);
        }

        $homNay = CarbonImmutable::now($user->timezone)->format('Y-m-d');
        $hopLe = $request->validate([
            'birth_date' => ['required', 'string', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:'.$homNay],
        ]);

        $user->forceFill(['birth_date' => (new DateTimeImmutable($hopLe['birth_date'].' 00:00:00', new DateTimeZone('UTC')))->getTimestamp()])->save();

        return response()->json(['data' => $this->duLieu($user)]);
    }

    /** Đồng ý hay rút đồng ý gửi nội dung cho nhà cung cấp AI; phải gửi đúng phiên bản lời đồng ý đang hiện. */
    public function dongYAi(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate([
            'granted' => ['required', 'boolean'],
            'version' => ['required', 'string', Rule::in([DongYAi::PHIEN_BAN])],
        ]);

        DongYAi::ghi($user, (bool) $hopLe['granted']);

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
            'ai' => TrangThaiAi::choToi($user),
            // Chỉ có khi đang là khách xem thử: app ẩn phần tài khoản, đăng xuất
            ...(KhachXemThu::la($user) ? ['guest' => true] : []),
        ];
    }
}
