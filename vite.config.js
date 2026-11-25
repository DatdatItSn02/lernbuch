import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // app.css + app.js: trang Blade công khai; app/main.tsx: app học React;
            // filament/admin/theme.css: trang quản trị (cần vendor/filament, nên chạy composer install trước khi build).
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/app/main.tsx',
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
