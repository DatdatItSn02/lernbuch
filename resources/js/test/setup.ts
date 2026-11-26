import { cleanup } from '@testing-library/react';
import { afterEach, beforeEach, vi } from 'vitest';

// Test chạy trong Node (không có trình duyệt, vd tests/js) thì bỏ qua phần dọn trình duyệt
const coTrinhDuyet = typeof window !== 'undefined';

// jsdom chưa có cuộn trang: thay bằng hàm rỗng để khỏi báo "Not implemented"
if (coTrinhDuyet) Object.defineProperty(window, 'scrollTo', { value: () => {}, writable: true });

beforeEach(() => {
    if (coTrinhDuyet) window.localStorage.clear();
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    vi.useRealTimers();
    if (!coTrinhDuyet) return;
    cleanup();
    const html = document.documentElement;
    delete html.dataset.theme;
    delete html.dataset.fontSize;
    delete html.dataset.season;
    document.title = '';
});
