import { describe, expect, it } from 'vitest';
import { initialProgress, words } from '../data/demo';
import { buildSession, isDue, levelCounts } from './session';

describe('phiên học', () => {
    it('Học ngay: thẻ đến hạn mọi chủ đề xen kẽ từ mới của chủ đề đang học', () => {
        const ids = buildSession(words, initialProgress, { newTopicId: 'tiem-banh', newCount: 10 });
        const due = ids.filter((id) => isDue(initialProgress[id]));
        const fresh = ids.filter((id) => initialProgress[id].level === 0);
        expect(due).toHaveLength(12);
        expect(fresh).toHaveLength(9);
        expect(new Set(fresh.map((id) => words.find((w) => w.id === id)?.topicId))).toEqual(new Set(['tiem-banh']));
        // xen kẽ: ôn, mới, ôn, mới…
        expect(isDue(initialProgress[ids[0]])).toBe(true);
        expect(initialProgress[ids[1]].level).toBe(0);
        // quá hạn lâu nhất ra trước
        expect(ids[0]).toBe('orange');
    });

    it('giới hạn số từ mới và chế độ chỉ ôn', () => {
        expect(buildSession(words, initialProgress, { newTopicId: 'tiem-banh', newCount: 3 }).filter((id) => initialProgress[id].level === 0)).toHaveLength(3);
        const review = buildSession(words, initialProgress, { dueTopicId: 'tiem-banh', newTopicId: 'tiem-banh', newCount: 'all', reviewOnly: true });
        expect(review).toEqual(['brot', 'broetchen', 'baeckerei', 'baecker', 'brezel']);
    });

    it('không có thẻ nào thì phiên rỗng, số âm coi như 0', () => {
        expect(buildSession([], initialProgress, { newTopicId: 'tiem-banh', newCount: 10 })).toEqual([]);
        expect(buildSession(words, initialProgress, { dueTopicId: 'khong-co', newCount: -5 })).toEqual([]);
    });

    it('đếm 6 mức', () => {
        const ids = words.filter((w) => w.topicId === 'tiem-banh').map((w) => w.id);
        const counts = levelCounts(ids, initialProgress);
        expect(counts.reduce((a, b) => a + b, 0)).toBe(24);
        expect(counts[0]).toBe(9);
    });
});
