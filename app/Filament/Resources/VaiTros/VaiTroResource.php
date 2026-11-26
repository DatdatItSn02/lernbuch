<?php

namespace App\Filament\Resources\VaiTros;

use App\Filament\Resources\VaiTros\Pages\EditVaiTro;
use App\Filament\Resources\VaiTros\Pages\ListVaiTros;
use App\Models\Role;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Vai trò và quyền của nhân viên. Danh sách vai trò có sẵn trong seed.sql; ở đây chỉ đổi tên hiện và quyền. */
class VaiTroResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $slug = 'vai-tro';

    protected static ?string $modelLabel = 'vai trò';

    protected static ?string $pluralModelLabel = 'Vai trò';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Hệ thống';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'label';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('roles.manage') ?? false;
    }

    /** Quản trị cao nhất đã có mọi quyền (Gate::before) và vai trò người học không dùng ở trang quản trị: không sửa. */
    public static function canEdit(Model $record): bool
    {
        return $record instanceof Role && ! in_array($record->name, [Role::CAO_NHAT, Role::NGUOI_HOC], true) && static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Mã')->disabled()->dehydrated(false),
            TextInput::make('label')->label('Tên hiện')->required()->maxLength(160),
            Textarea::make('description')->label('Mô tả')->maxLength(255)->columnSpanFull(),
            CheckboxList::make('permissions')
                ->label('Quyền')
                ->relationship('permissions', 'label')
                ->getOptionLabelFromRecordUsing(fn (Model $quyen) => $quyen->getAttribute('label').' ('.$quyen->getAttribute('name').')')
                ->columns(2)
                ->bulkToggleable()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['permissions', 'users']))
            ->columns([
                TextColumn::make('label')->label('Vai trò')->searchable()->description(fn (Role $record) => $record->name),
                TextColumn::make('permissions_count')->label('Số quyền')
                    ->formatStateUsing(fn (Role $record, int $state) => $record->name === Role::CAO_NHAT ? 'Tất cả' : (string) $state),
                TextColumn::make('users_count')->label('Số người')->numeric(),
            ])
            ->defaultSort('id')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVaiTros::route('/'),
            'edit' => EditVaiTro::route('/{record}/edit'),
        ];
    }
}
