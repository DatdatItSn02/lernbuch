// Bài tập theo Lernbuch (viewExercises, viewPlural): ô luyện nhanh, bài theo cấp (cấp › chương › bài ngữ pháp › bộ),
// tab Ôn tập (Từ vựng, Ngữ pháp theo chương, chọn cách chấm), trang Số nhiều.
import { ArrowRight, Coins, PencilLine, RefreshCcw, Shuffle, Table2, Tally5, type LucideIcon } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import { Link, useSearchParams } from 'react-router';
import { Choice } from '../../components/Choice';
import { Sheet } from '../../components/Shell';
import { DangTai, LoiTai } from '../../components/TaiNoiDung';
import { XU } from '../../lib/hoc';
import { XU_CAU, XU_DONG_TU, docCachCham, ghiCachCham, type BoBai, type CachCham, type DanhSachBaiTap } from '../../lib/baiTap';
import { tachTenBai } from '../../lib/nguPhap';
import { docCap, tenCap, truyVan } from '../../lib/noiDung';
import { useApi } from '../../lib/useApi';
import { useTitle } from '../../lib/useTitle';
import { so, t } from '../../i18n';
import { LOP_CHIP, LocCap, NutLink, ThanhNho } from './chung';

type BaiCua = DanhSachBaiTap['lessons'][number];

const tong = (ds: BoBai[]) => ({ n: ds.reduce((a, s) => a + s.count, 0), done: ds.reduce((a, s) => a + s.done, 0) });

/** Đường làm một bộ: bộ có nhóm (Ôn tập, bài theo chương) làm cả bộ theo thứ tự, chấm theo cách đã chọn; còn lại 20 câu ngẫu nhiên. */
function duongBo(s: BoBai, cham: CachCham, ve: string): string {
    return `/bai-tap/lam${truyVan(s.group ? { bo: s.slug, n: s.count, 'thu-tu': 1, cham: cham === 'sheet' ? 'phieu' : null, ve } : { bo: s.slug, n: 20, ve })}`;
}

function DongBo({ s, cham, ve }: { s: BoBai; cham: CachCham; ve: string }) {
    return (
        <li>
            <Link to={duongBo(s, cham, ve)} className="flex min-h-11 items-center gap-3 py-1.5 hover:underline hover:underline-offset-2">
                <span className="min-w-0 flex-1 break-words">{s.title}</span>
                <ThanhNho done={s.done} n={s.count} nhan={t('baiTap.tienDoBo', { done: so(s.done), n: so(s.count) })} />
                <span className="w-14 shrink-0 text-right text-label text-ink-2 tabular-nums">{`${so(s.done)}/${so(s.count)}`}</span>
                {s.weak > 0 && <span className="shrink-0 rounded-sm bg-danger-tint px-1.5 text-label font-semibold text-danger">{t('baiTap.sai', { n: so(s.weak) })}</span>}
            </Link>
        </li>
    );
}

function MucMo({ tieuDe, phu, ds, mo = false, children }: { tieuDe: ReactNode; phu: string; ds: BoBai[]; mo?: boolean; children: ReactNode }) {
    const { n, done } = tong(ds);
    return (
        <details open={mo} className="group border-b border-line">
            <summary className="flex min-h-12 cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 py-2 [&::-webkit-details-marker]:hidden">
                <ArrowRight size={16} aria-hidden="true" className="shrink-0 transition-transform duration-200 group-open:rotate-90" />
                <span className="min-w-0 flex-1 font-semibold">{tieuDe}</span>
                <ThanhNho done={done} n={n} nhan={t('baiTap.tienDoBo', { done: so(done), n: so(n) })} />
                <span className="text-label text-ink-2 tabular-nums">{phu}</span>
            </summary>
            <div className="pb-3 pl-6">{children}</div>
        </details>
    );
}

