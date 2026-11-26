import { afterEach, describe, expect, it, vi } from 'vitest';
import { fakeSpeech } from '../../test/fakeSpeech';
import { SPEAK_MESSAGES, speak } from './speech';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('speak: đọc tiếng Đức bằng giọng trình duyệt', () => {
    it('đọc xong thì trả ok, dùng giọng Đức; nghe chậm thì đọc chậm hơn', async () => {
        const synth = fakeSpeech();
        const p = speak('das Brötchen');
        synth.finish();
        expect(await p).toBe('ok');
        const q = speak('das Brötchen', true);
        synth.finish();
        expect(await q).toBe('ok');
        expect(synth.spoken.map((u) => [u.lang, u.rate])).toEqual([['de-DE', 0.95], ['de-DE', 0.6]]);
    });

    it('bấm đọc lại khi câu trước chưa xong: câu trước trả interrupted, không phải lỗi; câu sau đọc từ đầu', async () => {
        const synth = fakeSpeech();
        const dau = speak('das Brötchen');
        const sau = speak('das Brötchen');
        expect(await dau).toBe('interrupted');
        synth.finish();
        expect(await sau).toBe('ok');
        expect(synth.spoken).toHaveLength(2);
    });

    it('câu bị bỏ khỏi hàng chờ (canceled) cũng không tính là lỗi', async () => {
        const synth = fakeSpeech();
        const p = speak('Guten Tag');
        synth.fail('canceled');
        expect(await p).toBe('interrupted');
    });

    it('lỗi thật của trình duyệt thì trả error', async () => {
        const synth = fakeSpeech();
        const p = speak('Guten Tag');
        synth.fail('synthesis-failed');
        expect(await p).toBe('error');
        expect(SPEAK_MESSAGES.error).toBe('Không đọc được, thử lại.');
    });

    it('máy không có giọng tiếng Đức thì trả no-voice, không đọc', async () => {
        const synth = fakeSpeech([{ lang: 'en-US', name: 'Sam' }]);
        expect(await speak('Guten Tag')).toBe('no-voice');
        expect(synth.spoken).toHaveLength(0);
    });

    it('trình duyệt không có giọng đọc thì trả unsupported', async () => {
        expect(await speak('Guten Tag')).toBe('unsupported');
    });
});
