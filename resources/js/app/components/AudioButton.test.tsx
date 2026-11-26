import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fakeSpeech } from '../../test/fakeSpeech';
import { AudioButton } from './AudioButton';

afterEach(() => {
    vi.unstubAllGlobals();
});

const LOI = 'Không đọc được, thử lại.';

describe('AudioButton: nút nghe', () => {
    it('bấm lần hai khi lần đầu chưa đọc xong: đọc lại từ đầu, không hiện lỗi', async () => {
        const synth = fakeSpeech();
        render(<AudioButton text="das Brötchen" />);
        const nut = screen.getByRole('button', { name: 'Nghe' });
        await act(async () => fireEvent.click(nut));
        await act(async () => fireEvent.click(nut));
        expect(synth.spoken).toHaveLength(2);
        expect(screen.queryByText(LOI)).toBeNull();
        expect(screen.queryByRole('status')).toBeNull();
        expect(nut.getAttribute('aria-busy')).toBe('true');
        await act(async () => synth.finish());
        expect(nut.getAttribute('aria-busy')).toBe('false');
        expect(screen.queryByRole('status')).toBeNull();
    });

    it('bấm liên tục nhiều lần cũng không hiện lỗi', async () => {
        const synth = fakeSpeech();
        render(<AudioButton text="das Brötchen" />);
        const nut = screen.getByRole('button', { name: 'Nghe' });
        for (let i = 0; i < 5; i++) await act(async () => fireEvent.click(nut));
        expect(screen.queryByRole('status')).toBeNull();
        await act(async () => synth.finish());
        expect(synth.spoken).toHaveLength(5);
        expect(screen.queryByRole('status')).toBeNull();
        expect(nut.getAttribute('aria-busy')).toBe('false');
    });

    it('bấm nút nghe khác khi đang đọc: nút trước thôi đọc, không nút nào hiện lỗi', async () => {
        const synth = fakeSpeech();
        render(
            <>
                <AudioButton text="das Brötchen" />
                <AudioButton text="das Brötchen" slow />
            </>,
        );
        const nghe = screen.getByRole('button', { name: 'Nghe' });
        const cham = screen.getByRole('button', { name: 'Nghe chậm' });
        await act(async () => fireEvent.click(nghe));
        await act(async () => fireEvent.click(cham));
        expect(nghe.getAttribute('aria-busy')).toBe('false');
        expect(cham.getAttribute('aria-busy')).toBe('true');
        await act(async () => synth.finish());
        expect(screen.queryByRole('status')).toBeNull();
    });

    it('lỗi thật của trình duyệt vẫn báo dưới nút; bấm lại thì dòng báo mất', async () => {
        const synth = fakeSpeech();
        render(<AudioButton text="das Brötchen" />);
        const nut = screen.getByRole('button', { name: 'Nghe' });
        await act(async () => fireEvent.click(nut));
        await act(async () => synth.fail('synthesis-failed'));
        expect(screen.getByRole('status').textContent).toBe(LOI);
        await act(async () => fireEvent.click(nut));
        expect(screen.queryByRole('status')).toBeNull();
    });

    it('máy không có giọng tiếng Đức thì báo cách thêm giọng', async () => {
        fakeSpeech([{ lang: 'en-US', name: 'Sam' }]);
        render(<AudioButton text="das Brötchen" />);
        await act(async () => fireEvent.click(screen.getByRole('button', { name: 'Nghe' })));
        expect(screen.getByRole('status').textContent).toContain('chưa có giọng đọc tiếng Đức');
    });
});