function O({ to, Icon, ten, moTa, xu }: { to: string; Icon: LucideIcon; ten: string; moTa: string; xu?: number }) {
    return (
        <Link to={to} className="flex min-h-24 flex-col gap-1 rounded-md border border-line p-3 transition-colors duration-200 hover:border-line-strong hover:bg-sunken">
            <Icon size={22} aria-hidden="true" className="text-ink-2" />
            <span className="font-bold">{ten}</span>
            <span className="text-label text-ink-2">{moTa}</span>
            {xu !== undefined && (
                <span className="mt-auto flex items-center gap-1 text-label text-ink-2">
                    <Coins size={14} aria-hidden="true" />
                    {t('baiTap.xuMoiCau', { n: xu })}
                </span>
            )}
        </Link>
    );
}

/** Số chương để xếp: "Ôn tập 2 · Bài 1 đến Bài 12" nằm sau Bài 12 (như Lernbuch). */
function soChuong(nhom: string): number {
    const on = nhom.match(/^Ôn tập \d+ · Bài 1 đến Bài (\d+)/);
    return on ? Number(on[1]) + 0.5 : Number(nhom.match(/\d+/)?.[0] ?? 0);
}

function PhanOnTap({ tieuDe, moTa, ds, cham, laTuVung }: { tieuDe: string; moTa: string; ds: BoBai[]; cham: CachCham; laTuVung: boolean }) {
    const chuong = useMemo(() => {
        const m = new Map<string, BoBai[]>();
        for (const s of ds) m.set(s.group ?? '', [...(m.get(s.group ?? '') ?? []), s]);
        return [...m].sort((a, b) => soChuong(a[0]) - soChuong(b[0]));
    }, [ds]);
    if (ds.length === 0) return null;
    const { n } = tong(ds);
    const ve = '/bai-tap?tab=on-tap';
    return (
        <section aria-label={tieuDe} className="mt-6">
            <h2 className="flex flex-wrap items-baseline gap-x-3 border-b-2 border-ink pb-1">
                <span className="text-title font-extrabold">{tieuDe}</span>
                <span className="text-label text-ink-2">{moTa}</span>
                <span className="ml-auto text-label text-ink-2 tabular-nums">{t(laTuVung ? 'baiTap.soBaiCau' : 'baiTap.soChuongCau', { n: so(chuong.length), cau: so(n) })}</span>
            </h2>
            {chuong.map(([ten, cacBo]) => {
                const c = tong(cacBo);
                const cap = cacBo[0].level;
                return (
                    <MucMo
                        key={ten}
                        tieuDe={
                            <>
                                {ten}
                                {cap && <span className="ml-2 rounded-sm border border-line px-1 text-label font-normal">{cap}</span>}
                            </>
                        }
                        phu={t('baiTap.soBaiCau', { n: so(cacBo.length), cau: so(c.n) })}
                        ds={cacBo}
                    >
                        <ul>
                            {cacBo.map((s) => (
                                <DongBo key={s.slug} s={s} cham={cham} ve={ve} />
                            ))}
                        </ul>
                        <NutLink to={`/bai-tap/lam${truyVan({ bo: cacBo.map((s) => s.slug).join(','), n: Math.min(25, c.n), ve })}`} className="mt-2">
                            <Shuffle size={18} aria-hidden="true" />
                            {t('baiTap.tronChuong')}
                        </NutLink>
                    </MucMo>
                );
            })}
        </section>
    );
}

