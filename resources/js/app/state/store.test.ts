import { renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { SETTINGS_KEY, canPersist, defaultSettings, initialState, loadSettings, reducer, sanitizeSettings, seasonOf, useStore } from './store';

describe('cài đặt lưu trên trình duyệt', () => {
    it('chưa lưu gì thì dùng mặc định', () => {
        expect(loadSettings()).toEqual(defaultSettings);
    });

    it('JSON hỏng thì dùng mặc định', () => {
        window.localStorage.setItem(SETTINGS_KEY, '{"theme":');
        expect(loadSettings()).toEqual(defaultSettings);
    });

    it('giữ giá trị đúng, bỏ giá trị lạ', () => {
        expect(
            sanitizeSettings({ theme: 'dark', fontSize: 'xl', season: 'winter', newPerSession: '20', langs: { vi: false, zh: 1 }, them: 'rác' }),
        ).toEqual({ ...defaultSettings, theme: 'dark', season: 'winter', langs: { vi: false, en: true, zh: true } });
    });

    it('không phải object (null, mảng chuỗi, số) thì về mặc định', () => {
        expect(sanitizeSettings(null)).toEqual(defaultSettings);
        expect(sanitizeSettings('dark')).toEqual(defaultSettings);
        expect(sanitizeSettings(42)).toEqual(defaultSettings);
        expect(sanitizeSettings({ langs: null })).toEqual(defaultSettings);
    });

    it('nhận đủ các số từ mới hợp lệ, kể cả "all"', () => {
        for (const n of [10, 20, 30, 50, 100, 'all'] as const) {
            expect(sanitizeSettings({ newPerSession: n }).newPerSession).toBe(n);
        }
        expect(sanitizeSettings({ newPerSession: 0 }).newPerSession).toBe(10);
    });

    it('canPersist báo đúng khi localStorage ghi được', () => {
        expect(canPersist()).toBe(true);
    });
});

describe('reducer', () => {
    it('chấm Tốt: lên một mức, hạn ôn theo mức mới, cộng xu và số thẻ đã ôn', () => {
        const s0 = initialState();
        const s1 = reducer(s0, { type: 'grade', id: 'broetchen', grade: 'tot' });
        expect(s1.progress.broetchen).toEqual({ level: 3, dueInDays: 7, lapses: 0 });
        expect(s1.reviewedToday).toBe(s0.reviewedToday + 1);
        expect(s1.coins).toBe(s0.coins + 5);
        // không sửa trạng thái cũ
        expect(s0.progress.broetchen.level).toBe(2);
    });

    it('chấm Quên: lùi mức, tính một lần quên, không có xu', () => {
        const s1 = reducer(initialState(), { type: 'grade', id: 'brot', grade: 'quen' });
        expect(s1.progress.brot.level).toBe(1);
        expect(s1.progress.brot.lapses).toBe(1);
        expect(s1.coins).toBe(initialState().coins);
    });

    it('từ chưa có tiến độ thì coi như từ mới', () => {
        const s1 = reducer(initialState(), { type: 'grade', id: 'tu-la', grade: 'de' });
        expect(s1.progress['tu-la']).toEqual({ level: 3, dueInDays: 7, lapses: 0 });
    });

    it('sao bật tắt; ghi chú lưu theo từ; cài đặt gộp phần đổi', () => {
        let s = initialState();
        s = reducer(s, { type: 'star', id: 'brot' });
        expect(s.starred.brot).toBe(true);
        s = reducer(s, { type: 'star', id: 'brot' });
        expect(s.starred.brot).toBe(false);
        s = reducer(s, { type: 'note', id: 'brot', text: 'ăn sáng' });
        expect(s.notes.brot).toBe('ăn sáng');
        s = reducer(s, { type: 'settings', patch: { theme: 'dark' } });
        expect(s.settings).toEqual({ ...defaultSettings, theme: 'dark' });
    });
});

describe('mùa theo tháng', () => {
    it.each([
        [0, 'winter'], [1, 'winter'], [2, 'spring'], [4, 'spring'], [5, 'summer'], [7, 'summer'],
        [8, 'autumn'], [10, 'autumn'], [11, 'winter'],
    ] as const)('tháng %i (đếm từ 0) là %s', (m, season) => {
        expect(seasonOf(new Date(2026, m, 15))).toBe(season);
    });
});

it('useStore ngoài StoreProvider thì báo lỗi rõ ràng', () => {
    expect(() => renderHook(() => useStore())).toThrow('useStore cần nằm trong StoreProvider');
});
