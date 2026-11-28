<?php

use App\Models\User;
use App\Support\Ai\BoNoi\BoNoiGia;
use App\Support\Ai\CaiDatAi;
use App\Support\Ai\DanhSachThu;
use App\Support\Ai\DongYAi;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

// Dùng chung cho các test học (HocApiTest, CheDoHocTest): giờ theo múi giờ của người học, một lượt chấm như app gửi.

const MUI_GIO_HOC = 'Asia/Ho_Chi_Minh';

function lucHoc(string $gio): CarbonImmutable
{
    return CarbonImmutable::parse($gio, MUI_GIO_HOC);
}

function gioHoc(?int $luc): ?string
{
    return $luc === null ? null : CarbonImmutable::createFromTimestamp($luc, MUI_GIO_HOC)->format('Y-m-d H:i:s');
}

/** @return array<string, mixed> một lượt chấm như app gửi */
function luot(int $the, int $cham, CarbonImmutable $luc, ?int $phien = null, ?int $thoiGian = 3000, string $cheDo = 'flashcard', ?string $uid = null): array
{
    return [
        'uid' => $uid ?? (string) Str::ulid(),
        'card_id' => $the,
        'session_id' => $phien,
        'mode' => $cheDo,
        'rating' => $cham,
        'reviewed_at_ms' => (int) $luc->getTimestampMs(),
        'duration_ms' => $thoiGian,
    ];
}

// Dùng chung cho các test trang quản trị (QuanTriTest, QuanTriNguoiHocTest, QuanTriNoiDungTest).

/** Nhân viên đang làm, có một vai trò (cần seed trước: vai trò và quyền nằm trong seed.sql). */
function nhanVien(string $vaiTro, array $doi = []): User
{
    $user = User::factory()->create($doi);
    $user->forceFill(['is_staff' => true])->save();
    $user->assignRole($vaiTro);

    return $user;
}

/** @return array<string, mixed> dòng nhật ký thao tác mới nhất của một việc */
function dongNhatKy(string $viec): array
{
    $dong = DB::table('audit_logs')->where('action', $viec)->latest('id')->first();
    expect($dong)->not->toBeNull("không có nhật ký {$viec}");

    return (array) $dong;
}

// Dùng chung cho các test AI (tests/Feature/Ai*Test.php): AI giả (AI_DRIVER=fake trong phpunit.xml), nội dung mẫu.

/**
 * Bật AI với các ô cài đặt cho trước. Mặc định giai đoạn thử; trần lượt mỗi phút, mỗi ngày đặt cao để không vướng
 * (test trần hệ thống tự đặt lại).
 *
 * @param  array<string, mixed>  $doi
 */
function batAi(array $doi = []): void
{
    CaiDatAi::luu($doi + ['enabled' => true, 'rpm_cap' => 10000, 'rpd_cap' => 1000000], null);
}

/**
 * Người học dùng được AI: đủ tuổi (sinh 1/1/1990), trong danh sách thử, đã đồng ý.
 *
 * @param  array<string, mixed>  $doi
 */
function nguoiHocAi(array $doi = [], bool $thu = true, bool $dongY = true): User
{
    $user = User::factory()->create($doi + ['birth_date' => gmmktime(0, 0, 0, 1, 1, 1990)]);

    if ($thu) {
        DanhSachThu::dat($user, true);
    }

    if ($dongY) {
        DongYAi::ghi($user, true);
    }

    return $user;
}

function aiGia(): BoNoiGia
{
    return app(BoNoiGia::class);
}

/** Mã thẻ mẫu của NoiDungThuSeeder theo mặt trước. */
function theMau(string $front): int
{
    return (int) DB::table('cards')->where('front', $front)->value('id');
}

/** Gọi Giải thích từ cho một thẻ bằng tài khoản đang đăng nhập. */
function giaiThich(int $the): TestResponse
{
    return test()->postJson('/api/v1/ai/giai-thich-tu', ['card_id' => $the]);
}

/**
 * Thêm các dòng nhật ký AI giả lập (vd lượt đã dùng hôm nay).
 *
 * @param  array<string, mixed>  $doi
 */
