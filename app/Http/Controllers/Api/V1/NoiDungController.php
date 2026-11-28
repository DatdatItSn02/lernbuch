<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Hoc\PhienHoc;
use App\Support\NoiDung;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use stdClass;

/**
 * Nội dung học: loại thẻ, cây chủ đề 3 tầng theo loại thẻ (Chủ đề, Cấp độ, Giao tiếp), trang chủ đề, thẻ từ.
 * Chỉ đọc. Mọi người học đã đăng nhập xem được hết (gói Free/Premium và mức truy cập để G4).
 */
class NoiDungController extends Controller
{
    /** Các loại thẻ có thẻ đã xuất bản, kèm số thẻ (tab của trang Cấp độ). */
    public function boThe(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $dem = NoiDung::cacThe($user)->whereNotNull('cards.topic_id')->selectRaw('cards.collection AS ma, COUNT(*) AS n')->groupBy('cards.collection')->pluck('n', 'ma');

        $ds = DB::table('card_collections')->orderBy('sort')->orderBy('code')->get()
            ->filter(fn ($b) => (int) ($dem[$b->code] ?? 0) > 0)
            ->map(fn ($b) => ['code' => (string) $b->code, 'name' => NoiDung::tenTheoNgonNgu($b->name_vi, $b->name_en), 'card_count' => (int) $dem[$b->code]])
            ->values();

        return response()->json(['data' => $ds]);
    }

    /** Cây chủ đề của một loại thẻ: nhóm › chủ đề vừa › chủ đề nhỏ; lọc theo cấp và loại từ thì chỉ còn chủ đề có thẻ khớp. */
    public function chuDe(Request $request): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $hopLe = $request->validate([
            'bo' => ['sometimes', 'required', 'string', Rule::exists('card_collections', 'code')],
            'cap' => ['sometimes', 'nullable', 'string', 'max:20'],
            'loai_tu' => ['sometimes', 'nullable', 'string', 'max:16', 'regex:/^[a-z_]+$/'],
        ]);
        $bo = (string) ($hopLe['bo'] ?? 'vocab');
        $cap = NoiDung::docCap($hopLe['cap'] ?? null);
        $loaiTu = $hopLe['loai_tu'] ?? null;

        $goc = fn (): Builder => NoiDung::cacThe($user)->where('cards.collection', $bo)->whereNotNull('cards.topic_id');
        $theoLoaiTu = fn (Builder $q): Builder => $loaiTu !== null ? $q->where('cards.pos', $loaiTu) : $q;

        /** @var Collection<int, int> $dem */
        $dem = $theoLoaiTu(NoiDung::locCap($goc(), $cap))->selectRaw('cards.topic_id AS id, COUNT(*) AS n')->groupBy('cards.topic_id')
            ->pluck('n', 'id')->map(fn ($n) => (int) $n);

        $nho = NoiDung::cacChuDe()->where('depth', 3)->where('collection', $bo)->whereIn('id', $dem->keys()->all())->get();
        $vua = NoiDung::cacChuDe()->whereIn('id', $nho->pluck('parent_id')->unique()->values()->all())->get()->keyBy('id');
        $nhom = NoiDung::cacChuDe()->whereIn('id', $vua->pluck('parent_id')->unique()->values()->all())->get()->keyBy('id');

        $cay = [];
        foreach (self::xep($nho) as $t) {
            $cha = $vua[$t->parent_id] ?? null;
            $ong = $cha !== null ? ($nhom[$cha->parent_id] ?? null) : null;

            if ($cha === null || $ong === null) {
                continue;
            }

            $cay[$ong->id] ??= ['t' => $ong, 'vua' => []];
            $cay[$ong->id]['vua'][$cha->id] ??= ['t' => $cha, 'nho' => []];
            $cay[$ong->id]['vua'][$cha->id]['nho'][] = [...NoiDung::tenGon($t), 'icon' => $t->icon, 'card_count' => $dem[(int) $t->id]];
        }

        $nhomRa = [];
        foreach (self::xep(collect($cay)->pluck('t')) as $g) {
            $cacVua = [];
            foreach (self::xep(collect($cay[$g->id]['vua'])->pluck('t')) as $p) {
                $cacVua[] = [...NoiDung::tenGon($p), 'topics' => $cay[$g->id]['vua'][$p->id]['nho']];
            }

            $cacNho = array_merge(...array_column($cacVua, 'topics'));
            $nhomRa[] = [...NoiDung::tenGon($g), 'topic_count' => count($cacNho), 'card_count' => array_sum(array_column($cacNho, 'card_count')), 'parents' => $cacVua];
        }

