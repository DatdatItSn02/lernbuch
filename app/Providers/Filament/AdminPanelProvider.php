<?php

namespace App\Providers\Filament;

use Filament\Actions\Action;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Table;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /** Giờ hiện trong trang quản trị (database lưu Unix timestamp, app chạy giờ UTC). */
    public const MUI_GIO = 'Asia/Ho_Chi_Minh';

    /** Tên đọc được của nút Lọc trên bảng: kèm số bộ lọc đang dùng, đúng số hiện trên nút. */
    public static function nhanNutLoc(int $dangDung): string
    {
        return "Lọc, đang dùng {$dangDung}";
    }

    public function boot(): void
    {
        FilamentTimezone::set(self::MUI_GIO);

        // Nút Lọc của bảng là nút biểu tượng kèm số bộ lọc đang dùng; tên đọc được phải chứa chữ nhìn thấy (WCAG 2.5.3)
        Table::configureUsing(fn (Table $table) => $table->filtersTriggerAction(
            fn (Action $action) => $action->label(fn (): string => self::nhanNutLoc($table->getActiveFiltersCount())),
        ));
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->brandName('Lernbuch')
            ->path('admin')
            ->login()
            // Cùng bảng màu với app học (design/tokens.css, bản sáng); font tự lưu trữ nằm trong theme.
            ->colors([
                'primary' => Color::hex('#442e9d'),
                'success' => Color::hex('#21763c'),
                'danger' => Color::hex('#b71824'),
                'warning' => Color::hex('#e6ac3d'),
                'info' => Color::hex('#2266a4'),
                'gray' => Color::Slate,
            ])
            ->font('Be Vietnam Pro', provider: LocalFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->navigationGroups(['Người dùng', 'Nội dung', 'Hệ thống'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
