// AI của app học: trạng thái AI của tài khoản (khối "ai" trong GET /toi) và các lệnh gọi API.
// Mọi lượt hỏi đi qua máy chủ; khoá AI không bao giờ ở trình duyệt.
import type { Toi } from '../state/taiKhoan';
import { api } from './api';

export type TrangThaiAi =
    | { state: 'hidden' }
    | { state: 'need_birth_date'; min_age: number }
    | { state: 'underage'; min_age: number }
    | { state: 'need_consent'; min_age: number; consent_version: string }
    | { state: 'ready'; consent_version: string; limit: number; used: number; reset_at: number };

export type GiaiThichTu = { meaning: string; usage: string; examples: { de: string; vi: string }[]; common_mistakes: string[] };

export type MetaAi = { cached: boolean; limit: number; used: number; remaining: number; reset_at: number };

/** Phiên bản lời đồng ý app đang hiện; máy chủ từ chối phiên bản khác. */
export const PHIEN_BAN_DONG_Y = '2026-11';

/** Các trạng thái có tab Hỏi AI ở thẻ từ: còn bước để làm hoặc đã dùng được. */
export function coTabAi(ai: TrangThaiAi | undefined): boolean {
    return ai?.state === 'need_birth_date' || ai?.state === 'need_consent' || ai?.state === 'ready';
}

/** Mã lỗi nghĩa là trạng thái AI của tài khoản đã đổi: tải lại hồ sơ để cổng hiện đúng bước. */
export const MA_DOI_TRANG_THAI = ['ai_can_ngay_sinh', 'ai_chua_du_tuoi', 'ai_can_dong_y', 'ai_chua_mo', 'ai_tam_tat'];

/** Ngày có thật không (31/2 thì không). */
export function ngayCoThat(nam: number, thang: number, ngay: number): boolean {
    const d = new Date(Date.UTC(nam, thang - 1, ngay));
    return d.getUTCFullYear() === nam && d.getUTCMonth() === thang - 1 && d.getUTCDate() === ngay;
}

export const khaiNgaySinh = (ngay: string) => api.sua<{ data: Toi }>('/toi/ngay-sinh', { birth_date: ngay });

export const guiDongY = (granted: boolean) => api.sua<{ data: Toi }>('/toi/dong-y-ai', { granted, version: PHIEN_BAN_DONG_Y });

export const giaiThichTu = (cardId: number) => api.gui<{ data: GiaiThichTu; meta: MetaAi }>('/ai/giai-thich-tu', { card_id: cardId });
