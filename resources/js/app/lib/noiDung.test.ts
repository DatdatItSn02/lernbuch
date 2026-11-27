import { act, renderHook, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { tachChu } from '../components/CauBamDuoc';
import { giaApi } from '../../test/fakeApi';
import { demThe, docCap, tenCap, tenLoaiTu, trangCuaBo, truyVan } from './noiDung';
import { useApi } from './useApi';

describe('noiDung: hàm phụ', () => {
    it('docCap: đọc ?cap=1,2,0, bỏ giá trị lạ và trùng', () => {
        expect(docCap('1,2,x,9,0,2,-1,1.5')).toEqual([1, 2, 0]);
        expect(docCap(null)).toEqual([]);
        expect(docCap('')).toEqual([]);
    });

    it('truyVan: bỏ giá trị rỗng; không còn gì thì chuỗi rỗng', () => {
        expect(truyVan({ bo: 'vocab', cap: '', loai: null, trang: 2, tim: undefined })).toBe('?bo=vocab&trang=2');
        expect(truyVan({ cap: '' })).toBe('');
        expect(truyVan({ tim: 'bánh mì' })).toBe('?tim=b%C3%A1nh+m%C3%AC');
    });

    it('tên loại từ, cấp, đơn vị đếm, trang danh sách theo loại thẻ', () => {
        expect(tenLoaiTu('noun')).toBe('danh từ');
        expect(tenLoaiTu('adj_adv')).toBe('tính từ / trạng từ');
        expect(tenLoaiTu('ma-la')).toBe('khác');
        expect(tenLoaiTu(null)).toBe('');
        expect([tenCap(1), tenCap(4), tenCap(6), tenCap(0)]).toEqual(['A1', 'B2', 'C2', 'Chưa xếp cấp']);
        expect(demThe('vocab', 8691)).toBe('8.691 từ');
        expect(demThe('comm', 951)).toBe('951 câu');
        expect(demThe('pattern', 1)).toBe('1 câu');
        expect(trangCuaBo('vocab')).toEqual({ to: '/chu-de', khoa: 'khung.chuDe' });
        expect(trangCuaBo('comm')).toEqual({ to: '/giao-tiep', khoa: 'noiDung.giaoTiep' });
        expect(trangCuaBo('phrase')).toEqual({ to: '/cap-do?bo=phrase', khoa: 'noiDung.capDo' });
    });

    it('tachChu: chữ có lược, dấu nối, số; dấu câu và khoảng trắng để riêng', () => {
        const tu = (cau: string) => tachChu(cau).filter((p) => p.laTu).map((p) => p.chu);
        expect(tu("Wie geht's? – Gut!")).toEqual(['Wie', "geht's", 'Gut']);
        expect(tu('Die E-Mail kommt um 8 Uhr.')).toEqual(['Die', 'E-Mail', 'kommt', 'um', '8', 'Uhr']);
        expect(tu('Ich hätte gern zwei Brötchen.')).toEqual(['Ich', 'hätte', 'gern', 'zwei', 'Brötchen']);
        expect(tachChu('Ja, bitte.').map((p) => p.chu).join('')).toBe('Ja, bitte.');
        expect(tachChu('')).toEqual([]);
    });
});

describe('useApi', () => {
    it('đường dẫn null thì không tải; có đường dẫn thì tải, xong thì hết dangTai', async () => {
        const goi = giaApi({ 'GET /api/v1/a': () => ({ json: { n: 1 } }) });
        const { result, rerender } = renderHook(({ d }: { d: string | null }) => useApi<{ n: number }>(d), { initialProps: { d: null as string | null } });
        expect(result.current).toMatchObject({ duLieu: undefined, dangTai: false, loi: null });
        rerender({ d: '/a' });
        expect(result.current.dangTai).toBe(true);
        await waitFor(() => expect(result.current.duLieu).toEqual({ n: 1 }));
        expect(result.current.dangTai).toBe(false);
        expect(goi).toHaveLength(1);
    });

    it('đổi đường dẫn: giữ dữ liệu cũ tới khi có bản mới; kết quả về muộn của đường dẫn cũ bị bỏ', async () => {
        const cho: Record<string, (v: Response) => void> = {};
        vi.stubGlobal(
            'fetch',
            vi.fn((url: string) => new Promise<Response>((xong) => (cho[url] = xong))),
        );
        const tra = (url: string, n: number) => cho[url](new Response(JSON.stringify({ n }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
        const { result, rerender } = renderHook(({ d }) => useApi<{ n: number }>(d), { initialProps: { d: '/x?trang=1' } });
        await act(async () => tra('/api/v1/x?trang=1', 1));
        expect(result.current.duLieu).toEqual({ n: 1 });

        rerender({ d: '/x?trang=2' });
        rerender({ d: '/x?trang=3' });
        expect(result.current).toMatchObject({ duLieu: { n: 1 }, dangTai: true });
        await act(async () => tra('/api/v1/x?trang=3', 3));
        await act(async () => tra('/api/v1/x?trang=2', 2));
        expect(result.current).toMatchObject({ duLieu: { n: 3 }, dangTai: false });
    });

    it('lỗi: có mã lỗi, mất mạng là mat_mang; Thử lại tải lại; sua đổi dữ liệu đang hiện', async () => {
        let lan = 0;
        giaApi({ 'GET /api/v1/b': () => (++lan === 1 ? { status: 500, json: null } : { json: { n: 2 } }) });
        const { result } = renderHook(() => useApi<{ n: number }>('/b'));
        await waitFor(() => expect(result.current.loi?.trangThai).toBe(500));
        act(() => result.current.taiLai());
        await waitFor(() => expect(result.current.duLieu).toEqual({ n: 2 }));
        expect(result.current.loi).toBeNull();
        act(() => result.current.sua((cu) => ({ n: cu.n + 1 })));
        expect(result.current.duLieu).toEqual({ n: 3 });

        vi.stubGlobal('fetch', vi.fn(async () => Promise.reject(new TypeError('Failed to fetch'))));
        const mat = renderHook(() => useApi<{ n: number }>('/c'));
        await waitFor(() => expect(mat.result.current.loi?.ma).toBe('mat_mang'));
    });

    it('mở lại đường dẫn đã tải thì hiện ngay bản đã nhớ, rồi tải lại ở nền', async () => {
        let n = 0;
        giaApi({ 'GET /api/v1/d': () => ({ json: { n: ++n } }) });
        const dau = renderHook(() => useApi<{ n: number }>('/d'));
        await waitFor(() => expect(dau.result.current.duLieu).toEqual({ n: 1 }));
        dau.unmount();
        const sau = renderHook(() => useApi<{ n: number }>('/d'));
        expect(sau.result.current).toMatchObject({ duLieu: { n: 1 }, dangTai: true });
        await waitFor(() => expect(sau.result.current.duLieu).toEqual({ n: 2 }));
    });
});
