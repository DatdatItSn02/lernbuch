import { vi } from 'vitest';
import type { Toi } from '../app/state/taiKhoan';

export type LanGoi = { method: string; url: string; body: unknown; headers: Record<string, string> };
export type Tuyen = (body: unknown, url: URL) => { status?: number; json?: unknown };

/**
 * Máy chủ giả cho fetch: tuyến theo "PHƯƠNG_THỨC /đường/dẫn?truy=vấn" (khớp đúng), không có thì theo
 * "PHƯƠNG_THỨC /đường/dẫn" (bỏ phần truy vấn, hàm xử lý đọc url.searchParams); ghi lại mọi lần gọi để test kiểm.
 */
export function giaApi(tuyen: Record<string, Tuyen>): LanGoi[] {
    const goi: LanGoi[] = [];
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init: RequestInit = {}) => {
            const method = init.method ?? 'GET';
            const body = init.body ? JSON.parse(String(init.body)) : undefined;
            goi.push({ method, url, body, headers: (init.headers ?? {}) as Record<string, string> });
            const u = new URL(url, 'http://lernbuch.test');
            const xuLy = tuyen[`${method} ${url}`] ?? tuyen[`${method} ${u.pathname}`];
            const kq = xuLy ? xuLy(body, u) : { status: 404, json: { code: 'khong_thay', message: '' } };
            return new Response(JSON.stringify(kq.json ?? null), { status: kq.status ?? 200, headers: { 'Content-Type': 'application/json' } });
        }),
    );
    return goi;
}

export function toiMau(doi: Partial<Toi> = {}): Toi {
    return {
        id: '01JDTEST0000000000000000AA',
        name: 'Lan',
        email: 'lan@example.com',
        ui_locale: 'vi',
        timezone: 'Asia/Ho_Chi_Minh',
        created_at: 1764201600,
        course: { slug: 'tieng-duc', level_min: 1, level_max: 4, goal: null, level: null },
        settings: { new_per_session: 10, daily_goal: 20, theme: 'light', font_size: 'md', season: 'auto', meaning_langs: ['vi', 'en', 'zh'] },
        ai: { state: 'hidden' },
        ...doi,
    };
}
