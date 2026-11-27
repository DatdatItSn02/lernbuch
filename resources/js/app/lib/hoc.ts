// Kiểu dữ liệu của API học (/api/v1/hom-nay, /hoc/phien, /hoc/con-lai, /hoc/luot-cham).
import type { NgonNguNghia, TenGon, TheTomTat } from './noiDung';

export type CheDo = 'flashcard' | 'quiz' | 'listening' | 'typing' | 'match' | 'mixed' | 'sentence';

/** 7 chế độ học, theo thứ tự hiện trên app (máy chủ: CaiDatHoc::CHE_DO). */
export const CAC_CHE_DO: readonly CheDo[] = ['flashcard', 'quiz', 'listening', 'typing', 'match', 'mixed', 'sentence'];

/** Chế độ của lượt chấm thẻ: 7 chế độ học và Điền câu (bài tập làm từ câu có chỗ trống của thẻ). Máy chủ: CaiDatHoc::CHE_DO_CHAM. */
export type CheDoCham = CheDo | 'cloze';

/** Xu cho mỗi thẻ chấm Tốt hoặc Dễ (Khó được nửa); khớp CaiDatHoc::XU qua tests/chung/xu-che-do.json. */
export const XU: Record<CheDoCham, number> = { flashcard: 5, quiz: 10, listening: 15, typing: 10, match: 10, mixed: 20, sentence: 20, cloze: 10 };

export const laCheDo = (x: string | null): x is CheDo => !!x && (CAC_CHE_DO as readonly string[]).includes(x);

/** Đáp án nhiễu (Quiz, Ghép cặp, Hỗn hợp): thẻ khác cùng loại thẻ, ưu tiên cùng loại từ và cùng chủ đề. */
export type DapAnNhieu = Pick<TheTomTat, 'id' | 'front' | 'article' | 'word' | 'gender' | 'meanings'>;

/** Thẻ trong phiên: tóm tắt, câu ví dụ chính, chủ đề, mức hiện tại (0 = từ mới). */
export type TheHoc = TheTomTat & {
    example: { sentence: string; translations: Partial<Record<NgonNguNghia, string>> } | null;
    topic: TenGon | null;
    box: number;
    due_at: number | null;
    /** Chỉ có ở Quiz, Ghép cặp, Hỗn hợp. */
    options?: DapAnNhieu[];
};

export type PhienHoc = {
    /** Mã phiên trên máy chủ; null là phiên app lập trên máy lúc mất mạng (lượt chấm gửi sau, không gắn phiên). */
    id: number | null;
    mode: CheDo;
    started_at: number;
    /** Học riêng chủ đề này; null là ôn mọi chủ đề, từ mới theo chủ đề đang học. */
    topic: TenGon | null;
    new_topic: TenGon | null;
    counts: { review: number; new: number };
    cards: TheHoc[];
    /** Học khi mất mạng: phiên dở học tiếp, hoặc phiên lập trên máy từ chủ đề đã tải. */
    offline?: boolean;
};

export type ConLai = { review: number; new: number };

export type NgayHoc = { date: string; count: number; kind: 'past' | 'today' | 'future' };

export type ChuDeDenHan = TenGon & { collection: string; due: number; new: number; levels: number[] };

export type HomNayData = {
    /** Ngày hôm nay theo múi giờ của người học (YYYY-MM-DD). */
    date: string;
    timezone: string;
    due: number;
    due_by_level: number[];
    levels: number[];
    new_next: number;
    /** Từ chưa học còn lại của loại thẻ đang học: số từ mới của phiên tới = min(số mỗi lượt, số này). */
    new_available: number;
    new_topic: (TenGon & { new: number }) | null;
    reviewed_today: number;
    goal: number;
    coins: number;
    coins_today: number;
    streak: number;
    streak_longest: number;
    /** 10 ngày qua, hôm nay, 3 ngày tới. */
    days: NgayHoc[];
    due_topics: ChuDeDenHan[];
};

/** Tiến độ của người học trong một chủ đề (trang chủ đề). */
export type TienDoChuDe = { due: number; new: number; levels: number[] };
