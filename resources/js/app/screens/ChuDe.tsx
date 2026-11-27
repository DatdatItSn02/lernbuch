import { ChevronLeft, ChevronRight, Search } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router';
import { coDang, DongDang, TuDuc } from '../components/Article';
import { Choice } from '../components/Choice';
import { Kbd } from '../components/Kbd';
import { LevelBar } from '../components/LevelBar';
import { Sheet } from '../components/Shell';
import { DangTai, LoiTai } from '../components/TaiNoiDung';
import { demThe, docCap, tenCap, tenLoaiTu, trangCuaBo, truyVan, type TheTomTat, type TrangChuDe } from '../lib/noiDung';
import type { TienDoChuDe } from '../lib/hoc';
import { ChonCheDo } from '../components/ChonCheDo';
import { useApi } from '../lib/useApi';
import { useTitle } from '../lib/useTitle';
import { useStore } from '../state/store';
import { KhongThay } from './KhongThay';
import { so, t } from '../i18n';

type Cover = 'none' | 'de' | 'vi';

/** Gõ trong ô tìm: chờ ngừng gõ chừng này rồi mới hỏi máy chủ. */
export const CHO_TIM_MS = 300;

const isTyping = (el: EventTarget | null) => el instanceof HTMLElement && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));

export function ChuDe() {
    const { ma = '' } = useParams();
    return <TopicPage key={ma} slug={ma} />;
}

/**
 * Trang chủ đề nhỏ: tên, vị trí trong cây, lọc theo cấp, loại từ và chữ (máy chủ lọc), 20 thẻ mỗi trang.
 * Bộ lọc và số trang nằm trên đường dẫn (?cap=&loai=&tim=&trang=) nên tải lại hay quay lại vẫn giữ.
 */
