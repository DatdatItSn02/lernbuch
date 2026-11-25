import type { Progress, Word } from '../data/demo';

export type SessionOptions = {
    /** Chủ đề đang học: lấy từ mới ở đây. Không có thì không lấy từ mới. */
    newTopicId?: string;
    /** Chỉ lấy thẻ đến hạn của chủ đề này; bỏ trống thì lấy đến hạn ở mọi chủ đề. */
    dueTopicId?: string;
    /** Số từ mới mỗi lượt; 'all' là hết từ mới của chủ đề. */
    newCount: number | 'all';
    reviewOnly?: boolean;
};

/** Đến hạn: hạn ôn đã tới hoặc còn dưới 1 ngày (thẻ vừa Quên ôn lại sau 10 phút cũng tính hôm nay). */
export const isDue = (p: Progress | undefined) => !!p && p.level > 0 && p.dueInDays !== null && p.dueInDays < 1;

/** Ngày đến hạn tính từ hôm nay: 0 là hôm nay, 1 là ngày mai… */
export const dueDay = (p: Progress | undefined): number | null =>
    !p || p.level === 0 || p.dueInDays === null ? null : p.dueInDays < 1 ? 0 : Math.floor(p.dueInDays);
export const isNew = (p: Progress | undefined) => !p || p.level === 0;

/**
 * Phiên học như Lernbuch: thẻ đến hạn cộng số từ mới đã chọn, xen kẽ ôn và mới.
 * Từ mới chỉ lấy trong một chủ đề (không trộn chủ đề); thẻ đến hạn quá lâu nhất ra trước.
 */
export function buildSession(words: Word[], progress: Record<string, Progress>, opts: SessionOptions): string[] {
    const due = words
        .filter((w) => (!opts.dueTopicId || w.topicId === opts.dueTopicId) && isDue(progress[w.id]))
        .sort((a, b) => (progress[a.id].dueInDays ?? 0) - (progress[b.id].dueInDays ?? 0))
        .map((w) => w.id);
    const limit = opts.newCount === 'all' ? Infinity : Math.max(0, opts.newCount);
    const fresh = opts.reviewOnly || !opts.newTopicId
        ? []
        : words.filter((w) => w.topicId === opts.newTopicId && isNew(progress[w.id])).slice(0, limit).map((w) => w.id);
    const out: string[] = [];
    for (let i = 0; i < Math.max(due.length, fresh.length); i++) {
        if (i < due.length) out.push(due[i]);
        if (i < fresh.length) out.push(fresh[i]);
    }
    return out;
}

/** Đếm số thẻ theo 6 mức (0–5). */
export function levelCounts(ids: string[], progress: Record<string, Progress>): number[] {
    const counts = [0, 0, 0, 0, 0, 0];
    for (const id of ids) counts[progress[id]?.level ?? 0]++;
    return counts;
}
