<?php

namespace App\Providers;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tự kiểm G0 mục 9: cố ý làm /up lỗi trên máy chủ thật để xem workflow tự quay lại. Gỡ ngay sau lần thử.
        Event::listen(DiagnosingHealth::class, function () {
            if (app()->environment('production')) {
                throw new \RuntimeException('Cố ý làm /up lỗi để thử mục 9.');
            }
        });
    }
}