        return response()->json(['data' => [
            'collection' => NoiDung::boThe($bo),
            'topic_count' => array_sum(array_column($nhomRa, 'topic_count')),
            'card_count' => array_sum(array_column($nhomRa, 'card_count')),
            'levels' => NoiDung::demTheoCap($theoLoaiTu($goc())),
            'pos' => NoiDung::demTheoLoaiTu(NoiDung::locCap($goc(), $cap)),
            'groups' => $nhomRa,
        ]]);
    }

    /** Trang chủ đề nhỏ: thông tin chủ đề, số thẻ theo cấp và loại từ, một trang 20 thẻ theo bộ lọc. */
    public function moChuDe(Request $request, string $slug): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $t = NoiDung::cacChuDe()->where('slug', $slug)->where('depth', 3)->first() ?? abort(404);
        $hopLe = $request->validate([
            'cap' => ['sometimes', 'nullable', 'string', 'max:20'],
            'loai_tu' => ['sometimes', 'nullable', 'string', 'max:16', 'regex:/^[a-z_]+$/'],
            'tim' => ['sometimes', 'nullable', 'string', 'max:100'],
            'trang' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ]);
        $cap = NoiDung::docCap($hopLe['cap'] ?? null);

        $goc = fn (): Builder => NoiDung::cacThe($user)->where('cards.topic_id', $t->id);
        $loc = fn (): Builder => NoiDung::locChu(NoiDung::locCap($goc(), $cap), $hopLe['tim'] ?? null)
            ->when($hopLe['loai_tu'] ?? null, fn (Builder $q, string $pos) => $q->where('cards.pos', $pos));

        $tong = $loc()->count();
        $soTrang = max(1, (int) ceil($tong / NoiDung::MOI_TRANG));
        $trang = min((int) ($hopLe['trang'] ?? 1), $soTrang);
        $the = NoiDung::xepThe($loc())->select(NoiDung::cotTomTat())->forPage($trang, NoiDung::MOI_TRANG)->get();

        $cha = $t->parent_id !== null ? NoiDung::cacChuDe()->where('id', $t->parent_id)->first() : null;
        $ong = $cha?->parent_id !== null ? NoiDung::cacChuDe()->where('id', $cha->parent_id)->first() : null;

        return response()->json([
            'data' => [
                'topic' => [
                    ...NoiDung::tenGon($t),
                    'name_en' => $t->name_en,
                    'icon' => $t->icon,
                    'collection' => NoiDung::boThe((string) $t->collection),
                    'card_count' => $goc()->count(),
                ],
                'parent' => $cha !== null ? NoiDung::tenGon($cha) : null,
                'group' => $ong !== null ? NoiDung::tenGon($ong) : null,
                'levels' => NoiDung::demTheoCap($goc()),
                'pos' => NoiDung::demTheoLoaiTu($goc()),
                'progress' => PhienHoc::tienDoChuDe($user, $t),
                'cards' => NoiDung::tomTat($the, $user),
            ],
            'meta' => ['page' => $trang, 'per_page' => NoiDung::MOI_TRANG, 'total' => $tong, 'pages' => $soTrang],
        ]);
    }

    /** Thẻ từ đủ thông tin: nghĩa, câu ví dụ, ghi chú, mẹo, bảng chia động từ, vị trí trong chủ đề, sao và ghi chú riêng. */
    public function the(Request $request, string $id): JsonResponse
    {
        $user = $this->nguoiDung($request);
        $t = NoiDung::cacThe($user)->where('cards.id', (int) $id)->first() ?? abort(404);

        $viDu = DB::table('card_examples')->where('card_id', $t->id)->orderBy('sort')->get(['id', 'sentence', 'translations'])
            ->map(fn ($v) => ['id' => (int) $v->id, 'sentence' => (string) $v->sentence, 'translations' => (object) (json_decode((string) $v->translations, true) ?: [])])->all();

        $chuDe = $t->topic_id !== null ? NoiDung::cacChuDe()->where('id', $t->topic_id)->first() : null;
        $soTay = DB::table('user_card_notes')->where('user_id', $user->id)->where('card_id', $t->id)->first();
        $extra = json_decode((string) $t->extra, true);

        return response()->json(['data' => [
            ...NoiDung::tomTat([$t], $user)[0],
            'collection_name' => NoiDung::boThe((string) $t->collection)['name'],
            'note' => $t->note,
            'tip' => $t->tip,
            'lektion' => $t->lektion,
            'extra' => is_array($extra) && $extra !== [] ? $extra : null,
            'examples' => $viDu,
            'verb' => self::dongTu($t),
            'topic' => $chuDe !== null ? NoiDung::tenGon($chuDe) : null,
            'nav' => $chuDe !== null ? self::viTri($user, $t) : null,
            'bookmark' => ['starred' => (bool) ($soTay->is_starred ?? false), 'note' => $soTay->note ?? null],
        ]]);
    }

    /** @return array<string, mixed>|null bảng chia của động từ gắn với thẻ */
    private static function dongTu(stdClass $t): ?array
    {
        $v = DB::table('verbs')->where('lang', NoiDung::LANG)->where('card_id', $t->id)->where('status', 'published')->whereNull('deleted_at')
            ->orderBy('id')->first();

        if ($v === null) {
            return null;
        }

        $chia = json_decode((string) $v->conjugation, true);

        return [
            'infinitive' => (string) $v->infinitive,
            'auxiliary' => $v->auxiliary,
            'is_separable' => (bool) $v->is_separable,
            'prefix' => $v->prefix,
            'form_du' => $v->form_du,
            'form_er' => $v->form_er,
            'preterite' => $v->preterite,
            'perfect' => $v->perfect,
            'conjugation' => is_array($chia) && $chia !== [] ? $chia : null,
        ];
    }

    /** @return array{index: int, total: int, prev: array{id: int, front: string}|null, next: array{id: int, front: string}|null} */
    private static function viTri(User $user, stdClass $t): array
    {
        $ds = NoiDung::xepThe(NoiDung::cacThe($user)->where('cards.topic_id', $t->topic_id))->get(['cards.id', 'cards.front'])->values();
        $i = (int) $ds->search(fn ($d) => (int) $d->id === (int) $t->id);
        $gon = fn (?stdClass $d) => $d !== null ? ['id' => (int) $d->id, 'front' => (string) $d->front] : null;

        return ['index' => $i + 1, 'total' => $ds->count(), 'prev' => $gon($ds[$i - 1] ?? null), 'next' => $gon($ds[$i + 1] ?? null)];
    }

    /**
     * Xếp chủ đề theo cột sort rồi id.
     *
     * @param  Collection<array-key, mixed>  $ds
     * @return list<mixed>
     */
    private static function xep(Collection $ds): array
    {
        return $ds->sortBy([['sort', 'asc'], ['id', 'asc']])->values()->all();
    }
}
