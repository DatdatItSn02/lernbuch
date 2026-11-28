// AI của app học: trạng thái AI của tài khoản (khối "ai" trong GET /toi) và các lệnh gọi API.
// Mọi lượt hỏi đi qua máy chủ; khoá AI không bao giờ ở trình duyệt.
import type { Toi } from '../state/taiKhoan';
import { api } from './api';

export type TrangThaiAi =
    | { state: 'hidden' }
    | { state: 'need_birth_date'; min_age: number }
    | { state: 'underage'; min_age: number }
    | { state: 'need_consent'; min_age: number; consent_version: string }
    | { state: 'ready'; consent_version: string; limit: number; used: number; reset_at: number; pron?: TrangThaiPhatAm };

/** Chấm phát âm: cách chấm đang dùng (azure, browser: bản dự phòng, off), lượt mỗi ngày, số giây ghi âm tối đa. */
export type TrangThaiPhatAm = { mode: 'azure' | 'browser' | 'off'; limit: number; used: number; reset_at: number; max_seconds: number };

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

// ---------------------------------------------------------------- G2 tuần 2: chín chức năng chữ

/** Giới hạn ký tự từng ô (máy chủ kiểm lại y như vậy). */
export const GIOI_HAN = { dich: 500, sua: 800, traTu: 60, hoiNguPhap: 300, kiemDapAn: 200, vietCau: 200, troChuyen: 300, chuDe: 60, tu: 40 } as const;

/** Số từ tối đa của Bài học AI. */
export const TOI_DA_TU = 10;

/** Có ít nhất một chữ cái (toàn số, ký hiệu, emoji thì không gửi). */
export const coChu = (chu: string) => /\p{L}/u.test(chu);

export type ViDu = { de: string; vi: string };
export type LoiSua = { wrong: string; right: string; why: string };
export type KetQuaAi<T> = { data: T; meta: MetaAi };

export type KetQuaDich = { translation: string; literal: string; words: { de: string; vi: string; note: string }[]; grammar: string; natural: string };
export type KetQuaSua = { corrected: string; errors: LoiSua[]; natural: string; level: string };
export type KetQuaTraTu = {
    lemma: string;
    pos: string;
    gender: 'm' | 'f' | 'n' | '';
    plural: string;
    forms: { label: string; form: string }[];
    meanings_vi: string[];
    examples: ViDu[];
    collocations: string[];
    level: string;
};
export type KetQuaDoan = { summary: string; points: string[]; examples: ViDu[] };
export type KetQuaHoi = { answer: string; examples: ViDu[] };
export type KetQuaDapAn = { ok: boolean; why: string; better: string };
export type KetQuaCau = {
    verdict: 'dung' | 'gan_dung' | 'sai';
    word_ok: boolean;
    corrected: string;
    errors: LoiSua[];
    explain: string;
    use: string;
    tip: string;
};
export type NoiDungBaiHoc = {
    title: string;
    level: string;
    text: ViDu[];
    new_words: ViDu[];
    questions: { q: string; choices: string[]; answer: number }[];
    exercise: { de: string; answer: string }[];
};
export type BaiHocDaLuu = { id: number; title: string; level: string | null; score: number | null; completed_at: number | null; created_at: number };

export type TinhHuong = 'free' | 'restaurant' | 'visa_interview' | 'workplace';
export const TINH_HUONG: readonly TinhHuong[] = ['free', 'restaurant', 'visa_interview', 'workplace'];
export type CuocTroChuyen = {
    id: string;
    scenario: TinhHuong;
    level: string | null;
    title: string | null;
    message_count: number;
    last_message_at: number | null;
    created_at: number;
};
export type TinNhan = {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    extra: { vi: string; hint: string; errors: LoiSua[] } | null;
    created_at: number;
};

export const dich = (text: string, target: 'de' | 'vi') => api.gui<KetQuaAi<KetQuaDich>>('/ai/dich', { text, target });
export const suaNguPhap = (text: string) => api.gui<KetQuaAi<KetQuaSua>>('/ai/sua-ngu-phap', { text });
export const traTuAi = (text: string) => api.gui<KetQuaAi<KetQuaTraTu>>('/ai/tra-tu', { text });
export const giaiThichNguPhap = (slug: string, section: number) => api.gui<KetQuaAi<KetQuaDoan>>('/ai/ngu-phap/giai-thich', { slug, section });
export const hoiNguPhap = (slug: string, question: string) => api.gui<KetQuaAi<KetQuaHoi>>('/ai/ngu-phap/hoi', { slug, question });
export const kiemDapAn = (than: { exercise_id: number } | { card_id: number }, text: string) =>
    api.gui<KetQuaAi<KetQuaDapAn>>('/ai/kiem-dap-an', { ...than, text });
export const vietCau = (cardId: number, text: string) => api.gui<KetQuaAi<KetQuaCau> & { saved: { id: number } }>('/ai/viet-cau', { card_id: cardId, text });

export const dsCuoc = () => api.lay<{ data: CuocTroChuyen[] }>('/ai/tro-chuyen');
export const taoCuoc = (scenario: TinhHuong) => api.gui<{ data: CuocTroChuyen }>('/ai/tro-chuyen', { scenario });
export const xemCuoc = (ma: string) => api.lay<{ data: { conversation: CuocTroChuyen; messages: TinNhan[] } }>(`/ai/tro-chuyen/${ma}`);
export const guiTin = (ma: string, text: string) =>
    api.gui<KetQuaAi<unknown> & { messages: TinNhan[]; conversation: CuocTroChuyen }>(`/ai/tro-chuyen/${ma}/tin`, { text });
export const xoaCuoc = (ma: string) => api.xoa(`/ai/tro-chuyen/${ma}`);

export const dsBaiHoc = () => api.lay<{ data: BaiHocDaLuu[] }>('/ai/bai-hoc');
export const soanBaiHoc = (topic: string, level: string, words: string[]) =>
    api.gui<KetQuaAi<NoiDungBaiHoc> & { lesson: BaiHocDaLuu }>('/ai/bai-hoc', { topic, level, words });
export const xemBaiHoc = (id: number) => api.lay<{ data: NoiDungBaiHoc; lesson: BaiHocDaLuu }>(`/ai/bai-hoc/${id}`);
export const ghiDiemBaiHoc = (id: number, score: number) => api.sua<{ data: BaiHocDaLuu }>(`/ai/bai-hoc/${id}/ket-qua`, { score });
export const xoaBaiHoc = (id: number) => api.xoa(`/ai/bai-hoc/${id}`);
