import { afterEach, describe, expect, it, vi } from 'vitest';
import { fakeSpeech } from '../../test/fakeSpeech';
import { SPEAK_MESSAGES, germanVoice, speak } from './speech';

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

    it('ưu tiên giọng nữ: Windows có Stefan và Katja thì đọc bằng Katja', async () => {
        const synth = fakeSpeech([
            { lang: 'de-DE', name: 'Microsoft Stefan - German (Germany)' },
            { lang: 'en-US', name: 'Microsoft Zira' },
            { lang: 'de-DE', name: 'Microsoft Katja - German (Germany)' },
        ]);
        const p = speak('Das Kleid sieht schön aus.');
        synth.finish();
        expect(await p).toBe('ok');
        expect((synth.spoken[0].voice as { name: string }).name).toBe('Microsoft Katja - German (Germany)');
    });

    it('giọng nữ de-AT thắng giọng nam de-DE; hai giọng nữ thì de-DE trước', () => {
        fakeSpeech([
            { lang: 'de-DE', name: 'Markus' },
            { lang: 'de-AT', name: 'Microsoft Ingrid' },
            { lang: 'de-CH', name: 'Microsoft Leni' },
        ]);
        expect(germanVoice()?.name).toBe('Microsoft Ingrid');
        fakeSpeech([
            { lang: 'de-CH', name: 'Microsoft Leni' },
            { lang: 'de_DE', name: 'Anna' },
        ]);
        expect(germanVoice()?.name).toBe('Anna');
    });

    it('chữ "Female" không bị nhận nhầm là nam; giọng chưa rõ đứng trước giọng nam', () => {
        fakeSpeech([
            { lang: 'de-DE', name: 'German Male' },
            { lang: 'de-DE', name: 'German Female' },
        ]);
        expect(germanVoice()?.name).toBe('German Female');
        fakeSpeech([
            { lang: 'de-DE', name: 'Microsoft Conrad Online' },
            { lang: 'de-DE', name: 'Deutsch 3' },
        ]);
        expect(germanVoice()?.name).toBe('Deutsch 3');
    });

    it('máy chỉ có giọng nam thì vẫn đọc bằng giọng nam, không báo lỗi', async () => {
        const synth = fakeSpeech([{ lang: 'de-DE', name: 'Microsoft Stefan' }]);
        const p = speak('Guten Tag');
        synth.finish();
        expect(await p).toBe('ok');
        expect((synth.spoken[0].voice as { name: string }).name).toBe('Microsoft Stefan');
    });

    it('trình duyệt không có giọng đọc thì trả unsupported', async () => {
        expect(await speak('Guten Tag')).toBe('unsupported');
    });
});
