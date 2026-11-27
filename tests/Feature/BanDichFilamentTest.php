<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

// Trang quản trị (Filament) bằng tiếng Việt (mục tự kiểm 19): mọi khoá dịch tiếng Anh của các gói Filament đều có bản tiếng
// Việt, của gói hoặc bổ sung ở lang/vendor, nên không bao giờ hiện mã khoá (vd "filament-panels::layout.navigation.label"
// làm tên của thanh điều hướng). Nâng Filament mà có khoá mới chưa dịch thì test này hỏng, chỉ đúng khoá cần dịch.

/**
 * Khoá dịch tiếng Anh của Filament chưa có tiếng Việt.
 *
 * @return list<string>
 */
function khoaFilamentThieuTiengViet(): array
{
    // Thư mục gói trong vendor/filament → namespace dịch; gói khác là "filament-<tên thư mục>"
    $namespace = ['filament' => 'filament-panels', 'support' => 'filament'];
    $thieu = [];

    foreach (glob(base_path('vendor/filament/*/resources/lang/en')) ?: [] as $en) {
        $goi = basename(dirname($en, 3));
        $ns = $namespace[$goi] ?? "filament-{$goi}";
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($en, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $nhom = str_replace('\\', '/', substr($file->getPathname(), strlen($en) + 1, -4));

            foreach (array_keys(Arr::dot(require $file->getPathname())) as $khoa) {
                if (! Lang::has("{$ns}::{$nhom}.{$khoa}", 'vi', false)) {
                    $thieu[] = "{$ns}::{$nhom}.{$khoa}";
                }
            }
        }
    }

    sort($thieu);

    return $thieu;
}

it('mọi khoá dịch của Filament có tiếng Việt (của gói hoặc lang/vendor), trang quản trị không hiện mã khoá', function () {
    expect(glob(base_path('vendor/filament/*/resources/lang/en')))->not->toBeEmpty()
        ->and(khoaFilamentThieuTiengViet())->toBe([])
        ->and(__('filament-panels::layout.navigation.label', locale: 'vi'))->toBe('Thanh điều hướng bên')
        ->and(__('filament::components/copyable.label', ['label' => 'lan@vidu.test'], 'vi'))->toBe('Sao chép lan@vidu.test')
        // File bổ sung gộp với bản dịch của gói, không thay cả file
        ->and(__('filament::components/copyable.messages.copied', locale: 'vi'))->toBe('Đã sao chép')
        ->and(trans_choice('filament-tables::table.result_count', 0, [], 'vi'))->toBe('Không có kết quả')
        ->and(trans_choice('filament-tables::table.result_count', 3, ['count' => 3], 'vi'))->toBe('3 kết quả');
});

it('khoá không có thì cách kiểm báo thiếu (không lẫn với khoá có thật)', function () {
    expect(Lang::has('filament-panels::layout.khoa_khong_co', 'vi', false))->toBeFalse()
        ->and(Lang::has('filament-panels::layout.navigation.label', 'vi', false))->toBeTrue()
        ->and(Lang::has('filament-panels::layout.navigation.label', 'xx', false))->toBeFalse();
});

it('trang quản trị không còn mã khoá dịch nào trong HTML', function () {
    $this->seed();
    $this->actingAs(nhanVien('super_admin'));
    foreach (['/admin', '/admin/nguoi-hoc', '/admin/nguon', '/admin/nguon/create'] as $trang) {
        expect($this->get($trang)->assertOk()->getContent())->not->toMatch('/filament[a-z-]*::[a-z]/');
    }
});