function dongAi(?User $user, int $soDong, array $doi = []): void
{
    for ($i = 0; $i < $soDong; $i++) {
        DB::table('ai_requests')->insert($doi + [
            'user_id' => $user?->id, 'kind' => 'word', 'provider' => 'fake', 'status' => 'ok', 'cache_hit' => 0,
            'cost_micro_usd' => 0, 'created_at' => now()->getTimestamp(),
        ]);
    }
}

/** Tên mười đường gọi AI (dùng làm dataset); chi tiết từng đường ở duongAi(). */
const DUONG_AI = ['giai-thich-tu', 'dich', 'sua-ngu-phap', 'tra-tu', 'ngu-phap-giai-thich', 'ngu-phap-hoi', 'kiem-dap-an', 'viet-cau', 'tro-chuyen', 'bai-hoc'];

/**
 * Một đường gọi AI kèm thân hợp lệ, ô chữ người học gõ và giới hạn (null nếu đường không có ô chữ), loại prompt.
 * Cần seed nội dung mẫu: (new NoiDungThuSeeder)->tao()->taoLuyenTap(). Trò chuyện: tạo sẵn một cuộc của $user.
 *
 * @return array{url: string, than: array<string, mixed>, o: string|null, toiDa: int|null, loai: string}
 */
function duongAi(string $ten, User $user): array
{
    $brot = (int) DB::table('cards')->where('front', 'das Brot')->value('id');

    return match ($ten) {
        'giai-thich-tu' => ['url' => '/api/v1/ai/giai-thich-tu', 'than' => ['card_id' => $brot], 'o' => null, 'toiDa' => null, 'loai' => 'word'],
        'dich' => ['url' => '/api/v1/ai/dich', 'than' => ['text' => 'Tôi muốn uống cà phê.', 'target' => 'de'], 'o' => 'text', 'toiDa' => 500, 'loai' => 'translate'],
        'sua-ngu-phap' => ['url' => '/api/v1/ai/sua-ngu-phap', 'than' => ['text' => 'Ich bin gestern gegangt.'], 'o' => 'text', 'toiDa' => 800, 'loai' => 'grammar'],
        'tra-tu' => ['url' => '/api/v1/ai/tra-tu', 'than' => ['text' => 'Fernweh'], 'o' => 'text', 'toiDa' => 60, 'loai' => 'lookup'],
        'ngu-phap-giai-thich' => ['url' => '/api/v1/ai/ngu-phap/giai-thich', 'than' => ['slug' => 'g-A1-22', 'section' => 0], 'o' => null, 'toiDa' => null, 'loai' => 'doc'],
        'ngu-phap-hoi' => ['url' => '/api/v1/ai/ngu-phap/hoi', 'than' => ['slug' => 'g-A1-22', 'question' => 'Vì sao là das Brot?'], 'o' => 'question', 'toiDa' => 300, 'loai' => 'ask'],
        'kiem-dap-an' => ['url' => '/api/v1/ai/kiem-dap-an', 'than' => ['card_id' => $brot, 'text' => 'die Semmel'], 'o' => 'text', 'toiDa' => 200, 'loai' => 'answer'],
        'viet-cau' => ['url' => '/api/v1/ai/viet-cau', 'than' => ['card_id' => $brot, 'text' => 'Ich esse Brot.'], 'o' => 'text', 'toiDa' => 200, 'loai' => 'sentence'],
        'tro-chuyen' => ['url' => '/api/v1/ai/tro-chuyen/'.cuocTroChuyen($user).'/tin', 'than' => ['text' => 'Ich möchte ein Wasser.'], 'o' => 'text', 'toiDa' => 300, 'loai' => 'chat'],
        'bai-hoc' => ['url' => '/api/v1/ai/bai-hoc', 'than' => ['topic' => 'Ăn uống', 'level' => 'A1', 'words' => ['das Brot']], 'o' => 'topic', 'toiDa' => 60, 'loai' => 'lesson'],
    };
}

/** Tạo một cuộc trò chuyện (thẳng vào database, không qua cổng AI); trả mã công khai. */
function cuocTroChuyen(User $user, string $tinhHuong = 'restaurant'): string
{
    $ma = (string) Str::ulid();
    DB::table('ai_conversations')->insert(['public_id' => $ma, 'user_id' => $user->id, 'scenario' => $tinhHuong, 'level' => 'A1', 'message_count' => 0, 'created_at' => now()->getTimestamp()]);

    return $ma;
}