/** Tab theo cấp: cấp › chương (theo bài ngữ pháp của bộ) › bài › bộ; bộ không gắn bài hay bài không thuộc chương vào "Tổng hợp". */
function TheoCap({ d, capChon, cham }: { d: DanhSachBaiTap; capChon: number[]; cham: CachCham }) {
    const nhom = useMemo(() => {
        const baiCua = new Map(d.lessons.map((b) => [b.slug, b]));
        const chuong = new Map(d.chapters.map((c) => [c.code, c]));
        const tenChon = capChon.map(tenCap);
        const theoCap = new Map<string, Map<string, Map<string, { bai: BaiCua | null; ds: BoBai[] }>>>();
        for (const s of d.sets) {
            if (s.slug.startsWith('dx-') && !s.lesson) continue;
            const bai = s.lesson ? (baiCua.get(s.lesson) ?? null) : null;
            const cap = bai?.level ?? s.level;
            if (!cap || (tenChon.length > 0 && !tenChon.some((l) => l === s.level || l === bai?.level))) continue;
            const ma = bai?.chapter && chuong.has(bai.chapter) ? bai.chapter : '';
            const cacChuong = theoCap.get(cap) ?? new Map<string, Map<string, { bai: BaiCua | null; ds: BoBai[] }>>();
            const cacBai = cacChuong.get(ma) ?? new Map<string, { bai: BaiCua | null; ds: BoBai[] }>();
            const x = cacBai.get(bai?.slug ?? '-') ?? { bai, ds: [] };
            x.ds.push(s);
            cacBai.set(bai?.slug ?? '-', x);
            cacChuong.set(ma, cacBai);
            theoCap.set(cap, cacChuong);
        }
        return [...theoCap].sort((a, b) => a[0].localeCompare(b[0])).map(([cap, cacChuong]) => ({
            cap,
            chuong: [...cacChuong]
                .map(([ma, cacBai]) => ({ c: ma ? chuong.get(ma) : undefined, bai: [...cacBai.values()].sort((a, b) => (a.bai?.slug ?? '').localeCompare(b.bai?.slug ?? '')) }))
                .sort((a, b) => (a.c?.no ?? 99) - (b.c?.no ?? 99)),
        }));
    }, [d, capChon]);

    if (nhom.length === 0) return <p className="mt-6 text-ink-2">{t('baiTap.khongCoBo')}</p>;
    return (
        <>
            {nhom.map(({ cap, chuong }) => {
                const ds = chuong.flatMap((c) => c.bai.flatMap((b) => b.ds));
                return (
                    <section key={cap} aria-label={cap} className="mt-6">
                        <h2 className="flex items-baseline gap-3 border-b-2 border-ink pb-1">
                            <span className="text-title font-extrabold">{cap}</span>
                            <span className="text-label text-ink-2 tabular-nums">{t('baiTap.soBaiCau', { n: so(ds.length), cau: so(tong(ds).n) })}</span>
                        </h2>
                        {chuong.map(({ c, bai }) => {
                            const cua = bai.flatMap((b) => b.ds);
                            return (
                                <MucMo
                                    key={c?.code ?? 'tong-hop'}
                                    tieuDe={c ? (c.no === 9 ? c.name : t('nguPhap.chuong', { so: c.no, ten: c.name })) : t('baiTap.tongHop')}
                                    phu={t('baiTap.soBaiCau', { n: so(cua.length), cau: so(tong(cua).n) })}
                                    ds={cua}
                                >
                                    {bai.map(({ bai: b, ds: cacBo }) => {
                                        const ten = b ? tachTenBai(b.title) : null;
                                        return (
                                            <div key={b?.slug ?? '-'} className="mt-2 first:mt-0">
                                                {b && ten && (
                                                    <Link to={`/ngu-phap/${b.slug}`} className="inline-flex min-h-11 flex-wrap items-baseline gap-x-2 font-bold hover:underline hover:underline-offset-2">
                                                        {ten.viet ?? b.title}
                                                        {ten.viet && (
                                                            <span lang="de" className="text-label font-normal text-ink-2">
                                                                {ten.duc}
                                                            </span>
                                                        )}
                                                    </Link>
                                                )}
                                                <ul>
                                                    {cacBo.map((s) => (
                                                        <DongBo key={s.slug} s={s} cham={cham} ve="/bai-tap" />
                                                    ))}
                                                </ul>
                                            </div>
                                        );
                                    })}
                                </MucMo>
                            );
                        })}
                    </section>
                );
            })}
        </>
    );
}

