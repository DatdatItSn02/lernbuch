<?php

namespace App\Filament\Pages;

use App\Support\Hoc\CaiDatHoc;
use App\Support\NhatKy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
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
 * Cài đặt của phần học: ngưỡng chấm tối thiểu theo chế độ. Chấm nhanh hơn ngưỡng thì vẫn ghi tiến độ nhưng không cộng xu.
 * Cần quyền settings.manage; mỗi lần lưu ghi nhật ký thao tác.
 *
 * @property-read Schema $form
 */
class TrangCaiDatHoc extends Page
{
    protected static ?string $slug = 'cai-dat-hoc';

    protected static ?string $title = 'Cài đặt học';

    protected static ?string $navigationLabel = 'Cài đặt học';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Hệ thống';

    protected static ?int $navigationSort = 30;

    /** Tên chế độ hiện trên trang. */
    public const TEN_CHE_DO = [
        'flashcard' => 'Flashcard', 'quiz' => 'Trắc nghiệm', 'listening' => 'Nghe', 'typing' => 'Gõ từ',
        'match' => 'Ghép cặp', 'mixed' => 'Hỗn hợp', 'sentence' => 'Đặt câu', 'cloze' => 'Điền câu (bài tập)',
    ];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['nguong' => CaiDatHoc::nguong()]);
    }

    public function form(Schema $schema): Schema
    {
        $o = [];

        foreach (CaiDatHoc::CHE_DO_CHAM as $cheDo) {
            $o[] = TextInput::make("nguong.{$cheDo}")
                ->label(self::TEN_CHE_DO[$cheDo])
                ->required()
                ->integer()
                ->minValue(0)
                ->maxValue(CaiDatHoc::NGUONG_TOI_DA)
                ->suffix('ms');
        }

        return $schema->statePath('data')->components([
            Section::make('Ngưỡng chấm tối thiểu')
                ->description('Chấm một thẻ nhanh hơn ngưỡng (tính từ lượt chấm trước, hoặc từ lúc thẻ hiện) thì vẫn ghi tiến độ nhưng không cộng xu. 0 là không chặn.')
                ->columns(2)
                ->schema($o),
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

        /** @var array{nguong: array<string, int|string>} $data */
        $data = $this->form->getState();
        $truoc = CaiDatHoc::nguong();
        $nguoi = auth()->id();
        CaiDatHoc::luuNguong(array_map(fn ($v) => (int) $v, $data['nguong']), $nguoi !== null ? (int) $nguoi : null);
        $sau = CaiDatHoc::nguong();

        if ($sau !== $truoc) {
            NhatKy::ghi('setting.update', null, [CaiDatHoc::TEN_NGUONG => $truoc], [CaiDatHoc::TEN_NGUONG => $sau]);
        }

        Notification::make()->success()->title('Đã lưu')->send();
    }
}
