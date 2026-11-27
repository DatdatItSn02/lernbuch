import { ChevronLeft, ChevronRight, Search, Star } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { DongDang, TuDuc } from '../components/Article';
import { Sheet } from '../components/Shell';
import { DangTai, LoiTai } from '../components/TaiNoiDung';
import { api } from '../lib/api';
import { truyVan, type DemSoTay, type PhanTrang, type SoTay as SoTayApi, type TheSoTay } from '../lib/noiDung';
import { quenDaTai, useApi } from '../lib/useApi';
import { useTitle } from '../lib/useTitle';
import { so, t, type Khoa } from '../i18n';

type Loc = '' | 'sao' | 'ghi-chu';
const LOC: { id: Loc; khoa: Khoa; dem: keyof DemSoTay }[] = [
    { id: '', khoa: 'soTay.tatCa', dem: 'all' },
    { id: 'sao', khoa: 'soTay.coSao', dem: 'starred' },
    { id: 'ghi-chu', khoa: 'soTay.coGhiChu', dem: 'noted' },
];

/** Ngừng gõ chừng này thì tìm. */
const CHO_TIM_MS = 300;

/** Sổ tay: thẻ có sao và thẻ có ghi chú riêng của người đang đăng nhập; sửa gần nhất trước, 20 thẻ mỗi trang. */
export function SoTay() {
    useTitle(t('khung.soTay'));
    const [params, setParams] = useSearchParams();
    const loc = (['sao', 'ghi-chu'].includes(params.get('loc') ?? '') ? params.get('loc') : '') as Loc;
    const tim = params.get('tim') ?? '';
    const trangHoi = Number(params.get('trang') ?? '1');
    const trang = Number.isInteger(trangHoi) && trangHoi > 1 ? trangHoi : 1;
    const [chu, setChu] = useState(tim);

    const { duLieu, loi, taiLai, dangTai } = useApi<{ data: TheSoTay[]; meta: PhanTrang & { counts: DemSoTay } }>(
        `/so-tay${truyVan({ loc, tim: tim.trim(), trang: trang > 1 ? trang : null })}`,
    );

    useEffect(() => {
        if (chu.trim() === tim.trim()) return;
        const hen = window.setTimeout(() => setParams(new URLSearchParams(truyVan({ loc, tim: chu.trim() }).slice(1)), { replace: true }), CHO_TIM_MS);
        return () => window.clearTimeout(hen);
        // chỉ chạy lại khi chữ gõ đổi
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [chu]);

    const boSao = async (the: TheSoTay) => {
        try {
            await api.sua<{ data: SoTayApi }>(`/the/${the.id}/so-tay`, { starred: !the.bookmark.starred });
        } finally {
            quenDaTai();
            taiLai();
        }
    };

    const ds = duLieu?.data;
    const meta = duLieu?.meta;
    const duong = (doi: { loc?: Loc; trang?: number }) => ({ search: truyVan({ loc: doi.loc ?? loc, tim: tim.trim(), trang: (doi.trang ?? 1) > 1 ? doi.trang : null }) });

    return (
        <Sheet>
            <div className="px-5 pt-6 pb-4 md:px-8">
                <h1 className="text-headline font-bold">{t('khung.soTay')}</h1>
                <p className="mt-1 text-ink-2">{t('soTay.moTa')}</p>
                {meta && meta.counts.all > 0 && (
                    <div className="mt-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <nav aria-label={t('soTay.loc')}>
                            <ul className="flex flex-wrap gap-1.5">
                                {LOC.map((l) => {
                                    const on = l.id === loc;
                                    return (
                                        <li key={l.id}>
                                            <Link
                                                to={duong({ loc: l.id })}
                                                aria-current={on ? 'page' : undefined}
                                                className={`flex min-h-11 items-center rounded-md border px-3.5 text-label font-semibold tabular-nums transition-colors duration-200 ${
                                                    on ? 'border-tim bg-tim-tint text-ink' : 'border-line text-ink-2 hover:border-line-strong hover:text-ink'
                                                }`}
                                            >
                                                {t(l.khoa, { n: so(meta.counts[l.dem]) })}
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </nav>
                        <div className="relative md:w-72">
                            <Search size={18} aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-2" />
                            <input
                                type="search"
                                value={chu}
                                onChange={(e) => setChu(e.target.value)}
                                aria-label={t('soTay.timTrong')}
                                className="min-h-11 w-full rounded-md border border-line-strong bg-paper pr-3 pl-9"
                            />
                        </div>
                    </div>
                )}
            </div>

            <section aria-label={t('soTay.danhSach')} aria-busy={dangTai} className="border-t border-line">
                {!ds || !meta ? (
                    loi ? <LoiTai loi={loi} taiLai={taiLai} className="px-5 md:px-8" /> : <DangTai className="px-5 md:px-8" />
                ) : ds.length === 0 ? (
                    <p className="px-5 py-8 text-ink-2 md:px-8">{meta.counts.all === 0 ? t('soTay.trong') : t('soTay.khongKhop')}</p>
                ) : (
                    <ul className="divide-y divide-line">
                        {ds.map((the) => (
                            <li key={the.id} className="flex items-start gap-3 px-5 py-3 md:px-8">
                                <div className="min-w-0 flex-1">
                                    <Link to={`/tu/${the.id}`} className="font-bold break-words underline-offset-2 hover:underline">
                                        <TuDuc the={the} />
                                    </Link>
                                    <span className="ml-2 text-label text-ink-2">
                                        <DongDang the={the} />
                                    </span>
                                    {the.meanings.vi && <span className="block break-words">{the.meanings.vi}</span>}
                                    {the.bookmark.note && (
                                        <p className="mt-1 font-hand text-hand break-words whitespace-pre-line text-ink-2">
                                            <span className="sr-only">{t('soTay.ghiChuRieng')} </span>
                                            {the.bookmark.note}
                                        </p>
                                    )}
                                </div>
                                <button
                                    type="button"
                                    aria-pressed={the.bookmark.starred}
                                    aria-label={the.bookmark.starred ? t('soTay.boSao', { tu: the.front }) : t('soTay.danhSao', { tu: the.front })}
                                    onClick={() => void boSao(the)}
                                    className={`flex size-11 shrink-0 items-center justify-center rounded-md border border-line transition-colors duration-200 hover:bg-sunken ${
                                        the.bookmark.starred ? 'text-warning-ink' : 'text-ink-2'
                                    }`}
                                >
                                    <Star size={20} aria-hidden="true" fill={the.bookmark.starred ? 'currentColor' : 'none'} />
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {meta && meta.pages > 1 && (
                <nav aria-label={t('chuDe.phanTrang')} className="flex items-center justify-between gap-3 border-t border-line px-5 py-3 md:px-8">
                    {meta.page > 1 ? (
                        <Link to={duong({ trang: meta.page - 1 })} className="inline-flex min-h-11 items-center gap-1 font-semibold hover:underline">
                            <ChevronLeft size={18} aria-hidden="true" />
                            {t('chuDe.trangTruoc')}
                        </Link>
                    ) : (
                        <span />
                    )}
                    <span className="text-label text-ink-2 tabular-nums">{t('chuDe.trang', { trang: meta.page, tong: meta.pages })}</span>
                    {meta.page < meta.pages ? (
                        <Link to={duong({ trang: meta.page + 1 })} className="inline-flex min-h-11 items-center gap-1 font-semibold hover:underline">
                            {t('chuDe.trangSau')}
                            <ChevronRight size={18} aria-hidden="true" />
                        </Link>
                    ) : (
                        <span />
                    )}
                </nav>
            )}
        </Sheet>
    );
}