export function BaiTap() {
    useTitle(t('khung.baiTap'));
    const [params, setParams] = useSearchParams();
    const onTap = params.get('tab') === 'on-tap';
    const cap = docCap(params.get('cap')).filter((c) => c >= 1 && c <= 4);
    const [cham, setCham] = useState<CachCham>(docCachCham);
    const { duLieu, loi, taiLai } = useApi<{ data: DanhSachBaiTap }>('/bai-tap');
    const d = duLieu?.data;
    const capChu = cap.join(',');

    const doi = (moi: { cap?: number[]; onTap?: boolean }) =>
        setParams(new URLSearchParams(truyVan({ tab: (moi.onTap ?? onTap) ? 'on-tap' : null, cap: (moi.cap ?? cap).join(',') }).slice(1)), { replace: true });
    const chonCham = (c: CachCham) => {
        setCham(c);
        ghiCachCham(c);
    };

    const tk = d ? tong(d.sets) : null;
    const sai = d ? d.sets.reduce((a, s) => a + s.weak, 0) : 0;

    return (
        <Sheet className="px-5 py-6 md:px-8">
            <div className="flex flex-wrap items-baseline justify-between gap-3">
                <h1 className="text-headline font-bold">{t('khung.baiTap')}</h1>
                {tk && tk.n > 0 && <p className="text-ink-2 tabular-nums">{t('baiTap.daThuoc', { done: so(tk.done), tong: so(tk.n) })}</p>}
            </div>

            {!onTap && (
                <nav aria-label={t('baiTap.cachLuyen')} className="mt-5">
                    <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                        <li>
                            <O to={`/bai-tap/lam${truyVan({ n: 15, cap: capChu })}`} Icon={Shuffle} ten={t('baiTap.luyenNhanh')} moTa={t('baiTap.luyenNhanhMoTa')} xu={XU_CAU} />
                        </li>
                        <li>
                            <O to={`/bai-tap/dien-cau${truyVan({ cap: capChu })}`} Icon={PencilLine} ten={t('baiTap.dienCau')} moTa={t('baiTap.dienCauMoTa')} xu={XU.cloze} />
                        </li>
                        <li>
                            <O to={`/bai-tap/chia-dong-tu${truyVan({ cap: capChu })}`} Icon={Table2} ten={t('baiTap.chiaDongTu')} moTa={t('baiTap.chiaDongTuMoTa')} xu={XU_DONG_TU} />
                        </li>
                        <li>
                            <O to="/so-nhieu" Icon={Tally5} ten={t('baiTap.soNhieu')} moTa={t('baiTap.soNhieuMoTa')} />
                        </li>
                        {sai > 0 && (
                            <li>
                                <O to={`/bai-tap/lam${truyVan({ on: 1, n: 15 })}`} Icon={RefreshCcw} ten={t('baiTap.onCauSai')} moTa={t('baiTap.onCauSaiMoTa', { n: so(sai) })} xu={XU_CAU} />
                            </li>
                        )}
                    </ul>
                </nav>
            )}

            <div className="mt-5 flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center md:gap-x-6">
                <LocCap
                    cap={onTap ? [] : cap}
                    onDoi={(c) => doi({ cap: c, onTap: false })}
                    them={
                        <button type="button" aria-pressed={onTap} onClick={() => doi({ onTap: !onTap })} className={LOP_CHIP(onTap)}>
                            {t('baiTap.onTap')}
                        </button>
                    }
                />
                {onTap && (
                    <Choice
                        legend={t('baiTap.cachCham')}
                        hideLegend
                        name="cach-cham"
                        value={cham}
                        options={[
                            { value: 'instant', label: t('baiTap.chamNgay') },
                            { value: 'sheet', label: t('baiTap.chamPhieu') },
                        ]}
                        onChange={chonCham}
                    />
                )}
                <NutLink to="/dong-tu" className="self-start md:ml-auto md:self-auto">
                    {t('nguPhap.bangDongTu')}
                </NutLink>
            </div>

            {!d ? (
                loi ? <LoiTai loi={loi} taiLai={taiLai} /> : <DangTai />
            ) : d.sets.length === 0 ? (
                <p className="mt-6 text-ink-2">{t('baiTap.chuaCo')}</p>
            ) : onTap ? (
                <>
                    <p className="mt-4 text-ink-2">{t('baiTap.onTapGhiChu')}</p>
                    <PhanOnTap tieuDe={t('baiTap.phanTuVung')} moTa={t('baiTap.phanTuVungMoTa')} ds={d.sets.filter((s) => s.slug.startsWith('dx-v'))} cham={cham} laTuVung />
                    <PhanOnTap tieuDe={t('baiTap.phanNguPhap')} moTa={t('baiTap.phanNguPhapMoTa')} ds={d.sets.filter((s) => s.slug.startsWith('dx-g'))} cham={cham} laTuVung={false} />
                </>
            ) : (
                <TheoCap d={d} capChon={cap} cham={cham} />
            )}
        </Sheet>
    );
}

