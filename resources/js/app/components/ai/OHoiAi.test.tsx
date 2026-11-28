import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { OHoiAi } from './OHoiAi';

// Ô nhập dùng chung của các chức năng AI (mục tự kiểm 10, phần giao diện).

function Mau({ onGui, toiDa = 20 }: { onGui: (c: string) => void; toiDa?: number }) {
    const [chu, setChu] = useState('');
    return <OHoiAi nhan="Câu cần dịch" toiDa={toiDa} giaTri={chu} onDoi={setChu} onGui={onGui} nut="Dịch" />;
}

describe('Ô hỏi AI', () => {
    it('bộ đếm ký tự; gửi chữ đã bỏ khoảng trắng hai đầu', () => {
        const gui = vi.fn();
        render(<Mau onGui={gui} />);
        fireEvent.change(screen.getByLabelText('Câu cần dịch'), { target: { value: '  Xin chào  ' } });
        expect(screen.getByText('8/20 ký tự')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Dịch' }));
        expect(gui).toHaveBeenCalledWith('Xin chào');
    });

    it('dán quá dài: báo, nút gửi tắt, không gửi; không chặn dán', () => {
        const gui = vi.fn();
        render(<Mau onGui={gui} />);
        const o = screen.getByLabelText('Câu cần dịch');
        fireEvent.change(o, { target: { value: 'a'.repeat(10000) } });
        expect((o as HTMLTextAreaElement).value).toHaveLength(10000);
        expect(screen.getByRole('alert').textContent).toBe('Dài quá: tối đa 20 ký tự.');
        expect(o.getAttribute('aria-invalid')).toBe('true');
        expect((screen.getByRole('button', { name: 'Dịch' }) as HTMLButtonElement).disabled).toBe(true);
        fireEvent.keyDown(o, { key: 'Enter', ctrlKey: true });
        expect(gui).not.toHaveBeenCalled();
    });

    it('ô trống, toàn số và ký hiệu: báo ngay, không gửi; gõ lại thì hết báo', () => {
        const gui = vi.fn();
        render(<Mau onGui={gui} />);
        fireEvent.click(screen.getByRole('button', { name: 'Dịch' }));
        expect(screen.getByRole('alert').textContent).toBe('Hãy gõ nội dung trước.');
        fireEvent.change(screen.getByLabelText('Câu cần dịch'), { target: { value: '?!… 123 🙂' } });
        expect(screen.queryByRole('alert')).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Dịch' }));
        expect(screen.getByRole('alert').textContent).toBe('Hãy gõ chữ, không chỉ số hay ký hiệu.');
        expect(gui).not.toHaveBeenCalled();
    });

    it('Ctrl+Enter gửi ở ô nhiều dòng; đúng giới hạn thì gửi được', () => {
        const gui = vi.fn();
        render(<Mau onGui={gui} toiDa={5} />);
        const o = screen.getByLabelText('Câu cần dịch');
        fireEvent.change(o, { target: { value: 'abcde' } });
        fireEvent.keyDown(o, { key: 'Enter', ctrlKey: true });
        expect(gui).toHaveBeenCalledWith('abcde');
    });
});
