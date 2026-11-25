import { cleanup } from '@testing-library/react';
import { afterEach, beforeEach, vi } from 'vitest';

// jsdom chưa có cuộn trang: thay bằng hàm rỗng để khỏi báo "Not implemented"
Object.defineProperty(window, 'scrollTo', { value: () => {}, writable: true });

beforeEach(() => {
    window.localStorage.clear();
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    const html = document.documentElement;
    delete html.dataset.theme;
    delete html.dataset.fontSize;
    delete html.dataset.season;
    document.title = '';
});
