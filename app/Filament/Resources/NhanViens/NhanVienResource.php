<?php

namespace App\Filament\Resources\NhanViens;

use App\Filament\Resources\NhanViens\Pages\CreateNhanVien;
use App\Filament\Resources\NhanViens\Pages\EditNhanVien;
use App\Filament\Resources\NhanViens\Pages\ListNhanViens;
use App\Models\Role;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
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

/** Tài khoản nhân viên (users.is_staff = 1): tạo, gán vai trò, khoá. Người học quản lý ở trang khác (tuần 5). */
class NhanVienResource extends Resource
{
    /** Mật khẩu nhân viên dài hơn của người học (8) vì trang quản trị không dùng xác thực hai bước. */
    public const MAT_KHAU_TOI_THIEU = 12;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'nhan-vien';

    protected static ?string $modelLabel = 'nhân viên';

    protected static ?string $pluralModelLabel = 'Nhân viên';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Hệ thống';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('roles.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_staff', true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Tên')->required()->maxLength(100),
            TextInput::make('email')->label('Email')->email()->required()->maxLength(191)
                ->unique(table: 'users', column: 'email', ignoreRecord: true)
                ->disabledOn('edit'),
            TextInput::make('password')->label('Mật khẩu')->password()->revealable()
                ->minLength(self::MAT_KHAU_TOI_THIEU)->maxLength(72)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText(fn (string $operation) => $operation === 'create'
                    ? 'Ít nhất '.self::MAT_KHAU_TOI_THIEU.' ký tự.'
                    : 'Để trống nếu không đổi. Ít nhất '.self::MAT_KHAU_TOI_THIEU.' ký tự.'),
            Select::make('status')->label('Trạng thái')
                ->options(['active' => 'Đang làm', 'suspended' => 'Đã khoá'])
                ->required()->default('active')->selectablePlaceholder(false)
                ->hiddenOn('create')
                // Không tự khoá chính mình
                ->disabled(fn (?Model $record) => $record !== null && $record->getKey() === auth()->id()),
            CheckboxList::make('roles')->label('Vai trò')
                ->relationship('roles', 'label', fn (Builder $query) => $query->where('name', '!=', Role::NGUOI_HOC)->orderBy('id'))
                ->getOptionLabelFromRecordUsing(fn (Model $vaiTro) => $vaiTro->getAttribute('label') ?: $vaiTro->getAttribute('name'))
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->columns([
                TextColumn::make('name')->label('Tên')->searchable()->sortable()->description(fn (User $record) => $record->email),
                TextColumn::make('roles.label')->label('Vai trò')->badge(),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'active' ? 'Đang làm' : 'Đã khoá')
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('last_login_at')->label('Đăng nhập lần cuối')->dateTime('d/m/Y H:i')->placeholder('Chưa đăng nhập'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(['active' => 'Đang làm', 'suspended' => 'Đã khoá']),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNhanViens::route('/'),
            'create' => CreateNhanVien::route('/create'),
            'edit' => EditNhanVien::route('/{record}/edit'),
        ];
    }
}
