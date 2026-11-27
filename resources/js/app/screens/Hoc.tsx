import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { DongDang, TuDuc } from '../components/Article';
import { AudioButton } from '../components/AudioButton';
import { Kbd } from '../components/Kbd';
import { LevelIcon } from '../components/LevelIcon';
import { DangTai, LoiTai } from '../components/TaiNoiDung';
import { PenTick } from '../components/Vo';
import { api, LoiApi } from '../lib/api';
import { guiDi } from '../lib/hangCho';
import { laCheDo, type CheDo, type PhienHoc, type TheHoc } from '../lib/hoc';
import { nghe } from '../lib/amThanh';
import { GRADES, formatInterval, grade as gradeCard, levelName, stageName, type Grade } from '../lib/srs';
import { useStore } from '../state/store';
import { useTitle } from '../lib/useTitle';
import { t } from '../i18n';
import { tGhep } from '../i18n/ghep';
import { Empty, Header, Summary, ketQua, useGhiLuot, type Done } from './hoc/chung';
import { PhienLuyen } from './hoc/PhienLuyen';

export { sessionLine } from './hoc/chung';

/** Thẻ Quên quay lại trong phiên tối đa chừng này lần (để luyện, không tính vào tiến độ). */
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
 * Phiên học: máy chủ lập phiên (thẻ đến hạn và từ mới, xen kẽ), app chạy phiên theo chế độ đã chọn (?che-do=, mặc định Flashcard),
 * mỗi lượt chấm vào hàng chờ trên máy rồi gửi theo lô. Mất mạng giữa phiên vẫn chấm tiếp được; lượt chờ gửi khi có mạng.
 */
export function Hoc() {
    const [params] = useSearchParams();
    const navigate = useNavigate();
    useTitle(t('hoc.tieuDe'));
    const chuDe = params.get('chu-de');
    const chiOn = params.get('on') === '1';
    const cheDoChon = params.get('che-do');
    const cheDo: CheDo = laCheDo(cheDoChon) ? cheDoChon : 'flashcard';
    const [tai, setTai] = useState<Tai>({ trangThai: 'dang-tai' });
    const [lan, setLan] = useState(0);
    const daGoi = useRef(-1);

    // Mở trang là lập một phiên (StrictMode chạy effect hai lần: chỉ gọi một lần cho mỗi lần thử).
    // Mỗi lần mở /hoc (kể cả "Học tiếp") trang được dựng lại (App.tsx: HocRoute), nên đây luôn là phiên mới.
    useEffect(() => {
        if (daGoi.current === lan) return;
        daGoi.current = lan;
        api.gui<{ data: PhienHoc }>('/hoc/phien', { topic: chuDe, review_only: chiOn, mode: cheDo }).then(
            ({ data }) => setTai({ trangThai: 'xong', phien: data }),
            (e: unknown) => setTai({ trangThai: 'loi', loi: e instanceof LoiApi ? e : new LoiApi(0, 'mat_mang') }),
        );
    }, [chuDe, chiOn, cheDo, lan]);

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

    return tai.phien.mode === 'flashcard' ? (
        <Phien phien={tai.phien} chuDe={chuDe} chiOn={chiOn} onExit={exit} />
    ) : (
        <PhienLuyen phien={tai.phien} chuDe={chuDe} chiOn={chiOn} onExit={exit} />
    );
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
    // Lúc thẻ đang học hiện lên (đặt trong effect bên dưới; máy chủ dùng để nhận ra chấm quá nhanh)
    const shownAt = useRef(0);

    const finished = pos >= queue.length;
    const { ghi, daGuiHet } = useGhiLuot(phien.id, 'flashcard', finished);
    const reviewing = lookBack > 0 ? done[done.length - lookBack] : null;
    const card = reviewing ? byId.get(reviewing.id) : finished ? undefined : byId.get(queue[pos]);
    const level = card ? (reviewing ? reviewing.after : (levels[card.id] ?? 0)) : 0;
    // Thẻ ôn lại trong phiên (đã chấm lượt đầu): nút chấm ghi "lát nữa" / "xong" thay cho hạn ôn
    const onLai = !!card && !reviewing && done.some((d) => d.id === card.id);
    const last = done[done.length - 1];

    useEffect(() => {
        shownAt.current = Date.now();
    }, [pos]);

    const doGrade = useCallback(
        (g: Grade) => {
            if (!card || reviewing || !revealed) return;
            const before = levels[card.id] ?? 0;
            const d = ketQua(card, g, before, ghi(card, g, shownAt.current));
            setLevels((l) => ({ ...l, [card.id]: d.after }));
            setDone((x) => [...x, d]);
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
        [card, reviewing, revealed, levels, ghi, requeued, pos],
    );

    // Gắn phím ngay khi thẻ hiện (layout effect chạy cùng lúc vẽ thẻ): bấm Space ngay lúc thẻ đầu vừa hiện không bị mất
    useLayoutEffect(() => {
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
            if (k === 'p') void nghe(card.front, { url: card.audio });
            if (k === 's') void nghe(card.front, { url: card.audio, cham: true });
            if (k === 'e' && card.example) void nghe(card.example.sentence);
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
                <Summary done={done} byId={byId} chuDe={chuDe} chiOn={chiOn} cheDo="flashcard" tenChuDe={phien.topic?.name} daGuiHet={daGuiHet} />
            ) : card ? (
                <Card
                    key={`${card.id}-${pos}-${lookBack}`}
                    card={card}
                    level={level}
                    revealed={revealed || !!reviewing}
                    reviewing={reviewing}
                    onLai={onLai}
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

function Card(props: {
    card: TheHoc;
    level: number;
    revealed: boolean;
    reviewing: Done | null;
    /** Thẻ quay lại sau khi Quên: lượt này chỉ để luyện, không đổi mức. */
    onLai: boolean;
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
                            {(card.topic || props.onLai) && (
                                <p className="mb-3 flex flex-wrap gap-x-3 text-label text-ink-2">
                                    {card.topic && <span>{card.topic.name}</span>}
                                    {props.onLai && <span className="font-semibold text-danger">{t('hoc.onLai')}</span>}
                                </p>
                            )}
                            <h1 className="max-w-full text-study-word font-extrabold break-words">
                                <TuDuc the={card} />
                            </h1>
                            <p className="mt-1 text-ink-2">
                                <DongDang the={card} />
                            </p>
                            <div className="mt-4 flex gap-2">
                                <AudioButton text={card.front} url={card.audio} shortcut="P" iconOnly />
                                <AudioButton text={card.front} url={card.audio} slow shortcut="S" iconOnly />
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
                                        {props.onLai ? (g.id === 'quen' ? t('hoc.latNua') : t('hoc.xong')) : formatInterval(gradeCard(level, g.id).intervalDays)}{' '}
                                        <Kbd>{g.key}</Kbd>
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
