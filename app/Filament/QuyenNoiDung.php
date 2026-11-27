<?php

namespace App\Filament;

use Filament\Forms\Components\Select;

/**
 * Quyền ở các trang nội dung: xem và sửa cần content.edit; đổi trạng thái (xuất bản, ẩn) cần thêm content.publish,
 * người chỉ có quyền sửa thấy ô trạng thái nhưng không đổi được (giá trị cũ giữ nguyên khi lưu).
 */
final class QuyenNoiDung
{
    public const TRANG_THAI = ['published' => 'Đã xuất bản', 'draft' => 'Nháp', 'in_review' => 'Chờ duyệt', 'archived' => 'Đã ẩn'];

    public const CAP = [1 => 'A1', 2 => 'A2', 3 => 'B1', 4 => 'B2', 5 => 'C1', 6 => 'C2'];

    public static function sua(): bool
    {
        return auth()->user()?->can('content.edit') ?? false;
    }

    public static function xuatBan(): bool
    {
        return auth()->user()?->can('content.publish') ?? false;
    }

    public static function oTrangThai(): Select
    {
        return Select::make('status')->label('Trạng thái')
            ->options(self::TRANG_THAI)
            ->required()
            ->selectablePlaceholder(false)
            ->disabled(fn () => ! self::xuatBan())
            ->dehydrated(fn () => self::xuatBan())
            ->helperText(fn () => self::xuatBan() ? 'Người học chỉ thấy nội dung đã xuất bản.' : 'Cần quyền xuất bản nội dung để đổi trạng thái.');
    }

    public static function mauTrangThai(string $trangThai): string
    {
        return match ($trangThai) {
            'published' => 'success',
            'draft', 'in_review' => 'warning',
            default => 'gray',
        };
    }
}
