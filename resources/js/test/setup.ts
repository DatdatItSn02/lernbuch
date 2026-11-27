import { cleanup, configure } from '@testing-library/react';
import { afterEach, beforeEach, vi } from 'vitest';
import { quenDaTai } from '../app/lib/useApi';

// Máy CI chạy nhiều file test song song: lần render đầu của một file có thể chậm hơn 1 giây mặc định của findBy/waitFor
configure({ asyncUtilTimeout: 3000 });

// Test chạy trong Node (không có trình duyệt, vd tests/js) thì bỏ qua phần dọn trình duyệt
const coTrinhDuyet = typeof window !== 'undefined';

// jsdom chưa có cuộn trang: thay bằng hàm rỗng để khỏi báo "Not implemented"
if (coTrinhDuyet) Object.defineProperty(window, 'scrollTo', { value: () => {}, writable: true });

beforeEach(() => {
    // Bản nhớ dữ liệu đã tải của useApi nằm ở cấp module: xoá để test này không thấy dữ liệu của test trước
    quenDaTai();
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
