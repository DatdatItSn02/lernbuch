import { screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { renderApp } from '../../test/render';
import { datNgonNgu, ngonNgu, so, t, themBanDich } from './index';
import { vi as tuDienVi } from './vi';

afterEach(() => {
    themBanDich('en', {});
    datNgonNgu('vi');
});

describe('t(): chữ giao diện theo ngôn ngữ', () => {
    it('tiếng Việt, thay tham số', () => {
        expect(t('caiDat.sang')).toBe('Sáng');
        expect(t('chuDe.demTu', { tu: 12, denHan: 3, moi: 5 })).toBe('12 từ · 3 đến hạn · 5 từ mới');
    });

    it('thiếu tham số thì giữ chỗ trống {ten} để dễ thấy, không làm hỏng câu', () => {
        expect(t('thoiGian.ngay', {})).toBe('{n} ngày');
    });

    it('ngôn ngữ chưa có bản dịch (tiếng Anh) thì hiện tiếng Việt, không hiện mã khoá', () => {
        datNgonNgu('en');
        expect(ngonNgu()).toBe('en');
        expect(t('caiDat.sang')).toBe('Sáng');
        expect(t('khung.caiDat')).not.toContain('khung.');
    });

    it('bản dịch thiếu một phần: khoá đã dịch hiện bản dịch, khoá thiếu hiện tiếng Việt; mã vùng dùng bản chung', () => {
        themBanDich('en', { caiDat: { sang: 'Light' } });
        datNgonNgu('en-GB');
        expect(t('caiDat.sang')).toBe('Light');
        expect(t('caiDat.toi')).toBe('Tối');
    });

    it('mã ngôn ngữ rỗng hoặc lạ không làm vỡ: dùng tiếng Việt', () => {
        datNgonNgu('');
        expect(ngonNgu()).toBe('vi');
        datNgonNgu('xx');
        expect(t('caiDat.lon')).toBe('Lớn');
    });

    it('số viết theo tiếng Việt', () => {
        expect(so(1.5)).toBe('1,5');
        expect(so(2466)).toBe('2.466');
    });

    it('từ điển tiếng Việt: mọi chữ khác rỗng, không thừa khoảng trắng đầu cuối', () => {
        // Nhóm có thể lồng nhiều tầng (vd ai.ngaySinh.tieuDe): gom mọi chữ ở mọi tầng
        const gom = (nut: unknown): string[] => (typeof nut === 'string' ? [nut] : Object.values(nut as Record<string, unknown>).flatMap(gom));
        const chu = gom(tuDienVi);
        expect(chu.length).toBeGreaterThan(200);
        for (const c of chu) {
            expect(c.trim()).not.toBe('');
            if (!c.startsWith(', ')) expect(c).toBe(c.trim());
        }
    });
});

describe('cả màn hình khi tài khoản đặt tiếng Anh mà chưa có bản dịch', () => {
    it('Cài đặt vẫn hiện đủ chữ tiếng Việt', () => {
        datNgonNgu('en');
        renderApp('/cai-dat');
        expect(screen.getByRole('heading', { level: 1, name: 'Cài đặt' })).toBeTruthy();
        expect(screen.getByRole('radio', { name: 'Tối' })).toBeTruthy();
        expect(document.body.textContent).not.toMatch(/\b(caiDat|khung|chung)\.[a-zA-Z]/);
    });
});
