// Ngữ pháp theo Lernbuch (viewGrammar, viewDoc): danh sách theo cấp và chương, tìm bài; trang bài có mục lục,
// nội dung theo khối, nút luyện tập bài này, bài trước và bài sau.
import { ArrowLeft, ArrowRight, Search } from 'lucide-react';
import { useDeferredValue, useMemo, useState, type MouseEvent, type ReactNode } from 'react';
import { Link, useParams, useSearchParams } from 'react-router';
import { HoiNguPhap } from '../../components/ai/HoiNguPhap';
import { Choice } from '../../components/Choice';
import { KhoiNguPhap, cacPhan } from '../../components/KhoiNguPhap';
import { Sheet } from '../../components/Shell';
import { DangTai, LoiTai } from '../../components/TaiNoiDung';
import type { BaiNguPhap as BaiApi, BaiNguPhapTomTat, Chuong, DanhSachNguPhap } from '../../lib/baiTap';
import { fold } from '../../lib/luyen';
import { chuMucLuc, tachTenBai } from '../../lib/nguPhap';
import { tenCap, truyVan } from '../../lib/noiDung';
import { useApi } from '../../lib/useApi';
import { useTitle } from '../../lib/useTitle';
import { useStore } from '../../state/store';
import { useTaiKhoan } from '../../state/taiKhoan';
import { coTabAi } from '../../lib/ai';
import { so, t } from '../../i18n';
import { CAC_CAP, NutLink } from './chung';

const TEN_CAP = CAC_CAP.map((c) => tenCap(c));

/** Tên chương như Lernbuch: "3. Động từ"; chương 9 (bổ trợ) chỉ có tên. */
const tenChuong = (c: Chuong) => (c.no === 9 ? c.name : t('nguPhap.chuong', { so: c.no, ten: c.name }));

function DongBai({ b }: { b: BaiNguPhapTomTat }) {
    const ten = tachTenBai(b.title);
    return (
        <li>
            <Link to={`/ngu-phap/${b.slug}`} className="flex min-h-11 items-baseline justify-between gap-4 py-2 hover:underline hover:underline-offset-2">
                <span className="min-w-0 break-words">
                    {ten.viet ? (
                        <>
                            <span lang="de" className="font-semibold">
                                {ten.duc}
                            </span>
                            <span className="text-ink-2">{` · ${ten.viet}`}</span>
                        </>
                    ) : (
                        <span className="font-semibold">{b.title}</span>
                    )}
                </span>
                {b.exercises > 0 && <span className="shrink-0 text-label text-ink-2 tabular-nums">{t('nguPhap.soCau', { n: so(b.exercises) })}</span>}
            </Link>
        </li>
    );
}

function Mo({ tieuDe, phu, mo, children }: { tieuDe: string; phu: string; mo: boolean; children: ReactNode }) {
    return (
        <details open={mo} className="group border-b border-line">
            <summary className="flex min-h-12 cursor-pointer list-none items-center justify-between gap-4 py-2 font-semibold [&::-webkit-details-marker]:hidden">
                <span className="flex items-center gap-2">
                    <ArrowRight size={16} aria-hidden="true" className="shrink-0 transition-transform duration-200 group-open:rotate-90" />
                    {tieuDe}
                </span>
                <span className="shrink-0 text-label font-normal text-ink-2 tabular-nums">{phu}</span>
            </summary>
            <ul className="pb-3 pl-6">{children}</ul>
        </details>
    );
}

