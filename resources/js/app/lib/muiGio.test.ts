import { describe, expect, it } from 'vitest';
import { cacMuiGio, tenChuan } from './muiGio';

describe('múi giờ', () => {
    it('tên cũ của trình duyệt đổi sang tên chuẩn, không trùng', () => {
        expect(tenChuan('Asia/Saigon')).toBe('Asia/Ho_Chi_Minh');
        expect(tenChuan('Europe/Berlin')).toBe('Europe/Berlin');
        expect(cacMuiGio('Europe/Berlin', ['Asia/Saigon', 'Asia/Ho_Chi_Minh', 'Europe/Berlin'])).toEqual(['Asia/Ho_Chi_Minh', 'Europe/Berlin']);
    });

    it('múi giờ đang dùng không có trong danh sách thì vẫn hiện ở đầu', () => {
        expect(cacMuiGio('Asia/Ho_Chi_Minh', ['Europe/Berlin'])).toEqual(['Asia/Ho_Chi_Minh', 'Europe/Berlin']);
        expect(cacMuiGio('UTC', [])).toEqual(['UTC']);
    });
});
