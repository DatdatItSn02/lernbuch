<?php

namespace App\Filament\Resources\NguoiHocs;

use App\Filament\Resources\NguoiHocs\Pages\ListNguoiHocs;
use App\Filament\Resources\NguoiHocs\Pages\ViewNguoiHoc;
use App\Models\User;
use App\Support\QuanTri\KhoaTaiKhoan;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Người học (users không phải nhân viên): tìm theo tên hoặc email, xem tiến độ, khoá hoặc mở khoá (cần users.manage).
 * Không tạo, sửa, xoá ở đây: dữ liệu học (tiến độ, sổ tay, ghi chú) là của người học. Đường dẫn dùng mã ULID.
 */
class NguoiHocResource extends Resource
{
    public const TRANG_THAI = [KhoaTaiKhoan::DANG_HOC => 'Đang học', KhoaTaiKhoan::BI_KHOA => 'Đã khoá'];

    protected static ?string $model = User::class;

    protected static ?string $slug = 'nguoi-hoc';

    protected static ?string $recordRouteKeyName = 'public_id';

    protected static ?string $modelLabel = 'người học';

    protected static ?string $pluralModelLabel = 'Người học';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Người dùng';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('users.view') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess() && $record instanceof User && ! $record->is_staff;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
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

    /** Khoá, mở khoá: cần quyền users.manage. */
    public static function duocKhoa(): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_staff', false);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Số thẻ đã học (mức 1 trở lên), đếm bằng truy vấn con để sắp xếp được
            ->modifyQueryUsing(fn (Builder $query) => $query->addSelect([
                'so_the_da_hoc' => DB::table('card_progress')->selectRaw('COUNT(*)')->whereColumn('card_progress.user_id', 'users.id')->where('card_progress.box', '>', 0),
            ]))
            ->columns([
                TextColumn::make('name')->label('Tên')->searchable(['name', 'email'])->sortable()->description(fn (User $record) => $record->email),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => self::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => $state === KhoaTaiKhoan::DANG_HOC ? 'success' : 'danger'),
                TextColumn::make('so_the_da_hoc')->label('Thẻ đã học')->numeric()->sortable(),
                TextColumn::make('email_verified_at')->label('Xác minh email')->date('d/m/Y')->placeholder('Chưa xác minh'),
                TextColumn::make('created_at')->label('Ngày đăng ký')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('last_login_at')->label('Đăng nhập lần cuối')->dateTime('d/m/Y H:i')->sortable()->placeholder('Chưa đăng nhập'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(self::TRANG_THAI),
                TernaryFilter::make('email_verified_at')->label('Xác minh email')->nullable()
                    ->trueLabel('Đã xác minh')->falseLabel('Chưa xác minh'),
            ])
            ->searchPlaceholder('Tìm tên hoặc email')
            ->defaultSort('created_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNguoiHocs::route('/'),
            'view' => ViewNguoiHoc::route('/{record}'),
        ];
    }
}