/** Danh sách ngữ pháp: lọc một cấp (hoặc tất cả), tìm theo tên bài (không cần dấu). */
export function NguPhap() {
    useTitle(t('khung.nguPhap'));
    const [params, setParams] = useSearchParams();
    const capChon = TEN_CAP.includes(params.get('cap') ?? '') ? (params.get('cap') as string) : '';
    const [tim, setTim] = useState('');
    const timTre = useDeferredValue(tim);
    const { duLieu, loi, taiLai } = useApi<{ data: DanhSachNguPhap }>('/ngu-phap');

    const nhom = useMemo(() => {
        if (!duLieu) return null;
        const { lessons, chapters } = duLieu.data;
        const f = fold(timTre);
        const khop = (b: BaiNguPhapTomTat) => !f || fold(b.title).includes(f);
        const chuong = new Map(chapters.map((c) => [c.code, c]));
        const theoCap = TEN_CAP.filter((c) => !capChon || c === capChon).map((cap) => {
            const cua = lessons.filter((b) => b.level === cap && khop(b));
            const moi = cua.filter((b) => b.chapter !== null);
            const cacChuong = chapters
                .filter((c) => c.level === cap)
                .map((c) => ({ c, bai: moi.filter((b) => b.chapter === c.code) }))
                .filter((x) => x.bai.length > 0);
            return { cap, soMoi: moi.length, cacChuong, cu: cua.filter((b) => b.chapter === null || !chuong.has(b.chapter)) };
        });
        return {
            theoCap: theoCap.filter((x) => x.cacChuong.length > 0 || x.cu.length > 0),
            chuyenDe: capChon ? [] : lessons.filter((b) => b.level === null && khop(b)),
            tong: lessons.length,
        };
    }, [duLieu, timTre, capChon]);

    return (
        <Sheet className="px-5 py-6 md:px-8">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-headline font-bold">{t('khung.nguPhap')}</h1>
                <div className="flex flex-wrap gap-2">
                    <NutLink to="/dong-tu">{t('nguPhap.bangDongTu')}</NutLink>
                    <NutLink to="/so-nhieu">{t('nguPhap.soNhieu')}</NutLink>
                    <NutLink to="/bai-tap" chinh>
                        {t('nguPhap.lamBaiTap')}
                    </NutLink>
                </div>
            </div>

            <div className="mt-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <Choice
                    legend={t('noiDung.locCap')}
                    hideLegend
                    name="cap-ngu-phap"
                    value={capChon}
                    options={[{ value: '', label: t('nguPhap.tatCaCap') }, ...TEN_CAP.map((c) => ({ value: c, label: c }))]}
                    onChange={(c) => setParams(new URLSearchParams(truyVan({ cap: c }).slice(1)), { replace: true })}
                />
                <div className="relative md:w-72">
                    <Search size={18} aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-2" />
                    <input
                        type="search"
                        value={tim}
                        onChange={(e) => setTim(e.target.value)}
                        aria-label={t('nguPhap.timBai')}
                        placeholder={t('nguPhap.timBai')}
                        className="min-h-11 w-full rounded-md border border-line-strong bg-paper pr-3 pl-9"
                    />
                </div>
            </div>

            {!nhom ? (
                loi ? <LoiTai loi={loi} taiLai={taiLai} /> : <DangTai />
            ) : nhom.tong === 0 ? (
                <p className="mt-6 text-ink-2">{t('nguPhap.chuaCo')}</p>
            ) : nhom.theoCap.length === 0 && nhom.chuyenDe.length === 0 ? (
                <p role="status" className="mt-6 text-ink-2">
                    {t('nguPhap.khongKhop')}
                </p>
            ) : (
                <>
                    {nhom.theoCap.map((x) => (
                        <section key={x.cap} aria-labelledby={`cap-${x.cap}`} className="mt-7">
                            <h2 id={`cap-${x.cap}`} className="flex items-baseline gap-3 border-b-2 border-ink pb-1">
                                <span className="text-title font-extrabold">{x.cap}</span>
                                <span className="text-label text-ink-2">{t('nguPhap.soDiem', { n: so(x.soMoi) })}</span>
                            </h2>
                            {x.cacChuong.map(({ c, bai }) => (
                                <Mo key={c.code} tieuDe={tenChuong(c)} phu={t('nguPhap.soBai', { n: so(bai.length) })} mo={!!timTre || x.cacChuong.length <= 3}>
                                    {bai.map((b) => (
                                        <DongBai key={b.slug} b={b} />
                                    ))}
                                </Mo>
                            ))}
                            {x.cu.length > 0 && (
                                <Mo tieuDe={t('nguPhap.giaoTrinhGoc')} phu={t('nguPhap.soBai', { n: so(x.cu.length) })} mo={!!timTre}>
                                    {x.cu.map((b) => (
                                        <DongBai key={b.slug} b={b} />
                                    ))}
                                </Mo>
                            )}
                        </section>
                    ))}
                    {nhom.chuyenDe.length > 0 && (
                        <section aria-labelledby="chuyen-de" className="mt-7">
                            <h2 id="chuyen-de" className="border-b-2 border-ink pb-1 text-title font-extrabold">
                                {t('nguPhap.chuyenDe')}
                            </h2>
                            <ul>
                                {nhom.chuyenDe.map((b) => (
                                    <DongBai key={b.slug} b={b} />
                                ))}
                            </ul>
                        </section>
                    )}
                </>
            )}
        </Sheet>
    );
}

