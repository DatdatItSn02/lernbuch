import { CircleCheck, Download, RotateCw, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { demThe } from '../lib/noiDung';
import { goiCua, taiChuDe, tomTatGoi, xoaChuDeTai, type TienDoTai, type TomTatGoi } from '../lib/offline';
import { ngonNgu, t } from '../i18n';

type TrangThai =
    | { kieu: 'dang-xem' }
    | { kieu: 'chua'; vuaXoa?: boolean }
    | { kieu: 'dang-tai'; tienDo: TienDoTai }
    | { kieu: 'xong'; goi: TomTatGoi }
    | { kieu: 'loi' };

const gio = (luc: number) => new Intl.DateTimeFormat(ngonNgu(), { dateStyle: 'short', timeStyle: 'short' }).format(new Date(luc * 1000));

/**
 * Tải một chủ đề về máy để học khi không có mạng (thẻ, mức hiện tại, đáp án nhiễu, audio): bấm tải, thấy tiến độ,
 * xong thì ghi đã tải lúc nào, tải lại bản mới hoặc xoá khỏi máy. Trình duyệt không lưu được (không có IndexedDB) thì ẩn.
 */
export function TaiVeMay({ slug, bo, soThe }: { slug: string; bo: string; soThe: number }) {
    const [tt, setTt] = useState<TrangThai>({ kieu: 'dang-xem' });

    useEffect(() => {
        let huy = false;
        void goiCua(slug).then((g) => !huy && setTt(g ? { kieu: 'xong', goi: tomTatGoi(g) } : { kieu: 'chua' }));
        return () => {
            huy = true;
        };
    }, [slug]);

    if (typeof indexedDB === 'undefined' || tt.kieu === 'dang-xem') return null;

    const tai = () => {
        setTt({ kieu: 'dang-tai', tienDo: { buoc: 'the', xong: 0, tong: 1 } });
        taiChuDe(slug, (tienDo) => setTt({ kieu: 'dang-tai', tienDo })).then(
            (goi) => setTt({ kieu: 'xong', goi }),
            () => setTt({ kieu: 'loi' }),
        );
    };
    const xoa = () => {
        void xoaChuDeTai(slug).then(() => setTt({ kieu: 'chua', vuaXoa: true }));
    };

    const nut = 'inline-flex min-h-11 items-center gap-2 rounded-md px-3 text-label font-semibold transition-colors duration-200';
    return (
        <div className="mt-3 flex max-w-2xl flex-wrap items-center gap-x-3 gap-y-1">
            <p role="status" className="min-w-0 flex-1 basis-64 text-label text-ink-2">
                {tt.kieu === 'chua' && (tt.vuaXoa ? t('taiVe.daXoa') : t('taiVe.moTa', { the: demThe(bo, soThe) }))}
                {tt.kieu === 'dang-tai' &&
                    (tt.tienDo.buoc === 'the' ? t('taiVe.dangTaiThe') : t('taiVe.dangTaiAudio', { xong: tt.tienDo.xong, tong: tt.tienDo.tong }))}
                {tt.kieu === 'xong' && (
                    <span className="inline-flex items-start gap-1.5 text-ink">
                        <CircleCheck size={18} aria-hidden="true" className="mt-0.5 shrink-0 text-tim" />
                        {t('taiVe.daTai', { the: demThe(bo, tt.goi.so_the), luc: gio(tt.goi.saved_at) })}
                    </span>
                )}
                {tt.kieu === 'loi' && <span className="font-semibold text-danger">{t('taiVe.loi')}</span>}
            </p>
            {(tt.kieu === 'chua' || tt.kieu === 'loi' || tt.kieu === 'dang-tai') && (
                <button type="button" onClick={tai} disabled={tt.kieu === 'dang-tai'} className={`${nut} border border-line-strong hover:bg-sunken disabled:opacity-60`}>
                    <Download size={18} aria-hidden="true" />
                    {t('taiVe.nut')}
                </button>
            )}
            {tt.kieu === 'xong' && (
                <span className="flex flex-wrap gap-1">
                    <button type="button" onClick={tai} className={`${nut} text-tim hover:bg-tim-tint`}>
                        <RotateCw size={16} aria-hidden="true" />
                        {t('taiVe.taiLai')}
                    </button>
                    <button type="button" onClick={xoa} className={`${nut} text-ink-2 hover:bg-sunken hover:text-ink`}>
                        <Trash2 size={16} aria-hidden="true" />
                        {t('taiVe.xoa')}
                    </button>
                </span>
            )}
        </div>
    );
}
