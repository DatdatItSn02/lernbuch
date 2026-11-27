<?php

namespace App\Filament\Resources\NguPhaps;

use App\Filament\QuyenNoiDung;
use App\Filament\Resources\NguPhaps\Pages\EditNguPhap;
use App\Filament\Resources\NguPhaps\Pages\ListNguPhaps;
use App\Models\GrammarChapter;
use App\Models\GrammarLesson;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Bài ngữ pháp: sửa tiêu đề, cấp, chương, thứ tự, trạng thái và nội dung (danh sách khối dạng JSON, như app học vẽ).
 * Nội dung sai dạng thì không lưu, báo khối nào sai.
 */
class NguPhapResource extends Resource
{
    /** Cấp của bài ngữ pháp: A1–B2; không có cấp là chuyên đề. */
    public const CAP = [1 => 'A1', 2 => 'A2', 3 => 'B1', 4 => 'B2'];

    protected static ?string $model = GrammarLesson::class;

    protected static ?string $slug = 'ngu-phap';

    protected static ?string $modelLabel = 'bài ngữ pháp';

    protected static ?string $pluralModelLabel = 'Ngữ pháp';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Nội dung';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return QuyenNoiDung::sua();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Bài')->columns(3)->schema([
                TextInput::make('title')->label('Tiêu đề')->required()->maxLength(200)->columnSpan(2),
                TextInput::make('slug')->label('Đường dẫn')->disabled()->dehydrated(false),
                Select::make('level')->label('Cấp')->options(self::CAP)->placeholder('Chuyên đề (không có cấp)'),
                Select::make('chapter_id')->label('Chương')->relationship('chapter', 'name_vi')
                    ->getOptionLabelFromRecordUsing(fn (GrammarChapter $c) => $c->code.' · '.$c->name_vi)
                    ->placeholder('Không thuộc chương'),
                TextInput::make('sort')->label('Thứ tự')->integer()->required()->minValue(-100000)->maxValue(100000),
                TextInput::make('summary')->label('Tóm tắt')->maxLength(500)->columnSpan(2),
                QuyenNoiDung::oTrangThai(),
            ]),
            Section::make('Nội dung')->description('Danh sách khối dạng JSON, mỗi khối có t là loại: '.implode(', ', GrammarLesson::LOAI_KHOI).'.')->schema([
                Textarea::make('blocks')->hiddenLabel()->rows(24)->required()
                    ->extraInputAttributes(['class' => 'font-mono', 'spellcheck' => 'false'])
                    ->formatStateUsing(fn (mixed $state) => is_array($state) ? (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $state)
                    ->dehydrateStateUsing(fn (?string $state) => json_decode((string) $state, true))
                    ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $loi = GrammarLesson::kiemKhoi(is_string($value) ? $value : '');

                        if ($loi !== null) {
                            $fail($loi);
                        }
                    }]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('chapter'))
            ->columns([
                TextColumn::make('title')->label('Bài')->searchable(['title', 'slug'])->sortable()->description(fn (GrammarLesson $record) => $record->slug),
                TextColumn::make('level')->label('Cấp')->formatStateUsing(fn (?int $state) => $state !== null ? (self::CAP[$state] ?? (string) $state) : null)->placeholder('Chuyên đề'),
                TextColumn::make('chapter.code')->label('Chương')->placeholder('—'),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => QuyenNoiDung::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => QuyenNoiDung::mauTrangThai($state)),
                TextColumn::make('updated_at')->label('Sửa lần cuối')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('level')->label('Cấp')->options(self::CAP),
                SelectFilter::make('status')->label('Trạng thái')->options(QuyenNoiDung::TRANG_THAI),
            ])
            ->defaultSort('sort')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNguPhaps::route('/'),
            'edit' => EditNguPhap::route('/{record}/edit'),
        ];
    }
}
