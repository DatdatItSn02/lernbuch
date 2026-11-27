import { vi } from 'vitest';

/**
 * Cache Storage giả (jsdom không có): kho theo tên, mỗi kho giữ bản Response theo địa chỉ đầy đủ. Trả về bản đồ các kho
 * để test xem trong kho có gì.
 */
export function giaCaches(): Map<string, Map<string, Response>> {
    const cacKho = new Map<string, Map<string, Response>>();
    const diaChi = (u: RequestInfo | URL) => new URL(typeof u === 'string' ? u : u instanceof URL ? u.href : u.url, window.location.href).href;
    const mo = (ten: string) => {
        let m = cacKho.get(ten);
        if (!m) {
            m = new Map();
            cacKho.set(ten, m);
        }
        const kho = m;
        return {
            match: async (u: RequestInfo | URL) => kho.get(diaChi(u))?.clone(),
            put: async (u: RequestInfo | URL, r: Response) => {
                kho.set(diaChi(u), r);
            },
            delete: async (u: RequestInfo | URL) => kho.delete(diaChi(u)),
            keys: async () => [...kho.keys()].map((k) => new Request(k)),
        };
    };
    vi.stubGlobal('caches', {
        open: async (ten: string) => mo(ten),
        delete: async (ten: string) => cacKho.delete(ten),
        has: async (ten: string) => cacKho.has(ten),
        keys: async () => [...cacKho.keys()],
    });
    return cacKho;
}
