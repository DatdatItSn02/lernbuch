import { fireEvent, screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { CuocTroChuyen, TinNhan } from '../../lib/ai';
import { META, SAN_SANG } from '../../../test/aiGia';
import { giaApi, toiMau, type Tuyen } from '../../../test/fakeApi';
import { renderAppCoTaiKhoan } from '../../../test/render';

// Trò chuyện (mục tự kiểm 10 và 11, phần giao diện).

const MA = '01JDTESTCUOC00000000000000';
const CUOC: CuocTroChuyen = { id: MA, scenario: 'restaurant', level: 'A1', title: null, message_count: 0, last_message_at: null, created_at: 1764201600 };
const HAI_TIN: TinNhan[] = [
    { id: 1, role: 'user', content: 'Ich heißen Lan.', extra: null, created_at: 1 },
    { id: 2, role: 'assistant', content: 'Hallo Lan! Was möchten Sie trinken?', extra: { vi: 'Chào Lan! Bạn muốn uống gì?', hint: 'Ein Wasser, bitte.', errors: [{ wrong: 'Ich heißen', right: 'Ich heiße', why: 'Ngôi ich.' }] }, created_at: 2 },
];

function mo(path: string, them: Record<string, Tuyen> = {}) {
    const goi = giaApi({
        'GET /api/v1/toi': () => ({ json: { data: toiMau({ ai: SAN_SANG }) } }),
        'GET /api/v1/ai/tro-chuyen': () => ({ json: { data: [{ ...CUOC, title: 'Guten Tag', message_count: 4 }] } }),
        [`GET /api/v1/ai/tro-chuyen/${MA}`]: () => ({ json: { data: { conversation: CUOC, messages: [] } } }),
        ...them,
    });
    renderAppCoTaiKhoan(path);
    return goi;
}

describe('Trò chuyện', () => {
    it('danh sách cuộc cũ; chọn tình huống rồi bắt đầu: tạo cuộc và mở cuộc đó', async () => {
        const goi = mo('/hoi-ai?tab=tro-chuyen', { 'POST /api/v1/ai/tro-chuyen': () => ({ status: 201, json: { data: CUOC } }) });
        expect(await screen.findByRole('link', { name: /Guten Tag/ })).toBeTruthy();
        fireEvent.click(screen.getByRole('radio', { name: 'Phỏng vấn xin visa' }));
        fireEvent.click(screen.getByRole('button', { name: 'Bắt đầu trò chuyện' }));
        await waitFor(() => expect(screen.getByTestId('duong-dan').textContent).toBe(`/hoi-ai/tro-chuyen/${MA}`));
        expect(goi.find((g) => g.method === 'POST')?.body).toEqual({ scenario: 'visa_interview' });
    });

    it('gửi tin: thêm hai tin, phần sửa câu dưới tin của bạn, nghĩa tiếng Việt bấm mới hiện, ô nhập trống lại', async () => {
        const goi = mo(`/hoi-ai/tro-chuyen/${MA}`, {
            [`POST /api/v1/ai/tro-chuyen/${MA}/tin`]: () => ({ json: { data: {}, meta: META, messages: HAI_TIN, conversation: { ...CUOC, message_count: 2 } } }),
        });
        expect(await screen.findByText('AI chỉ đọc 10 tin gần nhất.')).toBeTruthy();
        const o = screen.getByLabelText('Tin nhắn của bạn (tiếng Đức)');
        fireEvent.change(o, { target: { value: 'Ich heißen Lan.' } });
        fireEvent.click(screen.getByRole('button', { name: 'Gửi' }));

        expect(await screen.findByText('Hallo Lan! Was möchten Sie trinken?')).toBeTruthy();
        expect(goi.find((g) => g.method === 'POST')?.body).toEqual({ text: 'Ich heißen Lan.' });
        expect(screen.getByText('Sửa câu của bạn')).toBeTruthy();
        expect(screen.getByText('Ich heiße')).toBeTruthy();
        expect(screen.queryByText('Chào Lan! Bạn muốn uống gì?')).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Hiện nghĩa tiếng Việt' }));
        expect(screen.getByText('Chào Lan! Bạn muốn uống gì?')).toBeTruthy();
        expect(screen.getByText('Ein Wasser, bitte.')).toBeTruthy();
        expect((o as HTMLTextAreaElement).value).toBe('');
    });

    it('gửi hai tin liền nhau trong cùng cuộc: cả bốn tin hiện theo thứ tự', async () => {
        let n = 0;
        mo(`/hoi-ai/tro-chuyen/${MA}`, {
            [`POST /api/v1/ai/tro-chuyen/${MA}/tin`]: (b) => {
                n += 2;
                const chu = (b as { text: string }).text;
                return {
                    json: {
                        data: {},
                        meta: META,
                        messages: [
                            { id: n - 1, role: 'user', content: chu, extra: null, created_at: n },
                            { id: n, role: 'assistant', content: `Antwort ${n}`, extra: null, created_at: n },
                        ],
                        conversation: { ...CUOC, message_count: n },
                    },
                };
            },
        });
        const o = await screen.findByLabelText('Tin nhắn của bạn (tiếng Đức)');
        for (const cau of ['Guten Tag.', 'Ich möchte ein Wasser.']) {
            fireEvent.change(o, { target: { value: cau } });
            fireEvent.click(screen.getByRole('button', { name: 'Gửi' }));
            expect(await screen.findByText(cau)).toBeTruthy();
        }
        expect(screen.getByText('Antwort 4')).toBeTruthy();
    });

    it('AI lỗi: chữ vẫn còn trong ô, có nút Thử lại', async () => {
        mo(`/hoi-ai/tro-chuyen/${MA}`, {
            [`POST /api/v1/ai/tro-chuyen/${MA}/tin`]: () => ({ status: 503, json: { code: 'ai_loi_tam_thoi', message: 'AI chưa trả lời được, thử lại sau.' } }),
        });
        const o = await screen.findByLabelText('Tin nhắn của bạn (tiếng Đức)');
        fireEvent.change(o, { target: { value: 'Hallo' } });
        fireEvent.click(screen.getByRole('button', { name: 'Gửi' }));
        expect(await screen.findByText('AI chưa trả lời được, thử lại sau.')).toBeTruthy();
        expect((o as HTMLTextAreaElement).value).toBe('Hallo');
        expect(screen.getByRole('button', { name: 'Thử lại' })).toBeTruthy();
    });

    it('cuộc không có (404): Không tìm thấy, có đường về danh sách', async () => {
        mo('/hoi-ai/tro-chuyen/01JDKHONGCO0000000000000000', {});
        expect(await screen.findByText('Không tìm thấy cuộc trò chuyện này.')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Về danh sách trò chuyện' })).toBeTruthy();
    });

    it('xoá: hỏi lại; Huỷ thì thôi; Xoá hẳn thì gọi xoá rồi về danh sách', async () => {
        const goi = mo(`/hoi-ai/tro-chuyen/${MA}`, { [`DELETE /api/v1/ai/tro-chuyen/${MA}`]: () => ({ status: 200, json: null }) });
        fireEvent.click(await screen.findByRole('button', { name: 'Xoá cuộc trò chuyện' }));
        expect(screen.getByText('Xoá hẳn cuộc trò chuyện này? Không lấy lại được.')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Huỷ' }));
        expect(goi.some((g) => g.method === 'DELETE')).toBe(false);
        fireEvent.click(screen.getByRole('button', { name: 'Xoá cuộc trò chuyện' }));
        fireEvent.click(screen.getByRole('button', { name: 'Xoá hẳn' }));
        await waitFor(() => expect(screen.getByTestId('duong-dan').textContent).toBe('/hoi-ai?tab=tro-chuyen'));
        expect(goi.some((g) => g.method === 'DELETE')).toBe(true);
    });
});