function TopicPage({ slug }: { slug: string }) {
    const [params, setParams] = useSearchParams();
    const cap = docCap(params.get('cap'));
    const loai = params.get('loai') ?? '';
    const timTrenDuong = params.get('tim') ?? '';
    const trangHoi = Number(params.get('trang') ?? '1');
    const trangXin = Number.isInteger(trangHoi) && trangHoi >= 1 ? trangHoi : 1;

    const [query, setQuery] = useState(timTrenDuong);
    const [cover, setCover] = useState<Cover>('none');
    const [shown, setShown] = useState<Record<number, boolean>>({});
    const searchRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLHeadingElement>(null);
    const lastPage = useRef<number | null>(null);
    const uid = useId();

    const { duLieu, dangTai, loi, taiLai } = useApi<TrangChuDe>(
        `/chu-de/${encodeURIComponent(slug)}${truyVan({ cap: cap.join(','), loai_tu: loai, tim: timTrenDuong.trim(), trang: trangXin > 1 ? trangXin : null })}`,
    );
    const dl = duLieu?.data;
    const meta = duLieu?.meta;
    useTitle(dl?.topic.name ?? t('khung.chuDe'));

    // Đặt lại bộ lọc trên đường dẫn; đổi lọc thì về trang 1
    const datLoc = (moi: { cap?: number[]; loai?: string; tim?: string }) =>
        setParams(
            new URLSearchParams(truyVan({ cap: (moi.cap ?? cap).join(','), loai: moi.loai ?? loai, tim: (moi.tim ?? timTrenDuong).trim() }).slice(1)),
            { replace: true },
        );

    // Ngừng gõ một chút thì mới đổi đường dẫn (và hỏi máy chủ)
    useEffect(() => {
        if (query.trim() === timTrenDuong.trim()) return;
        const hen = window.setTimeout(() => datLoc({ tim: query }), CHO_TIM_MS);
        return () => window.clearTimeout(hen);
        // datLoc đọc params mới nhất mỗi lần render; chỉ cần chạy lại khi chữ gõ đổi
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey || isTyping(e.target)) return;
            e.preventDefault();
            searchRef.current?.focus();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const page = meta?.page ?? 1;
    // Sang trang khác thì đưa đầu danh sách vào tầm nhìn và đặt tiêu điểm ở đó
    useEffect(() => {
        if (lastPage.current !== null && lastPage.current !== page) {
            listRef.current?.scrollIntoView?.({ block: 'start' });
            listRef.current?.focus();
        }
        lastPage.current = page;
    }, [page]);

    if (loi?.trangThai === 404) {
        return <KhongThay title={t('chuDe.khongCo')} message={t('chuDe.khongCoMoTa')} back={{ to: '/chu-de', label: t('chung.veDanhSachChuDe') }} />;
    }
    if (!dl || !meta) {
        return <Sheet className="px-5 md:px-8">{loi ? <LoiTai loi={loi} taiLai={taiLai} /> : <DangTai />}</Sheet>;
    }

    const bo = dl.topic.collection.code;
    const danhSach = trangCuaBo(bo);
    const rows = dl.cards;
    const filtered = cap.length > 0 || loai !== '' || timTrenDuong.trim() !== '';
    const hiddenCount = cover === 'none' ? 0 : rows.filter((w) => !shown[w.id]).length;
    const clearFilters = () => {
        setQuery('');
        setParams({}, { replace: true });
    };
    const batCap = (c: number) => datLoc({ cap: cap.includes(c) ? cap.filter((x) => x !== c) : [...cap, c].sort((a, b) => (a || 9) - (b || 9)) });
    const pageLink = (n: number) => ({ search: truyVan({ cap: cap.join(','), loai, tim: timTrenDuong.trim(), trang: n > 1 ? n : null }) });

    return (
        <Sheet>
            <header className="px-5 pt-3 pb-4 md:px-8 md:pt-6 md:pb-5">
                <nav aria-label={t('chung.viTri')}>
                    <ol className="flex flex-wrap items-center gap-x-1 text-label text-ink-2">
                        <li>
                            <Link to={danhSach.to} className="inline-flex min-h-11 items-center underline-offset-2 hover:text-ink hover:underline">
                                {t(danhSach.khoa)}
                            </Link>
                        </li>
                        {dl.group && (
                            <li className="flex items-center gap-1">
                                <ChevronRight size={14} aria-hidden="true" />
                                {dl.group.name}
                            </li>
                        )}
                        {dl.parent && (
                            <li className="flex items-center gap-1">
                                <ChevronRight size={14} aria-hidden="true" />
                                {dl.parent.name}
                            </li>
                        )}
                    </ol>
                </nav>

                <h1 className="mt-1 text-headline font-bold break-words">{dl.topic.name}</h1>
                {dl.topic.name_en && dl.topic.name_en !== dl.topic.name && (
                    <p lang="en" className="text-ink-2">
                        {dl.topic.name_en}
                    </p>
                )}
                <p className="mt-2 text-label font-semibold tabular-nums">
                    {dl.topic.collection.name} · {demThe(bo, dl.topic.card_count)}
                </p>
                <HocChuDe slug={dl.topic.slug} tienDo={dl.progress} />

                {dl.levels.length > 1 && (
                    <div role="group" aria-label={t('chuDe.locCapThe')} className="mt-4 flex flex-wrap gap-1.5">
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
            </header>

            <div className="flex flex-col gap-3 border-y border-line px-5 py-3 md:flex-row md:flex-wrap md:items-end md:gap-x-8 md:px-8 md:py-4">
                <div className="flex flex-wrap items-end gap-2">
                    <Choice
                        legend={t('chuDe.cheCot')}
                        name={`${uid}-che`}
                        value={cover}
                        onChange={(v) => {
                            setCover(v);
                            setShown({});
                        }}
                        options={[
                            { value: 'none', label: t('chuDe.khongChe') },
                            { value: 'de', label: t('ngonNgu.de') },
                            { value: 'vi', label: t('ngonNgu.vi') },
                        ]}
                    />
                    {cover !== 'none' && (
                        <button
                            type="button"
                            onClick={() => setShown(hiddenCount > 0 ? Object.fromEntries(rows.map((w) => [w.id, true])) : {})}
                            className="min-h-11 rounded-md px-3 text-label font-semibold text-tim underline-offset-2 hover:underline"
                        >
                            {hiddenCount > 0 ? t('chuDe.hienHet') : t('chuDe.cheLai')}
                        </button>
                    )}
                </div>

                <div className="flex min-w-0 flex-1 gap-2 lg:min-w-80">
                    {dl.pos.length > 1 && (
                        <>
                            <label className="sr-only" htmlFor={`${uid}-loai`}>
                                {t('chuDe.loaiTu')}
                            </label>
                            <select
                                id={`${uid}-loai`}
                                value={loai}
                                onChange={(e) => datLoc({ loai: e.target.value })}
                                className="min-h-11 max-w-44 shrink-0 rounded-md border border-line-strong bg-paper px-3 text-label font-semibold"
                            >
                                <option value="">{t('chuDe.moiLoaiTu')}</option>
                                {dl.pos.map((p) => (
                                    <option key={p.pos} value={p.pos}>
                                        {`${tenLoaiTu(p.pos)} (${so(p.count)})`}
                                    </option>
                                ))}
                            </select>
                        </>
                    )}
                    <div className="relative min-w-0 flex-1">
                        <Search size={18} aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-2" />
                        <input
                            ref={searchRef}
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Escape' && query) {
                                    e.preventDefault();
                                    setQuery('');
                                }
                            }}
                            aria-label={t('chuDe.timTrong')}
                            aria-keyshortcuts="/"
                            placeholder={t('chuDe.timGoiY')}
                            className="min-h-11 w-full rounded-md border border-line-strong bg-paper pr-10 pl-9 placeholder:text-ink-2"
                        />
                        <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-ink-2">
                            <Kbd>/</Kbd>
                        </span>
                    </div>
                </div>
            </div>

            <section aria-labelledby={`${uid}-ds`} aria-busy={dangTai}>
                <div className="flex min-h-11 items-center gap-3 px-5 pt-2 md:px-8">
                    <h2 id={`${uid}-ds`} ref={listRef} tabIndex={-1} aria-live="polite" className="text-label font-semibold text-ink-2 tabular-nums">
                        {filtered ? t('chuDe.khopLoc', { khop: so(meta.total), tong: so(dl.topic.card_count) }) : demThe(bo, dl.topic.card_count)}
                        {meta.pages > 1 && ` · ${t('chuDe.trangGiuaCau', { trang: page, tong: meta.pages })}`}
                    </h2>
                    {filtered && rows.length > 0 && (
                        <button type="button" onClick={clearFilters} className="min-h-11 px-2 text-label font-semibold text-tim underline-offset-2 hover:underline">
                            {t('chuDe.boLoc')}
                        </button>
                    )}
                </div>

                {rows.length === 0 ? (
                    <div className="px-5 py-8 md:px-8">
                        <p className="font-semibold">{t('chuDe.khongKhop')}</p>
                        <button type="button" onClick={clearFilters} className="mt-3 min-h-11 rounded-md border border-line-strong px-4 font-semibold hover:bg-sunken">
                            {t('chuDe.boLoc')}
                        </button>
                    </div>
                ) : (
                    <table className="mt-2 w-full table-fixed border-collapse text-left">
                        <caption className="sr-only">{t('chuDe.bangTu')}</caption>
                        <thead>
                            <tr className="border-b border-line text-label text-ink-2">
                                <th scope="col" className="py-2 pr-3 pl-5 font-semibold md:pl-8">
                                    {t('ngonNgu.de')}
                                </th>
                                <th scope="col" className="py-2 pr-5 font-semibold md:pr-8">
                                    {t('ngonNgu.vi')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {rows.map((w) => (
                                <Dong key={w.id} the={w} cover={cover} open={!!shown[w.id]} reveal={() => setShown((s) => ({ ...s, [w.id]: true }))} />
                            ))}
                        </tbody>
                    </table>
                )}
            </section>

            {meta.pages > 1 && (
                <nav aria-label={t('chuDe.phanTrang')} className="flex items-center justify-between gap-3 border-t border-line px-5 py-3 md:px-8">
                    {page > 1 ? (
                        <Link to={pageLink(page - 1)} className="inline-flex min-h-11 items-center gap-1 font-semibold hover:underline">
                            <ChevronLeft size={18} aria-hidden="true" />
                            {t('chuDe.trangTruoc')}
                        </Link>
                    ) : (
                        <span />
                    )}
                    <span className="text-label text-ink-2 tabular-nums">{t('chuDe.trang', { trang: page, tong: meta.pages })}</span>
                    {page < meta.pages ? (
                        <Link to={pageLink(page + 1)} className="inline-flex min-h-11 items-center gap-1 font-semibold hover:underline">
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

/** Một dòng của bảng: tiếng Đức (link sang thẻ từ, dạng từ, cấp) và nghĩa tiếng Việt; ô bị che thì chạm để xem. */
function Dong({ the, cover, open, reveal }: { the: TheTomTat; cover: Cover; open: boolean; reveal: () => void }) {
    const nghia = the.meanings.vi ?? '';
    return (
        <tr className="align-top">
            <td className="py-3 pr-3 pl-5 md:pl-8">
                {cover === 'de' && !open ? (
                    <Covered label={t('chuDe.xemTiengDuc', { nghia })} onClick={reveal} />
                ) : (
                    <>
                        <Link to={`/tu/${the.id}`} className="font-semibold break-words underline-offset-2 hover:underline">
                            <TuDuc the={the} />
                        </Link>
                        <span className="block text-label text-ink-2">
                            <DongDang the={the} />
                            {coDang(the) && the.level ? ' · ' : ''}
                            {the.level}
                        </span>
                    </>
                )}
            </td>
            <td className="py-3 pr-5 md:pr-8">
                {cover === 'vi' && !open ? <Covered label={t('chuDe.xemTiengViet', { tu: the.front })} onClick={reveal} /> : <span className="break-words">{nghia}</span>}
            </td>
        </tr>
    );
}

/** Ô bị che: chạm để xem; nhãn đọc ra từ ở cột bên kia để biết đang xem dòng nào. */
function Covered({ label, onClick }: { label: string; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            className="flex min-h-11 w-full max-w-48 items-center rounded-sm border border-dashed border-line-strong bg-sunken px-3 text-label text-ink-2 transition-colors duration-200 hover:border-ink-3"
        >
            {t('chuDe.xem')}
        </button>
    );
}

/** Ô học của trang chủ đề: tiến độ của người học trong chủ đề và nút học (thẻ đến hạn và từ mới đều trong chủ đề này). */
function HocChuDe({ slug, tienDo }: { slug: string; tienDo: TienDoChuDe }) {
    const { settings } = useStore();
    const moi = settings.newPerSession === 'all' ? tienDo.new : Math.min(settings.newPerSession, tienDo.new);
    const on = Math.min(tienDo.due, ON_MOI_PHIEN);
    return (
        <>
            <div className="mt-4 flex max-w-2xl flex-col gap-3 rounded-md border border-line px-4 py-3 sm:flex-row sm:items-center sm:gap-5">
                <div className="min-w-0 flex-1">
                    <p className="text-label font-semibold">{t('chuDe.tienDo')}</p>
                    <p className="text-label text-ink-2 tabular-nums">{t('chuDe.demTienDo', { denHan: tienDo.due, moi: tienDo.new, qua: tienDo.levels[5] ?? 0 })}</p>
                    <div className="mt-2">
                        <LevelBar counts={tienDo.levels} compact />
                    </div>
                </div>
                {on + moi > 0 ? (
                    <Link
                        to={`/hoc?chu-de=${slug}`}
                        className="flex min-h-12 shrink-0 flex-col items-center justify-center rounded-md bg-tim px-5 py-1.5 text-on-tim transition-colors duration-200 hover:bg-tim-strong"
                    >
                        <span className="font-bold">{moi > 0 ? t('chuDe.hocChuDe') : t('chuDe.onChuDe')}</span>
                        <span className="text-label tabular-nums">{t('chuDe.onVaMoi', { on, moi })}</span>
                    </Link>
                ) : (
                    <p className="text-label font-semibold">{t('chuDe.daHocHet')}</p>
                )}
            </div>
            {on + moi > 0 && (
                <div className="mt-3 max-w-2xl">
                    <ChonCheDo chuDe={slug} nhan={t('cheDo.chon')} />
                </div>
            )}
        </>
    );
}

/** Phiên học lấy tối đa chừng này thẻ đến hạn (máy chủ: PhienHoc::ON_TOI_DA). */
const ON_MOI_PHIEN = 50;