/** Một bài ngữ pháp. */
export function BaiNguPhap() {
    const { slug = '' } = useParams();
    const { settings } = useStore();
    const coAi = coTabAi(useTaiKhoan().toi?.ai);
    const { duLieu, loi, taiLai } = useApi<{ data: BaiApi }>(`/ngu-phap/${encodeURIComponent(slug)}`);
    const bai = duLieu?.data.slug === slug ? duLieu.data : undefined;
    useTitle(bai?.title ?? t('khung.nguPhap'));
    const phan = useMemo(() => (bai ? cacPhan(bai.blocks) : []), [bai]);
    const ten = bai ? tachTenBai(bai.title) : null;

    const nhay = (id: string) => (e: MouseEvent) => {
        e.preventDefault();
        document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    return (
        <Sheet className="px-5 py-6 md:px-8">
            <div className="mx-auto max-w-[760px]">
                <Link to="/ngu-phap" className="-ml-2 inline-flex min-h-11 items-center gap-1.5 rounded-md px-2 text-label font-semibold text-ink-2 hover:text-ink">
                    <ArrowLeft size={18} aria-hidden="true" />
                    {t('nguPhap.veDanhSach')}
                </Link>
                {!bai || !ten ? (
                    loi ? (
                        loi.trangThai === 404 ? (
                            <p className="mt-6 font-semibold">{t('nguPhap.khongCoBai')}</p>
                        ) : (
                            <LoiTai loi={loi} taiLai={taiLai} />
                        )
                    ) : (
                        <DangTai />
                    )
                ) : (
                    <>
                        <h1 className="mt-2 text-headline font-bold text-balance">{ten.viet ? <span lang="de">{ten.duc}</span> : bai.title}</h1>
                        <p className="mt-1 text-ink-2">{[ten.viet, bai.level, bai.chapter ? tenChuong(bai.chapter) : null].filter(Boolean).join(' · ')}</p>

                        {bai.sets.length > 0 && (
                            <section aria-labelledby="luyen-bai" className="mt-5 border-y border-line py-3">
                                <h2 id="luyen-bai" className="font-bold">
                                    {t('nguPhap.luyenBaiNay')}
                                </h2>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {bai.sets.map((s) => (
                                        <NutLink key={s.slug} to={`/bai-tap/lam${truyVan({ bo: s.slug, n: 20, ve: `/ngu-phap/${bai.slug}` })}`} chinh>
                                            {t('nguPhap.boCau', { ten: s.title, n: so(s.count) })}
                                        </NutLink>
                                    ))}
                                </div>
                            </section>
                        )}

                        {phan.length > 2 && (
                            <nav aria-label={t('nguPhap.mucLuc')} className="mt-5">
                                <ul className="flex flex-wrap gap-1.5">
                                    {phan.map((p) => (
                                        <li key={p.id}>
                                            <a
                                                href={`#${p.id}`}
                                                onClick={nhay(p.id)}
                                                className="flex min-h-11 items-center rounded-md border border-line px-3 text-label font-semibold text-ink-2 transition-colors duration-200 hover:border-line-strong hover:text-ink"
                                            >
                                                {chuMucLuc(p.chu)}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </nav>
                        )}

                        <article className="mt-4 text-pretty">
                            {coAi && phan.length === 0 && <HoiNguPhap slug={bai.slug} phan={0} ten="" />}
                            <KhoiNguPhap
                                khoi={bai.blocks}
                                langs={settings.langs}
                                sauTieuDe={coAi ? (so, ten) => <HoiNguPhap slug={bai.slug} phan={so} ten={ten} /> : undefined}
                            />
                        </article>

                        <nav aria-label={t('nguPhap.chuyenBai')} className="mt-10 flex flex-wrap justify-between gap-3 border-t border-line pt-4">
                            {bai.prev ? (
                                <NutLink to={`/ngu-phap/${bai.prev.slug}`}>
                                    <ArrowLeft size={18} aria-hidden="true" />
                                    {t('nguPhap.baiTruoc')}
                                </NutLink>
                            ) : (
                                <span />
                            )}
                            {bai.next && (
                                <NutLink to={`/ngu-phap/${bai.next.slug}`}>
                                    {t('nguPhap.baiSau')}
                                    <ArrowRight size={18} aria-hidden="true" />
                                </NutLink>
                            )}
                        </nav>
                    </>
                )}
            </div>
        </Sheet>
    );
}
