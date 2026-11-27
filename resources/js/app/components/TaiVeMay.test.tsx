import 'fake-indexeddb/auto';
import { fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { giaApi } from '../../test/fakeApi';
import { giaCaches } from '../../test/fakeCaches';
import { theHoc } from '../../test/noiDungGia';
import { renderWith } from '../../test/render';
import { datLaiChoTest } from '../lib/hangCho';
import { KHO_AUDIO, datLaiOfflineChoTest, goiCua } from '../lib/offline';
import { TaiVeMay } from './TaiVeMay';

const GOI = {
    topic: { slug: 'so-dem-tu-vung', name: 'Số đếm', collection: 'vocab' },
    timezone: 'Asia/Ho_Chi_Minh',
    new_per_session: 10,
    saved_at: 1795744800,
    cards: [{ ...theHoc(8), audio: '/audio/zahlen.mp3' }, theHoc(9), theHoc(10)],
};

const mo = () => renderWith(<TaiVeMay slug="so-dem-tu-vung" bo="vocab" soThe={3} />);

beforeEach(async () => {
    datLaiOfflineChoTest();
    await datLaiChoTest();
});

afterEach(async () => {
    await datLaiChoTest();
});

describe('Tải chủ đề về máy để học khi không có mạng', () => {
    it('bấm tải: báo đang tải, xong thì ghi đã tải lúc nào; audio vào kho audio; xoá khỏi máy thì tải lại được', async () => {
        const kho = giaCaches();
        const goi = giaApi({ 'GET /api/v1/hoc/tai/so-dem-tu-vung': () => ({ json: { data: GOI } }), 'GET /audio/zahlen.mp3': () => ({ json: 'mp3' }) });
        mo();

        expect(await screen.findByText('3 từ, kèm audio. Học được cả khi mất mạng.')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Tải để học khi không có mạng' }));
        expect(screen.getByRole('button', { name: 'Tải để học khi không có mạng' }).hasAttribute('disabled')).toBe(true);

        expect(await screen.findByText(/^Đã tải về máy 3 từ lúc .+\. Học được cả khi mất mạng\.$/)).toBeTruthy();
        expect(goi.filter((g) => g.url === '/api/v1/hoc/tai/so-dem-tu-vung')).toHaveLength(1);
        expect((await goiCua('so-dem-tu-vung'))?.cards).toHaveLength(3);
        expect(kho.get(KHO_AUDIO)?.size).toBe(1);

        fireEvent.click(screen.getByRole('button', { name: 'Xoá khỏi máy' }));
        expect(await screen.findByText('Đã xoá chủ đề khỏi máy.')).toBeTruthy();
        expect(await goiCua('so-dem-tu-vung')).toBeNull();
        expect(kho.get(KHO_AUDIO)?.size).toBe(0);
        expect(screen.getByRole('button', { name: 'Tải để học khi không có mạng' })).toBeTruthy();
    });

    it('đã tải từ trước thì mở trang là thấy ngay; tải lại bản mới thì xin máy chủ lần nữa', async () => {
        giaCaches();
        const goi = giaApi({ 'GET /api/v1/hoc/tai/so-dem-tu-vung': () => ({ json: { data: GOI } }) });
        const dau = mo();
        fireEvent.click(await screen.findByRole('button', { name: 'Tải để học khi không có mạng' }));
        await screen.findByText(/^Đã tải về máy/);
        dau.unmount();

        mo();
        expect(await screen.findByText(/^Đã tải về máy 3 từ/)).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Tải lại bản mới' }));
        await screen.findByText(/^Đã tải về máy/);
        expect(goi.filter((g) => g.url === '/api/v1/hoc/tai/so-dem-tu-vung')).toHaveLength(2);
    });

    it('không tải được (mất mạng, máy chủ lỗi) thì báo, bấm lại được', async () => {
        giaApi({ 'GET /api/v1/hoc/tai/so-dem-tu-vung': () => ({ status: 503, json: { code: 'loi', message: '' } }) });
        mo();
        fireEvent.click(await screen.findByRole('button', { name: 'Tải để học khi không có mạng' }));
        expect(await screen.findByText('Không tải được. Kiểm tra mạng rồi thử lại.')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Tải để học khi không có mạng' }).hasAttribute('disabled')).toBe(false);
        expect(await goiCua('so-dem-tu-vung')).toBeNull();
    });
});
