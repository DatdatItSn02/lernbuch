import { ArrowRight, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { DongDang, TuDuc } from '../components/Article';
import { AudioButton } from '../components/AudioButton';
import { ChoGui } from '../components/ChoGui';
import { Kbd } from '../components/Kbd';
import { LevelIcon } from '../components/LevelIcon';
import { DangTai, LoiTai } from '../components/TaiNoiDung';
import { PenTick } from '../components/Vo';
import { api, LoiApi } from '../lib/api';
import { guiDi, themLuot, useHangCho } from '../lib/hangCho';
import type { ConLai, PhienHoc, TheHoc } from '../lib/hoc';
import { truyVan } from '../lib/noiDung';
import { speak } from '../lib/speech';
import { GRADES, SO_CHAM, formatInterval, grade as gradeCard, levelName, stageName, type Grade } from '../lib/srs';
import { ulidTangDan } from '../lib/ulid';
import { useStore } from '../state/store';
import { useTitle } from '../lib/useTitle';
import { t } from '../i18n';
import { tGhep } from '../i18n/ghep';

type Done = { id: number; grade: Grade; before: number; after: number };

const MAX_REQUEUE = 2;

const GRADE_STYLE: Record<Grade, string> = {
    quen: 'border-danger text-danger bg-sheet hover:bg-danger-tint',
    kho: 'border-warning-ink text-warning-ink bg-sheet hover:bg-sunken',
    tot: 'border-tim bg-tim text-on-tim hover:bg-tim-strong hover:border-tim-strong',
    de: 'border-tim text-tim bg-sheet hover:bg-tim-tint',
};

const isTyping = (el: EventTarget | null) => el instanceof HTMLElement && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));
/** Space/Enter trên nút hay liên kết đang có tiêu điểm thì để trình duyệt bấm chính nút đó. */
const onControl = (el: EventTarget | null) => el instanceof HTMLElement && !!el.closest('button, a');

type Tai = { trangThai: 'dang-tai' } | { trangThai: 'loi'; loi: LoiApi } | { trangThai: 'xong'; phien: PhienHoc };

/**
 * Phiên học Flashcard: máy chủ lập phiên (thẻ đến hạn và từ mới, xen kẽ), app chạy phiên, mỗi lượt chấm vào hàng chờ
 * trên máy rồi gửi theo lô. Mất mạng giữa phiên vẫn chấm tiếp được; lượt chờ gửi khi có mạng.
 */
export function Hoc() {
    const [params] = useSearchParams();
    const navigate = useNavigate();
    useTitle(t('hoc.tieuDe'));
    const chuDe = params.get('chu-de');
    const chiOn = params.get('on') === '1';
    const [tai, setTai] = useState<Tai>({ trangThai: 'dang-tai' });
    const [lan, setLan] = useState(0);
    const daGoi = useRef(-1);

    // Mở trang là lập một phiên (StrictMode chạy effect hai lần: chỉ gọi một lần cho mỗi lần thử)
    useEffect(() => {
        if (daGoi.current === lan) return;
        daGoi.current = lan;
        api.gui<{ data: PhienHoc }>('/hoc/phien', { topic: chuDe, review_only: chiOn, mode: 'flashcard' }).then(
            ({ data }) => setTai({ trangThai: 'xong', phien: data }),
            (e: unknown) => setTai({ trangThai: 'loi', loi: e instanceof LoiApi ? e : new LoiApi(0, 'mat_mang') }),
        );
    }, [chuDe, chiOn, lan]);

    const exit = useCallback(() => {
        void guiDi();
        navigate(chuDe ? `/chu-de/${chuDe}` : '/hom-nay');
    }, [navigate, chuDe]);

    if (tai.trangThai !== 'xong') {
        return (
            <div className="flex min-h-dvh flex-col bg-paper text-ink">
                <Header onExit={exit} pos={0} total={0} />
                <main className="mx-auto w-full max-w-[640px] flex-1 px-5">
                    <h1 className="sr-only">{t('hoc.tieuDe')}</h1>
                    {tai.trangThai === 'dang-tai' ? (
                        <DangTai />
                    ) : (
                        <LoiTai
                            loi={tai.loi}
                            taiLai={() => {
                                setTai({ trangThai: 'dang-tai' });
                                setLan((n) => n + 1);
                            }}
                        />
                    )}
                </main>
            </div>
        );
    }

    return <Phien phien={tai.phien} chuDe={chuDe} chiOn={chiOn} onExit={exit} />;
}

