import { useState } from 'react';
import { chuLoi, CongAi } from '../../components/ai/CongAi';
import { ngonNgu, t } from '../../i18n';
import { giaiThichTu, MA_DOI_TRANG_THAI, type GiaiThichTu, type MetaAi } from '../../lib/ai';
import { LoiApi } from '../../lib/api';
import { useTaiKhoan } from '../../state/taiKhoan';

const TIEU_DE = 'text-label font-semibold text-ink-2';
/** Hết lượt, mất mạng, chưa mở AI…: những lỗi này bấm lại ngay cũng vô ích, không hiện nút Thử lại. */
const KHONG_THU_LAI = ['ai_het_luot', 'ai_bi_tu_choi', ...MA_DOI_TRANG_THAI];

/** Giờ có lượt mới, theo múi giờ của tài khoản. */
function gioMoi(resetAt: number, muiGio: string | undefined): string {
    try {
        return new Intl.DateTimeFormat(ngonNgu(), { hour: '2-digit', minute: '2-digit', timeZone: muiGio }).format(new Date(resetAt * 1000));
    } catch {
        return new Intl.DateTimeFormat(ngonNgu(), { hour: '2-digit', minute: '2-digit' }).format(new Date(resetAt * 1000));
    }
}

/** Tab Hỏi AI của thẻ từ: qua cổng (ngày sinh, đồng ý) rồi bấm "Giải thích từ này". Kết quả luôn hiện như chữ. */
export default function TabHoiAi({ cardId }: { cardId: number }) {
    return (
        <CongAi>
            <GiaiThich cardId={cardId} />
        </CongAi>
    );
}

function GiaiThich({ cardId }: { cardId: number }) {
    const { toi, taiLaiToi } = useTaiKhoan();
    const [kq, setKq] = useState<{ data: GiaiThichTu; meta: MetaAi } | null>(null);
    const [dangHoi, setDangHoi] = useState(false);
    const [loi, setLoi] = useState<LoiApi | null>(null);

    const hoi = async () => {
        setDangHoi(true);
        setLoi(null);
        try {
            setKq(await giaiThichTu(cardId));
        } catch (e) {
            const l = e instanceof LoiApi ? e : new LoiApi(0, 'mat_mang');
            setLoi(l);
            if (l.ma && MA_DOI_TRANG_THAI.includes(l.ma)) void taiLaiToi();
        } finally {
            setDangHoi(false);
        }
    };

    const ai = toi?.ai;
    const conLai = kq ? kq.meta.remaining : ai?.state === 'ready' && ai.limit >= 0 ? Math.max(0, ai.limit - ai.used) : null;
    const resetAt = typeof loi?.than.reset_at === 'number' ? loi.than.reset_at : null;
    const d = kq?.data;

    return (
        <div>
            <button
                type="button"
                onClick={() => void hoi()}
                disabled={dangHoi}
                className="inline-flex min-h-12 items-center rounded-md bg-tim px-5 font-semibold text-on-tim transition-colors duration-200 hover:bg-tim-strong disabled:opacity-60"
            >
                {t('ai.giaiThich')}
            </button>
            {dangHoi && (
                <p role="status" className="mt-3 text-ink-2">
                    {t('ai.dangTraLoi')}
                </p>
            )}
            {loi && (
                <div role="alert" className="mt-3">
                    <p className="font-semibold">{chuLoi(loi)}</p>
                    {loi.ma === 'ai_het_luot' && resetAt !== null && <p className="mt-1 text-ink-2">{t('ai.coLuotMoiLuc', { gio: gioMoi(resetAt, toi?.timezone) })}</p>}
                    {!KHONG_THU_LAI.includes(loi.ma ?? '') && loi.ma !== 'mat_mang' && (
                        <button
                            type="button"
                            onClick={() => void hoi()}
                            className="mt-3 inline-flex min-h-11 items-center gap-2 rounded-md border border-line-strong px-4 font-semibold transition-colors duration-200 hover:bg-sunken"
                        >
                            {t('ai.thuLai')}
                        </button>
                    )}
                </div>
            )}
            {d && (
                <div aria-live="polite" className="mt-5 space-y-5">
                    <section>
                        <h3 className={TIEU_DE}>{t('ai.nghia')}</h3>
                        <p className="mt-1 whitespace-pre-line">{d.meaning}</p>
                    </section>
                    {d.usage && (
                        <section>
                            <h3 className={TIEU_DE}>{t('ai.cachDung')}</h3>
                            <p className="mt-1 whitespace-pre-line">{d.usage}</p>
                        </section>
                    )}
                    {d.examples.length > 0 && (
                        <section>
                            <h3 className={TIEU_DE}>{t('ai.viDu')}</h3>
                            <ul className="mt-1 divide-y divide-line">
                                {d.examples.map((v, i) => (
                                    <li key={i} className="py-2">
                                        <p lang="de" className="font-semibold">
                                            {v.de}
                                        </p>
                                        <p className="text-ink-2">{v.vi}</p>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                    {d.common_mistakes.length > 0 && (
                        <section>
                            <h3 className={TIEU_DE}>{t('ai.loiHayGap')}</h3>
                            <ul className="mt-1 list-disc space-y-1 pl-5">
                                {d.common_mistakes.map((l, i) => (
                                    <li key={i}>{l}</li>
                                ))}
                            </ul>
                        </section>
                    )}
                </div>
            )}
            {conLai !== null && conLai >= 0 && <p className="mt-5 text-label text-ink-2">{t('ai.conLuot', { n: conLai })}</p>}
            <p className="mt-1 text-label text-ink-2">{t('ai.coTheSai')}</p>
        </div>
    );
}
