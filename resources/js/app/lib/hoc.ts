// Kiểu dữ liệu của API học (/api/v1/hom-nay, /hoc/phien, /hoc/con-lai, /hoc/luot-cham).
import type { NgonNguNghia, TenGon, TheTomTat } from './noiDung';

export type CheDo = 'flashcard' | 'quiz' | 'listening' | 'typing' | 'match' | 'mixed' | 'sentence';

/** Thẻ trong phiên: tóm tắt, câu ví dụ chính, chủ đề, mức hiện tại (0 = từ mới). */
export type TheHoc = TheTomTat & {
    example: { sentence: string; translations: Partial<Record<NgonNguNghia, string>> } | null;
    topic: TenGon | null;
    box: number;
    due_at: number | null;
};

export type PhienHoc = {
    id: number;
    mode: CheDo;
    started_at: number;
    /** Học riêng chủ đề này; null là ôn mọi chủ đề, từ mới theo chủ đề đang học. */
    topic: TenGon | null;
    new_topic: TenGon | null;
    counts: { review: number; new: number };
    cards: TheHoc[];
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
