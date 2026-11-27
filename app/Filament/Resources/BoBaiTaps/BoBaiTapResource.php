<?php

namespace App\Filament\Resources\BoBaiTaps;

use App\Filament\QuyenNoiDung;
use App\Filament\Resources\BoBaiTaps\Pages\EditBoBaiTap;
use App\Filament\Resources\BoBaiTaps\Pages\ListBoBaiTaps;
use App\Filament\Resources\BoBaiTaps\RelationManagers\CauBaiTapRelationManager;
use App\Filament\Resources\NguPhaps\NguPhapResource;
use App\Models\ExerciseSet;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Bộ bài tập (theo cấp, Ôn tập, Số nhiều): sửa tên, mô tả, nhóm, cấp, thứ tự, trạng thái; sửa từng câu ở bảng câu bên dưới. */
class BoBaiTapResource extends Resource
{
    protected static ?string $model = ExerciseSet::class;

    protected static ?string $slug = 'bai-tap';

    protected static ?string $modelLabel = 'bộ bài tập';

    protected static ?string $pluralModelLabel = 'Bài tập';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Nội dung';

    protected static ?int $navigationSort = 4;

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
        return $schema->columns(3)->components([
            TextInput::make('title')->label('Tên bộ')->required()->maxLength(200)->columnSpan(2),
            TextInput::make('slug')->label('Đường dẫn')->disabled()->dehydrated(false),
            TextInput::make('group_name')->label('Nhóm hiện trên trang')->maxLength(160)->columnSpan(2),
            Select::make('section')->label('Phần')->options(ExerciseSet::PHAN)->disabled()->dehydrated(false),
            TextInput::make('description')->label('Mô tả (ghi nhớ khi làm cả bộ)')->maxLength(500)->columnSpan(2),
            Select::make('level')->label('Cấp')->options(NguPhapResource::CAP)->placeholder('Không có cấp'),
            TextInput::make('sort')->label('Thứ tự')->integer()->required()->minValue(-100000)->maxValue(100000),
            QuyenNoiDung::oTrangThai(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Bộ bài tập')->searchable(['title', 'slug', 'group_name'])->sortable()
                    ->description(fn (ExerciseSet $record) => $record->getAttribute('group_name')),
                TextColumn::make('section')->label('Phần')->formatStateUsing(fn (string $state) => ExerciseSet::PHAN[$state] ?? $state),
                TextColumn::make('level')->label('Cấp')->formatStateUsing(fn (?int $state) => $state !== null ? (NguPhapResource::CAP[$state] ?? (string) $state) : null)->placeholder('—'),
                TextColumn::make('item_count')->label('Số câu')->numeric(),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => QuyenNoiDung::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => QuyenNoiDung::mauTrangThai($state)),
            ])
            ->filters([
                SelectFilter::make('section')->label('Phần')->options(ExerciseSet::PHAN),
                SelectFilter::make('level')->label('Cấp')->options(NguPhapResource::CAP),
                SelectFilter::make('status')->label('Trạng thái')->options(QuyenNoiDung::TRANG_THAI),
            ])
            ->defaultSort('sort')
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [CauBaiTapRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBoBaiTaps::route('/'),
            'edit' => EditBoBaiTap::route('/{record}/edit'),
        ];
    }
}
