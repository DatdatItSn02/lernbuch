import { t } from '../i18n';
// Đọc tiếng Đức bằng giọng của trình duyệt (bản G0; G1 dùng audio Piper tạo sẵn, giọng trình duyệt làm dự phòng).

// interrupted: câu đang đọc bị huỷ vì người dùng bấm đọc lại hoặc bấm nút nghe khác; không phải lỗi.
export type SpeakResult = 'ok' | 'interrupted' | 'no-voice' | 'unsupported' | 'error';

// Trình duyệt không cho biết giọng nam hay nữ, nên đoán theo tên giọng (Windows/Edge, macOS/iOS, Chrome, Android).
// Thứ tự chọn: giọng nữ đã biết → giọng chưa rõ → giọng nam đã biết; trong cùng nhóm thì de-DE trước, rồi de-AT, de-CH.
const GIONG_NU = [
    'katja', 'hedda', 'amala', 'seraphina', 'louisa', 'elke', 'gisela', 'klarissa', 'maja', 'tanja', 'ingrid', 'leni',
    'anna', 'helena', 'petra', 'marlene', 'sandy', 'shelley', 'grandma', 'google deutsch', 'female', 'weiblich', 'frau',
];
const GIONG_NAM = [
    'stefan', 'conrad', 'killian', 'florian', 'bernd', 'christoph', 'kasper', 'ralf', 'jonas', 'jan ', 'markus',
    'yannick', 'viktor', 'eddy', 'reed', 'rocko', 'grandpa', 'male', 'männlich', 'mann',
];

function nhomGioi(name: string): number {
    const n = name.toLowerCase();
    // "Female" chứa "male": có dấu hiệu nữ thì tính là nữ trước
    if (GIONG_NU.some((x) => n.includes(x))) return 0;
    return GIONG_NAM.some((x) => n.includes(x)) ? 2 : 1;
}

/** Giọng tiếng Đức của máy, ưu tiên giọng nữ để cùng giọng với audio tạo sẵn. */
export function germanVoice(): SpeechSynthesisVoice | undefined {
    const de = window.speechSynthesis.getVoices().filter((v) => v.lang.replace('_', '-').toLowerCase().startsWith('de'));
    const thuTuVung = (v: SpeechSynthesisVoice) => (v.lang.replace('_', '-') === 'de-DE' ? 0 : 1);
    return [...de].sort((a, b) => nhomGioi(a.name) - nhomGioi(b.name) || thuTuVung(a) - thuTuVung(b))[0];
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
            u.onerror = (e) => resolve(e.error === 'interrupted' || e.error === 'canceled' ? 'interrupted' : 'error');
            synth.speak(u);
        };
        if (synth.getVoices().length > 0) return run();
        // Chrome nạp danh sách giọng sau một nhịp
        const timer = window.setTimeout(() => resolve(germanVoice() ? 'error' : 'no-voice'), 1500);
        synth.addEventListener('voiceschanged', () => { window.clearTimeout(timer); run(); }, { once: true });
    });
}

// Lấy từ từ điển mỗi lần đọc (getter), nên đúng ngôn ngữ giao diện hiện tại.
export const SPEAK_MESSAGES: Record<Exclude<SpeakResult, 'ok' | 'interrupted'>, string> = {
    get 'no-voice'() { return t('docTieng.khongCoGiong'); },
    get unsupported() { return t('docTieng.khongHoTro'); },
    get error() { return t('docTieng.loi'); },
};
