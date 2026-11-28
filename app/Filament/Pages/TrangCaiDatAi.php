<?php

namespace App\Filament\Pages;

use App\Support\Ai\BoNoi\ChonBoNoi;
use App\Support\Ai\CaiDatAi;
use App\Support\NhatKy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Cài đặt AI: công tắc tắt khẩn cấp, ai được dùng, nhà cung cấp, model, các trần, hạn mức giai đoạn thử, giá quy đổi.
 * Cần quyền ai.manage; mỗi lần lưu có đổi thì ghi nhật ký. Khoá AI không bao giờ hiện ở đây, chỉ hiện "đã có" hay "chưa có".
 *
 * @property-read Schema $form
 */
class TrangCaiDatAi extends Page
{
    protected static ?string $slug = 'cai-dat-ai';

    protected static ?string $title = 'Cài đặt AI';

    protected static ?string $navigationLabel = 'Cài đặt AI';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Hệ thống';

    protected static ?int $navigationSort = 31;

    public const AI_DUNG = ['testers' => 'Chỉ danh sách thử', 'adults' => 'Mọi tài khoản từ 18 tuổi'];

    public const NHA_CUNG_CAP = ['gemini' => 'Gemini', 'openai_compatible' => 'Dịch vụ kiểu OpenAI'];

    public const TEN_BO_NOI = ['fake' => 'AI giả (máy dev, test)', 'gemini' => 'Gemini', 'openai_compatible' => 'Dịch vụ kiểu OpenAI'];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ai.manage') ?? false;
    }

    public function mount(): void
    {
        $cd = CaiDatAi::tatCa();
        $this->form->fill([
            ...$cd,
            'price_in' => $cd['price_usd_per_mtok']['in'],
            'price_out' => $cd['price_usd_per_mtok']['out'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $nguyen = fn (string $ten, string $nhan, int $tu, int $den) => TextInput::make($ten)->label($nhan)->required()->integer()->minValue($tu)->maxValue($den);
        $so = fn (string $ten, string $nhan, float $tu, float $den) => TextInput::make($ten)->label($nhan)->required()->numeric()->minValue($tu)->maxValue($den)->step(0.01);

        return $schema->statePath('data')->components([
            Section::make('Bật tắt')
                ->description('Tắt AI có hiệu lực ngay ở lượt hỏi kế tiếp; phần học không bị ảnh hưởng.')
                ->columns(2)
                ->schema([
                    Toggle::make('enabled')->label('Bật AI'),
                    Select::make('audience')->label('Ai được dùng')->options(self::AI_DUNG)->required(),
                ]),
            Section::make('Nhà cung cấp')
                ->columns(2)
                ->schema([
                    Select::make('provider')->label('Nhà cung cấp')->options(self::NHA_CUNG_CAP)->required(),
                    TextInput::make('default_model')->label('Model')->required()->maxLength(60)->regex('/^[A-Za-z0-9._-]{1,60}$/'),
                ]),
            Section::make('Hạn mức và trần')
                ->description('Lượt mỗi phút và mỗi ngày nên đặt bằng khoảng 80% hạn mức Google cấp cho project. Chạm trần thì người học được hẹn thử lại, không mất lượt.')
                ->columns(2)
                ->schema([
                    $nguyen('rpm_cap', 'Lượt mỗi phút', 1, 10000),
                    $nguyen('rpd_cap', 'Lượt mỗi ngày (tính theo giờ Thái Bình Dương)', 1, 1000000),
                    $nguyen('max_concurrent', 'Lượt cùng lúc', 1, 10),
                    $so('daily_spend_cap_usd', 'Chi phí mỗi ngày (USD)', 0.1, 1000),
                    $nguyen('trial_daily_cap', 'Hạn mức giai đoạn thử (lượt mỗi người mỗi ngày)', 0, 500),
                    $so('price_in', 'Giá vào (USD cho 1 triệu token)', 0, 1000),
                    $so('price_out', 'Giá ra (USD cho 1 triệu token)', 0, 1000),
                ]),
            Section::make('Đang chạy')
                ->columns(3)
                ->schema([
                    TextEntry::make('bo_noi_dang_chay')->label('Bộ nối đang chạy')->state(fn () => self::TEN_BO_NOI[ChonBoNoi::ten()] ?? ChonBoNoi::ten()),
                    TextEntry::make('khoa_gemini')->label('Khoá Gemini')->state(fn () => ChonBoNoi::khoa('gemini') !== '' ? 'Đã có' : 'Chưa có'),
                    TextEntry::make('tuoi_toi_thieu')->label('Tuổi tối thiểu')->state(fn () => CaiDatAi::tuoiToiThieu(CaiDatAi::tatCa()).' tuổi'),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label('Lưu')->submit('save')->keyBindings(['mod+s'])]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        /** @var array<string, mixed> $d */
        $d = $this->form->getState();
        $truoc = CaiDatAi::tatCa();
        $nguoi = auth()->id();
        CaiDatAi::luu([
            'enabled' => (bool) $d['enabled'],
            'audience' => (string) $d['audience'],
            'provider' => (string) $d['provider'],
            'default_model' => trim((string) $d['default_model']),
            'rpm_cap' => (int) $d['rpm_cap'],
            'rpd_cap' => (int) $d['rpd_cap'],
            'max_concurrent' => (int) $d['max_concurrent'],
            'daily_spend_cap_usd' => self::so($d['daily_spend_cap_usd']),
            'trial_daily_cap' => (int) $d['trial_daily_cap'],
            'price_usd_per_mtok' => ['in' => self::so($d['price_in']), 'out' => self::so($d['price_out'])],
        ], $nguoi !== null ? (int) $nguoi : null);
        $sau = CaiDatAi::tatCa();
        $doi = array_keys(array_filter($sau, fn ($v, $k) => $v !== $truoc[$k], ARRAY_FILTER_USE_BOTH));

        if ($doi !== []) {
            $ten = array_map(fn ($k) => 'ai.'.$k, $doi);
            NhatKy::ghi('setting.update', null, array_combine($ten, array_map(fn ($k) => $truoc[$k], $doi)), array_combine($ten, array_map(fn ($k) => $sau[$k], $doi)));
        }

        Notification::make()->success()->title('Đã lưu')->send();
    }

    /** Số từ ô nhập: số nguyên thì giữ kiểu int (20 chứ không phải 20.0), để lưu lại y như cũ không bị coi là đổi. */
    private static function so(mixed $v): int|float
    {
        $f = is_numeric($v) ? (float) $v : 0.0;

        return floor($f) === $f ? (int) $f : $f;
    }
}
