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
        // Tự kiểm G0 mục 9: cố ý làm /up lỗi trên bản phát hành (có file RELEASE) để xem workflow tự quay lại. Gỡ ngay sau lần thử.
        Event::listen(DiagnosingHealth::class, function () {
            if (config('app.release') !== 'dev') {
                throw new \RuntimeException('Cố ý làm /up lỗi để thử mục 9.');
            }
        });
    }
}
