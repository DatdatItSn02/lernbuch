<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ThietBi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Các máy đang đăng nhập (bảng sessions của Laravel) và "đăng xuất các máy khác". Không trả mã phiên ra ngoài. */
class PhienController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);

        if (config('session.driver') !== 'database') {
            return response()->json(['data' => [], 'supported' => false]);
        }

        $hienTai = $request->hasSession() ? $request->session()->getId() : null;

        $ds = DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->limit(50)
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (stdClass $phien) => ThietBi::tu($phien->user_agent) + [
                'ip' => $phien->ip_address,
                'last_active_at' => (int) $phien->last_activity,
                'is_current' => $phien->id === $hienTai,
            ])
            ->sortByDesc('is_current')
            ->values();

        return response()->json(['data' => $ds, 'supported' => true]);
    }

    public function dangXuatMayKhac(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $request->validate(['password' => ['required', 'string', 'current_password:web']]);

        Auth::guard('web')->logoutOtherDevices((string) $request->input('password'));

        $conLai = 0;

        if (config('session.driver') === 'database') {
            $hienTai = $request->hasSession() ? $request->session()->getId() : '';
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->where('id', '!=', $hienTai)->delete();
            $conLai = DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->count();
        }

        return response()->json(['data' => ['remaining' => $conLai]]);
    }
}
