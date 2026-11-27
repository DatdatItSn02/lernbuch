<?php

namespace App\Filament\Resources\ChuDes;

use App\Filament\QuyenNoiDung;
use App\Filament\Resources\ChuDes\Pages\EditChuDe;
use App\Filament\Resources\ChuDes\Pages\ListChuDes;
use App\Models\Topic;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Chủ đề 3 tầng: sửa tên (tiếng Việt, tiếng Anh), biểu tượng, thứ tự, trạng thái. Mã, đường dẫn, cây chủ đề giữ theo lần nhập. */
class ChuDeResource extends Resource
{
    protected static ?string $model = Topic::class;

    protected static ?string $slug = 'chu-de';

    protected static ?string $modelLabel = 'chủ đề';

    protected static ?string $pluralModelLabel = 'Chủ đề';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Nội dung';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name_vi';

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
        return $schema->components([
            TextInput::make('code')->label('Mã')->disabled()->dehydrated(false),
            TextInput::make('slug')->label('Đường dẫn')->disabled()->dehydrated(false),
            TextInput::make('name_vi')->label('Tên tiếng Việt')->required()->maxLength(160),
            TextInput::make('name_en')->label('Tên tiếng Anh')->maxLength(160),
            TextInput::make('icon')->label('Biểu tượng (mã OpenMoji)')->maxLength(40)->regex('/^[A-Za-z0-9_-]*$/'),
            TextInput::make('sort')->label('Thứ tự')->integer()->required()->minValue(-100000)->maxValue(100000),
            QuyenNoiDung::oTrangThai(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('parent'))
            ->columns([
                TextColumn::make('name_vi')->label('Chủ đề')->searchable(['name_vi', 'name_en', 'code'])->sortable()
                    ->description(fn (Topic $record) => $record->parent !== null ? $record->parent->name_vi : null),
                TextColumn::make('depth')->label('Tầng')->formatStateUsing(fn (int $state) => Topic::TANG[$state] ?? (string) $state),
                TextColumn::make('collection')->label('Loại thẻ')->placeholder('—'),
                TextColumn::make('card_count')->label('Số thẻ')->numeric()->sortable(),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => QuyenNoiDung::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => QuyenNoiDung::mauTrangThai($state)),
                TextColumn::make('code')->label('Mã')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('depth')->label('Tầng')->options(Topic::TANG),
                SelectFilter::make('status')->label('Trạng thái')->options(QuyenNoiDung::TRANG_THAI),
            ])
            ->defaultSort('sort')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChuDes::route('/'),
            'edit' => EditChuDe::route('/{record}/edit'),
        ];
    }
}
