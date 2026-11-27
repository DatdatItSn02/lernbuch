import { ChevronLeft, ChevronRight, Star } from 'lucide-react';
import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';
import { Link, useParams } from 'react-router';
import { Article, DongDang, TuDuc } from '../components/Article';
import { AudioButton } from '../components/AudioButton';
import { CauBamDuoc } from '../components/CauBamDuoc';
import { Sheet } from '../components/Shell';
import { DangTai, LoiTai } from '../components/TaiNoiDung';
import { api } from '../lib/api';
import { tenLoaiTu, type MaoTu, type SoTay, type TheChiTiet } from '../lib/noiDung';
import { quenDaTai, useApi } from '../lib/useApi';
import { useStore, type Lang } from '../state/store';
import { KhongThay } from './KhongThay';
import { useTitle } from '../lib/useTitle';
import { t, type Khoa } from '../i18n';

const TABS = [
    { id: 'nghia', get label() { return t('theTu.tabNghia'); } },
    { id: 'ngu-phap', get label() { return t('khung.nguPhap'); } },
    { id: 'ghi-chu', get label() { return t('theTu.tabGhiChu'); } },
] as const;
type TabId = (typeof TABS)[number]['id'];

// short: mã ngắn trên nút (giữ nguyên ở mọi ngôn ngữ giao diện)
const LANGS: { id: Lang; short: string; readonly name: string }[] = [
    { id: 'vi', short: 'VI', get name() { return t('theTu.tenVi'); } },
    { id: 'en', short: 'EN', get name() { return t('theTu.tenEn'); } },
    { id: 'zh', short: '中', get name() { return t('theTu.tenZh'); } },
];

const GENDER_NAME = { der: 'theTu.giongDuc', die: 'theTu.giongCai', das: 'theTu.giongTrung' } as const satisfies Record<MaoTu, Khoa>;
const PRONOUNS = ['ich', 'du', 'er/sie/es', 'wir', 'ihr', 'sie/Sie'];
const IMPERATIV = ['du', 'ihr', 'Sie'];
export const NOTE_MAX = 500;
/** Ngừng gõ ghi chú chừng này thì lưu. */
export const CHO_LUU_GHI_CHU_MS = 600;

export function TheTu() {
    const { ma = '' } = useParams();
    if (!/^\d+$/.test(ma)) {
        return <KhongThay title={t('theTu.khongCo')} message={t('theTu.khongCoMoTa')} back={{ to: '/chu-de', label: t('chung.veDanhSachChuDe') }} />;
    }
    return <WordSheet key={ma} id={ma} />;
}

