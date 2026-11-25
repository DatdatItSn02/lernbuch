// Đọc tiếng Đức bằng giọng của trình duyệt (bản G0; G1 dùng audio Piper tạo sẵn, giọng trình duyệt làm dự phòng).

export type SpeakResult = 'ok' | 'no-voice' | 'unsupported' | 'error';

function germanVoice(): SpeechSynthesisVoice | undefined {
    const voices = window.speechSynthesis.getVoices();
    return voices.find((v) => v.lang === 'de-DE') ?? voices.find((v) => v.lang.toLowerCase().startsWith('de'));
}

export function canSpeak(): boolean {
    return typeof window !== 'undefined' && 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
}

/** Đọc một câu; slow = nghe chậm. Kết quả báo khi máy không có giọng tiếng Đức. */
export function speak(text: string, slow = false): Promise<SpeakResult> {
    if (!canSpeak()) return Promise.resolve('unsupported');
    const synth = window.speechSynthesis;
    return new Promise((resolve) => {
        const run = () => {
            const voice = germanVoice();
            if (!voice) return resolve('no-voice');
            synth.cancel();
            const u = new SpeechSynthesisUtterance(text);
            u.lang = 'de-DE';
            u.voice = voice;
            u.rate = slow ? 0.6 : 0.95;
            u.onend = () => resolve('ok');
            u.onerror = () => resolve('error');
            synth.speak(u);
        };
        if (synth.getVoices().length > 0) return run();
        // Chrome nạp danh sách giọng sau một nhịp
        const timer = window.setTimeout(() => resolve(germanVoice() ? 'error' : 'no-voice'), 1500);
        synth.addEventListener('voiceschanged', () => { window.clearTimeout(timer); run(); }, { once: true });
    });
}

export const SPEAK_MESSAGES: Record<Exclude<SpeakResult, 'ok'>, string> = {
    'no-voice': 'Máy này chưa có giọng đọc tiếng Đức. Thêm giọng Deutsch trong cài đặt giọng nói của máy, hoặc mở bằng Chrome hay Edge.',
    unsupported: 'Trình duyệt này không đọc được thành tiếng.',
    error: 'Không đọc được, thử lại.',
};
