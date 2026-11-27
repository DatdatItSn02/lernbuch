// Bảng động từ theo Lernbuch (viewVerbs): tìm nguyên mẫu hoặc nghĩa, lọc cấp, động từ tách; mỗi trang 80 động từ;
// bấm động từ để mở bảng chia (Präsens, Präteritum, Perfekt theo 6 ngôi, Imperativ).
import { ChevronDown, Search } from 'lucide-react';
import { Fragment, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { CacTieng } from '../../components/KhoiNguPhap';
import { Sheet } from '../../components/Shell';
import { DangTai, LoiTai } from '../../components/TaiNoiDung';
import { api, LoiApi } from '../../lib/api';
import type { DongTuBang as DongTuApi } from '../../lib/baiTap';
import { docCap, truyVan, type PhanTrang } from '../../lib/noiDung';
import { useTitle } from '../../lib/useTitle';
import { useStore } from '../../state/store';
import { so, t } from '../../i18n';
import { LOP_CHIP, LocCap, NutLink } from './chung';

const CHO_TIM_MS = 220;
const NGOI = ['ich', 'du', 'er / sie / es', 'wir', 'ihr', 'sie / Sie'];

function BangChia({ v }: { v: DongTuApi }) {
    const c = v.conjugation;
    if (!c) return null;
    const imp = (c.imp ?? []).filter(Boolean);
    return (
        <div className="py-2">
            <div className="overflow-x-auto">
                <table className="border-collapse text-left" lang="de">
                    <caption className="sr-only">{t('dongTu.bangChia', { tu: v.infinitive })}</caption>
                    <thead>
                        <tr>
                            <td className="px-3 py-1" />
                            {(['thiPres', 'thiPrat', 'thiPerf'] as const).map((k) => (
                                <th key={k} scope="col" className="border-b border-line px-3 py-1 text-label font-semibold text-ink-2">
                                    {t(`dongTu.${k}`)}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {NGOI.map((ngoi, i) => (
                            <tr key={ngoi}>
                                <th scope="row" className="px-3 py-1 font-normal text-ink-2">
                                    {ngoi}
                                </th>
                                <td className="px-3 py-1 font-semibold">{c.pres?.[i]}</td>
                                <td className="px-3 py-1 font-semibold">{c.prat?.[i]}</td>
                                <td className="px-3 py-1 font-semibold">{c.perf?.[i]}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {imp.length > 0 && (
                <p className="mt-2">
                    <span className="font-semibold">{`${t('dongTu.imperativ')}: `}</span>
                    <span lang="de">{imp.join('  ·  ')}</span>
                </p>
            )}
            {(c.more ?? []).map((x, i) => (
                <p key={i} lang="de" className="mt-1">
                    {x}
                </p>
            ))}
            {c.example && (
                <p lang="de" className="mt-2 italic">
                    {c.example}
                </p>
            )}
            {v.card_id !== null && (
                <Link to={`/tu/${v.card_id}`} className="mt-2 inline-flex min-h-11 items-center font-semibold text-tim underline-offset-2 hover:underline">
                    {t('dongTu.xemThe')}
                </Link>
            )}
        </div>
    );
}

type Trang = { ds: DongTuApi[]; meta: PhanTrang };

export function DongTuBang() {
    useTitle(t('nguPhap.bangDongTu'));
    const { settings } = useStore();
    const [params, setParams] = useSearchParams();
    const q = params.get('q') ?? '';
    const cap = docCap(params.get('cap')).filter((c) => c >= 1 && c <= 4);
    const tach = params.get('tach') === '1';
    const [chu, setChu] = useState(q);
    const [trang, setTrang] = useState<Trang | null>(null);
    const [loi, setLoi] = useState<LoiApi | null>(null);
    const [loiThem, setLoiThem] = useState<LoiApi | null>(null);
    const [lan, setLan] = useState(0);
    const [dangThem, setDangThem] = useState(false);
    const [mo, setMo] = useState<string | null>(null);
    const loc = truyVan({ q: q.trim(), cap: cap.join(','), tach: tach ? 1 : null });

    const doi = (moi: { q?: string; cap?: number[]; tach?: boolean }) =>
        setParams(new URLSearchParams(truyVan({ q: (moi.q ?? q).trim(), cap: (moi.cap ?? cap).join(','), tach: (moi.tach ?? tach) ? 1 : null }).slice(1)), { replace: true });

    useEffect(() => {
        if (chu.trim() === q.trim()) return;
        const hen = window.setTimeout(() => doi({ q: chu }), CHO_TIM_MS);
        return () => window.clearTimeout(hen);
        // chỉ chạy lại khi chữ gõ đổi
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [chu]);

    useEffect(() => {
        let bo = false;
        api.lay<{ data: DongTuApi[]; meta: PhanTrang }>(`/dong-tu${loc}`).then(
            (r) => {
                if (bo) return;
                setTrang({ ds: r.data, meta: r.meta });
                setLoi(null);
            },
            (e: unknown) => {
                if (bo) return;
                setTrang(null);
                setLoi(e instanceof LoiApi ? e : new LoiApi(0, 'mat_mang'));
            },
        );
        return () => {
            bo = true;
        };
    }, [loc, lan]);

    const hienThem = async () => {
        if (!trang) return;
        setDangThem(true);
        setLoiThem(null);
        try {
            const r = await api.lay<{ data: DongTuApi[]; meta: PhanTrang }>(`/dong-tu${truyVan({ q: q.trim(), cap: cap.join(','), tach: tach ? 1 : null, trang: trang.meta.page + 1 })}`);
            setTrang((cu) => (cu ? { ds: [...cu.ds, ...r.data], meta: r.meta } : cu));
        } catch (e) {
            setLoiThem(e instanceof LoiApi ? e : new LoiApi(0, 'mat_mang'));
        } finally {
            setDangThem(false);
        }
    };

    const conLai = trang ? trang.meta.total - trang.ds.length : 0;

    return (
        <Sheet className="px-5 py-6 md:px-8">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-headline font-bold">{t('nguPhap.bangDongTu')}</h1>
                <NutLink to={`/bai-tap/chia-dong-tu${truyVan({ cap: cap.join(','), tach: tach ? 1 : null })}`} chinh>
                    {t('dongTu.luyenChia')}
                </NutLink>
            </div>

            <div className="mt-5 flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center md:gap-x-6">
                <LocCap
                    cap={cap}
                    onDoi={(c) => doi({ cap: c })}
                    them={
                        <button type="button" aria-pressed={tach} onClick={() => doi({ tach: !tach })} className={LOP_CHIP(tach)}>
                            {t('dongTu.dongTuTach')}
                        </button>
                    }
                />
                <div className="relative md:w-72">
                    <Search size={18} aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-2" />
                    <input
                        type="search"
                        value={chu}
                        onChange={(e) => setChu(e.target.value)}
                        aria-label={t('dongTu.tim')}
                        placeholder={t('dongTu.tim')}
                        className="min-h-11 w-full rounded-md border border-line-strong bg-paper pr-3 pl-9"
                    />
                </div>
                {trang && (
                    <p aria-live="polite" className="text-label font-semibold text-ink-2 tabular-nums">
                        {t('dongTu.soDongTu', { n: so(trang.meta.total) })}
                    </p>
                )}
            </div>

            {!trang ? (
                loi ? <LoiTai loi={loi} taiLai={() => setLan((n) => n + 1)} /> : <DangTai />
            ) : trang.ds.length === 0 ? (
                <p className="mt-6 text-ink-2">{loc ? t('dongTu.khongCo') : t('dongTu.chuaCo')}</p>
            ) : (
                <>
                    <div className="mt-4 -mx-5 overflow-x-auto md:mx-0">
                        <table className="w-full min-w-[720px] border-collapse text-left">
                            <thead>
                                <tr className="border-b-2 border-ink text-label text-ink-2">
                                    <th scope="col" className="px-3 py-2 font-semibold">
                                        {t('dongTu.cotNguyenMau')}
                                    </th>
                                    <th scope="col" className="px-3 py-2 font-semibold">
                                        {t('dongTu.cotDu')}
                                    </th>
                                    <th scope="col" className="px-3 py-2 font-semibold">
                                        {t('dongTu.cotEr')}
                                    </th>
                                    <th scope="col" className="px-3 py-2 font-semibold">
                                        {t('dongTu.cotPrat')}
                                    </th>
                                    <th scope="col" className="px-3 py-2 font-semibold">
                                        {t('dongTu.cotPerf')}
                                    </th>
                                    <th scope="col" className="px-3 py-2 font-semibold">
                                        {t('dongTu.cotNghia')}
                                    </th>
                                    <th scope="col" className="px-3 py-2 font-semibold">
                                        {t('dongTu.cotCap')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {trang.ds.map((v) => {
                                    const dangMo = mo === v.infinitive;
                                    const id = `chia-${v.infinitive.replace(/\s+/g, '-')}`;
                                    return (
                                        <Fragment key={v.infinitive}>
                                            <tr className="border-b border-line align-top">
                                                <th scope="row" className="px-3 py-1 text-left">
                                                    {v.conjugation ? (
                                                        <button
                                                            type="button"
                                                            aria-expanded={dangMo}
                                                            aria-controls={id}
                                                            onClick={() => setMo(dangMo ? null : v.infinitive)}
                                                            className="-ml-2 inline-flex min-h-11 items-center gap-1 rounded-md px-2 text-left font-bold hover:bg-sunken"
                                                        >
                                                            <span lang="de">{v.infinitive}</span>
                                                            <ChevronDown size={16} aria-hidden="true" className={`shrink-0 transition-transform duration-200 ${dangMo ? 'rotate-180' : ''}`} />
                                                            <span className="sr-only">{t('dongTu.moBangChia')}</span>
                                                        </button>
                                                    ) : (
                                                        <span lang="de" className="inline-flex min-h-11 items-center font-bold">
                                                            {v.infinitive}
                                                        </span>
                                                    )}
                                                    {v.is_separable && <span className="ml-1 rounded-sm border border-line px-1 text-label font-normal text-ink-2">{t('dongTu.tach')}</span>}
                                                </th>
                                                <td lang="de" className="px-3 py-3">
                                                    {v.form_du}
                                                </td>
                                                <td lang="de" className="px-3 py-3">
                                                    {v.form_er}
                                                </td>
                                                <td lang="de" className="px-3 py-3">
                                                    {v.preterite}
                                                </td>
                                                <td lang="de" className="px-3 py-3">
                                                    {v.perfect}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <CacTieng o={v.meanings} langs={settings.langs} />
                                                </td>
                                                <td className="px-3 py-3 text-label text-ink-2">{v.level}</td>
                                            </tr>
                                            {dangMo && (
                                                <tr id={id} className="border-b border-line bg-sunken">
                                                    <td colSpan={7} className="px-3">
                                                        <BangChia v={v} />
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {loiThem && <LoiTai loi={loiThem} taiLai={() => void hienThem()} />}
                    {conLai > 0 && (
                        <button
                            type="button"
                            onClick={() => void hienThem()}
                            disabled={dangThem}
                            className="mt-4 inline-flex min-h-11 items-center rounded-md border border-line-strong px-4 font-semibold transition-colors duration-200 hover:bg-sunken disabled:opacity-60"
                        >
                            {t('dongTu.hienThem', { n: so(conLai) })}
                        </button>
                    )}
                </>
            )}
        </Sheet>
    );
}