function WordSheet({ id }: { id: string }) {
    const { duLieu, loi, taiLai, sua } = useApi<{ data: TheChiTiet }>(`/the/${id}`);
    const word = duLieu?.data;
    useTitle(word?.front ?? '');
    const [tab, setTab] = useState<TabId>('nghia');
    const [loiSao, setLoiSao] = useState(false);
    const tabsRef = useRef<HTMLDivElement>(null);
    const baseId = useId();

    if (loi?.trangThai === 404) {
        return <KhongThay title={t('theTu.khongCo')} message={t('theTu.khongCoMoTa')} back={{ to: '/chu-de', label: t('chung.veDanhSachChuDe') }} />;
    }
    if (!word) {
        return (
            <div className="mx-auto max-w-[720px]">
                <Sheet className="px-5 md:px-8">{loi ? <LoiTai loi={loi} taiLai={taiLai} /> : <DangTai />}</Sheet>
            </div>
        );
    }

    // Sao và ghi chú đổi riêng từng phần: lưu ghi chú không ghi đè sao vừa bấm, và ngược lại
    const doiSoTay = (moi: Partial<SoTay>) =>
        sua((cu) => ({ data: { ...cu.data, bookmark: { ...cu.data.bookmark, ...moi }, starred: moi.starred ?? cu.data.starred } }));
    const batSao = async () => {
        const sao = !word.bookmark.starred;
        setLoiSao(false);
        doiSoTay({ starred: sao });
        try {
            const kq = await api.sua<{ data: SoTay }>(`/the/${word.id}/so-tay`, { starred: sao });
            doiSoTay({ starred: kq.data.starred });
            quenDaTai();
        } catch {
            doiSoTay({ starred: !sao });
            setLoiSao(true);
        }
    };
    const reg = word.extra?.register;
    const sacThai = typeof reg === 'string' ? reg : null;

    const onTabKey = (e: KeyboardEvent<HTMLDivElement>) => {
        const i = TABS.findIndex((tb) => tb.id === tab);
        const to =
            e.key === 'ArrowRight' ? (i + 1) % TABS.length
            : e.key === 'ArrowLeft' ? (i - 1 + TABS.length) % TABS.length
            : e.key === 'Home' ? 0
            : e.key === 'End' ? TABS.length - 1
            : null;
        if (to === null) return;
        e.preventDefault();
        setTab(TABS[to].id);
        tabsRef.current?.querySelector<HTMLButtonElement>(`[data-tab="${TABS[to].id}"]`)?.focus();
    };

    const { nav } = word;
    return (
        <div className="mx-auto max-w-[720px]">
            <nav aria-label={t('chung.viTri')} className="px-5 pt-4 pb-2 md:px-0 md:pt-0">
                {word.topic ? (
                    <Link to={`/chu-de/${word.topic.slug}`} className="inline-flex min-h-11 items-center gap-1 text-label font-semibold text-ink-2 hover:text-ink">
                        <ChevronLeft size={18} aria-hidden="true" />
                        {word.topic.name}
                    </Link>
                ) : (
                    <Link to="/tra-tu" className="inline-flex min-h-11 items-center gap-1 text-label font-semibold text-ink-2 hover:text-ink">
                        <ChevronLeft size={18} aria-hidden="true" />
                        {t('khung.traTu')}
                    </Link>
                )}
            </nav>

            <Sheet>
                <header className="px-5 pt-5 pb-4 md:px-8 md:pt-8">
                    <div className="flex items-start gap-3">
                        <div className="min-w-0 flex-1">
                            <h1 className={`${word.front.length > 30 ? 'text-headline' : 'text-display'} font-extrabold break-words`}>
                                <TuDuc the={word} />
                            </h1>
                            <p className="mt-1 text-title text-ink-2">
                                <DongDang the={word} />
                            </p>
                        </div>
                        <button
                            type="button"
                            aria-pressed={word.bookmark.starred}
                            onClick={batSao}
                            className={`flex size-11 shrink-0 items-center justify-center rounded-md border border-line transition-colors duration-200 hover:bg-sunken ${
                                word.bookmark.starred ? 'text-warning-ink' : 'text-ink-2'
                            }`}
                        >
                            <Star size={22} aria-hidden="true" fill={word.bookmark.starred ? 'currentColor' : 'none'} />
                            <span className="sr-only">{t('theTu.danhDauSao')}</span>
                        </button>
                    </div>
                    {loiSao && (
                        <p role="alert" className="mt-2 text-label text-danger">
                            {t('theTu.luuLoi')}
                        </p>
                    )}
                    <p className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-label text-ink-2">
                        <span>{[word.collection_name, tenLoaiTu(word.pos), word.level ? t('theTu.cap', { cap: word.level }) : null].filter(Boolean).join(' · ')}</span>
                        {sacThai && <span>{t('theTu.sacThai', { ten: sacThai })}</span>}
                    </p>
                    <div className="mt-4 flex flex-wrap gap-2">
                        <AudioButton text={word.front} />
                        <AudioButton text={word.front} slow />
                    </div>
                </header>

                <div ref={tabsRef} role="tablist" aria-label={t('theTu.thongTin')} onKeyDown={onTabKey} className="flex overflow-x-auto border-b border-line px-2 md:px-6">
                    {TABS.map((tb) => {
                        const on = tb.id === tab;
                        return (
                            <button
                                key={tb.id}
                                type="button"
                                role="tab"
                                data-tab={tb.id}
                                id={`${baseId}-tab-${tb.id}`}
                                aria-selected={on}
                                aria-controls={on ? `${baseId}-panel-${tb.id}` : undefined}
                                tabIndex={on ? 0 : -1}
                                onClick={() => setTab(tb.id)}
                                className={`-mb-px min-h-12 shrink-0 border-b-2 px-3 text-label font-semibold whitespace-nowrap transition-colors duration-200 ${
                                    on ? 'border-tim text-ink' : 'border-transparent text-ink-2 hover:text-ink'
                                }`}
                            >
                                {tb.label}
                            </button>
                        );
                    })}
                </div>

                <div role="tabpanel" id={`${baseId}-panel-${tab}`} aria-labelledby={`${baseId}-tab-${tab}`} tabIndex={0} className="px-5 py-5 md:px-8 md:py-6">
                    {tab === 'nghia' && <MeaningTab word={word} />}
                    {tab === 'ngu-phap' && <GrammarTab word={word} />}
                    {tab === 'ghi-chu' && <NoteTab word={word} onLuu={doiSoTay} />}
                </div>
            </Sheet>

            {nav && (
                <nav aria-label={t('theTu.tuKhac')} className="flex items-center justify-between gap-3 px-5 py-4 md:px-0">
                    {nav.prev ? (
                        <Link to={`/tu/${nav.prev.id}`} aria-label={t('theTu.tuTruoc', { tu: nav.prev.front })} className="inline-flex min-h-11 min-w-0 items-center gap-1 font-semibold hover:underline">
                            <ChevronLeft size={18} aria-hidden="true" className="shrink-0" />
                            <span lang="de" className="truncate">
                                {nav.prev.front}
                            </span>
                        </Link>
                    ) : (
                        <span />
                    )}
                    <span className="shrink-0 text-label text-ink-2 tabular-nums">{t('chuDe.trangTrenTong', { trang: nav.index, tong: nav.total })}</span>
                    {nav.next ? (
                        <Link to={`/tu/${nav.next.id}`} aria-label={t('theTu.tuSau', { tu: nav.next.front })} className="inline-flex min-h-11 min-w-0 items-center gap-1 font-semibold hover:underline">
                            <span lang="de" className="truncate">
                                {nav.next.front}
                            </span>
                            <ChevronRight size={18} aria-hidden="true" className="shrink-0" />
                        </Link>
                    ) : (
                        <span />
                    )}
                </nav>
            )}
        </div>
    );
}