/** Số nhiều (Lernbuch viewPlural): mỗi quy tắc một bộ, kèm tiến độ; ôn trộn tất cả. */
export function SoNhieu() {
    useTitle(t('baiTap.soNhieu'));
    const { duLieu, loi, taiLai } = useApi<{ data: DanhSachBaiTap }>('/bai-tap');
    const ds = duLieu ? duLieu.data.sets.filter((s) => s.section === 'plural') : null;
    const tk = ds ? tong(ds) : null;
    const lyThuyet = ds?.find((s) => s.lesson)?.lesson ?? null;

    return (
        <Sheet className="px-5 py-6 md:px-8">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-headline font-bold">{t('baiTap.soNhieu')}</h1>
                {ds && ds.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {lyThuyet && <NutLink to={`/ngu-phap/${lyThuyet}`}>{t('soNhieu.baiLyThuyet')}</NutLink>}
                        <NutLink to={`/bai-tap/lam${truyVan({ bo: ds.map((s) => s.slug).join(','), n: 25, ve: '/so-nhieu' })}`} chinh>
                            {t('soNhieu.onTron')}
                        </NutLink>
                    </div>
                )}
            </div>
            {!ds || !tk ? (
                loi ? <LoiTai loi={loi} taiLai={taiLai} /> : <DangTai />
            ) : ds.length === 0 ? (
                <p className="mt-6 text-ink-2">{t('soNhieu.chuaCo')}</p>
            ) : (
                <>
                    <p className="mt-1 text-ink-2 tabular-nums">{t('soNhieu.moTa', { done: so(tk.done), tong: so(tk.n) })}</p>
                    <ul className="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {ds.map((s) => (
                            <li key={s.slug}>
                                <Link
                                    to={`/bai-tap/lam${truyVan({ bo: s.slug, n: 20, ve: '/so-nhieu' })}`}
                                    className="flex h-full flex-col gap-1 rounded-md border border-line p-3 transition-colors duration-200 hover:border-line-strong hover:bg-sunken"
                                >
                                    <span className="font-bold">{s.title.replace(/^Số nhiều:\s*/, '')}</span>
                                    {s.note && <span className="text-label text-ink-2">{s.note.replace(/^Việc cần làm: viết dạng số nhiều của danh từ\.\s*/, '')}</span>}
                                    <span className="mt-auto flex items-center gap-2 pt-1 text-label text-ink-2 tabular-nums">
                                        <ThanhNho done={s.done} n={s.count} nhan={t('baiTap.tienDoBo', { done: so(s.done), n: so(s.count) })} />
                                        {t('soNhieu.soTu', { done: so(s.done), n: so(s.count) })}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </Sheet>
    );
}
