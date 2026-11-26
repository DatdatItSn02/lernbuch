import { vi } from 'vitest';

/** Giọng đọc giả cho test: cancel() huỷ câu đang đọc như trình duyệt thật (lỗi "interrupted"). */
export class FakeUtterance {
    text: string;
    lang = '';
    voice: unknown = null;
    rate = 1;
    onend: (() => void) | null = null;
    onerror: ((e: { error: string }) => void) | null = null;
    constructor(text: string) {
        this.text = text;
    }
}

export function fakeSpeech(voices: { lang: string; name: string }[] = [{ lang: 'de-DE', name: 'Anna' }]) {
    const spoken: FakeUtterance[] = [];
    let current: FakeUtterance | null = null;
    const end = (fn: (u: FakeUtterance) => void) => {
        const u = current;
        current = null;
        if (u) fn(u);
    };
    const synth = {
        spoken,
        get speaking() {
            return current !== null;
        },
        getVoices: () => voices,
        addEventListener: () => {},
        speak: (u: FakeUtterance) => {
            spoken.push(u);
            current = u;
        },
        cancel: () => end((u) => u.onerror?.({ error: 'interrupted' })),
        /** Câu đang đọc đọc xong. */
        finish: () => end((u) => u.onend?.()),
        /** Câu đang đọc gặp lỗi thật của trình duyệt. */
        fail: (error: string) => end((u) => u.onerror?.({ error })),
    };
    vi.stubGlobal('speechSynthesis', synth);
    vi.stubGlobal('SpeechSynthesisUtterance', FakeUtterance);
    return synth;
}
