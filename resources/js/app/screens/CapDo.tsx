import { useId } from 'react';
import { Link, useSearchParams } from 'react-router';
import { CachXem, CayChuDeView } from '../components/CayChuDe';
import { Sheet } from '../components/Shell';
import { DangTai, LoiTai } from '../components/TaiNoiDung';
import { demThe, docCap, tenCap, tenLoaiTu, truyVan, type BoThe, type CayChuDe } from '../lib/noiDung';
import { useApi } from '../lib/useApi';
import { useTitle } from '../lib/useTitle';
import { so, t } from '../i18n';

/**
 * Cấp độ: chọn loại thẻ (trừ Giao tiếp, có trang riêng), lọc theo cấp (chọn nhiều) và loại từ; danh sách chỉ còn chủ đề
 * có thẻ khớp. Bộ lọc nằm trên đường dẫn (?bo=&cap=&loai=) và đi theo sang trang chủ đề.
 */
export function CapDo() {
    useTitle(t('noiDung.capDo'));
    const uid = useId();
    const [params, setParams] = useSearchParams();
    const bo = params.get('bo') || 'vocab';
    const cap = docCap(params.get('cap'));
    const loai = params.get('loai') ?? '';

    const boThe = useApi<{ data: BoThe[] }>('/bo-the');
    const cay = useApi<{ data: CayChuDe }>(`/chu-de${truyVan({ bo, cap: cap.join(','), loai_tu: loai })}`);
    const dl = cay.duLieu?.data;
    const cacBo = (boThe.duLieu?.data ?? []).filter((b) => b.code !== 'comm');

    // Đổi bộ lọc thì thay đường dẫn hiện tại (không chồng thêm lịch sử); đổi loại thẻ là link riêng, bỏ bộ lọc cũ
    const doi = (moi: { cap?: number[]; loai?: string }) =>
        setParams(new URLSearchParams(truyVan({ bo: bo === 'vocab' ? null : bo, cap: (moi.cap ?? cap).join(','), loai: moi.loai ?? loai }).slice(1)), { replace: true });
    const batCap = (c: number) => doi({ cap: cap.includes(c) ? cap.filter((x) => x !== c) : [...cap, c].sort((a, b) => (a || 9) - (b || 9)) });
    const duongChuDe = (slug: string) => `/chu-de/${slug}${truyVan({ cap: cap.join(','), loai })}`;
    const coLoc = cap.length > 0 || loai !== '';

    return (
        <Sheet>
            <CachXem dang="/cap-do" />
            <div className="px-5 py-6 md:px-8">
                <h1 className="text-headline font-bold">{t('noiDung.capDo')}</h1>
                <p className="mt-1 text-ink-2">{t('noiDung.capDoMoTa')}</p>

                {cacBo.length > 0 && (
                    <nav aria-label={t('noiDung.loaiThe')} className="mt-5">
                        <ul className="flex flex-wrap gap-1.5">
                            {cacBo.map((b) => {
                                const on = b.code === bo;
                                return (
                                    <li key={b.code}>
                                        <Link
                                            to={b.code === 'vocab' ? '/cap-do' : `/cap-do?bo=${b.code}`}
                                            aria-current={on ? 'page' : undefined}
                                            className={`flex min-h-11 items-center gap-1.5 rounded-md border px-3.5 text-label font-semibold transition-colors duration-200 ${
                                                on ? 'border-tim bg-tim-tint text-ink' : 'border-line text-ink-2 hover:border-line-strong hover:text-ink'
                                            }`}
                                        >
                                            {b.name}
                                            <span className="font-normal tabular-nums">{so(b.card_count)}</span>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </nav>
                )}

                {dl && (
                    <div className="mt-4 flex flex-col gap-3 md:flex-row md:flex-wrap md:items-end md:gap-x-6">
                        {dl.levels.length > 0 && (
                            <div role="group" aria-label={t('noiDung.locCap')} className="flex flex-wrap gap-1.5">
                                {dl.levels.map((l) => {
                                    const on = cap.includes(l.level);
                                    return (
                                        <button
                                            key={l.level}
                                            type="button"
                                            aria-pressed={on}
                                            onClick={() => batCap(l.level)}
                                            className={`flex min-h-11 items-center gap-1.5 rounded-md border px-3 text-label font-semibold transition-colors duration-200 ${
                                                on ? 'border-tim bg-tim-tint text-ink' : 'border-line text-ink-2 hover:border-line-strong hover:text-ink'
                                            }`}
                                        >
                                            {tenCap(l.level)}
                                            <span className="font-normal tabular-nums">{so(l.count)}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                        {dl.pos.length > 1 && (
                            <div>
                                <label htmlFor={`${uid}-loai`} className="sr-only">
                                    {t('chuDe.loaiTu')}
                                </label>
                                <select
                                    id={`${uid}-loai`}
                                    value={loai}
                                    onChange={(e) => doi({ loai: e.target.value })}
                                    className="min-h-11 rounded-md border border-line-strong bg-paper px-3 text-label font-semibold"
                                >
                                    <option value="">{t('chuDe.moiLoaiTu')}</option>
                                    {dl.pos.map((p) => (
                                        <option key={p.pos} value={p.pos}>
                                            {`${tenLoaiTu(p.pos)} (${so(p.count)})`}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                        {coLoc && (
                            <button
                                type="button"
                                onClick={() => doi({ cap: [], loai: '' })}
                                className="min-h-11 self-start px-2 text-label font-semibold text-tim underline-offset-2 hover:underline md:self-auto"
                            >
                                {t('chuDe.boLoc')}
                            </button>
                        )}
                    </div>
                )}

                <div aria-busy={cay.dangTai}>
                    {dl ? (
                        <>
                            <p aria-live="polite" className="mt-4 text-label font-semibold tabular-nums">
                                {t('noiDung.tong', { chuDe: so(dl.topic_count), the: demThe(bo, dl.card_count) })}
                            </p>
                            {dl.groups.length === 0 && !coLoc ? (
                                <p className="mt-4 text-ink-2">{t('noiDung.chuaCoNoiDung')}</p>
                            ) : dl.groups.length === 0 ? (
                                <div className="mt-4">
                                    <p className="font-semibold">{t('noiDung.khongCoChuDe')}</p>
                                    <p className="mt-1 text-ink-2">{t('noiDung.goiYBoLoc')}</p>
                                </div>
                            ) : (
                                <CayChuDeView cay={dl} duongChuDe={duongChuDe} />
                            )}
                        </>
                    ) : cay.loi ? (
                        <LoiTai loi={cay.loi} taiLai={cay.taiLai} />
                    ) : (
                        <DangTai />
                    )}
                </div>
            </div>
        </Sheet>
    );
}
