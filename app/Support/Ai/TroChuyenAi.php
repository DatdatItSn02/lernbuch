<?php

namespace App\Support\Ai;

use App\Models\User;
use App\Support\LoiApi;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Trò chuyện với AI theo tình huống. Mỗi lượt AI chỉ nhận 10 tin gần nhất của chính cuộc đó. Tin chỉ được lưu khi AI trả
 * lời thành công. Cuộc của người khác, mã không có, mã sai dạng đều là 404. Xoá cuộc là xoá hẳn tin nhắn và xoá trắng
 * đoạn trích trong nhật ký AI của các lượt đó.
 */
final class TroChuyenAi
{
    public const TINH_HUONG = [
        'free' => 'trò chuyện tự do',
        'restaurant' => 'gọi món ở nhà hàng',
        'visa_interview' => 'phỏng vấn xin visa ở lãnh sự quán',
        'workplace' => 'nói chuyện với đồng nghiệp ở nơi làm việc',
    ];

    public const TOI_DA_CUOC = 50;

    public const TOI_DA_TIN = 200;

    public const SO_TIN_GUI = 10;

    public const CAT_TIN = 300;

    public const DANH_SACH = 30;

    /** @return list<array<string, mixed>> 30 cuộc mới nhất của người này */
    public static function danhSach(User $user): array
    {
        return DB::table('ai_conversations')->where('user_id', $user->id)
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')->orderByDesc('id')->limit(self::DANH_SACH)->get()
            ->map(fn (stdClass $c) => self::cuocApi($c))->values()->all();
    }

    /** @return array<string, mixed> */
    public static function tao(User $user, string $tinhHuong): array
    {
        TrangThaiAi::kiem($user, CaiDatAi::tatCa());

        if (DB::table('ai_conversations')->where('user_id', $user->id)->count() >= self::TOI_DA_CUOC) {
            throw new HttpResponseException(LoiApi::json('ai_qua_nhieu_cuoc', 422));
        }

        $bay = now()->getTimestamp();
        $id = (int) DB::table('ai_conversations')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'scenario' => $tinhHuong,
            'level' => TrinhDoAi::cua($user),
            'message_count' => 0,
            'created_at' => $bay,
            'updated_at' => $bay,
        ]);

        return self::cuocApi(self::dong($id));
    }

    /** @return array{conversation: array<string, mixed>, messages: list<array<string, mixed>>} */
    public static function xem(User $user, string $ma): array
    {
        $c = self::cuoc($user, $ma);
        $tin = DB::table('ai_messages')->where('conversation_id', $c->id)->orderBy('id')->get();

        return ['conversation' => self::cuocApi($c), 'messages' => $tin->map(fn (stdClass $t) => self::tinApi($t))->values()->all()];
    }

    /** @return array<string, mixed> kết quả của HoiAi (data, meta) kèm hai tin mới và cuộc đã cập nhật */
    public static function gui(User $user, string $ma, string $chu): array
    {
        $c = self::cuoc($user, $ma);

        if ((int) $c->message_count + 2 > self::TOI_DA_TIN) {
            throw new HttpResponseException(LoiApi::json('ai_cuoc_day', 422));
        }

        $kq = HoiAi::hoiKem($user, 'chat', [
            'scenario' => self::TINH_HUONG[(string) $c->scenario] ?? self::TINH_HUONG['free'],
            'history' => self::lichSu((int) $c->id),
            'text' => $chu,
            'level' => $c->level !== null ? (string) $c->level : TrinhDoAi::cua($user),
        ], $chu, 'conversation', (int) $c->id);

        $d = $kq['data'];
        $bay = now()->getTimestamp();
        $ids = DB::transaction(function () use ($c, $chu, $d, $kq, $bay): array {
            $a = (int) DB::table('ai_messages')->insertGetId(['conversation_id' => $c->id, 'role' => 'user', 'content' => $chu, 'created_at' => $bay]);
            $b = (int) DB::table('ai_messages')->insertGetId([
                'conversation_id' => $c->id,
                'role' => 'assistant',
                'content' => (string) $d['reply_de'],
                'corrections' => json_encode(['vi' => $d['reply_vi'], 'hint' => $d['next_hint'], 'errors' => $d['corrections']], JSON_UNESCAPED_UNICODE),
                'request_id' => $kq['request_id'],
                'created_at' => $bay,
            ]);
            DB::table('ai_conversations')->where('id', $c->id)->update([
                'message_count' => DB::raw('message_count + 2'),
                'last_message_at' => $bay,
                'updated_at' => $bay,
                'title' => $c->title ?? mb_substr($chu, 0, 60),
            ]);

            return [$a, $b];
        });

        return [
            'data' => $d,
            'meta' => $kq['meta'],
            'messages' => DB::table('ai_messages')->whereIn('id', $ids)->orderBy('id')->get()->map(fn (stdClass $t) => self::tinApi($t))->values()->all(),
            'conversation' => self::cuocApi(self::dong((int) $c->id)),
        ];
    }

    public static function xoa(User $user, string $ma): void
    {
        $c = self::cuoc($user, $ma);

        DB::transaction(function () use ($c): void {
            DB::table('ai_messages')->where('conversation_id', $c->id)->delete();
            DB::table('ai_conversations')->where('id', $c->id)->delete();
            DB::table('ai_requests')->where('context_type', 'conversation')->where('context_id', $c->id)->update(['input_excerpt' => null]);
        });
    }

    /** 10 tin gần nhất của cuộc, cũ trước, mỗi tin một dòng, mỗi dòng tối đa 300 ký tự; chưa có tin thì "(chưa có)". */
    public static function lichSu(int $cuoc): string
    {
        $tin = DB::table('ai_messages')->where('conversation_id', $cuoc)->orderByDesc('id')->limit(self::SO_TIN_GUI)->get(['role', 'content'])->reverse();

        if ($tin->isEmpty()) {
            return '(chưa có)';
        }

        return $tin->map(fn (stdClass $t) => ($t->role === 'user' ? 'Người học: ' : 'AI: ').mb_substr(str_replace("\n", ' ', (string) $t->content), 0, self::CAT_TIN))
            ->implode("\n");
    }

    private static function cuoc(User $user, string $ma): stdClass
    {
        return DB::table('ai_conversations')->where('public_id', $ma)->where('user_id', $user->id)->first() ?? abort(404);
    }

    private static function dong(int $id): stdClass
    {
        /** @var stdClass $c */
        $c = DB::table('ai_conversations')->where('id', $id)->first();

        return $c;
    }

    /** @return array<string, mixed> */
    private static function cuocApi(stdClass $c): array
    {
        return [
            'id' => (string) $c->public_id,
            'scenario' => (string) $c->scenario,
            'level' => $c->level !== null ? (string) $c->level : null,
            'title' => $c->title !== null ? (string) $c->title : null,
            'message_count' => (int) $c->message_count,
            'last_message_at' => $c->last_message_at !== null ? (int) $c->last_message_at : null,
            'created_at' => (int) $c->created_at,
        ];
    }

    /** @return array<string, mixed> */
    private static function tinApi(stdClass $t): array
    {
        $them = $t->role === 'assistant' && $t->corrections !== null ? json_decode((string) $t->corrections, true) : null;

        return [
            'id' => (int) $t->id,
            'role' => (string) $t->role,
            'content' => (string) $t->content,
            'extra' => is_array($them) ? $them : null,
            'created_at' => (int) $t->created_at,
        ];
    }
}
