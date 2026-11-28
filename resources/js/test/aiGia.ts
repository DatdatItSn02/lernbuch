import type { MetaAi, TrangThaiAi } from '../app/lib/ai';

/** Trạng thái AI dùng được (đủ tuổi, đã đồng ý) và meta mẫu của một lượt hỏi. */
export const SAN_SANG: TrangThaiAi = { state: 'ready', consent_version: '2026-11', limit: 50, used: 3, reset_at: 1764262800 };
export const META: MetaAi = { cached: false, limit: 50, used: 4, remaining: 46, reset_at: 1764262800 };