function MeaningTab({ word }: { word: TheChiTiet }) {
    const { settings, dispatch } = useStore();
    const langs = settings.langs;
    const none = !langs.vi && !langs.en && !langs.zh;
    const toggle = (id: Lang) => dispatch({ type: 'settings', patch: { langs: { ...langs, [id]: !langs[id] } } });
    return (
        <>
            <div role="group" aria-label={t('theTu.hienNghia')} className="flex gap-2">
                {LANGS.map((l) => (
                    <button
                        key={l.id}
                        type="button"
                        aria-label={`${l.short} (${l.name})`}
                        aria-pressed={langs[l.id]}
                        onClick={() => toggle(l.id)}
                        className={`min-h-11 min-w-11 rounded-md border px-4 text-label font-semibold transition-colors duration-200 ${
                            langs[l.id] ? 'border-tim bg-tim-tint text-ink' : 'border-line text-ink-2 hover:border-line-strong'
                        } ${l.id === 'zh' ? 'font-zh' : ''}`}
                    >
                        {l.short}
                    </button>
                ))}
            </div>

            <dl className="mt-4 space-y-2">
                {langs.vi && word.meanings.vi && (
                    <div>
                        <dt className="sr-only">{t('ngonNgu.vi')}</dt>
                        <dd className="text-title font-bold">{word.meanings.vi}</dd>
                    </div>
                )}
                {langs.en && word.meanings.en && (
                    <div>
                        <dt className="sr-only">{t('ngonNgu.en')}</dt>
                        <dd lang="en" className="text-ink-2">
                            {word.meanings.en}
                        </dd>
                    </div>
                )}
                {langs.zh && word.meanings.zh && (
                    <div>
                        <dt className="sr-only">{t('ngonNgu.zh')}</dt>
                        <dd lang="zh" className="font-zh text-ink-2">
                            {word.meanings.zh}
                        </dd>
                    </div>
                )}
            </dl>
            {none && <p className="mt-4 text-ink-2">{t('theTu.tatNghia')}</p>}

            {word.note && (
                <>
                    <h2 className="mt-6 text-label font-semibold text-ink-2">{t('theTu.ghiChuNoiDung')}</h2>
                    <p className="mt-1 whitespace-pre-line">{word.note}</p>
                </>
            )}

            <h2 className="mt-6 text-label font-semibold text-ink-2">{t('theTu.viDu')}</h2>
            {word.examples.length === 0 ? (
                <p className="mt-2 text-ink-2">{t('theTu.chuaCoViDu')}</p>
            ) : (
                <>
                    <p className="mt-1 text-label text-ink-2">{t('theTu.bamChu')}</p>
                    <ul className="mt-2 divide-y divide-line">
                        {word.examples.map((ex) => (
                            <li key={ex.sentence} className="flex items-start gap-3 py-3">
                                <AudioButton text={ex.sentence} label={t('nghe.viDu')} iconOnly />
                                <div className="min-w-0 flex-1 pt-2">
                                    <CauBamDuoc
                                        cau={ex.sentence}
                                        className="font-semibold"
                                        sauCau={(['vi', 'en', 'zh'] as const).map((l) =>
                                            langs[l] && ex.translations[l] ? (
                                                <p key={l} lang={l} className={`text-ink-2 ${l === 'zh' ? 'font-zh' : ''}`}>
                                                    {ex.translations[l]}
                                                </p>
                                            ) : null,
                                        )}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                </>
            )}

            {word.tip && (
                <p className="mt-5 rounded-md border border-dashed border-line-strong px-4 py-3 font-hand text-hand">
                    <span className="sr-only">{t('theTu.meo')} </span>
                    {word.tip}
                </p>
            )}
        </>
    );
}

/** Bảng chia 6 ngôi (ich … sie/Sie). */
function BangChia({ tieuDe, dang }: { tieuDe: string; dang: string[] }) {
    return (
        <table className="w-full max-w-sm text-left">
            <caption className="mb-2 text-left text-label font-semibold text-ink-2">{tieuDe}</caption>
            <tbody lang="de" className="divide-y divide-line">
                {dang.slice(0, 6).map((form, i) => (
                    <tr key={PRONOUNS[i]}>
                        <th scope="row" className="py-2 pr-6 font-normal text-ink-2">
                            {PRONOUNS[i]}
                        </th>
                        <td className="py-2 font-semibold">{form}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function GrammarTab({ word }: { word: TheChiTiet }) {
    const v = word.verb;
    if (word.pos === 'noun' && word.article) {
        return (
            <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-3">
                <dt className="text-ink-2">{t('theTu.giong')}</dt>
                <dd>
                    <span lang="de">
                        <Article gender={word.article} />
                    </span>{' '}
                    ({t(GENDER_NAME[word.article])})
                </dd>
                <dt className="text-ink-2">{t('theTu.soIt')}</dt>
                <dd className="font-semibold">
                    <TuDuc the={word} />
                </dd>
                {word.forms && (
                    <>
                        <dt className="text-ink-2">{t('theTu.soNhieu')}</dt>
                        <dd className="font-semibold">
                            <DongDang the={word} />
                        </dd>
                    </>
                )}
            </dl>
        );
    }
    if (v) {
        const chia = v.conjugation;
        return (
            <>
                {(v.form_er || v.preterite || v.perfect) && (
                    <table className="w-full text-left">
                        <caption className="mb-2 text-left text-label font-semibold text-ink-2">{t('theTu.baDang')}</caption>
                        <thead>
                            <tr className="border-b border-line text-label text-ink-2">
                                <th scope="col" className="py-2 pr-4 font-semibold">
                                    {t('theTu.hienTai3')}
                                </th>
                                <th scope="col" className="py-2 pr-4 font-semibold">
                                    {t('theTu.quaKhu')}
                                </th>
                                <th scope="col" className="py-2 font-semibold">
                                    {t('theTu.perfekt')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr lang="de">
                                {[v.form_er, v.preterite, v.perfect].map((x, i) => (
                                    <td key={i} className="py-2 pr-4 font-semibold">
                                        {x ?? '–'}
                                    </td>
                                ))}
                            </tr>
                        </tbody>
                    </table>
                )}
                {v.auxiliary && (
                    <p className="mt-3 text-label text-ink-2">
                        {t('theTu.troDongTu')}:{' '}
                        <span lang="de" className="font-semibold text-ink">
                            {v.auxiliary}
                        </span>
                    </p>
                )}
                {v.is_separable && v.prefix && <p className="mt-1 text-label text-ink-2">{t('theTu.tienTo', { tienTo: v.prefix })}</p>}
                {/* Máy tính: ba thì đứng cạnh nhau; điện thoại: xếp dọc */}
                {(chia?.pres || chia?.prat || chia?.perf) && (
                    <div className="mt-6 grid gap-6 md:grid-cols-3 md:gap-5">
                        {chia?.pres && <BangChia tieuDe={t('theTu.chiaHienTai')} dang={chia.pres} />}
                        {chia?.prat && <BangChia tieuDe={t('theTu.chiaQuaKhu')} dang={chia.prat} />}
                        {chia?.perf && <BangChia tieuDe={t('theTu.chiaPerfekt')} dang={chia.perf} />}
                    </div>
                )}
                {chia?.imp && (
                    <table className="mt-6 w-full max-w-sm text-left">
                        <caption className="mb-2 text-left text-label font-semibold text-ink-2">{t('theTu.menhLenh')}</caption>
                        <tbody lang="de" className="divide-y divide-line">
                            {chia.imp.slice(0, 3).map((form, i) => (
                                <tr key={IMPERATIV[i]}>
                                    <th scope="row" className="py-2 pr-6 font-normal text-ink-2">
                                        {IMPERATIV[i]}
                                    </th>
                                    <td className="py-2 font-semibold">{form}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </>
        );
    }
    // Tính từ: "süßer · am süßesten" là so sánh hơn và so sánh nhất
    const soSanh = (word.pos === 'adj' || word.pos === 'adj_adv') && word.forms ? word.forms.split(' · ') : [];
    if (soSanh.length === 2) {
        return (
            <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-3">
                <dt className="text-ink-2">{t('theTu.nguyenDang')}</dt>
                <dd lang="de" className="font-semibold">
                    {word.front}
                </dd>
                <dt className="text-ink-2">{t('theTu.soSanhHon')}</dt>
                <dd lang="de" className="font-semibold">
                    {soSanh[0]}
                </dd>
                <dt className="text-ink-2">{t('theTu.soSanhNhat')}</dt>
                <dd lang="de" className="font-semibold">
                    {soSanh[1]}
                </dd>
            </dl>
        );
    }
    if (word.forms && word.forms !== '–') {
        const nhan: Khoa = word.collection === 'verbprep' ? 'theTu.gioiTuCach' : word.pos === 'verb' || word.pos === 'sepverb' ? 'theTu.dangChia' : 'theTu.dang';
        return (
            <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-3">
                <dt className="text-ink-2">{t('theTu.nguyenMau')}</dt>
                <dd className="font-semibold">
                    <TuDuc the={word} />
                </dd>
                <dt className="text-ink-2">{t(nhan)}</dt>
                <dd lang="de" className="font-semibold">
                    {word.forms}
                </dd>
            </dl>
        );
    }
    return <p className="text-ink-2">{t('theTu.chuaCoNguPhap')}</p>;
}

type TrangThaiLuu = 'yen' | 'cho' | 'dang-luu' | 'da-luu' | 'loi';

/** Ghi chú riêng: ngừng gõ thì tự lưu lên tài khoản; rời trang khi còn chữ chưa lưu thì gửi nốt. */
function NoteTab({ word, onLuu }: { word: TheChiTiet; onLuu: (s: Partial<SoTay>) => void }) {
    const id = useId();
    const [text, setText] = useState(word.bookmark.note ?? '');
    const [luu, setLuu] = useState<TrangThaiLuu>('yen');
    const daGui = useRef(word.bookmark.note ?? '');
    const choGui = useRef<string | null>(null);
    const lanGui = useRef(0);

    const gui = async (chu: string) => {
        const lan = ++lanGui.current;
        choGui.current = null;
        setLuu('dang-luu');
        try {
            const kq = await api.sua<{ data: SoTay }>(`/the/${word.id}/so-tay`, { note: chu });
            if (lan !== lanGui.current) return;
            daGui.current = kq.data.note ?? '';
            onLuu({ note: kq.data.note });
            quenDaTai();
            setLuu('da-luu');
        } catch {
            if (lan !== lanGui.current) return;
            choGui.current = chu;
            setLuu('loi');
        }
    };

    // Ngừng gõ thì lưu
    useEffect(() => {
        if (text === daGui.current && choGui.current === null) return;
        choGui.current = text;
        const hen = window.setTimeout(() => void gui(text), CHO_LUU_GHI_CHU_MS);
        return () => window.clearTimeout(hen);
        // gui dùng giá trị mới nhất qua ref; chỉ chạy lại khi chữ đổi
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [text]);

    // Rời trang (đóng tab, chuyển trang, sang tab khác của thẻ) khi còn chữ chưa gửi: gửi nốt bằng keepalive
    // (trình duyệt giữ yêu cầu dù trang đã đóng) và giữ chữ đó trên thẻ đang hiện
    const luuRef = useRef(onLuu);
    useEffect(() => {
        luuRef.current = onLuu;
    });
    useEffect(() => {
        const guiNot = () => {
            const chu = choGui.current;
            if (chu === null || chu === daGui.current) return;
            api.guiKhiRoiTrang(`/the/${word.id}/so-tay`, { note: chu });
            daGui.current = chu;
            choGui.current = null;
            luuRef.current({ note: chu.trim() === '' ? null : chu });
            quenDaTai();
        };
        window.addEventListener('pagehide', guiNot);
        return () => {
            window.removeEventListener('pagehide', guiNot);
            guiNot();
        };
    }, [word.id]);

    return (
        <>
            <label htmlFor={id} className="text-label font-semibold">
                {t('theTu.ghiChuRieng')}
            </label>
            <textarea
                id={id}
                value={text}
                maxLength={NOTE_MAX}
                rows={5}
                aria-describedby={`${id}-goi-y`}
                onChange={(e) => setText(e.target.value)}
                className="mt-2 block w-full rounded-md border border-line-strong bg-paper px-3 py-2 text-body placeholder:text-ink-2"
                placeholder={t('theTu.ghiChuGoiY')}
            />
            <p id={`${id}-goi-y`} className="mt-2 flex justify-between gap-4 text-label text-ink-2">
                <span>{t('theTu.ghiChuMat')}</span>
                <span className="tabular-nums">
                    {text.length}/{NOTE_MAX}
                </span>
            </p>
            <p role="status" className="mt-1 flex min-h-11 items-center gap-3 text-label text-ink-2">
                {luu === 'dang-luu' && t('theTu.dangLuu')}
                {luu === 'da-luu' && t('theTu.daLuu')}
                {luu === 'loi' && (
                    <>
                        <span className="text-danger">{t('theTu.luuLoi')}</span>
                        <button type="button" onClick={() => void gui(text)} className="min-h-11 rounded-md border border-line-strong px-3 font-semibold text-ink hover:bg-sunken">
                            {t('theTu.luuLai')}
                        </button>
                    </>
                )}
            </p>
        </>
    );
}
