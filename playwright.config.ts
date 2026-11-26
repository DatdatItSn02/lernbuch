import { defineConfig, devices } from '@playwright/test';
import { PHIEN_NGUOI_HOC } from './tests/e2e/phien';

// Test trình duyệt thật cho các trang đã build (npm run build trước khi chạy).
// Cỡ máy tính và cỡ điện thoại đều dùng Chromium để đọc được font máy đã vẽ qua DevTools.
const port = Number(process.env.E2E_PORT ?? 8002);

export default defineConfig({
    testDir: 'tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        trace: 'retain-on-failure',
        // Máy không tải được Chromium của Playwright thì dùng Chrome đã cài: PW_CHANNEL=chrome
        ...(process.env.PW_CHANNEL ? { channel: process.env.PW_CHANNEL } : {}),
    },
    // App học cần đăng nhập: dự án dang-nhap đăng nhập một lần bằng tài khoản thử, hai cỡ màn hình dùng lại phiên đó
    projects: [
        { name: 'dang-nhap', testMatch: /dang-nhap\.setup\.ts/ },
        {
            name: 'may-tinh',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 }, storageState: PHIEN_NGUOI_HOC },
            dependencies: ['dang-nhap'],
        },
        { name: 'dien-thoai', use: { ...devices['Pixel 7'], storageState: PHIEN_NGUOI_HOC }, dependencies: ['dang-nhap'] },
    ],
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${port} --no-ansi`,
        url: `http://127.0.0.1:${port}/up`,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
    },
});
