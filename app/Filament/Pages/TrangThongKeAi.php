<?php

namespace App\Filament\Pages;

use App\Support\QuanTri\ThongKeAi;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Thống kê AI theo ngày (giờ Thái Bình Dương) và theo loại; cần quyền ai.manage. Chỉ xem. */
class TrangThongKeAi extends Page
{
    protected static ?string $slug = 'thong-ke-ai';

    protected static ?string $title = 'Thống kê AI';

    protected static ?string $navigationLabel = 'Thống kê AI';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Hệ thống';

    protected static ?int $navigationSort = 33;

    protected string $view = 'filament.pages.thong-ke-ai';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ai.manage') ?? false;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['homNay' => ThongKeAi::homNay(), 'ngay' => ThongKeAi::theoNgay(), 'loai' => ThongKeAi::theoLoai()];
    }
}
