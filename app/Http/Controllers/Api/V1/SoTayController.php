<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\NoiDung;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Sổ tay: thẻ đánh dấu sao và ghi chú riêng trên thẻ. Mỗi người chỉ đọc, sửa được dòng của chính mình
 * (khoá là người đang đăng nhập, không nhận mã người dùng từ ngoài vào).
 */
class SoTayController extends Controller
{
    public const GHI_CHU_TOI_DA = 500;

    /** Danh sách thẻ trong sổ tay: tất cả, chỉ thẻ có sao, hoặc chỉ thẻ có ghi chú; sửa gần nhất trước. */
    public function index(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate([
            'loc' => ['sometimes', 'nullable', Rule::in(['sao', 'ghi-chu'])],
            'tim' => ['sometimes', 'nullable', 'string', 'max:100'],
            'trang' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ]);

        $goc = fn (): Builder => NoiDung::cacThe($user)
            ->join('user_card_notes as so_tay', fn (JoinClause $j) => $j->on('so_tay.card_id', '=', 'cards.id')->where('so_tay.user_id', '=', $user->id))
            ->where(fn (Builder $q) => $q->where('so_tay.is_starred', 1)->orWhereNotNull('so_tay.note'));
        $loc = fn (): Builder => NoiDung::locChu($goc(), $hopLe['tim'] ?? null)
            ->when(($hopLe['loc'] ?? null) === 'sao', fn (Builder $q) => $q->where('so_tay.is_starred', 1))
            ->when(($hopLe['loc'] ?? null) === 'ghi-chu', fn (Builder $q) => $q->whereNotNull('so_tay.note'));

        $tong = $loc()->count();
        $soTrang = max(1, (int) ceil($tong / NoiDung::MOI_TRANG));
        $trang = min((int) ($hopLe['trang'] ?? 1), $soTrang);
        $dong = $loc()->select([...NoiDung::cotTomTat(), 'so_tay.is_starred', 'so_tay.note', 'so_tay.updated_at AS so_tay_luc'])
            ->orderByDesc('so_tay.updated_at')->orderByDesc('cards.id')->forPage($trang, NoiDung::MOI_TRANG)->get();
        $theoId = $dong->keyBy('id');

        $the = array_map(fn (array $t) => [...$t, 'bookmark' => [
            'starred' => (bool) $theoId[$t['id']]->is_starred,
            'note' => $theoId[$t['id']]->note,
            'updated_at' => (int) $theoId[$t['id']]->so_tay_luc,
        ]], NoiDung::tomTat($dong, $user));

        return response()->json([
            'data' => $the,
            'meta' => [
                'page' => $trang, 'per_page' => NoiDung::MOI_TRANG, 'total' => $tong, 'pages' => $soTrang,
                'counts' => [
                    'all' => $goc()->count(),
                    'starred' => $goc()->where('so_tay.is_starred', 1)->count(),
                    'noted' => $goc()->whereNotNull('so_tay.note')->count(),
                ],
            ],
        ]);
    }

    /**
     * Đánh dấu sao, bỏ sao, ghi hay xoá ghi chú riêng của một thẻ. Gửi ô nào thì đổi ô đó;
     * hết sao và hết ghi chú thì xoá dòng. Thẻ không xem được thì 404.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $the = NoiDung::cacThe($user)->where('cards.id', (int) $id)->first(['cards.id']) ?? abort(404);
        $hopLe = $request->validate([
            'starred' => ['sometimes', 'required', 'boolean'],
            'note' => ['sometimes', 'nullable', 'string', 'max:'.self::GHI_CHU_TOI_DA],
        ]);

        $doi = [];
        if (array_key_exists('starred', $hopLe)) {
            $doi['is_starred'] = (bool) $hopLe['starred'] ? 1 : 0;
        }
        if (array_key_exists('note', $hopLe)) {
            $doi['note'] = filled($hopLe['note']) ? (string) $hopLe['note'] : null;
        }

        if ($doi !== []) {
            $bay = time();
            $khoa = ['user_id' => $user->id, 'card_id' => (int) $the->id];

            // Chỉ cập nhật ô được gửi: hai lần gửi sát nhau (đánh sao rồi gõ ghi chú) không ghi đè lên nhau
            DB::transaction(function () use ($khoa, $doi, $bay): void {
                DB::table('user_card_notes')->upsert(
                    [[...$khoa, 'is_starred' => $doi['is_starred'] ?? 0, 'note' => $doi['note'] ?? null, 'created_at' => $bay, 'updated_at' => $bay]],
                    ['user_id', 'card_id'],
                    [...array_keys($doi), 'updated_at'],
                );
                DB::table('user_card_notes')->where($khoa)->where('is_starred', 0)->whereNull('note')->delete();
            });
        }

        $dong = DB::table('user_card_notes')->where('user_id', $user->id)->where('card_id', $the->id)->first();

        return response()->json(['data' => ['starred' => (bool) ($dong->is_starred ?? false), 'note' => $dong->note ?? null]]);
    }
}