function Phien({ phien, chuDe, chiOn, onExit }: { phien: PhienHoc; chuDe: string | null; chiOn: boolean; onExit: () => void }) {
    const { settings } = useStore();
    const byId = useMemo(() => new Map(phien.cards.map((c) => [c.id, c])), [phien]);
    const [queue, setQueue] = useState<number[]>(() => phien.cards.map((c) => c.id));
    const [levels, setLevels] = useState<Record<number, number>>(() => Object.fromEntries(phien.cards.map((c) => [c.id, c.box])));
    const [pos, setPos] = useState(0);
    const [revealed, setRevealed] = useState(false);
    const [done, setDone] = useState<Done[]>([]);
    const [requeued, setRequeued] = useState<Record<number, number>>({});
    const [lookBack, setLookBack] = useState(0); // 0: thẻ đang học; n: xem lại thẻ đã chấm thứ n tính từ cuối
    const [daGuiHet, setDaGuiHet] = useState(false);
    // Lúc thẻ đang học hiện lên (đặt trong effect bên dưới)
    const shownAt = useRef(0);
    // Lượt đang ghi vào hàng chờ (IndexedDB ghi không đồng bộ): xong phiên thì chờ ghi hết rồi mới gửi
    const dangGhi = useRef<Promise<void>[]>([]);

    const finished = pos >= queue.length;
    const reviewing = lookBack > 0 ? done[done.length - lookBack] : null;
    const card = reviewing ? byId.get(reviewing.id) : finished ? undefined : byId.get(queue[pos]);
    const level = card ? (reviewing ? reviewing.after : (levels[card.id] ?? 0)) : 0;
    const last = done[done.length - 1];

    // Thời gian thẻ hiện trên màn hình (máy chủ dùng để nhận ra chấm quá nhanh)
    useEffect(() => {
        shownAt.current = Date.now();
    }, [pos]);

    // Xong phiên: chờ ghi hết lượt vào hàng chờ, gửi ngay, rồi bảng tổng kết mới hỏi máy chủ phiên tới còn gì
    useEffect(() => {
        if (!finished) return;
        let bo = false;
        void Promise.all(dangGhi.current)
            .then(() => guiDi())
            .then(() => !bo && setDaGuiHet(true));
        return () => {
            bo = true;
        };
    }, [finished]);

    const doGrade = useCallback(
        (g: Grade) => {
            if (!card || reviewing || !revealed) return;
            const before = levels[card.id] ?? 0;
            const after = gradeCard(before, g).level;
            const now = Date.now();
            const ghi = themLuot({
                uid: ulidTangDan(now),
                card_id: card.id,
                session_id: phien.id,
                mode: 'flashcard',
                rating: SO_CHAM[g],
                reviewed_at_ms: now,
                duration_ms: Math.max(0, now - shownAt.current),
            });
            dangGhi.current.push(ghi.catch(() => {}));
            setLevels((l) => ({ ...l, [card.id]: after }));
            setDone((d) => [...d, { id: card.id, grade: g, before, after }]);
            if (g === 'quen' && (requeued[card.id] ?? 0) < MAX_REQUEUE) {
                // Ôn lại sau ít phút: chèn thẻ vào sau vài thẻ nữa
                setQueue((q) => {
                    const at = Math.min(q.length, pos + 4);
                    return [...q.slice(0, at), card.id, ...q.slice(at)];
                });
                setRequeued((r) => ({ ...r, [card.id]: (r[card.id] ?? 0) + 1 }));
            }
            setPos((p) => p + 1);
            setRevealed(false);
        },
        [card, reviewing, revealed, levels, phien.id, requeued, pos],
    );

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (isTyping(e.target) || e.altKey || e.ctrlKey || e.metaKey) return;
            const k = e.key.toLowerCase();
            if (k === 'escape') return onExit();
            if (k === 'arrowleft') return setLookBack((n) => Math.min(done.length, n + 1));
            if (k === 'arrowright') return setLookBack((n) => Math.max(0, n - 1));
            if (!card) return;
            if ((k === ' ' || k === 'enter') && onControl(e.target)) return;
            if (k === ' ' || k === 'spacebar') {
                e.preventDefault();
                if (reviewing) return setLookBack(0);
                return revealed ? doGrade('tot') : setRevealed(true);
            }
            const g = GRADES.find((x) => x.key === k);
            if (g && revealed) return doGrade(g.id);
            if (k === 'p') void speak(card.front);
            if (k === 's') void speak(card.front, true);
            if (k === 'e' && card.example) void speak(card.example.sentence);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [card, revealed, reviewing, doGrade, onExit, done.length]);

    const lastCard = last ? byId.get(last.id) : undefined;
    const lastRequeued = !!last && last.grade === 'quen' && queue.indexOf(last.id, pos) >= pos;
    let status: ReactNode = null;
    if (reviewing) {
        status = <>{t('hoc.dangXemLai', { cham: GRADES.find((g) => g.id === reviewing.grade)?.label ?? '' })}</>;
    } else if (!finished && lastCard && last && last.after > last.before) {
        status = (
            <span className="inline-flex items-center gap-1">
                {tGhep('hoc.lenMuc', { tu: <TuDuc the={lastCard} />, muc: levelName(last.after) })}
                <span className="lb-grow inline-flex">
                    <LevelIcon level={last.after} size={20} className="text-tim" />
                </span>
            </span>
        );
    } else if (!finished && lastCard && lastRequeued) {
        status = <>{tGhep('hoc.quayLai', { tu: <TuDuc the={lastCard} /> })}</>;
    }

    return (
        <div className="flex min-h-dvh flex-col bg-paper text-ink">
            <Header onExit={onExit} pos={pos} total={queue.length} finished={finished} />
            <p role="status" className="mx-auto min-h-6 w-full max-w-[640px] px-5 text-center text-label text-ink-2">
                {status}
            </p>

            {queue.length === 0 ? (
                <Empty onExit={onExit} />
            ) : finished && !reviewing ? (
                <Summary done={done} byId={byId} chuDe={chuDe} chiOn={chiOn} tenChuDe={phien.topic?.name} daGuiHet={daGuiHet} />
            ) : card ? (
                <Card
                    key={`${card.id}-${pos}-${lookBack}`}
                    card={card}
                    level={level}
                    revealed={revealed || !!reviewing}
                    reviewing={reviewing}
                    focusReveal={done.length > 0}
                    langs={settings.langs}
                    onReveal={() => setRevealed(true)}
                    onGrade={doGrade}
                    onBack={() => setLookBack(0)}
                />
            ) : null}
        </div>
    );
}

