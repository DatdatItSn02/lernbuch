import { act, fireEvent, render, screen } from '@testing-library/react';
import { createElement } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fakeSpeech } from '../../test/fakeSpeech';
import { AudioButton } from '../components/AudioButton';
import { TOC_DO_CHAM, dungPhat, nghe } from './amThanh';

// Mục tự kiểm 11: thẻ thiếu file, file trả 404, máy không có giọng Đức → tự chuyển sang giọng trình duyệt;
// không có giọng nào thì báo nhẹ, trang không lỗi.

/** Thẻ <audio> giả: ghi địa chỉ và tốc độ, test tự báo phát xong hay lỗi. */
class AudioGia {
    static cacLan: AudioGia[] = [];
    static play: (a: AudioGia) => Promise<void> = () => Promise.resolve();
    src: string;
    playbackRate = 1;
    preservesPitch = false;
    preload = '';
    paused = true;
    private nghe: Record<string, (() => void)[]> = {};
    constructor(src: string) {
        this.src = src;
        AudioGia.cacLan.push(this);
    }
    addEventListener(ten: string, fn: () => void) {
        (this.nghe[ten] ??= []).push(fn);
    }
    play() {
        this.paused = false;
        return AudioGia.play(this);
    }
    pause() {
        this.paused = true;
    }
    bao(ten: 'ended' | 'error') {
        this.nghe[ten]?.forEach((f) => f());
    }
}

const URL_FILE = '/audio/de_DE-thorsten-medium/2a/2aa53b81696a0f3cca04aca687df1cfa4409c930.mp3';
const cuoi = () => AudioGia.cacLan[AudioGia.cacLan.length - 1];
const nhip = () => new Promise((r) => setTimeout(r, 0));

beforeEach(() => {
    AudioGia.cacLan = [];
    AudioGia.play = () => Promise.resolve();
    vi.stubGlobal('Audio', AudioGia);
});

afterEach(() => {
    dungPhat();
    vi.unstubAllGlobals();
});

describe('nghe: file audio Piper, dự phòng giọng trình duyệt', () => {
    it('có file: phát file, không dùng giọng trình duyệt; nghe chậm phát ở 0,65, giữ cao độ', async () => {
        const synth = fakeSpeech();
        const kq = nghe('das Brötchen', { url: URL_FILE, cham: true });
        expect(cuoi().src).toBe(URL_FILE);
        expect(cuoi().playbackRate).toBe(TOC_DO_CHAM);
        expect(cuoi().preservesPitch).toBe(true);
        cuoi().bao('ended');
        expect(await kq).toBe('ok');
        expect(synth.spoken).toHaveLength(0);
    });

    it('thẻ chưa có file: đọc bằng giọng trình duyệt', async () => {
        const synth = fakeSpeech();
        const kq = nghe('das Brötchen');
        expect(AudioGia.cacLan).toHaveLength(0);
        synth.finish();
        expect(await kq).toBe('ok');
        expect(synth.spoken.map((u) => u.text)).toEqual(['das Brötchen']);
    });

    it('file trả 404 (lỗi tải): chuyển sang giọng trình duyệt, đọc chậm nếu đang nghe chậm', async () => {
        const synth = fakeSpeech();
        const kq = nghe('zählen', { url: URL_FILE, cham: true });
        cuoi().bao('error');
        await nhip();
        expect(synth.spoken.map((u) => [u.text, u.rate])).toEqual([['zählen', 0.6]]);
        synth.finish();
        expect(await kq).toBe('ok');
    });

    it('trình duyệt không cho phát (chưa bấm gì trên trang): cũng chuyển sang giọng trình duyệt', async () => {
        const synth = fakeSpeech();
        AudioGia.play = () => Promise.reject(new DOMException('chặn tự phát', 'NotAllowedError'));
        const kq = nghe('zahlen', { url: URL_FILE });
        await nhip();
        expect(synth.spoken.map((u) => u.text)).toEqual(['zahlen']);
        synth.finish();
        expect(await kq).toBe('ok');
    });

    it('máy không có giọng tiếng Đức và file lỗi: báo no-voice, không ném lỗi', async () => {
        fakeSpeech([{ lang: 'en-US', name: 'Sam' }]);
        const kq = nghe('die Zahl', { url: URL_FILE });
        cuoi().bao('error');
        expect(await kq).toBe('no-voice');
    });

    it('bấm nghe lần nữa khi file đang phát: lần trước dừng, báo interrupted (không phải lỗi)', async () => {
        fakeSpeech();
        const dau = nghe('das Brot', { url: URL_FILE });
        const truoc = cuoi();
        const sau = nghe('das Brot', { url: URL_FILE });
        expect(truoc.paused).toBe(true);
        expect(await dau).toBe('interrupted');
        cuoi().bao('ended');
        expect(await sau).toBe('ok');
    });
});

describe('nút nghe với file audio', () => {
    it('file lỗi, máy không có giọng Đức: nút báo nhẹ cách thêm giọng, trang vẫn dùng được', async () => {
        fakeSpeech([]);
        render(createElement(AudioButton, { text: 'das Brötchen', url: URL_FILE }));
        const nut = screen.getByRole('button', { name: 'Nghe' });
        await act(async () => {
            fireEvent.click(nut);
            await nhip();
            cuoi().bao('error');
            await new Promise((r) => setTimeout(r, 1600));
        });
        expect(screen.getByRole('status').textContent).toContain('chưa có giọng đọc tiếng Đức');
        expect(nut.getAttribute('aria-busy')).toBe('false');
    });

    it('có file: bấm nghe chậm thì phát file ở tốc độ chậm', async () => {
        fakeSpeech();
        render(createElement(AudioButton, { text: 'das Brötchen', url: URL_FILE, slow: true }));
        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: 'Nghe chậm' }));
        });
        expect(cuoi().src).toBe(URL_FILE);
        expect(cuoi().playbackRate).toBe(TOC_DO_CHAM);
        await act(async () => cuoi().bao('ended'));
        expect(screen.queryByRole('status')).toBeNull();
    });
});
