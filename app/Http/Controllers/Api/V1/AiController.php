<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Ai\ChuNhap;
use App\Support\Ai\DoanNguPhap;
use App\Support\Ai\HoiAi;
use App\Support\Ai\TrinhDoAi;
use App\Support\LoiApi;
use App\Support\LuyenTap\BaiTap;
use App\Support\LuyenTap\ChamBaiTap;
use App\Support\LuyenTap\NguPhap;
use App\Support\NoiDung;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use stdClass;

/**
 * Các chức năng AI của app học không lưu gì ngoài nhật ký. Mỗi lượt đi qua tám chặng của HoiAi; lỗi của từng chặng
 * thành JSON ở LoiApi. Chữ người học gõ kiểm bằng ChuNhap::luat (có chữ cái, không quá giới hạn).
 */
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
            'level' => TrinhDoAi::cua($user, self::cap($the)),
        ], $tu, 'card', $id));
    }

    /** Dịch Việt sang Đức hoặc Đức sang Việt, kèm nghĩa từng từ và ghi chú ngữ pháp. */
    public function dich(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate(['text' => ChuNhap::luat(500), 'target' => ['required', Rule::in(['de', 'vi'])]]);
        $chu = ChuNhap::sach((string) $hopLe['text']);

        return response()->json(HoiAi::hoi($user, 'translate', [
            'target' => $hopLe['target'] === 'de' ? 'tiếng Đức' : 'tiếng Việt',
            'text' => $chu,
            'level' => TrinhDoAi::cua($user),
        ], $chu));
    }

    /** Sửa lỗi một đoạn tiếng Đức. */
    public function suaNguPhap(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $chu = ChuNhap::sach((string) $request->validate(['text' => ChuNhap::luat(800)])['text']);

        return response()->json(HoiAi::hoi($user, 'grammar', ['text' => $chu, 'level' => TrinhDoAi::cua($user)], $chu));
    }

    /** Tra từ bằng AI khi từ điển của web không có từ đó. */
    public function traTu(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $luat = ChuNhap::luat(60);
        $luat[] = 'not_regex:/[\r\n]/';
        $chu = ChuNhap::sach((string) $request->validate(['text' => $luat])['text']);

        return response()->json(HoiAi::hoi($user, 'lookup', ['text' => $chu, 'level' => TrinhDoAi::cua($user)], $chu));
    }

    /** Giải thích một phần của bài ngữ pháp (phần đếm theo tiêu đề h2). */
    public function giaiThichNguPhap(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate(['slug' => ['required', 'string', 'max:160'], 'section' => ['required', 'integer', 'min:0', 'max:200']]);
        $bai = self::bai((string) $hopLe['slug']);
        $khoi = json_decode((string) $bai->blocks, true);
        $doan = DoanNguPhap::chu(is_array($khoi) ? $khoi : [], (int) $hopLe['section']);

        if ($doan === null) {
            throw ValidationException::withMessages(['section' => __('validation.in', ['attribute' => 'section'])]);
        }

        return response()->json(HoiAi::hoi($user, 'doc', [
            'text' => $doan,
            'level' => TrinhDoAi::cua($user, $bai->level !== null ? (int) $bai->level : null),
        ], (string) $bai->title.' · phần '.$hopLe['section'], 'grammar_lesson', (int) $bai->id));
    }

    /** Câu hỏi của người học về một bài ngữ pháp. */
    public function hoiNguPhap(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate(['slug' => ['required', 'string', 'max:160'], 'question' => ChuNhap::luat(300)]);
        $bai = self::bai((string) $hopLe['slug']);
        $hoi = ChuNhap::sach((string) $hopLe['question']);

        return response()->json(HoiAi::hoi($user, 'ask', [
            'text' => 'Bài ngữ pháp «'.(string) $bai->title.'». '.$hoi,
            'level' => TrinhDoAi::cua($user, $bai->level !== null ? (int) $bai->level : null),
        ], $hoi, 'grammar_lesson', (int) $bai->id));
    }

    /**
     * Câu trả lời khác đáp án mẫu có đúng không: theo một câu bài tập điền từ, sửa lỗi (exercise_id) hoặc một thẻ đang
     * luyện gõ (card_id). Kết quả không đổi điểm của câu.
     */
    public function kiemDapAn(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate([
            'text' => ChuNhap::luat(200),
            'exercise_id' => ['required_without:card_id', 'prohibits:card_id', 'integer', 'min:1'],
            'card_id' => ['required_without:exercise_id', 'integer', 'min:1'],
        ]);
        $chu = ChuNhap::sach((string) $hopLe['text']);

        if (isset($hopLe['exercise_id'])) {
            $cau = BaiTap::cacCau()->where('exercises.id', (int) $hopLe['exercise_id'])->first(['exercises.*']) ?? abort(404);

            if (! in_array((string) $cau->kind, ['fill', 'fix'], true)) {
                return LoiApi::json('ai_khong_ap_dung', 422);
            }

            $cham = BaiTap::cauCham($cau);

            if (ChamBaiTap::cham($cham, $chu) === 2) {
                return LoiApi::json('ai_dap_an_khop_mau', 422);
            }

            return response()->json(HoiAi::hoi($user, 'answer', [
                'expected' => mb_substr(implode(' / ', $cham['answers']), 0, 200),
                'prompt' => mb_substr(str_replace('**', '', $cham['prompt']), 0, 300),
                'text' => $chu,
                'level' => TrinhDoAi::cua($user),
            ], $chu, 'exercise', (int) $cau->id));
        }

        $the = NoiDung::cacThe($user)->where('cards.id', (int) $hopLe['card_id'])->first() ?? abort(404);
        $id = (int) $the->id;

        if (ChamBaiTap::fold($chu) === ChamBaiTap::fold((string) $the->front)) {
            return LoiApi::json('ai_dap_an_khop_mau', 422);
        }

        return response()->json(HoiAi::hoi($user, 'answer', [
            'expected' => (string) $the->front,
            'prompt' => 'Viết từ tiếng Đức có nghĩa: '.(NoiDung::nghia([$id])[$id]['vi'] ?? '?'),
            'text' => $chu,
            'level' => TrinhDoAi::cua($user, self::cap($the)),
        ], $chu, 'card', $id));
    }

    /** Viết câu: AI chấm câu người học đặt với một từ (đúng, gần đúng, sai). Mọi lượt chấm được lưu vào writing_submissions. */
    public function vietCau(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate(['card_id' => ['required', 'integer', 'min:1'], 'text' => ChuNhap::luat(200)]);
        $the = NoiDung::cacThe($user)->where('cards.id', (int) $hopLe['card_id'])->first() ?? abort(404);
        $id = (int) $the->id;
        $chu = ChuNhap::sach((string) $hopLe['text']);

        $kq = HoiAi::hoiKem($user, 'sentence', [
            'word' => (string) $the->front,
            'meaning' => NoiDung::nghia([$id])[$id]['vi'] ?? '?',
            'text' => $chu,
            'level' => TrinhDoAi::cua($user, self::cap($the)),
        ], $chu, 'card', $id);

        $luu = (int) DB::table('writing_submissions')->insertGetId([
            'user_id' => $user->id,
            'card_id' => $id,
            'kind' => 'sentence',
            'content' => $chu,
            'verdict' => (string) $kq['data']['verdict'],
            'result' => json_encode($kq['data'], JSON_UNESCAPED_UNICODE),
            'ai_request_id' => $kq['request_id'],
            'created_at' => now()->getTimestamp(),
        ]);

        return response()->json(['data' => $kq['data'], 'meta' => $kq['meta'], 'saved' => ['id' => $luu]]);
    }

    private static function cap(stdClass $the): ?int
    {
        return $the->level !== null ? (int) $the->level : null;
    }

    private static function bai(string $slug): stdClass
    {
        return NguPhap::cacBai()->where('slug', $slug)->first(['id', 'title', 'level', 'blocks']) ?? abort(404);
    }
}
