import { useCallback, useEffect, useState } from 'react';
import { api, LoiApi } from './api';

/** Bản đã tải gần đây theo đường dẫn: quay lại trang trước thì hiện ngay, rồi tải lại ở nền. */
const daTai = new Map<string, unknown>();
const GIU_TOI_DA = 60;

function nho(duong: string, duLieu: unknown) {
    daTai.delete(duong);
    daTai.set(duong, duLieu);
    if (daTai.size > GIU_TOI_DA) daTai.delete(daTai.keys().next().value as string);
}

/** Xoá bản nhớ (sau khi đổi dữ liệu, vd đánh dấu sao, để danh sách khác tải lại đúng). Test gọi để bắt đầu sạch. */
export function quenDaTai(): void {
    daTai.clear();
}

export type KetQuaApi<T> = {
    duLieu: T | undefined;
    /** Đang tải bản mới (có thể vẫn đang hiện bản cũ của bộ lọc trước). */
    dangTai: boolean;
    loi: LoiApi | null;
    taiLai: () => void;
    /** Sửa dữ liệu đang hiện mà không tải lại (vd đổi sao của một thẻ). */
    sua: (doi: (cu: T) => T) => void;
};

type Ve<T> = { duong: string | null; lan: number; duLieu: T | undefined; loi: LoiApi | null };

/**
 * Tải GET /api/v1{duong}; duong null thì không tải. Đổi đường dẫn (bộ lọc, trang) thì giữ dữ liệu cũ tới khi có bản mới;
 * kết quả về muộn của đường dẫn cũ bị bỏ qua.
 */
export function useApi<T>(duong: string | null): KetQuaApi<T> {
    const [lan, datLan] = useState(0);
    const [ve, datVe] = useState<Ve<T>>({ duong: null, lan: -1, duLieu: undefined, loi: null });

    useEffect(() => {
        if (duong === null) return;
        let bo = false;
        api.lay<T>(duong).then(
            (duLieu) => {
                if (bo) return;
                nho(duong, duLieu);
                datVe({ duong, lan, duLieu, loi: null });
            },
            (e: unknown) => {
                if (bo) return;
                const loi = e instanceof LoiApi ? e : new LoiApi(0, 'mat_mang');
                datVe((cu) => ({ duong, lan, duLieu: cu.duong === duong ? cu.duLieu : undefined, loi }));
            },
        );
        return () => {
            bo = true;
        };
    }, [duong, lan]);

    const taiLai = useCallback(() => datLan((n) => n + 1), []);
    const sua = useCallback(
        (doi: (cu: T) => T) =>
            datVe((cu) => {
                if (cu.duLieu === undefined) return cu;
                const moi = doi(cu.duLieu);
                if (cu.duong !== null) nho(cu.duong, moi);
                return { ...cu, duLieu: moi };
            }),
        [],
    );

    const dungDuong = ve.duong === duong;
    const duLieu = duong === null ? undefined : dungDuong ? ve.duLieu : ((daTai.get(duong) as T | undefined) ?? ve.duLieu);
    return {
        duLieu,
        dangTai: duong !== null && !(dungDuong && ve.lan === lan),
        loi: dungDuong ? ve.loi : null,
        taiLai,
        sua,
    };
}