function Header({ onExit, pos, total, finished = false }: { onExit: () => void; pos: number; total: number; finished?: boolean }) {
    const pct = total === 0 ? 0 : Math.round((Math.min(pos, total) / total) * 100);
    return (
        <header className="mx-auto flex w-full max-w-[640px] items-center gap-4 px-4 pt-3 pb-2">
            <button
                type="button"
                onClick={onExit}
                aria-label={t('hoc.thoat')}
                className="flex size-11 items-center justify-center rounded-md text-ink-2 hover:bg-sunken hover:text-ink"
            >
                <X size={24} aria-hidden="true" />
            </button>
            <div
                role="progressbar"
                aria-label={t('hoc.tienDo')}
                aria-valuemin={0}
                aria-valuemax={total}
                aria-valuenow={Math.min(pos, total)}
                className="h-2 flex-1 overflow-hidden rounded-sm bg-sunken"
            >
                <div className="h-full bg-tim transition-[width] duration-300" style={{ width: `${pct}%` }} />
            </div>
            <span className="min-w-12 text-right text-label font-semibold tabular-nums text-ink-2">
                {Math.min(pos + (finished || total === 0 ? 0 : 1), total)}/{total}
            </span>
            <ChoGui />
        </header>
    );
}

function Card(props: {
    card: TheHoc;
    level: number;
    revealed: boolean;
    reviewing: Done | null;
    /** Sau khi chấm, nút vừa bấm biến mất: đặt tiêu điểm vào nút Lật thẻ của thẻ mới (thẻ đầu phiên thì không). */
    focusReveal: boolean;
    langs: { vi: boolean; en: boolean; zh: boolean };
    onReveal: () => void;
    onGrade: (g: Grade) => void;
    onBack: () => void;
}) {
    const { card, level, revealed, reviewing, focusReveal, langs } = props;
    const example = card.example;
    const revealRef = useRef<HTMLButtonElement>(null);
    const answerRef = useRef<HTMLDivElement>(null);
    const wasRevealed = useRef(revealed);
    const nghia = (['vi', 'en', 'zh'] as const).filter((l) => langs[l] && card.meanings[l]);

    // Giữ tiêu điểm trong phiên: thẻ mới đặt vào nút Lật thẻ, lật xong đặt vào phần đáp án để trình đọc màn hình đọc tiếp.
    useEffect(() => {
        const lost = !document.activeElement || document.activeElement === document.body;
        if (!revealed && focusReveal && lost) revealRef.current?.focus();
    }, [revealed, focusReveal]);
    useEffect(() => {
        if (revealed && !wasRevealed.current && !reviewing) answerRef.current?.focus();
        wasRevealed.current = revealed;
    }, [revealed, reviewing]);
    return (
        <>
            <main className="mx-auto flex w-full max-w-[640px] flex-1 flex-col px-4 pt-6 pb-48">
                {/* Thẻ rút từ hộp: hai thẻ chờ phía sau, tai thẻ ghi mức thuộc */}
                <div className="relative">
                    <span aria-hidden="true" className="absolute inset-x-4 -top-2 h-4 rounded-t-md border border-b-0 border-line bg-sheet" />
                    <span aria-hidden="true" className="absolute inset-x-2 -top-1 h-4 rounded-t-md border border-b-0 border-line bg-sheet" />
                    <article className="relative flex min-h-[min(56vh,30rem)] flex-col rounded-md border border-line bg-sheet shadow-sheet">
                        <p
                            className="absolute -top-6 right-4 flex h-6 items-center gap-1 rounded-t-md px-2.5 text-label font-semibold"
                            style={{ background: `var(--color-level-${level})`, color: `var(--color-on-level-${level})` }}
                        >
                            <LevelIcon level={level} size={16} title="" className="text-tim" />
                            {level === 0 ? t('hoc.tuMoi') : `${stageName(level)} · ${levelName(level)}`}
                        </p>
                        <div className="relative flex-1 py-6 pr-5 pl-14 md:pl-16">
                            <span aria-hidden="true" className="absolute inset-y-0 left-10 w-[1.5px] bg-pen opacity-45 md:left-12" />
                            {reviewing && (reviewing.grade === 'tot' || reviewing.grade === 'de') && (
                                <span className="absolute top-7 left-2.5 md:left-3.5">
                                    <PenTick size={22} />
                                </span>
                            )}
                            {card.topic && <p className="mb-3 text-label text-ink-2">{card.topic.name}</p>}
                            <h1 className="max-w-full text-study-word font-extrabold break-words">
                                <TuDuc the={card} />
                            </h1>
                            <p className="mt-1 text-ink-2">
                                <DongDang the={card} />
                            </p>
                            <div className="mt-4 flex gap-2">
                                <AudioButton text={card.front} shortcut="P" iconOnly />
                                <AudioButton text={card.front} slow shortcut="S" iconOnly />
                            </div>

                            {revealed && (
                                <div ref={answerRef} tabIndex={-1} aria-label={t('hoc.dapAn')} className="lb-reveal mt-6 border-t border-dashed border-line-strong pt-5">
                                    {nghia.map((l, i) => (
                                        <p key={l} lang={l} className={i === 0 ? 'text-title font-bold' : `mt-1 text-ink-2 ${l === 'zh' ? 'font-zh' : ''}`}>
                                            {card.meanings[l]}
                                        </p>
                                    ))}
                                    {nghia.length === 0 && <p className="text-ink-2">{t('hoc.tatNghia')}</p>}
                                    {example && (
                                        <div className="mt-5 flex items-start gap-3">
                                            <AudioButton text={example.sentence} label={t('nghe.viDu')} shortcut="E" iconOnly />
                                            <p>
                                                <span lang="de" className="block font-semibold">{example.sentence}</span>
                                                {langs.vi && example.translations.vi && <span className="block text-ink-2">{example.translations.vi}</span>}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                        {!revealed && (
                            <p className="border-t border-dashed border-line-strong py-3 pr-5 pl-14 text-label text-ink-2 md:pl-16">{t('hoc.tuNho')}</p>
                        )}
                    </article>
                </div>
            </main>

            <footer className="fixed inset-x-0 bottom-0 border-t border-line bg-sheet pb-[env(safe-area-inset-bottom)]">
                <div className="mx-auto max-w-[640px] px-4 py-3">
                    {reviewing ? (
                        <button type="button" onClick={props.onBack} className="flex min-h-14 w-full items-center justify-center gap-3 rounded-md border border-line-strong bg-sheet font-semibold hover:bg-sunken">
                            {t('hoc.veTheDangHoc')} <Kbd>→</Kbd>
                        </button>
                    ) : !revealed ? (
                        <button
                            ref={revealRef}
                            type="button"
                            onClick={props.onReveal}
                            className="flex min-h-14 w-full items-center justify-center gap-3 rounded-md bg-tim text-title font-bold text-on-tim transition-colors duration-200 hover:bg-tim-strong"
                        >
                            {t('hoc.latThe')} <Kbd>{t('phim.space')}</Kbd>
                        </button>
                    ) : (
                        <div role="group" aria-label={t('hoc.tuCham')} className="grid grid-cols-4 gap-2">
                            {GRADES.map((g) => (
                                <button
                                    key={g.id}
                                    type="button"
                                    onClick={() => props.onGrade(g.id)}
                                    aria-keyshortcuts={g.key}
                                    className={`flex min-h-14 flex-col items-center justify-center rounded-md border-[1.5px] px-1 transition-colors duration-200 ${GRADE_STYLE[g.id]}`}
                                >
                                    <span className="font-bold">{g.label}</span>
                                    <span className="text-label tabular-nums">
                                        {formatInterval(gradeCard(level, g.id).intervalDays)} <Kbd>{g.key}</Kbd>
                                    </span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </footer>
        </>
    );
}

/** Một câu tổng kết thay cho ô số: bỏ phần bằng 0 cho dễ đọc. */
export function sessionLine(total: number, fresh: number) {
    if (fresh === 0) return t('hoc.tongKetOn', { n: total });
    if (fresh === total) return t('hoc.tongKetMoi', { n: total });
    return t('hoc.tongKet', { n: total, moi: fresh, on: total - fresh });
}

function Summary(props: { done: Done[]; byId: Map<number, TheHoc>; chuDe: string | null; chiOn: boolean; tenChuDe?: string; daGuiHet: boolean }) {
    const { done, byId, chuDe, chiOn, tenChuDe } = props;
    const { settings } = useStore();
    const { cho } = useHangCho();
    const [conLai, setConLai] = useState<ConLai | null>(null);
    // Máy chủ đã nhận hết lượt của phiên này (mất mạng thì chờ tới khi hàng chờ gửi được)
    const daGui = props.daGuiHet && cho === 0;

    // Biết phiên tới còn gì sau khi máy chủ đã nhận hết lượt của phiên này
    useEffect(() => {
        if (!daGui) return;
        let bo = false;
        api.lay<{ data: ConLai }>(`/hoc/con-lai${truyVan({ chu_de: chuDe, on: chiOn ? '1' : null })}`).then(
            ({ data }) => !bo && setConLai(data),
            () => {},
        );
        return () => {
            bo = true;
        };
    }, [daGui, chuDe, chiOn]);

    const stats = useMemo(() => {
        const first = new Map<number, Done>();
        const lastOf = new Map<number, Done>();
        for (const d of done) {
            if (!first.has(d.id)) first.set(d.id, d);
            lastOf.set(d.id, d);
        }
        const cards = [...first.values()];
        const grown = cards.map((f) => ({ id: f.id, from: f.before, to: lastOf.get(f.id)!.after })).filter((c) => c.to > c.from);
        const perGrade = Object.fromEntries(GRADES.map((g) => [g.id, done.filter((d) => d.grade === g.id).length])) as Record<Grade, number>;
        return { total: cards.length, fresh: cards.filter((c) => c.before === 0).length, grown, perGrade };
    }, [done]);
    const newLimit = settings.newPerSession === 'all' ? Infinity : settings.newPerSession;
    const more = conLai !== null && conLai.review + conLai.new > 0;
    const xong = conLai !== null && !more;
    const back = chuDe ? { to: `/chu-de/${chuDe}`, label: t('chuDe.veChuDe') } : { to: '/hom-nay', label: t('chung.veHomNay') };

    return (
        <main className="mx-auto w-full max-w-[640px] flex-1 px-5 py-6">
            <h1 className="text-headline font-bold">{t('hoc.xongPhien')}</h1>
            {tenChuDe && <p className="text-ink-2">{tenChuDe}</p>}
            <p className="mt-4 text-title">
                {sessionLine(stats.total, stats.fresh)} {stats.grown.length > 0 ? t('hoc.theLenMuc', { n: stats.grown.length }) : t('hoc.chuaLenMuc')}
            </p>
            <p className="mt-1 text-label text-ink-2 tabular-nums">{GRADES.map((g) => `${g.label} ${stats.perGrade[g.id]}`).join(' · ')}</p>
            {props.daGuiHet && cho > 0 && <p className="mt-3 text-label text-ink-2">{t('hoc.conChoGui', { n: cho })}</p>}

            {stats.grown.length > 0 && <CayLonLen grown={stats.grown} byId={byId} />}

            {xong && <p className="mt-8 font-semibold">{t('hoc.hetChoNay')}</p>}
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                {more && (
                    <Link
                        to={`/hoc${truyVan({ 'chu-de': chuDe, on: chiOn ? '1' : null })}`}
                        className="flex min-h-12 items-center justify-center rounded-md bg-tim px-6 font-bold text-on-tim hover:bg-tim-strong"
                    >
                        {conLai.new > 0 ? t('chuDe.hocTiep') : t('hoc.onTiep')}
                        <span className="ml-2 text-label font-semibold tabular-nums">{t('chuDe.onVaMoi', { on: conLai.review, moi: Math.min(conLai.new, newLimit) })}</span>
                    </Link>
                )}
                <Link
                    to={back.to}
                    className={`flex min-h-12 items-center justify-center rounded-md px-6 font-semibold ${
                        more ? 'border border-line-strong hover:bg-sunken' : 'bg-tim font-bold text-on-tim hover:bg-tim-strong'
                    }`}
                >
                    {back.label}
                </Link>
            </div>
        </main>
    );
}

function CayLonLen({ grown, byId }: { grown: { id: number; from: number; to: number }[]; byId: Map<number, TheHoc> }) {
    return (
        <section aria-labelledby="cay-lon" className="mt-8">
            <h2 id="cay-lon" className="text-title font-bold">
                {t('hoc.cayLonLen')}
            </h2>
            <ul className="mt-2 grid gap-x-8 sm:grid-cols-2">
                {grown.map((c, i) => (
                    <CayMot key={c.id} c={c} i={i} the={byId.get(c.id)} />
                ))}
            </ul>
        </section>
    );
}

function CayMot({ c, i, the }: { c: { id: number; from: number; to: number }; i: number; the: TheHoc | undefined }) {
    return (
        <li className="flex min-h-12 items-center justify-between gap-4 border-b border-line py-2">
            <span className="min-w-0 font-semibold break-words">{the && <TuDuc the={the} />}</span>
            <span className="flex shrink-0 items-center gap-1 text-label text-ink-2">
                <LevelIcon level={c.from} size={20} title="" className="text-tim" />
                <ArrowRight size={14} aria-hidden="true" />
                <span className="lb-grow inline-flex" style={{ animationDelay: `${i * 80}ms` }}>
                    <LevelIcon level={c.to} size={28} title="" className="text-tim" />
                </span>
                <span className="sr-only">{t('hoc.tuMucLen', { muc: levelName(c.from) })}</span>
                {levelName(c.to)}
            </span>
        </li>
    );
}

function Empty({ onExit }: { onExit: () => void }) {
    return (
        <main className="mx-auto flex w-full max-w-[640px] flex-1 flex-col items-center justify-center px-5 text-center">
            <h1 className="text-headline font-bold">{t('hoc.khongCoThe')}</h1>
            <p className="mt-2 text-ink-2">{t('hoc.khongCoTheLyDo')}</p>
            <button type="button" onClick={onExit} className="mt-6 min-h-12 rounded-md border border-line-strong px-6 font-semibold hover:bg-sunken">
                {t('chung.quayLai')}
            </button>
        </main>
    );
}
