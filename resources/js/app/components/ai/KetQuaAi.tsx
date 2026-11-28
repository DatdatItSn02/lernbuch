// Phần dùng chung của các chức năng AI: gọi và báo lỗi (useHoiAi, TrangThaiHoi), số lượt còn lại, các khối kết quả.
// Mọi chuỗi AI trả về luôn hiện như chữ (React tự thoát), không bao giờ chạy như mã.
import { useCallback, useRef, useState, type ReactNode } from 'react';
import { ngonNgu, t } from '../../i18n';
import { MA_DOI_TRANG_THAI, type LoiSua, type MetaAi, type ViDu } from '../../lib/ai';
import { LoiApi } from '../../lib/api';
import { useTaiKhoan } from '../../state/taiKhoan';
import { chuLoi } from './CongAi';

export const TIEU_DE_AI = 'text-label font-semibold text-ink-2';
export const NUT_AI =
    'inline-flex min-h-12 items-center rounded-md bg-tim px-5 font-semibold text-on-tim transition-colors duration-200 hover:bg-tim-strong disabled:opacity-60';
export const NUT_PHU_AI = 'inline-flex min-h-11 items-center gap-2 rounded-md border border-line-strong px-4 font-semibold transition-colors duration-200 hover:bg-sunken';

/** Hết lượt, mất mạng, chưa mở AI…: những lỗi này bấm lại ngay cũng vô ích, không hiện nút Thử lại. */
export const KHONG_THU_LAI = ['ai_het_luot', 'ai_bi_tu_choi', 'ai_dap_an_khop_mau', 'ai_khong_ap_dung', 'ai_qua_nhieu_cuoc', 'ai_cuoc_day', ...MA_DOI_TRANG_THAI];

/** Giờ có lượt mới, theo múi giờ của tài khoản. */
export function gioMoi(resetAt: number, muiGio: string | undefined): string {
    try {
        return new Intl.DateTimeFormat(ngonNgu(), { hour: '2-digit', minute: '2-digit', timeZone: muiGio }).format(new Date(resetAt * 1000));
    } catch {
        return new Intl.DateTimeFormat(ngonNgu(), { hour: '2-digit', minute: '2-digit' }).format(new Date(resetAt * 1000));
    }
}

/** Gọi một chức năng AI: đang hỏi, kết quả, lỗi; lỗi do trạng thái AI đổi thì tải lại hồ sơ để cổng hiện đúng bước. */
export function useHoiAi<T>() {
    const { taiLaiToi } = useTaiKhoan();
    const [kq, setKq] = useState<T | null>(null);
    const [dangHoi, setDangHoi] = useState(false);
    const [loi, setLoi] = useState<LoiApi | null>(null);
    const lanCuoi = useRef<(() => Promise<T>) | null>(null);

    const hoi = useCallback(
        async (goi: () => Promise<T>): Promise<T | null> => {
            lanCuoi.current = goi;
            setDangHoi(true);
            setLoi(null);
            try {
                const r = await goi();
                setKq(r);
                return r;
            } catch (e) {
                const l = e instanceof LoiApi ? e : new LoiApi(0, 'mat_mang');
                setLoi(l);
                if (l.ma && MA_DOI_TRANG_THAI.includes(l.ma)) void taiLaiToi();
                return null;
            } finally {
                setDangHoi(false);
            }
        },
        [taiLaiToi],
    );
    const thuLai = useCallback(() => (lanCuoi.current ? hoi(lanCuoi.current) : Promise.resolve(null)), [hoi]);
    const datLai = useCallback(() => {
        setKq(null);
        setLoi(null);
    }, []);

    return { kq, setKq, dangHoi, loi, hoi, thuLai, datLai };
}

/** Dòng "AI đang trả lời…" và khối lỗi (có giờ có lượt mới, có nút Thử lại khi bấm lại có ích). */
export function TrangThaiHoi({ dangHoi, loi, onThuLai, o }: { dangHoi: boolean; loi: LoiApi | null; onThuLai: () => void; o?: string }) {
    const { toi } = useTaiKhoan();
    const resetAt = typeof loi?.than.reset_at === 'number' ? loi.than.reset_at : null;
    return (
        <>
            {dangHoi && (
                <p role="status" className="mt-3 text-ink-2">
                    {t('ai.dangTraLoi')}
                </p>
            )}
            {loi && (
                <div role="alert" className="mt-3">
                    <p className="font-semibold">{chuLoi(loi, o)}</p>
                    {loi.ma === 'ai_het_luot' && resetAt !== null && <p className="mt-1 text-ink-2">{t('ai.coLuotMoiLuc', { gio: gioMoi(resetAt, toi?.timezone) })}</p>}
                    {!KHONG_THU_LAI.includes(loi.ma ?? '') && loi.ma !== 'mat_mang' && loi.trangThai !== 422 && (
                        <button type="button" onClick={onThuLai} className={`mt-3 ${NUT_PHU_AI}`}>
                            {t('ai.thuLai')}
                        </button>
                    )}
                </div>
            )}
        </>
    );
}

/** Số lượt AI còn lại hôm nay và lời nhắc AI có thể sai. */
export function ConLuotAi({ meta }: { meta: MetaAi | null | undefined }) {
    const { toi } = useTaiKhoan();
    const ai = toi?.ai;
    const conLai = meta ? meta.remaining : ai?.state === 'ready' && ai.limit >= 0 ? Math.max(0, ai.limit - ai.used) : null;
    return (
        <>
            {conLai !== null && conLai >= 0 && <p className="mt-5 text-label text-ink-2">{t('ai.conLuot', { n: conLai })}</p>}
            <p className="mt-1 text-label text-ink-2">{t('ai.coTheSai')}</p>
        </>
    );
}

/** Một phần của kết quả: tiêu đề nhỏ và nội dung; không có nội dung thì không hiện. */
export function MucAiKq({ tieuDe, children, co = true }: { tieuDe: string; children: ReactNode; co?: boolean }) {
    if (!co) return null;
    return (
        <section>
            <h3 className={TIEU_DE_AI}>{tieuDe}</h3>
            <div className="mt-1">{children}</div>
        </section>
    );
}

export function DoanAi({ chu, de = false }: { chu: string; de?: boolean }) {
    return (
        <p lang={de ? 'de' : undefined} className="whitespace-pre-line">
            {chu}
        </p>
    );
}

export function DsViDu({ ds }: { ds: ViDu[] }) {
    return (
        <ul className="divide-y divide-line">
            {ds.map((v, i) => (
                <li key={i} className="py-2">
                    <p lang="de" className="font-semibold">
                        {v.de}
                    </p>
                    <p className="text-ink-2">{v.vi}</p>
                </li>
            ))}
        </ul>
    );
}

export function DsLoi({ ds }: { ds: LoiSua[] }) {
    return (
        <ul className="space-y-2">
            {ds.map((l, i) => (
                <li key={i}>
                    <p>
                        <s lang="de">{l.wrong}</s> → <strong lang="de">{l.right}</strong>
                    </p>
                    {l.why && <p className="text-ink-2">{l.why}</p>}
                </li>
            ))}
        </ul>
    );
}
