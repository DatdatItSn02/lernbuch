import { vi } from 'vitest';
import type { Toi } from '../app/state/taiKhoan';

export type LanGoi = { method: string; url: string; body: unknown; headers: Record<string, string> };
type Tuyen = (body: unknown) => { status?: number; json?: unknown };

/** Máy chủ giả cho fetch: tuyến theo "PHƯƠNG_THỨC /đường/dẫn"; ghi lại mọi lần gọi để test kiểm. */
export function giaApi(tuyen: Record<string, Tuyen>): LanGoi[] {
    const goi: LanGoi[] = [];
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init: RequestInit = {}) => {
            const method = init.method ?? 'GET';
            const body = init.body ? JSON.parse(String(init.body)) : undefined;
            goi.push({ method, url, body, headers: (init.headers ?? {}) as Record<string, string> });
            const xuLy = tuyen[`${method} ${url}`];
            const kq = xuLy ? xuLy(body) : { status: 404, json: { code: 'khong_thay', message: '' } };
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
        ...doi,
    };
}
