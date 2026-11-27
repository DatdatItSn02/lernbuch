// Nghe từ tiếng Đức: file audio Piper tạo sẵn (máy chủ cho địa chỉ trong thẻ), nghe chậm phát ở tốc độ 0,65.
// Thẻ chưa có file, file trả lỗi (404, hỏng) hay trình duyệt không phát được thì đọc bằng giọng của trình duyệt.
import { speak, type SpeakResult } from './speech';

/** Tốc độ nghe chậm khi phát file (giọng trình duyệt có tốc độ riêng trong speech.ts). */
export const TOC_DO_CHAM = 0.65;

let dangPhat: { audio: HTMLAudioElement; xong: (r: SpeakResult) => void } | null = null;

/** Dừng file đang phát (bấm nghe lại hay nghe từ khác): lần phát trước báo "interrupted", không phải lỗi. */
export function dungPhat(): void {
    if (!dangPhat) return;
    const { audio, xong } = dangPhat;
    dangPhat = null;
    audio.pause();
    xong('interrupted');
}

function phatFile(url: string, cham: boolean): Promise<SpeakResult> {
    dungPhat();
    if (typeof window === 'undefined' || typeof window.Audio !== 'function') return Promise.resolve('unsupported');
    if (typeof window.speechSynthesis !== 'undefined') window.speechSynthesis.cancel();
    return new Promise((resolve) => {
        const audio = new window.Audio(url);
        let daXong = false;
        const xong = (r: SpeakResult) => {
            if (daXong) return;
            daXong = true;
            if (dangPhat?.audio === audio) dangPhat = null;
            resolve(r);
        };
        dangPhat = { audio, xong };
        audio.preload = 'auto';
        audio.playbackRate = cham ? TOC_DO_CHAM : 1;
        // Nghe chậm giữ cao độ giọng (mặc định của trình duyệt; đặt rõ cho chắc)
        audio.preservesPitch = true;
        audio.addEventListener('ended', () => xong('ok'));
        audio.addEventListener('error', () => xong('error'));
        try {
            const p = audio.play();
            if (p && typeof p.catch === 'function') p.catch((e: unknown) => xong(e instanceof DOMException && e.name === 'AbortError' ? 'interrupted' : 'error'));
        } catch {
            xong('error');
        }
    });
}

/**
 * Đọc một chữ: có địa chỉ file thì phát file, không có hoặc phát lỗi thì đọc bằng giọng trình duyệt.
 * Kết quả như speak(): máy không có giọng tiếng Đức thì 'no-voice' (nút nghe báo nhẹ, trang không lỗi).
 */
export async function nghe(chu: string, { url = null, cham = false }: { url?: string | null; cham?: boolean } = {}): Promise<SpeakResult> {
    if (url) {
        const r = await phatFile(url, cham);
        if (r === 'ok' || r === 'interrupted') return r;
    } else {
        dungPhat();
    }
    return speak(chu, cham);
}
